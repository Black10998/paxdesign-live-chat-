<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Rest {
    const NS = 'albatros/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register'));
        add_filter('rest_authentication_errors', array(__CLASS__, 'allow_auth_routes'), 99);
        add_filter('rest_post_dispatch', array(__CLASS__, 'nocache_rest'), 999, 3);
        add_filter('rest_pre_serve_request', array(__CLASS__, 'serve_nocache'), 0, 4);
        add_filter('litespeed_control_cacheable', array(__CLASS__, 'disable_litespeed_rest_cache'));
    }

    public static function disable_litespeed_rest_cache($cacheable) {
        if (self::is_albatros_rest_request()) {
            return false;
        }
        return $cacheable;
    }

    private static function is_albatros_rest_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $route = isset($GLOBALS['wp']->query_vars['rest_route']) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
            if ($route !== '' && strpos($route, '/' . self::NS) === 0) {
                return true;
            }
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        return strpos($uri, '/wp-json/' . self::NS) !== false;
    }

    public static function nocache_rest($response, $server, $request) {
        unset($server);
        $route = (string) $request->get_route();
        if (strpos($route, '/' . self::NS) !== 0) {
            return $response;
        }
        if ($response instanceof WP_REST_Response) {
            $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            $response->header('X-LiteSpeed-Cache-Control', 'no-cache');
            $response->header('CDN-Cache-Control', 'no-store');
            $response->header('Surrogate-Control', 'no-store');
            $response->header('Vary', 'Cookie');
            if (is_user_logged_in()) {
                $response->header('X-WP-Nonce', wp_create_nonce('wp_rest'));
            }
        }
        self::send_nocache_headers();
        return $response;
    }

    public static function serve_nocache($served, $result, $request, $server) {
        unset($result, $server);
        $route = (string) $request->get_route();
        if (strpos($route, '/' . self::NS) === 0) {
            self::send_nocache_headers();
        }
        return $served;
    }

    private static function send_nocache_headers() {
        if (headers_sent()) {
            return;
        }
        nocache_headers();
        header_remove('Cache-Control');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('CDN-Cache-Control: no-store');
        header('Surrogate-Control: no-store');
        header('Vary: Cookie');
        if (is_user_logged_in()) {
            header('X-WP-Nonce: ' . wp_create_nonce('wp_rest'));
        }
    }

    public static function allow_auth_routes($result) {
        $route = isset($GLOBALS['wp']->query_vars['rest_route']) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
        if ($route && (strpos($route, '/albatros/v1/auth/') === 0 || strpos($route, '/albatros/v1/scan/') === 0)) {
            return true;
        }
        return $result;
    }

    public static function register() {
        register_rest_route(self::NS, '/auth/login', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'login'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'logout'),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ));
        register_rest_route(self::NS, '/auth/reset', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'reset'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(self::NS, '/me', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'me'),
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_me'),
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ),
        ));
        register_rest_route(self::NS, '/bootstrap', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'bootstrap'),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ));
        register_rest_route(self::NS, '/dashboard', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'dashboard'),
            'permission_callback' => array(__CLASS__, 'can_dashboard'),
        ));
        register_rest_route(self::NS, '/scanners', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'list_scanners'),
                'permission_callback' => array(__CLASS__, 'can_scanners_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_scanner'),
                'permission_callback' => array(__CLASS__, 'can_scanners_create'),
            ),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_scanner'),
                'permission_callback' => array(__CLASS__, 'can_scanners_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_scanner'),
                'permission_callback' => array(__CLASS__, 'can_scanners_update'),
            ),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/assign', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'assign_scanner'),
            'permission_callback' => array(__CLASS__, 'can_scanners_assign'),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'status_scanner'),
            'permission_callback' => array(__CLASS__, 'can_scanners_status'),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/history', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'scanner_history'),
            'permission_callback' => array(__CLASS__, 'can_history'),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/delete', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'delete_scanner'),
            'permission_callback' => array(__CLASS__, 'can_scanners_delete'),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/restore', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'restore_scanner'),
            'permission_callback' => array(__CLASS__, 'can_scanners_restore'),
        ));
        register_rest_route(self::NS, '/scanners/(?P<id>\d+)/take-over', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'take_over_scanner'),
            'permission_callback' => array(__CLASS__, 'can_scanners_assign'),
        ));
        register_rest_route(self::NS, '/scan/(?P<token>[A-Za-z0-9]+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'public_scan'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'public_scan_action'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::NS, '/drivers', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'list_drivers'),
                'permission_callback' => array(__CLASS__, 'can_drivers_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_driver'),
                'permission_callback' => array(__CLASS__, 'can_drivers_create'),
            ),
        ));
        register_rest_route(self::NS, '/drivers/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_driver'),
                'permission_callback' => array(__CLASS__, 'can_drivers_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_driver'),
                'permission_callback' => array(__CLASS__, 'can_drivers_edit'),
            ),
        ));
        register_rest_route(self::NS, '/drivers/(?P<id>\d+)/photo', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'driver_photo'),
            'permission_callback' => array(__CLASS__, 'can_driver_photo'),
        ));
        register_rest_route(self::NS, '/phones', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'list_phones'),
                'permission_callback' => array(__CLASS__, 'can_phones_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_phone'),
                'permission_callback' => array(__CLASS__, 'can_phones_create'),
            ),
        ));
        register_rest_route(self::NS, '/phones/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_phone'),
                'permission_callback' => array(__CLASS__, 'can_phones_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_phone'),
                'permission_callback' => array(__CLASS__, 'can_phones_update'),
            ),
        ));
        register_rest_route(self::NS, '/phones/(?P<id>\d+)/assign', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'assign_phone'),
            'permission_callback' => array(__CLASS__, 'can_phones_assign'),
        ));
        register_rest_route(self::NS, '/phones/(?P<id>\d+)/return', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'return_phone'),
            'permission_callback' => array(__CLASS__, 'can_phones_assign'),
        ));
        register_rest_route(self::NS, '/phones/(?P<id>\d+)/status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'status_phone'),
            'permission_callback' => array(__CLASS__, 'can_phones_edit'),
        ));
        register_rest_route(self::NS, '/phones/(?P<id>\d+)/history', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'phone_history'),
            'permission_callback' => array(__CLASS__, 'can_phones_view'),
        ));
        register_rest_route(self::NS, '/audit', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'audit'),
            'permission_callback' => array(__CLASS__, 'can_audit'),
        ));
        register_rest_route(self::NS, '/users', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'list_users'),
                'permission_callback' => array(__CLASS__, 'can_users_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_user'),
                'permission_callback' => array(__CLASS__, 'can_users_manage'),
            ),
        ));
        register_rest_route(self::NS, '/users/(?P<id>\d+)/photo', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'user_photo'),
            'permission_callback' => array(__CLASS__, 'can_users_manage'),
        ));
        register_rest_route(self::NS, '/users/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_user'),
                'permission_callback' => array(__CLASS__, 'can_users_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_user'),
                'permission_callback' => array(__CLASS__, 'can_users_manage'),
            ),
        ));
        register_rest_route(self::NS, '/settings', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_settings'),
                'permission_callback' => array(__CLASS__, 'can_settings_view'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_settings'),
                'permission_callback' => array(__CLASS__, 'can_settings_manage'),
            ),
        ));
        register_rest_route(self::NS, '/permissions', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_permissions'),
                'permission_callback' => array(__CLASS__, 'can_roles'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_permissions'),
                'permission_callback' => array(__CLASS__, 'can_roles'),
            ),
        ));
        register_rest_route(self::NS, '/export/(?P<type>[a-z]+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'export'),
            'permission_callback' => array(__CLASS__, 'can_export'),
        ));
        register_rest_route(self::NS, '/updates/check', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'check_updates'),
            'permission_callback' => array(__CLASS__, 'can_app'),
        ));
    }

    public static function can_app() {
        return is_user_logged_in() && Alb_Capabilities::can_use_admin_app();
    }

    public static function can_dashboard() {
        return Alb_Capabilities::current_user_can('dashboard.view');
    }
    public static function can_scanners_view() {
        return Alb_Capabilities::current_user_can('scanners.view');
    }
    public static function can_scanners_create() {
        return Alb_Capabilities::current_user_can('scanners.create');
    }
    public static function can_scanners_edit() {
        return Alb_Capabilities::current_user_can('scanners.edit')
            || Alb_Capabilities::current_user_can('scanners.identity');
    }
    public static function can_scanners_update() {
        return self::can_scanners_edit()
            || self::can_scanners_assign()
            || self::can_scanners_status();
    }
    public static function can_scanners_assign() {
        return Alb_Capabilities::current_user_can('scanners.assign');
    }
    public static function can_scanners_status() {
        return Alb_Capabilities::current_user_can('scanners.status');
    }
    public static function can_scanners_delete() {
        return Alb_Capabilities::current_user_can('scanners.delete');
    }
    public static function can_scanners_restore() {
        return Alb_Capabilities::current_user_can('scanners.delete')
            || Alb_Capabilities::current_user_can('scanners.status');
    }
    public static function can_drivers_view() {
        return Alb_Capabilities::current_user_can('drivers.view');
    }
    public static function can_drivers_create() {
        return Alb_Capabilities::current_user_can('drivers.create');
    }
    public static function can_drivers_edit() {
        return Alb_Capabilities::current_user_can('drivers.edit') || Alb_Capabilities::current_user_can('drivers.deactivate');
    }
    public static function can_driver_photo() {
        return self::can_drivers_edit() || self::can_scanners_assign();
    }
    public static function can_phones_view() {
        return Alb_Capabilities::current_user_can('phones.view');
    }
    public static function can_phones_create() {
        return Alb_Capabilities::current_user_can('phones.create');
    }
    public static function can_phones_edit() {
        return Alb_Capabilities::current_user_can('phones.edit');
    }
    public static function can_phones_assign() {
        return Alb_Capabilities::current_user_can('phones.assign');
    }
    public static function can_phones_delete() {
        return Alb_Capabilities::current_user_can('phones.delete');
    }
    public static function can_phones_update() {
        return self::can_phones_edit() || self::can_phones_assign() || self::can_phones_delete();
    }
    public static function can_history() {
        return Alb_Capabilities::current_user_can('history.view');
    }
    public static function can_audit() {
        return Alb_Capabilities::current_user_can('audit.view');
    }
    public static function can_users_view() {
        return Alb_Capabilities::current_user_can('users.view') || Alb_Capabilities::current_user_can('users.manage');
    }
    public static function can_users_manage() {
        return Alb_Capabilities::current_user_can('users.manage');
    }
    public static function can_roles() {
        return Alb_Capabilities::current_user_can('roles.manage');
    }
    public static function can_settings_view() {
        return Alb_Capabilities::current_user_can('settings.view');
    }
    public static function can_settings_manage() {
        return Alb_Capabilities::current_user_can('settings.manage');
    }
    public static function can_export() {
        return Alb_Capabilities::current_user_can('reports.export');
    }

    public static function login(WP_REST_Request $request) {
        $result = Alb_Auth::login($request->get_param('login'), $request->get_param('password'), $request->get_param('remember'));
        return self::respond($result);
    }

    public static function logout() {
        return rest_ensure_response(Alb_Auth::logout());
    }

    public static function reset(WP_REST_Request $request) {
        return self::respond(Alb_Auth::request_reset($request->get_param('login')));
    }

    public static function me() {
        return rest_ensure_response(Alb_Auth::current_payload());
    }

    public static function update_me(WP_REST_Request $request) {
        return self::respond(Alb_Auth::update_me($request->get_json_params() ?: $request->get_params()));
    }

    public static function bootstrap() {
        $settings = Alb_Settings::get();
        return rest_ensure_response(array(
            'user' => Alb_Auth::current_payload(),
            'settings' => array(
                'company_name' => $settings['company_name'],
                'owner_name' => $settings['owner_name'],
                'default_language' => $settings['default_language'],
                'timezone' => $settings['timezone'],
                'date_format' => $settings['date_format'],
                'items_per_page' => $settings['items_per_page'],
            ),
            'i18n' => Alb_I18n::catalog(),
            'locale' => Alb_I18n::current(),
            'locales' => Alb_I18n::supported(),
            'statuses' => Alb_Scanners::statuses(),
            'phone_statuses' => Alb_Phones::statuses(),
            'roles' => Alb_Capabilities::roles(),
            'permission_keys' => Alb_Capabilities::permission_keys(),
            'assignable_roles' => Alb_Capabilities::assignable_roles(),
            'can_assign_permissions' => Alb_Capabilities::can_assign_user_permissions(),
            'extra_permission_keys' => Alb_Capabilities::extra_permission_keys(),
            'is_primary' => Alb_Capabilities::is_primary(),
            'branches' => Alb_Branches::keys(),
            'driver_options' => Alb_Capabilities::current_user_can('drivers.view') ? Alb_Drivers::options() : array(),
        ));
    }

    public static function dashboard() {
        return rest_ensure_response(array(
            'counts' => Alb_Scanners::counts(),
            'recent_handovers' => Alb_Scanners::recent_handovers(8),
            'recent_activity' => Alb_Capabilities::current_user_can('audit.view')
                ? Alb_Audit::query(array('per_page' => 8, 'page' => 1, 'exclude_actions' => array('login', 'logout')))['items']
                : array(),
        ));
    }

    public static function list_scanners(WP_REST_Request $request) {
        return rest_ensure_response(Alb_Scanners::query($request->get_params()));
    }

    public static function create_scanner(WP_REST_Request $request) {
        return self::respond(Alb_Scanners::create($request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function get_scanner(WP_REST_Request $request) {
        $item = Alb_Scanners::get((int) $request['id']);
        if (!$item) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404)));
        }
        $item['history'] = Alb_Capabilities::current_user_can('history.view') ? Alb_Scanners::history($item['id']) : array();
        return rest_ensure_response($item);
    }

    public static function update_scanner(WP_REST_Request $request) {
        return self::respond_scanner(Alb_Scanners::update((int) $request['id'], $request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function assign_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $driver_id = Alb_Scanners::person_id_from_request($params, get_current_user_id());
        if (is_wp_error($driver_id)) {
            return self::respond($driver_id);
        }
        return self::respond_scanner(Alb_Scanners::assign(
            (int) $request['id'],
            $driver_id,
            $params['handover_date'] ?? '',
            $params['notes'] ?? '',
            get_current_user_id()
        ));
    }

    public static function status_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $result = Alb_Scanners::change_status((int) $request['id'], $params['status'] ?? '', $params['notes'] ?? '', get_current_user_id());
        if (!is_wp_error($result)) {
            $map = array(
                'lost' => 'mark_lost',
                'defective' => 'mark_defective',
                'returned' => 'mark_returned',
                'inactive' => 'deactivate',
                'active' => 'restore',
            );
            $status = $result['status'] ?? '';
            Alb_Scan::record($result, $map[$status] ?? 'status', $params['notes'] ?? '');
        }
        return self::respond_scanner($result);
    }

    public static function delete_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond_scanner(Alb_Scanners::soft_delete((int) $request['id'], $params['notes'] ?? '', get_current_user_id()));
    }

    public static function restore_scanner(WP_REST_Request $request) {
        $item = Alb_Scanners::get((int) $request['id']);
        if (!$item) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('scanner.error.not_found'), array('status' => 404)));
        }
        if (!empty($item['deleted_at']) && !Alb_Capabilities::current_user_can('scanners.delete')) {
            return self::respond(new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403)));
        }
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond_scanner(Alb_Scanners::restore((int) $request['id'], $params['notes'] ?? '', get_current_user_id()));
    }

    public static function take_over_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $driver_id = Alb_Scanners::person_id_from_request($params, get_current_user_id());
        if (is_wp_error($driver_id)) {
            return self::respond($driver_id);
        }
        return self::respond_scanner(Alb_Scanners::take_over(
            (int) $request['id'],
            $driver_id,
            $params['notes'] ?? '',
            get_current_user_id()
        ));
    }

    public static function public_scan(WP_REST_Request $request) {
        $scanner = Alb_Scanners::public_view((string) $request['token']);
        if (!$scanner) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('scan.not_found'), array('status' => 404)));
        }
        return rest_ensure_response(array(
            'record' => self::public_scanner_payload($scanner),
        ));
    }

    public static function public_scan_action(WP_REST_Request $request) {
        return self::respond(new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403)));
    }

    public static function dispatch_scan_action($scanner, $action, $params) {
        return self::run_scan_action($scanner, $action, $params, $params['notes'] ?? '');
    }

    private static function run_scan_action($scanner, $action, $params, $notes) {
        $id = (int) $scanner['id'];
        $user_id = get_current_user_id();
        if ($action === 'take_over') {
            if (!Alb_Capabilities::current_user_can('scanners.assign')) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
            }
            return Alb_Scanners::take_over($id, (int) ($params['driver_id'] ?? 0), $notes, $user_id);
        }
        if ($action === 'return' || $action === 'mark_returned') {
            if (!Alb_Capabilities::current_user_can('scanners.status')) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
            }
            return Alb_Scanners::return_device($id, $notes, $user_id);
        }
        if (in_array($action, array('mark_lost', 'mark_defective', 'deactivate', 'status'), true)) {
            if (!Alb_Capabilities::current_user_can('scanners.status')) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
            }
            $map = array(
                'mark_lost' => 'lost',
                'mark_defective' => 'defective',
                'deactivate' => 'inactive',
            );
            $status = $map[$action] ?? sanitize_key($params['status'] ?? '');
            $result = Alb_Scanners::change_status($id, $status, $notes, $user_id);
            if (!is_wp_error($result)) {
                Alb_Scan::record($result, $action === 'status' ? 'status' : $action, $notes);
            }
            return $result;
        }
        if ($action === 'restore') {
            if (!empty($scanner['deleted_at']) && !Alb_Capabilities::current_user_can('scanners.delete')) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
            }
            if (empty($scanner['deleted_at']) && !Alb_Capabilities::current_user_can('scanners.status')) {
                return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
            }
            return Alb_Scanners::restore($id, $notes, $user_id);
        }
        return new WP_Error('alb_invalid', Alb_I18n::t('common.error'), array('status' => 400));
    }

    private static function public_scanner_payload($scanner) {
        $has_driver = !empty($scanner['current_driver_id']);
        return array(
            'scanner_code' => $scanner['scanner_code'],
            'brand' => $scanner['brand'],
            'model' => $scanner['model'],
            'serial_number' => $scanner['serial_number'],
            'phone_number' => $scanner['phone_number'],
            'status' => $scanner['status'],
            'status_label' => Alb_I18n::t('status.' . $scanner['status']),
            'branch_label' => (!empty($scanner['driver_branch']) ? $scanner['driver_branch_label'] : $scanner['branch_label']) ?: ($scanner['branch_label'] ?? ''),
            'handover_date_display' => $scanner['handover_at_display'] ?: $scanner['handover_date_display'],
            'driver_name' => $scanner['driver_name'] ?: '',
            'driver_phone' => $scanner['driver_phone'] ?: '',
            'driver_photo_url' => $has_driver ? Alb_Scanners::public_photo_url($scanner['qr_token'], $scanner['driver_photo_path'] ?? '') : '',
        );
    }

    private static function scan_permissions() {
        return array(
            'assign' => Alb_Capabilities::current_user_can('scanners.assign'),
            'status' => Alb_Capabilities::current_user_can('scanners.status'),
            'delete' => Alb_Capabilities::current_user_can('scanners.delete'),
            'view_record' => is_user_logged_in() && Alb_Capabilities::current_user_can('scanners.view'),
        );
    }

    public static function scanner_history(WP_REST_Request $request) {
        return rest_ensure_response(array('items' => Alb_Scanners::history((int) $request['id'])));
    }

    public static function list_drivers(WP_REST_Request $request) {
        return rest_ensure_response(Alb_Drivers::query($request->get_params()));
    }

    public static function create_driver(WP_REST_Request $request) {
        return self::respond(Alb_Drivers::create($request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function get_driver(WP_REST_Request $request) {
        $item = Alb_Drivers::get((int) $request['id']);
        if (!$item) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('driver.error.not_found'), array('status' => 404)));
        }
        $item['assigned_scanners'] = Alb_Drivers::assigned_scanners($item['id']);
        $item['assigned_phones'] = Alb_Capabilities::current_user_can('phones.view') ? Alb_Phones::assigned_to_driver($item['id']) : array();
        $item['history'] = Alb_Capabilities::current_user_can('history.view') ? Alb_Drivers::history($item['id']) : array();
        return rest_ensure_response($item);
    }

    public static function update_driver(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        if (isset($params['status']) && $params['status'] === 'inactive' && !Alb_Capabilities::current_user_can('drivers.deactivate')) {
            return self::respond(new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403)));
        }
        return self::respond_driver(Alb_Drivers::update((int) $request['id'], $params, get_current_user_id()));
    }

    public static function audit(WP_REST_Request $request) {
        return rest_ensure_response(Alb_Audit::query($request->get_params()));
    }

    public static function list_users(WP_REST_Request $request) {
        return rest_ensure_response(Alb_Users::list_users($request->get_params()));
    }

    public static function get_user(WP_REST_Request $request) {
        $item = Alb_Users::get((int) $request['id']);
        if (!$item) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('users.error.not_found'), array('status' => 404)));
        }
        return rest_ensure_response($item);
    }

    public static function create_user(WP_REST_Request $request) {
        return self::respond(Alb_Users::create($request->get_json_params() ?: $request->get_params()));
    }

    public static function update_user(WP_REST_Request $request) {
        return self::respond(Alb_Users::update((int) $request['id'], $request->get_json_params() ?: $request->get_params()));
    }

    public static function user_photo(WP_REST_Request $request) {
        return self::respond(Alb_Users::set_photo((int) $request['id'], Alb_Photos::from_request($request)));
    }

    public static function driver_photo(WP_REST_Request $request) {
        return self::respond(Alb_Drivers::set_photo((int) $request['id'], Alb_Photos::from_request($request), get_current_user_id()));
    }

    public static function list_phones(WP_REST_Request $request) {
        return rest_ensure_response(Alb_Phones::query($request->get_params()));
    }

    public static function create_phone(WP_REST_Request $request) {
        return self::respond(Alb_Phones::create($request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function get_phone(WP_REST_Request $request) {
        $item = Alb_Phones::get((int) $request['id']);
        if (!$item) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('phone.error.not_found'), array('status' => 404)));
        }
        $item['history'] = Alb_Capabilities::current_user_can('history.view') ? Alb_Phones::history($item['id']) : array();
        return rest_ensure_response($item);
    }

    public static function update_phone(WP_REST_Request $request) {
        return self::respond_phone(Alb_Phones::update((int) $request['id'], $request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function assign_phone(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $driver_id = Alb_Scanners::person_id_from_request($params, get_current_user_id());
        if (is_wp_error($driver_id)) {
            return self::respond($driver_id);
        }
        return self::respond_phone(Alb_Phones::assign(
            (int) $request['id'],
            $driver_id,
            $params['assigned_date'] ?? '',
            $params['notes'] ?? '',
            get_current_user_id()
        ));
    }

    public static function return_phone(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond_phone(Alb_Phones::return_phone((int) $request['id'], $params['notes'] ?? '', get_current_user_id()));
    }

    public static function status_phone(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond_phone(Alb_Phones::change_status((int) $request['id'], $params['status'] ?? '', $params['notes'] ?? '', get_current_user_id()));
    }

    public static function phone_history(WP_REST_Request $request) {
        return rest_ensure_response(array('items' => Alb_Phones::history((int) $request['id'])));
    }

    public static function check_updates() {
        return rest_ensure_response(Alb_Updates::payload(true));
    }

    public static function get_settings() {
        return rest_ensure_response(Alb_Settings::public_settings());
    }

    public static function update_settings(WP_REST_Request $request) {
        $before = Alb_Settings::get();
        $after = Alb_Settings::update($request->get_json_params() ?: $request->get_params());
        if (is_wp_error($after)) {
            return self::respond($after);
        }
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                Alb_Audit::record(array(
                    'action' => 'settings_update',
                    'entity_type' => 'settings',
                    'entity_id' => 0,
                    'field' => $key,
                    'old' => (string) ($before[$key] ?? ''),
                    'new' => (string) $value,
                ));
            }
        }
        return rest_ensure_response(Alb_Settings::public_settings());
    }

    public static function get_permissions() {
        return rest_ensure_response(array(
            'roles' => Alb_Capabilities::roles(),
            'keys' => Alb_Capabilities::permission_keys(),
            'map' => Alb_Capabilities::map(),
        ));
    }

    public static function update_permissions(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $map = isset($params['map']) && is_array($params['map']) ? $params['map'] : $params;
        $saved = Alb_Capabilities::save_map($map);
        if (is_wp_error($saved)) {
            return self::respond($saved);
        }
        Alb_Audit::record(array(
            'action' => 'permissions_update',
            'entity_type' => 'settings',
            'entity_id' => 0,
            'field' => 'permissions',
        ));
        return rest_ensure_response(array('map' => $saved));
    }

    public static function export(WP_REST_Request $request) {
        $result = Alb_Export::send($request['type'], $request->get_param('format') ?: 'csv');
        if (is_wp_error($result)) {
            return self::respond($result);
        }
        return $result;
    }

    private static function respond($result) {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;
            return new WP_REST_Response(array('code' => $result->get_error_code(), 'message' => $result->get_error_message()), $status);
        }
        return rest_ensure_response($result);
    }

    private static function respond_scanner($result) {
        return self::respond(self::with_scanner_detail($result));
    }

    private static function respond_driver($result) {
        return self::respond(self::with_driver_detail($result));
    }

    private static function respond_phone($result) {
        return self::respond(self::with_phone_detail($result));
    }

    private static function with_phone_detail($result) {
        if (is_wp_error($result) || !is_array($result) || empty($result['id'])) {
            return $result;
        }
        $result['history'] = Alb_Capabilities::current_user_can('history.view')
            ? Alb_Phones::history((int) $result['id'])
            : array();
        return $result;
    }

    private static function with_scanner_detail($result) {
        if (is_wp_error($result) || !is_array($result) || empty($result['id'])) {
            return $result;
        }
        $result['history'] = Alb_Capabilities::current_user_can('history.view')
            ? Alb_Scanners::history((int) $result['id'])
            : array();
        return $result;
    }

    private static function with_driver_detail($result) {
        if (is_wp_error($result) || !is_array($result) || empty($result['id'])) {
            return $result;
        }
        $result['assigned_scanners'] = Alb_Drivers::assigned_scanners((int) $result['id']);
        $result['assigned_phones'] = Alb_Capabilities::current_user_can('phones.view')
            ? Alb_Phones::assigned_to_driver((int) $result['id'])
            : array();
        $result['history'] = Alb_Capabilities::current_user_can('history.view')
            ? Alb_Drivers::history((int) $result['id'])
            : array();
        return $result;
    }
}
