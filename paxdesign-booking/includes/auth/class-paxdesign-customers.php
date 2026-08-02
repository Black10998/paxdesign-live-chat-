<?php
/**
 * Customer account status — booking-native (toolbar-free path).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customers {

    const META_ACCOUNT_STATUS = 'pdx_account_status';
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
        if ($user_id <= 0 || user_can($user_id, 'manage_options')) {
            return false;
        }
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_PENDING), true)) {
            return false;
        }
        update_user_meta($user_id, self::META_ACCOUNT_STATUS, $status);
        return true;
    }

    public static function is_login_allowed($user_id) {
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
        if ($user_id <= 0 || user_can($user_id, 'manage_options')) {
            return false;
        }
        return true;
    }
}
