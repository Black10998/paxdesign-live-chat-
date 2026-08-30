<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Rest {
    const NS = 'albatros/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register'));
        add_filter('rest_authentication_errors', array(__CLASS__, 'allow_auth_routes'), 99);
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
                'permission_callback' => array(__CLASS__, 'can_scanners_edit'),
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
            'roles' => Alb_Capabilities::roles(),
            'permission_keys' => Alb_Capabilities::permission_keys(),
            'assignable_roles' => Alb_Capabilities::assignable_roles(),
            'can_assign_permissions' => Alb_Capabilities::can_assign_user_permissions(),
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
        return self::respond(Alb_Scanners::update((int) $request['id'], $request->get_json_params() ?: $request->get_params(), get_current_user_id()));
    }

    public static function assign_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond(Alb_Scanners::assign(
            (int) $request['id'],
            (int) ($params['driver_id'] ?? 0),
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
        return self::respond($result);
    }

    public static function delete_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond(Alb_Scanners::soft_delete((int) $request['id'], $params['notes'] ?? '', get_current_user_id()));
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
        return self::respond(Alb_Scanners::restore((int) $request['id'], $params['notes'] ?? '', get_current_user_id()));
    }

    public static function take_over_scanner(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        return self::respond(Alb_Scanners::take_over(
            (int) $request['id'],
            (int) ($params['driver_id'] ?? 0),
            $params['notes'] ?? '',
            get_current_user_id()
        ));
    }

    public static function public_scan(WP_REST_Request $request) {
        $scanner = Alb_Scanners::get_by_qr((string) $request['token']);
        if (!$scanner) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('scan.not_found'), array('status' => 404)));
        }
        $identity = Alb_Scan::identity();
        if (!empty($identity['identified'])) {
            Alb_Scan::maybe_record_open($scanner);
        }
        return rest_ensure_response(array(
            'scanner' => self::public_scanner_payload($scanner),
            'identity' => $identity,
            'permissions' => self::scan_permissions(),
            'nonce' => wp_create_nonce('wp_rest'),
        ));
    }

    public static function public_scan_action(WP_REST_Request $request) {
        $scanner = Alb_Scanners::get_by_qr((string) $request['token']);
        if (!$scanner) {
            return self::respond(new WP_Error('alb_not_found', Alb_I18n::t('scan.not_found'), array('status' => 404)));
        }
        $params = $request->get_json_params() ?: $request->get_params();
        $action = sanitize_key($params['alb_action'] ?? $params['action'] ?? '');
        if ($action === 'identify') {
            $name = Alb_Scan::set_guest_name($params['full_name'] ?? $params['name'] ?? '');
            if (is_wp_error($name)) {
                return self::respond($name);
            }
            Alb_Scan::maybe_record_open($scanner);
            return rest_ensure_response(array(
                'ok' => true,
                'identity' => Alb_Scan::identity(),
                'scanner' => self::public_scanner_payload($scanner),
                'nonce' => wp_create_nonce('wp_rest'),
            ));
        }
        if (!is_user_logged_in()) {
            return self::respond(new WP_Error('alb_auth', Alb_I18n::t('error.auth_required'), array('status' => 401)));
        }
        $notes = $params['notes'] ?? '';
        $result = self::run_scan_action($scanner, $action, $params, $notes);
        return self::respond($result);
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
        return array(
            'scanner_code' => $scanner['scanner_code'],
            'brand' => $scanner['brand'],
            'model' => $scanner['model'],
            'serial_number' => $scanner['serial_number'],
            'phone_number' => $scanner['phone_number'],
            'status' => $scanner['status'],
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
        $item['history'] = Alb_Capabilities::current_user_can('history.view') ? Alb_Drivers::history($item['id']) : array();
        return rest_ensure_response($item);
    }

    public static function update_driver(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        if (isset($params['status']) && $params['status'] === 'inactive' && !Alb_Capabilities::current_user_can('drivers.deactivate')) {
            return self::respond(new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403)));
        }
        return self::respond(Alb_Drivers::update((int) $request['id'], $params, get_current_user_id()));
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

    public static function get_settings() {
        return rest_ensure_response(Alb_Settings::public_settings());
    }

    public static function update_settings(WP_REST_Request $request) {
        $before = Alb_Settings::get();
        $after = Alb_Settings::update($request->get_json_params() ?: $request->get_params());
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
}
