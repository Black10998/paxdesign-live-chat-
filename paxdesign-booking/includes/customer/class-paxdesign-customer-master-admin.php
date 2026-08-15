<?php
/**
 * PAXDesign Master Administrator access control.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Master_Admin {

    /** Primary iCloud email for the Master Administrator account. */
    const MASTER_EMAIL = 'awjime29@icloud.com';

    /** Apple Private Relay email for the same Master Administrator (Sign in with Apple). */
    const MASTER_APPLE_RELAY_EMAIL = 'ftbkvmfy6g@privaterelay.appleid.com';

    const META_FLAG = 'pax_master_admin';

    /**
     * All emails that identify the single Master Administrator account.
     *
     * @return array<int, string>
     */
    public static function master_emails() {
        return array(
            self::MASTER_EMAIL,
            self::MASTER_APPLE_RELAY_EMAIL,
        );
    }

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
     * @param string $email
     * @return bool
     */
    public static function is_master_email($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }
        foreach (self::master_emails() as $master_email) {
            if ($email === strtolower($master_email)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find the canonical Master Administrator WordPress user by any known email.
     *
     * @return WP_User|null
     */
    public static function find_master_user() {
        foreach (self::master_emails() as $email) {
            $user = get_user_by('email', $email);
            if ($user instanceof WP_User) {
                return $user;
            }
        }
        return null;
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
        return self::is_master_email($user->user_email);
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
