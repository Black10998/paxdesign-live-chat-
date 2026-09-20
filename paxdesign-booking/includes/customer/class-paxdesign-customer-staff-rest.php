<?php
/**
 * Staff/admin REST for customer platform management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Staff_REST {

    const NS = 'pdx/v1';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'), 21);
    }

    public static function register_routes() {
        $staff = array('permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'));
        $admin = array('permission_callback' => array('PAXdesign_Customer_Auth', 'require_admin'));

        register_rest_route(self::NS, '/customer/staff/projects', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_projects'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_project'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
            ),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/files/(?P<file_id>\d+)/download', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'download_project_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/milestones', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'add_milestone'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/milestones/(?P<mid>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array(__CLASS__, 'update_milestone'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/notes', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'add_note'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/assignees', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'assign_user'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)/files', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'upload_project_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'list_orders'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/orders/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_order'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_order'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
            ),
        ));

        register_rest_route(self::NS, '/customer/staff/orders/(?P<id>\d+)/files/(?P<file_id>\d+)/download', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'download_order_file'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/notifications', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'send_notification'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_admin'),
        ));
    }

    public static function list_projects(WP_REST_Request $request) {
        $status = sanitize_key((string) ($request->get_param('status') ?? ''));
        $limit = absint($request->get_param('limit') ?? 100);
        return rest_ensure_response(array(
            'projects' => PAXdesign_Customer_Projects::list_for_staff($status, $limit),
        ));
    }

    public static function update_project(WP_REST_Request $request) {
        $project_id = (int) $request['id'];
        $result = PAXdesign_Customer_Projects::update($project_id, $request->get_json_params() ?: $request->get_params(), PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function add_milestone(WP_REST_Request $request) {
        $result = PAXdesign_Customer_Projects::add_milestone((int) $request['id'], $request->get_json_params() ?: $request->get_params(), PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function update_milestone(WP_REST_Request $request) {
        $result = PAXdesign_Customer_Projects::update_milestone((int) $request['id'], (int) $request['mid'], $request->get_json_params() ?: $request->get_params(), PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function add_note(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $result = PAXdesign_Customer_Projects::add_note((int) $request['id'], $params, PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function assign_user(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $result = PAXdesign_Customer_Projects::assign_user((int) $request['id'], $params, PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function upload_project_file(WP_REST_Request $request) {
        $files = $request->get_file_params();
        $file = isset($files['file']) ? $files['file'] : null;
        if (!$file) {
            return new WP_Error('missing_file', __('File is required.', 'paxdesign-booking'), array('status' => 400));
        }
        $params = $request->get_params();
        $result = PAXdesign_Customer_Projects::add_file((int) $request['id'], $file, $params, PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function list_orders(WP_REST_Request $request) {
        $status = sanitize_key((string) ($request->get_param('status') ?? ''));
        $limit = absint($request->get_param('limit') ?? 100);
        return rest_ensure_response(array(
            'orders' => PAXdesign_Customer_Orders::list_for_staff($status, $limit),
        ));
    }

    public static function get_order(WP_REST_Request $request) {
        $order = PAXdesign_Customer_Orders::get_for_staff((int) $request['id']);
        if (!$order) {
            return new WP_Error('not_found', __('Order not found.', 'paxdesign-booking'), array('status' => 404));
        }
        return rest_ensure_response($order);
    }

    public static function download_project_file(WP_REST_Request $request) {
        $file = PAXdesign_Customer_Projects::get_file_for_staff((int) $request['id'], (int) $request['file_id']);
        if (!$file || empty($file['file_path']) || !file_exists($file['file_path'])) {
            return new WP_Error('not_found', __('File not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $mime = !empty($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream';
        $name = !empty($file['file_name']) ? $file['file_name'] : basename($file['file_path']);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) filesize($file['file_path']));
        readfile($file['file_path']);
        exit;
    }

    public static function download_order_file(WP_REST_Request $request) {
        global $wpdb;
        $order_id = absint($request['id']);
        $file_id = absint($request['file_id']);
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . PAXdesign_Customer_DB::table('order_files') . " WHERE id = %d AND order_id = %d LIMIT 1",
            $file_id,
            $order_id
        ), ARRAY_A);
        if (!$file || empty($file['file_path']) || !file_exists($file['file_path'])) {
            return new WP_Error('not_found', __('File not found.', 'paxdesign-booking'), array('status' => 404));
        }
        $mime = !empty($file['mime_type']) ? $file['mime_type'] : 'application/octet-stream';
        $name = !empty($file['file_name']) ? $file['file_name'] : basename($file['file_path']);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) filesize($file['file_path']));
        readfile($file['file_path']);
        exit;
    }

    public static function update_order(WP_REST_Request $request) {
        $result = PAXdesign_Customer_Orders::staff_update((int) $request['id'], $request->get_json_params() ?: $request->get_params(), PAXdesign_Customer_Auth::current_user_id());
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function send_notification(WP_REST_Request $request) {
        $params = $request->get_json_params() ?: $request->get_params();
        $user_id = absint($params['user_id'] ?? 0);
        $title = sanitize_text_field($params['title'] ?? '');
        $body = sanitize_textarea_field($params['body'] ?? '');
        if ($user_id <= 0 || $title === '') {
            return new WP_Error('invalid_payload', __('User and title are required.', 'paxdesign-booking'), array('status' => 400));
        }
        $id = PAXdesign_Customer_Notifications::notify_user(
            $user_id,
            sanitize_key($params['category'] ?? 'general'),
            $title,
            $body,
            sanitize_key($params['entity_type'] ?? ''),
            sanitize_text_field($params['entity_id'] ?? ''),
            sanitize_text_field($params['deep_link'] ?? '')
        );
        return rest_ensure_response(array('success' => true, 'notification_id' => $id));
    }
}
