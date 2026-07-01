<?php
/**
 * REST API for native iOS Live Chat admin app (Application Password auth).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Live_Chat_Mobile_API {

    const REST_NAMESPACE = 'paxdesign/v1';

    public static function init() {
        add_action('init', array(__CLASS__, 'bootstrap_basic_auth'), 1);
        add_filter('determine_current_user', array(__CLASS__, 'resolve_basic_auth_login'), 15);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Hostinger/LiteSpeed/Apache often strip Authorization unless passed through.
     */
    public static function bootstrap_basic_auth() {
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
     * Application Password auth expects user_login, not email. Map email → login early.
     *
     * @param int|false $user_id
     * @return int|false
     */
    public static function resolve_basic_auth_login($user_id) {
        if ($user_id) {
            return $user_id;
        }

        if (empty($_SERVER['PHP_AUTH_USER'])) {
            return $user_id;
        }

        $login = sanitize_text_field(wp_unslash((string) $_SERVER['PHP_AUTH_USER']));
        if ($login === '' || !is_email($login)) {
            return $user_id;
        }

        $user = get_user_by('email', $login);
        if (!$user instanceof WP_User) {
            self::log_auth_event('email_lookup_failed', array('email' => $login));
            return $user_id;
        }

        $_SERVER['PHP_AUTH_USER'] = $user->user_login;
        self::log_auth_event('email_mapped_to_login', array(
            'email' => $login,
            'login' => $user->user_login,
        ));

        return $user_id;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function log_auth_event($event, array $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $line = '[PAXdesign Live Chat Mobile API] ' . $event;
        if (!empty($context)) {
            $line .= ' ' . wp_json_encode($context);
        }
        error_log($line);
    }

    public static function register_routes() {
        $auth = array(__CLASS__, 'permission_admin');

        register_rest_route(self::REST_NAMESPACE, '/live-admin/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_me'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_sessions'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_session'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/poll', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_poll'),
            'permission_callback' => $auth,
            'args'                => array(
                'since' => array('default' => 0, 'sanitize_callback' => 'absint'),
                'full'  => array('default' => false),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/takeover', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_takeover'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/decline', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_decline'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/close', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_close'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/release', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_release'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/reopen', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_reopen'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/archive', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_archive'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'route_delete'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/messages', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_send_message'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/typing', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_typing'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/push/apns', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_apns_register'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/push/apns', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'route_apns_unregister'),
            'permission_callback' => $auth,
        ));
    }

    public static function permission_admin() {
        return PAXdesign_Chat_Live::rest_admin_authorized(true);
    }

    /**
     * @return PAXdesign_Chat_Live
     */
    private static function live() {
        return PAXdesign_Chat_Live::get_instance();
    }

    /**
     * @param array<string, mixed>|WP_Error $result
     * @return WP_REST_Response|WP_Error
     */
    private static function respond($result) {
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function route_me(WP_REST_Request $request) {
        $user = wp_get_current_user();
        return rest_ensure_response(array(
            'user_id'    => (int) $user->ID,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'username'   => $user->user_login,
            'avatar_url' => get_avatar_url((int) $user->ID, array('size' => 256)),
            'site_url'   => home_url('/'),
            'rest_base'  => rest_url(self::REST_NAMESPACE . '/live-admin/'),
            'live_agent' => PAXdesign_Chat_Live::get_agent_public_config(),
            'plugin_ver' => PAXDESIGN_BOOKING_VERSION,
        ));
    }

    public static function route_sessions(WP_REST_Request $request) {
        return self::respond(self::live()->get_live_list_data());
    }

    public static function route_session(WP_REST_Request $request) {
        return self::respond(self::live()->get_session_detail_data($request['id']));
    }

    public static function route_poll(WP_REST_Request $request) {
        $full = rest_sanitize_boolean($request->get_param('full'));
        return self::respond(self::live()->get_poll_data(
            $request['id'],
            (int) $request->get_param('since'),
            $full
        ));
    }

    public static function route_takeover(WP_REST_Request $request) {
        return self::respond(self::live()->admin_takeover($request['id']));
    }

    public static function route_decline(WP_REST_Request $request) {
        return self::respond(self::live()->admin_decline_live_request($request['id']));
    }

    public static function route_close(WP_REST_Request $request) {
        return self::respond(self::live()->admin_close($request['id']));
    }

    public static function route_release(WP_REST_Request $request) {
        return self::respond(self::live()->admin_release($request['id']));
    }

    public static function route_reopen(WP_REST_Request $request) {
        return self::respond(self::live()->admin_reopen($request['id']));
    }

    public static function route_archive(WP_REST_Request $request) {
        return self::respond(self::live()->admin_archive_session($request['id']));
    }

    public static function route_delete(WP_REST_Request $request) {
        return self::respond(self::live()->admin_delete_session($request['id']));
    }

    public static function route_send_message(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $message  = isset($params['message']) ? $params['message'] : $request->get_param('message');
        $reply_to = isset($params['reply_to']) ? $params['reply_to'] : $request->get_param('reply_to');
        return self::respond(self::live()->admin_send_message($request['id'], $message, $reply_to));
    }

    public static function route_typing(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $stop = !empty($params['stop']) || $request->get_param('stop');
        return self::respond(self::live()->admin_set_typing($request['id'], (bool) $stop));
    }

    public static function route_apns_register(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_APNS')) {
            return new WP_Error('apns_unavailable', 'APNs not configured.', array('status' => 501));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $token    = isset($params['device_token']) ? sanitize_text_field($params['device_token']) : '';
        $sandbox  = !empty($params['sandbox']);
        $bundle   = isset($params['bundle_id']) ? sanitize_text_field($params['bundle_id']) : '';

        if ($token === '') {
            return new WP_Error('invalid_token', 'device_token required.', array('status' => 400));
        }

        PAXdesign_APNS::register_device((int) get_current_user_id(), $token, $sandbox, $bundle);
        return rest_ensure_response(array('ok' => true));
    }

    public static function route_apns_unregister(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_APNS')) {
            return rest_ensure_response(array('ok' => true));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $token = isset($params['device_token']) ? sanitize_text_field($params['device_token']) : '';
        if ($token !== '') {
            PAXdesign_APNS::unregister_device((int) get_current_user_id(), $token);
        }
        return rest_ensure_response(array('ok' => true));
    }
}
