<?php
/**
 * PAXDesign Master Administrator access control.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Master_Admin {

    const MASTER_EMAIL = 'awjime29@icloud.com';
    const META_FLAG    = 'pax_master_admin';

    public static function init() {
        add_action('pdx_user_logged_in', array(__CLASS__, 'ensure_master_flag'), 5, 1);
        add_action('user_register', array(__CLASS__, 'ensure_master_flag'), 5, 1);
    }

    /**
     * @param int $user_id
     */
    public static function ensure_master_flag($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }
        if (self::email_matches_master($user_id)) {
            update_user_meta($user_id, self::META_FLAG, 1);
        }
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_master_admin($user_id = 0) {
        $user_id = $user_id > 0 ? absint($user_id) : get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        if (self::email_matches_master($user_id)) {
            return true;
        }
        return (string) get_user_meta($user_id, self::META_FLAG, true) === '1';
    }

    /**
     * @param int $user_id
     * @return bool
     */
    private static function email_matches_master($user_id) {
        $user = get_userdata(absint($user_id));
        if (!$user instanceof WP_User) {
            return false;
        }
        return strtolower(trim($user->user_email)) === strtolower(self::MASTER_EMAIL);
    }

    /**
     * @return true|WP_Error
     */
    public static function require_master_admin() {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('Authentication required.', 'paxdesign-booking'), array('status' => 401));
        }
        if (!self::is_master_admin()) {
            return new WP_Error('rest_forbidden', __('Master administrator access required.', 'paxdesign-booking'), array('status' => 403));
        }
        if (class_exists('PAXdesign_Customer_Auth') && !PAXdesign_Customer_Auth::is_login_allowed(get_current_user_id())) {
            return new WP_Error('rest_forbidden', __('Account suspended.', 'paxdesign-booking'), array('status' => 403));
        }
        return true;
    }
}
