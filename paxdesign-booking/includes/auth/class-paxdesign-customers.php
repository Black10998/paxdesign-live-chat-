<?php
/**
 * Customer account status — booking-native (toolbar-free path).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customers {

    const META_ACCOUNT_STATUS = 'pdx_account_status';
    const META_ADMIN_NOTES    = 'pdx_admin_notes';
    const META_LAST_LOGIN     = 'pdx_last_login';

    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PENDING   = 'pending';

    public static function account_status($user_id) {
        $status = (string) get_user_meta((int) $user_id, self::META_ACCOUNT_STATUS, true);
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_PENDING), true)) {
            return self::STATUS_ACTIVE;
        }
        return $status;
    }

    public static function set_account_status($user_id, $status) {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !self::is_customer($user_id)) {
            return false;
        }
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_PENDING), true)) {
            return false;
        }
        update_user_meta($user_id, self::META_ACCOUNT_STATUS, $status);
        return true;
    }

    public static function activate($user_id) {
        return self::set_account_status($user_id, self::STATUS_ACTIVE);
    }

    public static function suspend($user_id) {
        return self::set_account_status($user_id, self::STATUS_SUSPENDED);
    }

    public static function is_login_allowed($user_id) {
        if (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account((int) $user_id)) {
            return true;
        }
        return self::STATUS_SUSPENDED !== self::account_status((int) $user_id);
    }

    public static function record_login($user_id) {
        if ($user_id > 0) {
            update_user_meta((int) $user_id, self::META_LAST_LOGIN, current_time('mysql'));
        }
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_customer($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        if (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account($user_id)) {
            return false;
        }
        if (class_exists('PAXdesign_Live_Chat_Permissions') && PAXdesign_Live_Chat_Permissions::is_super_admin($user_id)) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return false;
        }
        return true;
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function admin_notes($user_id) {
        return (string) get_user_meta((int) $user_id, self::META_ADMIN_NOTES, true);
    }

    /**
     * @param int $user_id
     * @param string $notes
     * @return bool
     */
    public static function save_notes($user_id, $notes) {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !self::is_customer($user_id)) {
            return false;
        }
        update_user_meta($user_id, self::META_ADMIN_NOTES, sanitize_textarea_field((string) $notes));
        return true;
    }

    /**
     * @param int $user_id
     * @return string
     */
    public static function last_login($user_id) {
        return (string) get_user_meta((int) $user_id, self::META_LAST_LOGIN, true);
    }
}
