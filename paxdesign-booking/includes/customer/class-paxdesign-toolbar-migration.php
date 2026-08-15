<?php
/**
 * One-time migration: preserve toolbar-era customer accounts in the native booking platform.
 *
 * Customer identity already lives in wp_users / wp_usermeta (shared meta keys).
 * This class exports a backup snapshot and ensures every legacy customer is registered
 * in PAXdesign_Customer_Registry before paxdesign-toolbar is removed.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Toolbar_Migration {

    const OPTION_COMPLETED = 'pax_toolbar_migration_completed';
    const OPTION_BACKUP    = 'pax_toolbar_migration_backup';

    /** @var array<int, string> */
    private static $customer_meta_keys = array(
        'pdx_email_verified',
        'pdx_account_status',
        'pdx_admin_notes',
        'pdx_last_login',
        'pdx_apple_sub',
        'pax_portal_customer',
        'pax_portal_customer_synced_at',
        'pax_customer_avatar_preset',
        'pax_customer_avatar_id',
        'pdx_verify_token',
        'pdx_verify_code',
        'pdx_verify_expires',
        'pdx_reset_token',
        'pdx_reset_expires',
        'pdx_failed_logins',
        'pdx_locked_until',
    );

    /**
     * @return array<string, mixed>
     */
    public static function run($force = false) {
        if (!$force && get_option(self::OPTION_COMPLETED)) {
            return array(
                'ok'      => true,
                'skipped' => true,
                'message' => 'Toolbar customer migration already completed.',
            );
        }

        $users = self::discover_legacy_customers();
        $backup = self::build_backup($users);
        self::persist_backup($backup);

        $synced = 0;
        $email_fixed = 0;

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            $user_id = (int) $user->ID;

            if (self::repair_account_email($user_id)) {
                $email_fixed++;
            }

            if (class_exists('PAXdesign_Customer_Registry')) {
                PAXdesign_Customer_Registry::ensure_portal_customer($user_id);
            }

            if (class_exists('PAXdesign_Auth_Native') && !PAXdesign_Auth_Native::is_email_verified($user_id)) {
                $verified_flag = get_user_meta($user_id, 'pdx_email_verified', true);
                if ($verified_flag === '1' || $verified_flag === 1 || $verified_flag === true) {
                    update_user_meta($user_id, 'pdx_email_verified', '1');
                }
            }

            $synced++;
        }

        update_option(self::OPTION_COMPLETED, current_time('mysql'), false);

        return array(
            'ok'           => true,
            'skipped'      => false,
            'customers'    => $synced,
            'email_fixed'  => $email_fixed,
            'backup_users' => count($backup['users']),
            'backup_file'  => isset($backup['file']) ? $backup['file'] : '',
            'completed_at' => current_time('mysql'),
        );
    }

    /**
     * @return array<int, WP_User>
     */
    private static function discover_legacy_customers() {
        $seen = array();
        $users = array();

        $marker_keys = array_merge(
            self::$customer_meta_keys,
            array('pdx_account_status')
        );

        foreach (array_unique($marker_keys) as $meta_key) {
            $ids = get_users(array(
                'fields'       => 'ID',
                'number'       => 5000,
                'meta_key'     => $meta_key,
                'role__not_in' => array('administrator'),
            ));
            foreach ($ids as $uid) {
                $uid = absint($uid);
                if ($uid <= 0 || isset($seen[$uid])) {
                    continue;
                }
                $user = get_userdata($uid);
                if (!$user instanceof WP_User) {
                    continue;
                }
                if (user_can($uid, 'manage_options')) {
                    continue;
                }
                $seen[$uid] = true;
                $users[] = $user;
            }
        }

        if (class_exists('PAXdesign_Customer_Registry')) {
            foreach (PAXdesign_Customer_Registry::portal_customer_roles() as $role) {
                $role_users = get_users(array(
                    'fields' => 'ID',
                    'number' => 5000,
                    'role'   => $role,
                ));
                foreach ($role_users as $uid) {
                    $uid = absint($uid);
                    if ($uid <= 0 || isset($seen[$uid])) {
                        continue;
                    }
                    $user = get_userdata($uid);
                    if (!$user instanceof WP_User || user_can($uid, 'manage_options')) {
                        continue;
                    }
                    $seen[$uid] = true;
                    $users[] = $user;
                }
            }
        }

        return $users;
    }

    /**
     * @param array<int, WP_User> $users
     * @return array<string, mixed>
     */
    private static function build_backup($users) {
        $rows = array();
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            $user_id = (int) $user->ID;
            $meta = array();
            foreach (self::$customer_meta_keys as $key) {
                $value = get_user_meta($user_id, $key, true);
                if ($value !== '' && $value !== false && $value !== null) {
                    $meta[$key] = $value;
                }
            }
            $rows[] = array(
                'id'            => $user_id,
                'user_login'    => $user->user_login,
                'user_email'    => $user->user_email,
                'display_name'  => $user->display_name,
                'user_registered' => $user->user_registered,
                'roles'         => array_values((array) $user->roles),
                'meta'          => $meta,
            );
        }

        return array(
            'version'      => defined('PAXDESIGN_BOOKING_VERSION') ? PAXDESIGN_BOOKING_VERSION : 'unknown',
            'exported_at'  => current_time('mysql'),
            'users'        => $rows,
        );
    }

    /**
     * @param array<string, mixed> $backup
     */
    private static function persist_backup($backup) {
        update_option(self::OPTION_BACKUP, $backup, false);

        $upload = wp_upload_dir();
        if (empty($upload['basedir']) || !wp_mkdir_p($upload['basedir'] . '/paxdesign-migrations')) {
            return;
        }

        $filename = 'toolbar-customer-backup-' . gmdate('Ymd-His') . '.json';
        $path = trailingslashit($upload['basedir']) . 'paxdesign-migrations/' . $filename;
        $written = file_put_contents(
            $path,
            wp_json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        if ($written !== false) {
            $backup['file'] = $path;
            update_option(self::OPTION_BACKUP, $backup, false);
        }
    }

    /**
     * Ensure WordPress user_email is populated for Apple relay / legacy logins.
     *
     * @param int $user_id
     * @return bool True when email was repaired.
     */
    private static function repair_account_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return false;
        }

        $email = trim((string) $user->user_email);
        if ($email !== '' && is_email($email)) {
            return false;
        }

        $login = trim((string) $user->user_login);
        if ($login !== '' && is_email($login)) {
            $candidate = sanitize_email($login);
            if ($candidate !== '' && is_email($candidate)) {
                $existing = email_exists($candidate);
                if (!$existing || (int) $existing === $user_id) {
                    wp_update_user(array(
                        'ID'         => $user_id,
                        'user_email' => $candidate,
                    ));
                    return true;
                }
            }
        }

        return false;
    }
}
