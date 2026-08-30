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
        global $wp_query;
        if ($wp_query) {
            $wp_query->is_404 = false;
        }
        status_header(200);
        if (strpos($path, 'alb-photo/') === 0) {
            Alb_Photos::serve_request($path);
            exit;
        }
        if (preg_match('#^s/([A-Za-z0-9]+)/selfie$#', $path, $match)) {
            Alb_Photos::serve_employee_selfie($match[1]);
            exit;
        }
        if (strpos($path, 's/') === 0) {
            self::render_scan(substr($path, 2));
            exit;
        }
        if ($path === 'no-access') {
            self::print_denied();
            exit;
        }
        $logged_in = is_user_logged_in();
        if ($logged_in) {
            Alb_Capabilities::bootstrap_user(get_current_user_id());
        }
        if (!$logged_in && $path !== 'login') {
            $redirect = $path !== '' ? '/' . $path : '/';
            if ($path === '') {
                self::handle_login_post();
                self::print_login();
                exit;
            }
            wp_safe_redirect(home_url('/login?next=' . rawurlencode($redirect)));
            exit;
        }
        if (!$logged_in) {
            self::handle_login_post();
            self::print_login();
            exit;
        }
        if ($path === 'login') {
            wp_safe_redirect(Alb_Capabilities::can_use_admin_app() ? home_url('/') : home_url('/no-access'));
            exit;
        }
        if (!Alb_Capabilities::can_use_admin_app()) {
            self::print_denied();
            exit;
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
        header('Vary: Cookie');
    }

    private static function handle_login_post() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $action = isset($_POST['alb_action']) ? sanitize_key(wp_unslash($_POST['alb_action'])) : '';
        if ($action === 'reset') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'alb_reset')) {
                $GLOBALS['alb_login_error'] = Alb_I18n::t('error.forbidden');
                return;
            }
            $result = Alb_Auth::request_reset(wp_unslash($_POST['login'] ?? ''));
            $GLOBALS['alb_login_notice'] = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? Alb_I18n::t('login.reset_sent'));
            $GLOBALS['alb_login_notice_ok'] = !is_wp_error($result);
            return;
        }
        if ($action !== 'login') {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'alb_login')) {
            $GLOBALS['alb_login_error'] = Alb_I18n::t('error.forbidden');
            return;
        }
        $result = Alb_Auth::login(wp_unslash($_POST['login'] ?? ''), (string) ($_POST['password'] ?? ''), !empty($_POST['remember']));
        if (is_wp_error($result)) {
            $GLOBALS['alb_login_error'] = $result->get_error_message();
            return;
        }
        if (!Alb_Capabilities::can_use_admin_app()) {
            wp_safe_redirect(home_url('/no-access'));
            exit;
        }
        $next = isset($_GET['next']) ? wp_unslash($_GET['next']) : '/';
        $next = wp_validate_redirect($next, home_url('/'));
        wp_safe_redirect($next);
        exit;
    }

    private static function render_scan($token) {
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $token);
        if (isset($_GET['alb_lang'])) {
            Alb_I18n::set_locale(wp_unslash($_GET['alb_lang']), get_current_user_id());
            wp_safe_redirect(home_url('/s/' . $token));
            exit;
        }
        $scanner = $token !== '' ? Alb_Scanners::get_by_qr($token) : null;
        $scan_error = '';
        $otp_info = null;
        $accepted = false;
        $is_manager = Alb_Capabilities::can_use_admin_app();
        $employee = $scanner ? Alb_Employee::for_scanner($scanner['id']) : null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'alb_scan')) {
                $scan_error = Alb_I18n::t('error.forbidden');
            } elseif (!$scanner) {
                $scan_error = Alb_I18n::t('scan.not_found');
            } else {
                $action = sanitize_key(wp_unslash($_POST['alb_action'] ?? ''));
                if ($action === 'register') {
                    $result = Alb_Employee::register_and_send(
                        $scanner,
                        wp_unslash($_POST['full_name'] ?? ''),
                        wp_unslash($_POST['phone'] ?? ''),
                        $_FILES['selfie'] ?? array()
                    );
                    if (is_wp_error($result)) {
                        $scan_error = $result->get_error_message();
                    } else {
                        $otp_info = $result;
                    }
                } elseif ($action === 'verify') {
                    $result = Alb_Employee::verify_and_bind(
                        $scanner,
                        wp_unslash($_POST['phone'] ?? ''),
                        wp_unslash($_POST['otp_code'] ?? '')
                    );
                    if (is_wp_error($result)) {
                        $scan_error = $result->get_error_message();
                        $otp_info = array(
                            'phone' => wp_unslash($_POST['phone'] ?? ''),
                            'phone_masked' => Alb_Otp::mask_phone(wp_unslash($_POST['phone'] ?? '')),
                        );
                    } else {
                        wp_safe_redirect(home_url('/s/' . $token));
                        exit;
                    }
                } elseif ($action === 'accept') {
                    $result = Alb_Employee::accept($scanner);
                    if (is_wp_error($result)) {
                        $scan_error = $result->get_error_message();
                    } else {
                        $accepted = true;
                        $scanner = $result;
                        $employee = Alb_Employee::for_scanner($scanner['id']);
                    }
                } elseif ($is_manager) {
                    $params = wp_unslash($_POST);
                    $result = Alb_Rest::dispatch_scan_action($scanner, $action, $params);
                    if (is_wp_error($result)) {
                        $scan_error = $result->get_error_message();
                    } else {
                        wp_safe_redirect(home_url('/s/' . $token));
                        exit;
                    }
                } else {
                    $scan_error = Alb_I18n::t('error.forbidden');
                }
            }
        }
        if ($scanner && $employee && !$accepted) {
            Alb_Scan::maybe_record_open($scanner);
            $scanner = Alb_Scanners::get((int) $scanner['id']) ?: $scanner;
        }
        $locale = Alb_I18n::current();
        $i18n = Alb_I18n::catalog($locale);
        $settings = Alb_Settings::get();
        $history = ($scanner && $is_manager && Alb_Capabilities::current_user_can('history.view'))
            ? Alb_Scanners::history((int) $scanner['id'])
            : array();
        $config = array(
            'company' => $settings['company_name'],
            'logo' => Alb_Settings::logo_url(),
            'official_url' => Alb_Settings::official_url(),
            'locale' => $locale,
            'i18n' => $i18n,
            'is_manager' => $is_manager,
            'employee' => $employee,
            'otp' => $otp_info,
            'accepted' => $accepted,
            'sms_ready' => Alb_Settings::sms_ready(),
            'permissions' => array(
                'assign' => $is_manager && Alb_Capabilities::current_user_can('scanners.assign'),
                'status' => $is_manager && Alb_Capabilities::current_user_can('scanners.status'),
                'view_record' => $is_manager && Alb_Capabilities::current_user_can('scanners.view'),
            ),
        );
        include ALB_SCANNER_PLUGIN_DIR . 'templates/scan.php';
    }

    private static function print_denied() {
        $settings = Alb_Settings::get();
        $locale = Alb_I18n::current();
        $i18n = Alb_I18n::catalog($locale);
        $config = array(
            'company' => $settings['company_name'],
            'logo' => Alb_Settings::logo_url(),
            'official_url' => Alb_Settings::official_url(),
        );
        include ALB_SCANNER_PLUGIN_DIR . 'templates/denied.php';
    }

    private static function print_login() {
        $settings = Alb_Settings::get();
        $locale = Alb_I18n::current();
        $i18n = Alb_I18n::catalog($locale);
        $login_error = $GLOBALS['alb_login_error'] ?? '';
        $login_notice = $GLOBALS['alb_login_notice'] ?? '';
        $login_notice_ok = !empty($GLOBALS['alb_login_notice_ok']);
        $config = array(
            'rest' => esc_url_raw(rest_url(Alb_Rest::NS . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'locale' => $locale,
            'locales' => Alb_I18n::supported(),
            'i18n' => $i18n,
            'company' => $settings['company_name'],
            'logo' => Alb_Settings::logo_url(),
            'official_url' => Alb_Settings::official_url(),
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
            'logo' => Alb_Settings::logo_url(),
            'official_url' => Alb_Settings::official_url(),
            'user' => Alb_Auth::current_payload(),
            'statuses' => Alb_Scanners::statuses(),
            'roles' => Alb_Capabilities::roles(),
            'permission_keys' => Alb_Capabilities::permission_keys(),
            'assignable_roles' => Alb_Capabilities::assignable_roles(),
            'can_assign_permissions' => Alb_Capabilities::can_assign_user_permissions(),
            'device_mark' => ALB_SCANNER_PLUGIN_URL . 'assets/img/handheld-device.svg',
            'path' => '/' . self::path(),
        );
        include ALB_SCANNER_PLUGIN_DIR . 'templates/app.php';
    }
}
