<?php
/**
 * Customer auth bridge — WordPress cookie session + app passwords via PAXdesign_Auth.
 *
 * Web: WordPress cookie session + REST nonce (same as pdx-auth.js / /auth/*).
 * Mobile: WordPress Application Password (HTTP Basic) on /pdx/v1/customer/*.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Auth {

    const REST_NAMESPACE = 'pdx/v1';

    public static function init() {
        add_action('init', array(__CLASS__, 'bootstrap_basic_auth'), 1);
        add_filter('determine_current_user', array(__CLASS__, 'resolve_customer_app_password'), 20);
    }

    public static function bootstrap_basic_auth() {
        if (!self::is_customer_rest_request()) {
            return;
        }
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            return;
        }
        $header = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if ($header === '' || stripos($header, 'basic ') !== 0) {
            return;
        }
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return;
        }
        list($user, $pass) = explode(':', $decoded, 2);
        $_SERVER['PHP_AUTH_USER'] = $user;
        $_SERVER['PHP_AUTH_PW']   = $pass;
    }

    /**
     * Application Password auth for /pdx/v1/customer/* mobile routes.
     *
     * @param int|false $user_id
     * @return int|false
     */
    public static function resolve_customer_app_password($user_id) {
        if ($user_id || !self::is_customer_rest_request()) {
            return $user_id;
        }
        if (empty($_SERVER['PHP_AUTH_USER'])) {
            return $user_id;
        }
        $login = sanitize_text_field(wp_unslash((string) $_SERVER['PHP_AUTH_USER']));
        if ($login === '') {
            return $user_id;
        }
        $by_login = get_user_by('login', $login);
        if (!$by_login && is_email($login)) {
            $by_login = get_user_by('email', $login);
            if ($by_login instanceof WP_User) {
                $_SERVER['PHP_AUTH_USER'] = $by_login->user_login;
            }
        }
        return $user_id;
    }

    public static function is_customer_rest_request() {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && strpos($uri, '/wp-json/pdx/v1/customer') !== false) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $route = '';
            if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && !empty($GLOBALS['wp']->query_vars['rest_route'])) {
                $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
            }
            return $route !== '' && strpos($route, '/pdx/v1/customer') === 0;
        }
        return false;
    }

    public static function current_user_id() {
        return (int) get_current_user_id();
    }

    public static function is_logged_in() {
        return is_user_logged_in();
    }

    public static function is_email_verified($user_id = 0) {
        $user_id = $user_id > 0 ? (int) $user_id : self::current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        if (class_exists('PAXdesign_Auth')) {
            return PAXdesign_Auth::is_email_verified($user_id);
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        return (bool) get_user_meta($user_id, 'pdx_email_verified', true);
    }

    public static function is_login_allowed($user_id = 0) {
        $user_id = $user_id > 0 ? (int) $user_id : self::current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        if (class_exists('PAXdesign_Customers')) {
            return PAXdesign_Customers::is_login_allowed($user_id);
        }
        return true;
    }

    public static function user_payload($user_id = 0) {
        $user_id = $user_id > 0 ? (int) $user_id : self::current_user_id();
        if ($user_id <= 0) {
            $guest = array(
                'logged_in'    => false,
                'verified'     => false,
                'id'           => 0,
                'display_name' => '',
                'email'        => '',
                'role'         => 'guest',
                'nonce'        => wp_create_nonce('wp_rest'),
            );
            if (class_exists('PAXdesign_Auth')) {
                return array_merge(PAXdesign_Auth::user_payload(0), $guest);
            }
            return $guest;
        }

        if (class_exists('PAXdesign_Auth')) {
            $payload = PAXdesign_Auth::user_payload($user_id);
            $user = get_user_by('id', $user_id);
            if ($user instanceof WP_User) {
                $payload['role'] = self::resolve_portal_role($user);
                $payload['is_staff'] = PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id);
            }
            if (class_exists('PAXdesign_Customer_Avatar')) {
                $avatar = PAXdesign_Customer_Avatar::profile_fields($user_id);
                $payload = array_merge($payload, array(
                    'avatar_url'         => $avatar['avatar_url'] ?? '',
                    'avatar_has_image'   => !empty($avatar['avatar_has_image']),
                    'avatar_preset'      => $avatar['avatar_preset'] ?? '',
                    'customer_level'     => (int) ($avatar['customer_level'] ?? 0),
                    'level_label'        => (string) ($avatar['level_label'] ?? ''),
                    'level_title'        => (string) ($avatar['level_title'] ?? ''),
                    'level_description'  => (string) ($avatar['level_description'] ?? ''),
                    'has_customer_level' => !empty($avatar['has_customer_level']),
                ));
            }
            if (class_exists('PAXdesign_Customer_Master_Admin')) {
                $payload['is_master_admin'] = PAXdesign_Customer_Master_Admin::is_master_admin($user_id);
            }
            if (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account($user_id)) {
                $payload['is_owner'] = true;
                $payload['is_admin'] = true;
                $payload['is_master_admin'] = true;
            }
            $payload['nonce'] = wp_create_nonce('wp_rest');
            return $payload;
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return array('logged_in' => false, 'verified' => false, 'id' => 0);
        }
        return array(
            'logged_in'        => true,
            'verified'         => self::is_email_verified($user_id),
            'id'               => $user_id,
            'display_name'     => $user->display_name,
            'email'            => $user->user_email,
            'role'             => self::resolve_portal_role($user),
            'is_admin'         => user_can($user, 'manage_options') || (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account($user_id)),
            'is_owner'         => class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account($user_id),
            'is_master_admin'  => class_exists('PAXdesign_Customer_Master_Admin') && PAXdesign_Customer_Master_Admin::is_master_admin($user_id),
            'is_staff'         => PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id),
            'avatar_url'       => class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::url_for_user($user_id) : '',
            'avatar_has_image' => class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::has_visible_avatar($user_id) : false,
            'nonce'            => wp_create_nonce('wp_rest'),
        );
    }

    public static function resolve_portal_role(WP_User $user) {
        if (class_exists('PAXdesign_Auth_Native') && PAXdesign_Auth_Native::is_owner_account((int) $user->ID)) {
            return 'administrator';
        }
        if (class_exists('PAXdesign_Live_Chat_Permissions') && PAXdesign_Live_Chat_Permissions::is_super_admin($user)) {
            return 'administrator';
        }
        if (user_can($user, 'manage_options')) {
            return 'administrator';
        }
        if (PAXdesign_Live_Chat_Permissions::has_live_chat_access($user->ID)) {
            return 'employee';
        }
        if (class_exists('PAXdesign_Auth') && in_array(PAXdesign_Auth::customer_role(), (array) $user->roles, true)) {
            return 'customer';
        }
        return 'customer';
    }

    /**
     * REST permission callback for customer portal routes.
     */
    public static function require_customer(WP_REST_Request $request) {
        if (!self::is_logged_in()) {
            return new WP_Error('rest_forbidden', __('Authentication required.', 'paxdesign-booking'), array('status' => 401));
        }
        $user_id = self::current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('rest_forbidden', __('Authentication required.', 'paxdesign-booking'), array('status' => 401));
        }
        if (!self::is_login_allowed($user_id)) {
            return new WP_Error('pdx_account_suspended', __('Your account has been suspended.', 'paxdesign-booking'), array('status' => 403));
        }
        if (!self::is_email_verified($user_id) && !user_can($user_id, 'manage_options')) {
            return new WP_Error('pdx_email_unverified', __('Please verify your email address.', 'paxdesign-booking'), array('status' => 403));
        }
        $limited = self::check_rate_limit($user_id, $request);
        if (is_wp_error($limited)) {
            return $limited;
        }
        return true;
    }

    public static function require_staff(WP_REST_Request $request) {
        $base = self::require_customer($request);
        if (is_wp_error($base)) {
            return $base;
        }
        $user_id = self::current_user_id();
        if (!PAXdesign_Live_Chat_Permissions::has_live_chat_access($user_id) && !user_can($user_id, 'manage_options')) {
            return new WP_Error('rest_forbidden', __('Staff access required.', 'paxdesign-booking'), array('status' => 403));
        }
        return true;
    }

    public static function require_admin(WP_REST_Request $request) {
        $base = self::require_customer($request);
        if (is_wp_error($base)) {
            return $base;
        }
        if (!user_can(self::current_user_id(), 'manage_options')) {
            return new WP_Error('rest_forbidden', __('Administrator access required.', 'paxdesign-booking'), array('status' => 403));
        }
        return true;
    }

    public static function check_rate_limit($user_id, WP_REST_Request $request, $max_per_minute = null) {
        $route = $request->get_route();
        $method = strtoupper($request->get_method());
        $limits = self::rate_limit_profile($route, $method);
        $key = 'customer:' . $user_id . ':' . $limits['bucket'];
        $max_per_minute = $max_per_minute ?? $limits['max'];

        if (class_exists('PDX_RateLimit')) {
            $result = PDX_RateLimit::check($key, (float) $max_per_minute, $max_per_minute / 60.0, 1.0);
            if (empty($result['allowed'])) {
                return new WP_Error(
                    'rate_limited',
                    __('Too many requests. Please try again shortly.', 'paxdesign-booking'),
                    array('status' => 429, 'retry_after' => (int) ($result['retry_after'] ?? 60))
                );
            }
            return true;
        }
        $bucket = md5($key . '|' . gmdate('Y-m-d-H-i'));
        $transient_key = 'pax_cust_rl_' . $bucket;
        $count = (int) get_transient($transient_key);
        if ($count >= $max_per_minute) {
            return new WP_Error('rate_limited', __('Too many requests. Please try again shortly.', 'paxdesign-booking'), array('status' => 429, 'retry_after' => 60));
        }
        set_transient($transient_key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Route-aware rate limits. Chat sync reads share one bucket with a generous ceiling
     * so SSE + polling never trip 429 during normal conversation usage.
     */
    private static function rate_limit_profile($route, $method) {
        if ($method === 'GET' && preg_match('#/customer/chat/(messages|events|session|conversations)$#', $route)) {
            return array('bucket' => 'chat:sync', 'max' => 480);
        }
        if ($method === 'POST' && preg_match('#/customer/chat/typing$#', $route)) {
            return array('bucket' => 'chat:typing', 'max' => 360);
        }
        if ($method === 'GET' && preg_match('#/customer/notifications$#', $route)) {
            return array('bucket' => 'notifications', 'max' => 240);
        }
        return array('bucket' => $route, 'max' => 120);
    }

    public static function verify_rest_nonce(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        return !empty($_SERVER['PHP_AUTH_USER']);
    }
}
