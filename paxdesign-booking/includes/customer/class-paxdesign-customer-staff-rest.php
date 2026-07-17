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

        register_rest_route(self::NS, '/customer/staff/projects/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array(__CLASS__, 'update_project'),
                'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
            ),
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

        register_rest_route(self::NS, '/customer/staff/orders/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array(__CLASS__, 'update_order'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_staff'),
        ));

        register_rest_route(self::NS, '/customer/staff/notifications', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'send_notification'),
            'permission_callback' => array('PAXdesign_Customer_Auth', 'require_admin'),
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
