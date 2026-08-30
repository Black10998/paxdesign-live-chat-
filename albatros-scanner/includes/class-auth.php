<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Auth {
    public static function login($login, $password, $remember) {
        $login = trim((string) $login);
        $password = (string) $password;
        if ($login === '' || $password === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('login.error.empty'), array('status' => 400));
        }
        if (self::too_many_attempts()) {
            return new WP_Error('alb_limited', Alb_I18n::t('login.error.limited'), array('status' => 429));
        }
        $user = wp_signon(array(
            'user_login' => $login,
            'user_password' => $password,
            'remember' => (bool) $remember,
        ), is_ssl());
        if (is_wp_error($user)) {
            self::record_failure();
            return new WP_Error('alb_invalid', Alb_I18n::t('login.error.invalid'), array('status' => 401));
        }
        wp_set_current_user($user->ID);
        Alb_Capabilities::bootstrap_user($user->ID);
        self::clear_failures();
        Alb_Audit::record(array(
            'action' => 'login',
            'entity_type' => 'user',
            'entity_id' => (int) $user->ID,
            'field' => 'session',
            'new' => 'login',
        ));
        return self::current_payload();
    }

    public static function logout() {
        $user_id = get_current_user_id();
        if ($user_id) {
            Alb_Audit::record(array(
                'action' => 'logout',
                'entity_type' => 'user',
                'entity_id' => $user_id,
                'field' => 'session',
                'new' => 'logout',
            ));
        }
        wp_logout();
        return array('ok' => true);
    }

    public static function request_reset($login) {
        $login = trim((string) $login);
        if ($login === '') {
            return new WP_Error('alb_invalid', Alb_I18n::t('login.error.empty'), array('status' => 400));
        }
        if (self::too_many_attempts('reset')) {
            return new WP_Error('alb_limited', Alb_I18n::t('login.error.limited'), array('status' => 429));
        }
        $result = retrieve_password($login);
        self::record_failure('reset');
        if (is_wp_error($result)) {
            return array('ok' => true, 'message' => Alb_I18n::t('login.reset_sent'));
        }
        return array('ok' => true, 'message' => Alb_I18n::t('login.reset_sent'));
    }

    public static function current_payload() {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return null;
        }
        $role = Alb_Capabilities::role_of($user);
        $perms = array();
        foreach (Alb_Capabilities::permission_keys() as $key) {
            $perms[$key] = Alb_Capabilities::user_can($user, $key);
        }
        return array(
            'id' => (int) $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'role' => $role,
            'locale' => Alb_I18n::current(),
            'permissions' => $perms,
            'nonce' => wp_create_nonce('wp_rest'),
        );
    }

    public static function update_me($data) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('alb_auth', Alb_I18n::t('error.auth_required'), array('status' => 401));
        }
        if (!empty($data['locale'])) {
            Alb_I18n::set_locale($data['locale'], $user_id);
        }
        return self::current_payload();
    }

    private static function key($kind = 'login') {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0';
        return 'alb_' . $kind . '_' . md5($ip);
    }

    private static function too_many_attempts($kind = 'login') {
        $count = (int) get_transient(self::key($kind));
        return $count >= 8;
    }

    private static function record_failure($kind = 'login') {
        $key = self::key($kind);
        set_transient($key, (int) get_transient($key) + 1, 15 * MINUTE_IN_SECONDS);
    }

    private static function clear_failures() {
        delete_transient(self::key('login'));
    }
}
