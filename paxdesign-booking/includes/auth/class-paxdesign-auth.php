<?php
/**
 * Auth facade — booking-native authentication (no toolbar dependency).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth {

    public static function register_hooks() {
        PAXdesign_Auth_Native::register_hooks();
    }

    public static function customer_role() {
        return self::engine()::customer_role();
    }

    public static function is_email_verified($user_id = 0) {
        return self::engine()::is_email_verified((int) $user_id);
    }

    public static function user_payload($user_id = null) {
        return self::engine()::user_payload($user_id);
    }

    public static function register($email, $password, $name) {
        return self::engine()::register($email, $password, $name);
    }

    public static function login($email, $password, $remember = true) {
        return self::engine()::login($email, $password, $remember);
    }

    public static function logout() {
        return self::engine()::logout();
    }

    public static function forgot_password($email) {
        return self::engine()::forgot_password($email);
    }

    public static function reset_password($token, $user_id, $password) {
        return self::engine()::reset_password($token, (int) $user_id, $password);
    }

    public static function resend_verification($user_id = null) {
        return self::engine()::resend_verification($user_id);
    }

    public static function resend_verification_by_email($email) {
        return self::engine()::resend_verification_by_email($email);
    }

    public static function verify_email($user_id, $token = '', $code = '') {
        return self::engine()::verify_email((int) $user_id, $token, $code);
    }

    public static function verify_by_email_and_code($email, $code) {
        return self::engine()::verify_by_email_and_code($email, $code);
    }

    public static function mobile_login($login, $password, $device_label = '') {
        return self::engine()::mobile_login($login, $password, $device_label);
    }

    public static function apple_mobile_login($identity_token, $device_label = '', $profile = array()) {
        return PAXdesign_Auth_Apple::mobile_login($identity_token, $device_label, is_array($profile) ? $profile : array());
    }

    public static function mobile_logout($user_id, $uuid) {
        return self::engine()::mobile_logout((int) $user_id, $uuid);
    }

    public static function resolve_portal_role($user_id) {
        return self::engine()::resolve_portal_role((int) $user_id);
    }

    public static function resolve_mobile_session_mode($user_id) {
        return self::engine()::resolve_mobile_session_mode((int) $user_id);
    }

    public static function auth_rate_limit($action) {
        return PAXdesign_Auth_Native::auth_rate_limit($action);
    }

    private static function engine() {
        return 'PAXdesign_Auth_Native';
    }
}
