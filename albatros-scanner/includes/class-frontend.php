<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Frontend {
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'render'), 0);
        add_action('login_init', array(__CLASS__, 'redirect_wp_login'), 0);
        add_filter('rest_authentication_errors', array(__CLASS__, 'keep_rest_auth'), 20);
    }

    public static function redirect_wp_login() {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        if (in_array($action, array('logout', 'rp', 'resetpass', 'lostpassword', 'postpass'), true)) {
            return;
        }
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    public static function keep_rest_auth($result) {
        return $result;
    }

    public static function render() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        $path = self::path();
        if (self::is_wp_asset($path)) {
            return;
        }
        self::nocache();
        $logged_in = is_user_logged_in();
        if ($logged_in) {
            Alb_Capabilities::bootstrap_user(get_current_user_id());
        }
        if (!$logged_in && $path !== 'login') {
            $redirect = $path !== '' ? '/' . $path : '/';
            if ($path === '') {
                self::print_login();
                exit;
            }
            wp_safe_redirect(home_url('/login?next=' . rawurlencode($redirect)));
            exit;
        }
        if (!$logged_in) {
            self::print_login();
            exit;
        }
        if ($path === 'login') {
            wp_safe_redirect(home_url('/'));
            exit;
        }
        if (strpos($path, 's/') === 0) {
            $token = substr($path, 2);
            $scanner = Alb_Scanners::get_by_qr($token);
            if ($scanner && Alb_Capabilities::current_user_can('scanners.view')) {
                wp_safe_redirect(home_url('/scanners/' . $scanner['id']));
                exit;
            }
        }
        self::print_app();
        exit;
    }

    public static function path() {
        $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $request = strtok($request, '?');
        $home_path = (string) parse_url(home_url('/'), PHP_URL_PATH);
        if ($home_path && $home_path !== '/' && strpos($request, $home_path) === 0) {
            $request = substr($request, strlen($home_path) - 1);
        }
        return trim((string) $request, '/');
    }

    private static function is_wp_asset($path) {
        $prefixes = array('wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'xmlrpc.php');
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }
        return false;
    }

    private static function nocache() {
        if (headers_sent()) {
            return;
        }
        nocache_headers();
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    private static function print_login() {
        $settings = Alb_Settings::get();
        $locale = Alb_I18n::current();
        $i18n = Alb_I18n::catalog($locale);
        $config = array(
            'rest' => esc_url_raw(rest_url(Alb_Rest::NS . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => $locale,
            'locales' => Alb_I18n::supported(),
            'i18n' => $i18n,
            'company' => $settings['company_name'],
            'next' => isset($_GET['next']) ? esc_url_raw(wp_unslash($_GET['next'])) : '/',
        );
        include ALB_SCANNER_PLUGIN_DIR . 'templates/login.php';
    }

    private static function print_app() {
        $settings = Alb_Settings::get();
        $locale = Alb_I18n::current();
        $config = array(
            'rest' => esc_url_raw(rest_url(Alb_Rest::NS . '/')),
            'home' => home_url('/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => $locale,
            'locales' => Alb_I18n::supported(),
            'i18n' => Alb_I18n::catalog($locale),
            'company' => $settings['company_name'],
            'user' => Alb_Auth::current_payload(),
            'statuses' => Alb_Scanners::statuses(),
            'roles' => Alb_Capabilities::roles(),
            'permission_keys' => Alb_Capabilities::permission_keys(),
            'path' => '/' . self::path(),
        );
        include ALB_SCANNER_PLUGIN_DIR . 'templates/app.php';
    }
}
