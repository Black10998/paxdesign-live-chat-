<?php
/**
 * Portal customer registry — automatic visibility for Master Administrator customer management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Registry {

    const META_PORTAL    = 'pax_portal_customer';
    const META_SYNCED_AT = 'pax_portal_customer_synced_at';

    /** @var bool */
    private static $backfill_ran = false;

    public static function init() {
        add_action('user_register', array(__CLASS__, 'on_user_register'), 20, 1);
        add_action('pdx_user_logged_in', array(__CLASS__, 'on_user_logged_in'), 8, 1);
        add_action('profile_update', array(__CLASS__, 'on_profile_update'), 20, 2);
    }

    /**
     * @param int $user_id
     */
    public static function on_user_register($user_id) {
        self::ensure_portal_customer($user_id);
    }

    /**
     * @param int $user_id
     */
    public static function on_user_logged_in($user_id) {
        self::ensure_portal_customer($user_id);
    }

    /**
     * @param int $user_id
     * @param WP_User $old_user_data
     */
    public static function on_profile_update($user_id, $old_user_data) {
        unset($old_user_data);
        self::ensure_portal_customer($user_id);
        update_user_meta(absint($user_id), self::META_SYNCED_AT, current_time('mysql'));
    }

    /**
     * Mark an account as a portal customer and keep role/metadata in sync.
     *
     * @param int $user_id
     * @return bool
     */
    public static function ensure_portal_customer($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || self::should_exclude_from_portal($user_id)) {
            return false;
        }

        update_user_meta($user_id, self::META_PORTAL, 1);
        update_user_meta($user_id, self::META_SYNCED_AT, current_time('mysql'));

        if (class_exists('PAXdesign_Auth_Native')) {
            $user = get_userdata($user_id);
            if ($user instanceof WP_User) {
                $role = PAXdesign_Auth_Native::customer_role();
                if ($role !== '' && !in_array('administrator', (array) $user->roles, true)) {
                    $has_portal_role = in_array($role, (array) $user->roles, true)
                        || in_array('customer', (array) $user->roles, true);
                    if (!$has_portal_role && !self::has_preserved_staff_role($user)) {
                        $user->set_role($role);
                    }
                }
            }
        }

        return true;
    }

    /**
     * One-time style backfill so existing registered customers appear automatically.
     */
    public static function backfill_existing_portal_customers() {
        if (self::$backfill_ran) {
            return;
        }
        self::$backfill_ran = true;

        $marker_keys = array(
            self::META_PORTAL,
            'pdx_apple_sub',
            'pdx_email_verified',
            'pax_customer_avatar_preset',
            'pdx_account_status',
        );

        $seen = array();
        foreach ($marker_keys as $meta_key) {
            $user_ids = get_users(array(
                'fields'       => 'ID',
                'number'       => 500,
                'meta_key'     => $meta_key,
                'role__not_in' => array('administrator'),
            ));
            foreach ($user_ids as $uid) {
                $uid = absint($uid);
                if ($uid <= 0 || isset($seen[$uid])) {
                    continue;
                }
                $seen[$uid] = true;
                self::ensure_portal_customer($uid);
            }
        }

        foreach (self::portal_customer_roles() as $role_slug) {
            $role_users = get_users(array(
                'fields' => 'ID',
                'number' => 500,
                'role'   => $role_slug,
            ));
            foreach ($role_users as $uid) {
                $uid = absint($uid);
                if ($uid <= 0 || isset($seen[$uid])) {
                    continue;
                }
                $seen[$uid] = true;
                self::ensure_portal_customer($uid);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function portal_customer_roles() {
        $roles = array('pdx_customer', 'customer', 'subscriber');
        if (class_exists('PAXdesign_Auth_Native')) {
            $roles[] = PAXdesign_Auth_Native::customer_role();
        }
        return array_values(array_unique(array_filter($roles)));
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_portal_customer($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || self::should_exclude_from_portal($user_id)) {
            return false;
        }

        if ((string) get_user_meta($user_id, self::META_PORTAL, true) === '1') {
            return true;
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return false;
        }

        if (array_intersect(self::portal_customer_roles(), (array) $user->roles)) {
            return true;
        }

        foreach (array('pdx_apple_sub', 'pdx_email_verified', 'pax_customer_avatar_preset', 'pdx_account_status') as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            if ($value !== '' && $value !== false && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticated WordPress account email — source of truth for admin views.
     *
     * @param int $user_id
     * @return string
     */
    public static function account_email($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return '';
        }

        $email = trim((string) $user->user_email);
        if ($email !== '' && is_email($email)) {
            return $email;
        }

        $login = trim((string) $user->user_login);
        if (strpos($login, '@') !== false && is_email($login)) {
            return sanitize_email($login);
        }

        return $email;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_manageable_by_master_admin($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !self::is_portal_customer($user_id)) {
            return false;
        }
        if (class_exists('PAXdesign_Customer_Master_Admin') && PAXdesign_Customer_Master_Admin::is_master_admin($user_id)) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return false;
        }
        if (!PAXdesign_Customers::is_customer($user_id)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $search
     * @param int $page
     * @param int $per_page
     * @return array{users:array<int,WP_User>,total:int,page:int,per_page:int}
     */
    public static function query_manageable_customers($search = '', $page = 1, $per_page = 50) {
        self::backfill_existing_portal_customers();

        $page = max(1, (int) $page);
        $per_page = min(100, max(1, (int) $per_page));
        $search = sanitize_text_field((string) $search);

        $candidates = get_users(array(
            'role__not_in' => array('administrator'),
            'orderby'      => 'registered',
            'order'        => 'DESC',
            'number'       => 2000,
        ));

        $filtered = array();
        foreach ($candidates as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            if (!self::is_manageable_by_master_admin((int) $user->ID)) {
                continue;
            }
            if ($search !== '' && !self::matches_search($user, $search)) {
                continue;
            }
            $filtered[] = $user;
        }

        $total = count($filtered);
        $offset = ($page - 1) * $per_page;

        return array(
            'users'    => array_slice($filtered, $offset, $per_page),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        );
    }

    /**
     * @param WP_User $user
     * @param string $search
     * @return bool
     */
    private static function matches_search($user, $search) {
        $needle = strtolower(trim((string) $search));
        if ($needle === '') {
            return true;
        }
        $haystacks = array(
            (string) $user->display_name,
            (string) $user->user_email,
            (string) $user->user_login,
            self::account_email((int) $user->ID),
        );
        foreach ($haystacks as $value) {
            if ($value !== '' && stripos($value, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    private static function should_exclude_from_portal($user_id) {
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        if (class_exists('PAXdesign_Customer_Master_Admin') && PAXdesign_Customer_Master_Admin::is_master_admin($user_id)) {
            return true;
        }
        return false;
    }

    /**
     * @param WP_User $user
     * @return bool
     */
    private static function has_preserved_staff_role($user) {
        $staff_roles = array('administrator', 'editor', 'author', 'contributor', 'shop_manager');
        return (bool) array_intersect($staff_roles, (array) $user->roles);
    }
}
