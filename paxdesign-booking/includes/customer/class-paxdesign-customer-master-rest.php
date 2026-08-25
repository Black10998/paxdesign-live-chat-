<?php
/**
 * Master administrator REST API for in-account customer management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Master_REST {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 25);
    }

    public static function register_routes() {
        $guard = array('permission_callback' => array('PAXdesign_Customer_Master_Admin', 'require_master_admin'));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/customers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_customers'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/customers/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_customer'),
                'permission_callback' => $guard['permission_callback'],
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_customer'),
                'permission_callback' => $guard['permission_callback'],
            ),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/levels', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_levels'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/overview', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'overview'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/reports', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'reports'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/projects', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_projects'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_orders'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/tickets', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_tickets'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/files', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_files'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/services', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_services'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/conversations', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_conversations'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/notifications', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'list_notifications'),
                'permission_callback' => $guard['permission_callback'],
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'send_notification'),
                'permission_callback' => $guard['permission_callback'],
            ),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/news', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_news'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/staff', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'list_staff'),
                'permission_callback' => $guard['permission_callback'],
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'save_staff'),
                'permission_callback' => $guard['permission_callback'],
            ),
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/staff/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'remove_staff'),
            'permission_callback' => $guard['permission_callback'],
        ));

        register_rest_route(PAXdesign_Customer_REST::NS, '/customer/master/permissions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'permission_catalog'),
            'permission_callback' => $guard['permission_callback'],
        ));
    }

    public static function list_customers(WP_REST_Request $request) {
        $search = sanitize_text_field((string) $request->get_param('search'));
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 50)));

        $result = PAXdesign_Customer_Registry::query_manageable_customers($search, $page, $per_page);
        $items = array();
        foreach ($result['users'] as $user) {
            if ($user instanceof WP_User) {
                $summary = self::customer_summary($user->ID);
                if (!empty($summary)) {
                    $items[] = $summary;
                }
            }
        }

        return rest_ensure_response(array(
            'customers' => $items,
            'total'     => (int) $result['total'],
            'page'      => (int) $result['page'],
            'per_page'  => (int) $result['per_page'],
        ));
    }

    public static function get_customer(WP_REST_Request $request) {
        $user_id = absint($request->get_param('id'));
        PAXdesign_Customer_Registry::ensure_portal_customer($user_id);
        if ($user_id <= 0 || !self::is_manageable_customer($user_id)) {
            return new WP_Error('invalid_customer', __('Customer not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response(array('customer' => self::customer_detail($user_id)));
    }

    public static function update_customer(WP_REST_Request $request) {
        $user_id = absint($request->get_param('id'));
        if ($user_id <= 0 || !self::is_manageable_customer($user_id)) {
            return new WP_Error('invalid_customer', __('Customer not found.', 'paxdesign-booking'), array('status' => 404));
        }
        if (PAXdesign_Customer_Master_Admin::is_master_admin($user_id)) {
            return new WP_Error('invalid_customer', __('Cannot modify the master administrator account through customer management.', 'paxdesign-booking'), array('status' => 400));
        }

        $display_name = sanitize_text_field((string) $request->get_param('display_name'));
        if ($display_name !== '') {
            wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
        }

        $email = sanitize_email((string) $request->get_param('email'));
        if ($email !== '' && is_email($email)) {
            $existing = email_exists($email);
            if ($existing && (int) $existing !== $user_id) {
                return new WP_Error('email_exists', __('That email is already in use.', 'paxdesign-booking'), array('status' => 400));
            }
            wp_update_user(array('ID' => $user_id, 'user_email' => $email));
        }

        if ($request->has_param('account_status')) {
            $status = sanitize_key((string) $request->get_param('account_status'));
            if ($status === PAXdesign_Customers::STATUS_ACTIVE) {
                PAXdesign_Customers::activate($user_id);
            } elseif ($status === PAXdesign_Customers::STATUS_SUSPENDED) {
                PAXdesign_Customers::suspend($user_id);
            } elseif ($status === PAXdesign_Customers::STATUS_PENDING) {
                PAXdesign_Customers::set_account_status($user_id, PAXdesign_Customers::STATUS_PENDING);
            }
        }

        if ($request->has_param('customer_level')) {
            $level = PAXdesign_Customer_Levels::sanitize_level((int) $request->get_param('customer_level'));
            if ($level > 0) {
                PAXdesign_Customer_Levels::set_level_for_user($user_id, $level);
            } else {
                PAXdesign_Customer_Levels::clear_level_for_user($user_id);
            }
        }

        if ($request->has_param('vip_avatar_id') && class_exists('PAXdesign_Customer_Avatar')) {
            $avatar_id = PAXdesign_Customer_Avatar::sanitize_preset_id((string) $request->get_param('vip_avatar_id'));
            if ($avatar_id === '') {
                // no-op
            } elseif (PAXdesign_Customer_Avatar_Vip_Presets::is_vip($avatar_id)) {
                PAXdesign_Customer_Avatar::grant_vip_avatar($user_id, $avatar_id, true);
            } elseif (PAXdesign_Customer_Avatar_Presets::exists($avatar_id)) {
                PAXdesign_Customer_Avatar::set_preset_for_user($user_id, $avatar_id);
            }
        }

        if ($request->has_param('revoke_vip_avatar_id') && class_exists('PAXdesign_Customer_Avatar')) {
            $revoke_id = PAXdesign_Customer_Avatar_Vip_Presets::sanitize_id((string) $request->get_param('revoke_vip_avatar_id'));
            if ($revoke_id !== '') {
                PAXdesign_Customer_Avatar::revoke_vip_avatar($user_id, $revoke_id);
            }
        }

        if ($request->has_param('remove_avatar_upload') && class_exists('PAXdesign_Customer_Avatar')) {
            $remove = rest_sanitize_boolean($request->get_param('remove_avatar_upload'));
            if ($remove) {
                PAXdesign_Customer_Avatar::remove_upload_for_user($user_id);
            }
        }

        if ($request->has_param('admin_notes')) {
            PAXdesign_Customers::save_notes($user_id, (string) $request->get_param('admin_notes'));
        }

        PAXdesign_Customer_Registry::ensure_portal_customer($user_id);
        update_user_meta($user_id, PAXdesign_Customer_Registry::META_SYNCED_AT, current_time('mysql'));

        return rest_ensure_response(array(
            'success'  => true,
            'customer' => self::customer_detail($user_id),
        ));
    }

    public static function list_levels() {
        return rest_ensure_response(array(
            'levels'      => PAXdesign_Customer_Levels::catalog(),
            'vip_presets' => PAXdesign_Customer_Avatar_Vip_Presets::catalog_preview(),
            'standard_presets' => class_exists('PAXdesign_Customer_Avatar_Presets')
                ? PAXdesign_Customer_Avatar_Presets::catalog()
                : array(),
        ));
    }

    public static function overview() {
        return rest_ensure_response(array(
            'stats' => self::platform_stats(),
            'role'  => 'owner',
        ));
    }

    public static function reports() {
        $stats = self::platform_stats();
        $orders = class_exists('PAXdesign_Customer_Orders')
            ? PAXdesign_Customer_Orders::list_for_staff('', 200)
            : array();
        $by_status = array();
        foreach ($orders as $order) {
            $status = sanitize_key((string) ($order['status'] ?? 'unknown'));
            if ($status === '') {
                $status = 'unknown';
            }
            if (!isset($by_status[$status])) {
                $by_status[$status] = 0;
            }
            $by_status[$status]++;
        }
        return rest_ensure_response(array(
            'stats'         => $stats,
            'orders_by_status' => $by_status,
            'recent_orders' => array_slice($orders, 0, 8),
            'recent_projects' => class_exists('PAXdesign_Customer_Projects')
                ? array_slice(PAXdesign_Customer_Projects::list_for_staff('', 8), 0, 8)
                : array(),
        ));
    }

    public static function list_projects(WP_REST_Request $request) {
        $status = sanitize_key((string) ($request->get_param('status') ?? ''));
        $limit = absint($request->get_param('limit') ?? 100);
        return rest_ensure_response(array(
            'projects' => PAXdesign_Customer_Projects::list_for_staff($status, $limit),
        ));
    }

    public static function list_orders(WP_REST_Request $request) {
        $status = sanitize_key((string) ($request->get_param('status') ?? ''));
        $limit = absint($request->get_param('limit') ?? 100);
        return rest_ensure_response(array(
            'orders' => PAXdesign_Customer_Orders::list_for_staff($status, $limit),
        ));
    }

    public static function list_tickets(WP_REST_Request $request) {
        $limit = absint($request->get_param('limit') ?? 80);
        $tickets = class_exists('PAXdesign_Cybercrime_Tickets')
            ? PAXdesign_Cybercrime_Tickets::list_reports_for_admin($limit)
            : array();
        return rest_ensure_response(array('tickets' => $tickets));
    }

    public static function list_files(WP_REST_Request $request) {
        $limit = absint($request->get_param('limit') ?? 100);
        return rest_ensure_response(array(
            'files' => PAXdesign_Customer_Orders::library_for_staff($limit),
        ));
    }

    public static function list_services() {
        $services = class_exists('PAXdesign_Customer_Services')
            ? PAXdesign_Customer_Services::list_for_admin()
            : array();
        return rest_ensure_response(array('services' => $services));
    }

    public static function list_conversations() {
        $sessions = array();
        $live_count = 0;
        if (class_exists('PAXdesign_Chat_Live')) {
            $live = PAXdesign_Chat_Live::get_instance()->get_live_list_data(false);
            if (!is_wp_error($live) && is_array($live)) {
                $sessions = isset($live['sessions']) && is_array($live['sessions']) ? $live['sessions'] : array();
                $live_count = (int) ($live['live_count'] ?? 0);
            }
        }
        return rest_ensure_response(array(
            'conversations' => $sessions,
            'live_count'    => $live_count,
        ));
    }

    public static function list_notifications(WP_REST_Request $request) {
        $limit = absint($request->get_param('limit') ?? 80);
        return rest_ensure_response(array(
            'notifications' => PAXdesign_Customer_Notifications::list_recent_for_admin($limit),
        ));
    }

    public static function send_notification(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $title = sanitize_text_field((string) ($params['title'] ?? ''));
        $body = sanitize_textarea_field((string) ($params['body'] ?? ''));
        if ($title === '') {
            return new WP_Error('invalid_payload', __('Title is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $result = PAXdesign_Customer_Notifications::broadcast(
            $title,
            $body,
            sanitize_key((string) ($params['category'] ?? 'general')),
            absint($params['user_id'] ?? 0)
        );
        return rest_ensure_response(array_merge(array('success' => true), $result));
    }

    public static function list_news() {
        $items = class_exists('PAXdesign_Customer_News')
            ? PAXdesign_Customer_News::list_admin()
            : array();
        $out = array();
        foreach ($items as $item) {
            $out[] = array(
                'id'           => (int) ($item['id'] ?? 0),
                'title'        => (string) ($item['title'] ?? ''),
                'slug'         => (string) ($item['slug'] ?? ''),
                'status'       => (string) ($item['status'] ?? ''),
                'published_at' => (string) ($item['published_at'] ?? ''),
                'updated_at'   => (string) ($item['updated_at'] ?? ''),
            );
        }
        return rest_ensure_response(array('news' => $out));
    }

    public static function list_staff() {
        $staff = class_exists('PAXdesign_Live_Chat_Permissions')
            ? PAXdesign_Live_Chat_Permissions::list_staff_for_api()
            : array();
        $catalog = class_exists('PAXdesign_Live_Chat_Permissions')
            ? PAXdesign_Live_Chat_Permissions::permission_labels()
            : array();
        return rest_ensure_response(array(
            'staff'       => $staff,
            'permissions' => self::format_permission_catalog($catalog),
        ));
    }

    public static function save_staff(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Live_Chat_Permissions')) {
            return new WP_Error('unavailable', __('Staff permissions are unavailable.', 'paxdesign-booking'), array('status' => 500));
        }
        $params = $request->get_json_params() ?: $request->get_params();
        $user_id = absint($params['user_id'] ?? 0);
        $email = sanitize_email((string) ($params['email'] ?? ''));
        if ($user_id <= 0 && $email !== '') {
            $found = get_user_by('email', $email);
            if ($found instanceof WP_User) {
                $user_id = (int) $found->ID;
            }
        }
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', __('Provide an existing staff email or user id.', 'paxdesign-booking'), array('status' => 400));
        }
        $existing = PAXdesign_Live_Chat_Permissions::get_staff_record($user_id);
        $permissions = isset($params['permissions']) && is_array($params['permissions'])
            ? $params['permissions']
            : (isset($existing['permissions']) && is_array($existing['permissions']) ? $existing['permissions'] : array());
        $enabled = array_key_exists('enabled', $params)
            ? rest_sanitize_boolean($params['enabled'])
            : (empty($existing) ? true : !empty($existing['enabled']));
        $result = PAXdesign_Live_Chat_Permissions::save_staff_record($user_id, array(
            'enabled'     => $enabled,
            'permissions' => $permissions,
            'team_role'   => sanitize_key((string) ($params['team_role'] ?? ($existing['team_role'] ?? ''))),
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array(
            'success' => true,
            'staff'   => PAXdesign_Live_Chat_Permissions::list_staff_for_api(),
        ));
    }

    public static function remove_staff(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Live_Chat_Permissions')) {
            return new WP_Error('unavailable', __('Staff permissions are unavailable.', 'paxdesign-booking'), array('status' => 500));
        }
        $result = PAXdesign_Live_Chat_Permissions::remove_staff(absint($request->get_param('id')));
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array(
            'success' => true,
            'staff'   => PAXdesign_Live_Chat_Permissions::list_staff_for_api(),
        ));
    }

    public static function permission_catalog() {
        $catalog = class_exists('PAXdesign_Live_Chat_Permissions')
            ? PAXdesign_Live_Chat_Permissions::permission_labels()
            : array();
        return rest_ensure_response(array(
            'permissions' => self::format_permission_catalog($catalog),
            'staff'       => class_exists('PAXdesign_Live_Chat_Permissions')
                ? PAXdesign_Live_Chat_Permissions::list_staff_for_api()
                : array(),
        ));
    }

    /**
     * @return array<string, int>
     */
    private static function platform_stats() {
        global $wpdb;
        $customers = PAXdesign_Customer_Registry::query_manageable_customers('', 1, 1);
        $staff_count = class_exists('PAXdesign_Live_Chat_Permissions')
            ? count(PAXdesign_Live_Chat_Permissions::list_staff_for_api())
            : 0;
        return array(
            'customers'      => (int) ($customers['total'] ?? 0),
            'staff'          => $staff_count,
            'projects'       => self::count_table('projects'),
            'orders'         => self::count_table('orders'),
            'open_orders'    => (int) $wpdb->get_var(
                "SELECT COUNT(1) FROM " . PAXdesign_Customer_DB::table('orders') .
                " WHERE status NOT IN ('completed','cancelled')"
            ),
            'tickets'        => class_exists('PAXdesign_Cybercrime_Intake')
                ? (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . PAXdesign_Cybercrime_Intake::table_name())
                : 0,
            'files'          => self::count_table('project_files') + self::count_table('order_files'),
            'services'       => self::count_table('services'),
            'conversations'  => class_exists('PAXdesign_Chat_Log')
                ? (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . PAXdesign_Chat_Log::table_name())
                : 0,
            'notifications'  => self::count_table('notifications'),
            'news'           => self::count_table('news'),
        );
    }

    /**
     * @param string $suffix
     * @return int
     */
    private static function count_table($suffix) {
        global $wpdb;
        $table = PAXdesign_Customer_DB::table($suffix);
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return 0;
        }
        return (int) $wpdb->get_var("SELECT COUNT(1) FROM $table");
    }

    /**
     * @param array<string, string> $labels
     * @return array<int, array<string, string>>
     */
    private static function format_permission_catalog($labels) {
        $out = array();
        foreach ($labels as $key => $label) {
            $out[] = array(
                'key'   => (string) $key,
                'label' => (string) $label,
            );
        }
        return $out;
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    private static function customer_summary($user_id) {
        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return array();
        }
        $email = PAXdesign_Customer_Registry::account_email($user_id);
        $level = PAXdesign_Customer_Levels::profile_fields($user_id);
        $avatar = class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::profile_fields($user_id) : array();
        return array_merge(
            array(
                'id'                 => $user_id,
                'display_name'       => $user->display_name,
                'email'              => $email,
                'user_login'         => $user->user_login,
                'registered'         => $user->user_registered,
                'verified'           => class_exists('PAXdesign_Auth') ? PAXdesign_Auth::is_email_verified($user_id) : false,
                'account_status'     => PAXdesign_Customers::account_status($user_id),
                'customer_level'     => $level['customer_level'],
                'level_label'        => $level['level_label'],
                'has_customer_level' => $level['has_customer_level'],
                'avatar_preset'      => isset($avatar['avatar_preset']) ? $avatar['avatar_preset'] : '',
                'avatar_url'         => isset($avatar['avatar_url']) ? $avatar['avatar_url'] : '',
                'avatar_has_image'   => !empty($avatar['avatar_has_image']),
                'avatar_has_upload'  => !empty($avatar['avatar_has_upload']),
            )
        );
    }

    /**
     * @param int $user_id
     * @return array<string, mixed>
     */
    private static function customer_detail($user_id) {
        $summary = self::customer_summary($user_id);
        $avatar = class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::profile_fields($user_id) : array();
        $level = PAXdesign_Customer_Levels::profile_fields($user_id);
        return array_merge($summary, $avatar, $level, array(
            'admin_notes'       => PAXdesign_Customers::admin_notes($user_id),
            'vip_avatar_grants' => class_exists('PAXdesign_Customer_Avatar') ? PAXdesign_Customer_Avatar::vip_grants_for_user($user_id) : array(),
            'last_login'        => PAXdesign_Customers::last_login($user_id),
            'standard_presets'  => class_exists('PAXdesign_Customer_Avatar_Presets') ? PAXdesign_Customer_Avatar_Presets::catalog() : array(),
            'vip_presets'       => class_exists('PAXdesign_Customer_Avatar_Vip_Presets') ? PAXdesign_Customer_Avatar_Vip_Presets::catalog_for_user($user_id) : array(),
        ));
    }

    /**
     * @param int $user_id
     * @return bool
     */
    private static function is_manageable_customer($user_id) {
        return PAXdesign_Customer_Registry::is_manageable_by_master_admin($user_id);
    }
}
