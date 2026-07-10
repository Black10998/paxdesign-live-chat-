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

    /**
     * Accept 1, true, yes, on and other common truthy REST query values.
     *
     * @param mixed $value
     * @return bool
     */
    public static function sanitize_bool_param($value) {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, array('1', 'true', 'yes', 'on'), true);
    }

    /**
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function request_wants_full_history(WP_REST_Request $request) {
        return self::sanitize_bool_param($request->get_param('full'))
            || self::sanitize_bool_param($request->get_param('history'));
    }

    public static function register_routes() {
        $auth = array(__CLASS__, 'permission_admin');

        register_rest_route(self::REST_NAMESPACE, '/live-admin/me', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_me'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/profile', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_profile_save'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_sessions'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/conversations/sync', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_conversations_sync'),
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
                'since'   => array('default' => 0, 'sanitize_callback' => 'absint'),
                'full'    => array('default' => false, 'sanitize_callback' => array(__CLASS__, 'sanitize_bool_param')),
                'history' => array('default' => false, 'sanitize_callback' => array(__CLASS__, 'sanitize_bool_param')),
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

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/images', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_send_image'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/typing', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_typing'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/quick-replies', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_quick_replies'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/suggestions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_suggestions'),
            'permission_callback' => $auth,
            'args'                => array(
                'message_id' => array('required' => true, 'sanitize_callback' => 'absint'),
            ),
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

        register_rest_route(self::REST_NAMESPACE, '/live-admin/devices', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_devices_list'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/devices/heartbeat', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_devices_heartbeat'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/devices/(?P<device_id>[a-zA-Z0-9_\-]+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'route_devices_revoke'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, '/live-admin/devices/(?P<device_id>[a-zA-Z0-9_\-]+)/approve', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_devices_approve'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/devices/force-logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_devices_force_logout'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/onboarding/reset', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_onboarding_reset'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/onboarding/complete', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_onboarding_complete'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/staff', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_staff_list'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/staff', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_staff_save'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/staff/(?P<user_id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'route_staff_remove'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, '/live-admin/staff/(?P<user_id>\d+)/force-logout', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_staff_force_logout'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_team_sessions'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/contacts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_team_contacts'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/open', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_team_open'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/(?P<id>team_[0-9]+_[0-9]+)/poll', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_team_poll'),
            'permission_callback' => $auth,
            'args'                => array(
                'since'   => array('default' => 0, 'sanitize_callback' => 'absint'),
                'full'    => array('default' => false, 'sanitize_callback' => array(__CLASS__, 'sanitize_bool_param')),
                'history' => array('default' => false, 'sanitize_callback' => array(__CLASS__, 'sanitize_bool_param')),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/(?P<id>team_[0-9]+_[0-9]+)/messages', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_team_send_message'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/(?P<id>team_[0-9]+_[0-9]+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_team_mark_read'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/(?P<id>team_[0-9]+_[0-9]+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'route_team_delete'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/events/stream', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_inbox_stream'),
            'permission_callback' => $auth,
            'args'                => array(
                'since' => array('default' => 0, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/events/ack', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_event_ack'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_\-]+)/read', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'route_session_read'),
            'permission_callback' => $auth,
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/sessions/(?P<id>[a-zA-Z0-9_]+)/stream', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_session_stream'),
            'permission_callback' => $auth,
            'args'                => array(
                'since' => array('default' => 0, 'sanitize_callback' => 'absint'),
            ),
        ));

        register_rest_route(self::REST_NAMESPACE, '/live-admin/team/sessions/(?P<id>team_[0-9]+_[0-9]+)/stream', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'route_team_stream'),
            'permission_callback' => $auth,
            'args'                => array(
                'since' => array('default' => 0, 'sanitize_callback' => 'absint'),
            ),
        ));

        self::register_platform_routes($auth);
    }

    /**
     * @param callable $auth
     */
    private static function register_platform_routes($auth) {
        $base = '/live-admin/platform';

        register_rest_route(self::REST_NAMESPACE, $base . '/dashboard', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_dashboard'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/reports', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_reports'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/employee', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_employee'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/notifications', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_notifications'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/permissions', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_permissions'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_search'),
            'permission_callback' => $auth,
            'args' => array('q' => array('required' => false, 'sanitize_callback' => 'sanitize_text_field')),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/tasks', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_tasks_list'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_task_save'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/tasks/(?P<id>[a-zA-Z0-9_\-]+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'route_platform_task_delete'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/team-members', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_team_members'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/customers', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_customers_list'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_customer_save'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/calendar', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_calendar_list'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_calendar_save'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/calendar/(?P<id>[a-zA-Z0-9_\-]+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'route_platform_calendar_delete'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/files', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_files_list'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_file_save'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/files/(?P<id>[a-zA-Z0-9_\-]+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'route_platform_file_delete'),
            'permission_callback' => $auth,
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/activity', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_activity_list'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_activity_append'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::DELETABLE, 'callback' => array(__CLASS__, 'route_platform_activity_clear'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/settings', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'route_platform_settings_get'), 'permission_callback' => $auth),
            array('methods' => WP_REST_Server::CREATABLE, 'callback' => array(__CLASS__, 'route_platform_settings_save'), 'permission_callback' => $auth),
        ));
        register_rest_route(self::REST_NAMESPACE, $base . '/sync', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'route_platform_sync'),
            'permission_callback' => $auth,
        ));
    }

    public static function permission_admin() {
        return PAXdesign_Live_Chat_Permissions::authorize_api_access();
    }

    /**
     * @param string $permission
     * @return true|WP_Error
     */
    private static function require_perm($permission) {
        return PAXdesign_Live_Chat_Permissions::require_permission($permission);
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
        $response = rest_ensure_response($result);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private static function profile_payload($user) {
        $perms = PAXdesign_Live_Chat_Permissions::get_effective_permissions($user);
        $user_id = (int) $user->ID;
        $hub_name = trim((string) get_user_meta((int) $user->ID, 'pax_live_hub_display_name', true));
        if ($hub_name === '') {
            $hub_name = $user->display_name;
        }
        $avatar_meta = trim((string) get_user_meta($user_id, 'pax_live_avatar_url', true));
        $avatar_url = $avatar_meta !== '' ? esc_url_raw($avatar_meta) : get_avatar_url($user_id, array('size' => 256));
        $terms_accepted_at = (int) get_user_meta($user_id, 'pax_live_terms_accepted_at', true);
        $permission_status = array(
            'notifications' => sanitize_text_field((string) get_user_meta($user_id, 'pax_live_permission_notifications', true)),
            'location'      => sanitize_text_field((string) get_user_meta($user_id, 'pax_live_permission_location', true)),
        );
        $security_status = array(
            'device_type'         => sanitize_text_field((string) get_user_meta($user_id, 'pax_live_security_device_type', true)),
            'biometric_available' => (bool) get_user_meta($user_id, 'pax_live_security_biometric_available', true),
            'biometric_enabled'   => (bool) get_user_meta($user_id, 'pax_live_security_biometric_enabled', true),
            'pin_enabled'         => (bool) get_user_meta($user_id, 'pax_live_security_pin_enabled', true),
            'password_confirmed'  => (bool) get_user_meta($user_id, 'pax_live_security_password_confirmed', true),
        );
        return array(
            'user_id'         => $user_id,
            'name'            => $hub_name,
            'email'           => $user->user_email,
            'username'        => $user->user_login,
            'avatar_url'      => $avatar_url,
            'site_url'        => home_url('/'),
            'rest_base'       => rest_url(self::REST_NAMESPACE . '/live-admin/'),
            'employee'        => PAXdesign_Chat_Live::resolve_employee_identity($user_id),
            'live_agent'      => PAXdesign_Chat_Live::get_agent_public_config(),
            'plugin_ver'      => PAXDESIGN_BOOKING_VERSION,
            'is_super_admin'  => PAXdesign_Live_Chat_Permissions::is_super_admin($user),
            'permissions'     => $perms,
            'module_permissions' => PAXdesign_Platform_Store::module_permissions_for_user($user),
            'onboarding_completed' => (bool) get_user_meta((int) $user->ID, 'pax_live_onboarding_completed', true),
            'terms_accepted'  => $terms_accepted_at > 0,
            'terms_accepted_at' => $terms_accepted_at,
            'permission_status' => $permission_status,
            'security_status' => $security_status,
            'spoken_languages' => class_exists('PAXdesign_Language_Routing')
                ? PAXdesign_Language_Routing::get_user_spoken_languages($user_id)
                : array('de', 'en'),
        );
    }

    public static function route_me(WP_REST_Request $request) {
        $user = wp_get_current_user();
        return rest_ensure_response(self::profile_payload($user));
    }

    public static function route_profile_save(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_CUSTOMIZE_HUB_PROFILE);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_SETTINGS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $display_name = trim((string) sanitize_text_field($params['hub_display_name'] ?? $params['display_name'] ?? ''));
        if (strlen($display_name) > 80) {
            $display_name = substr($display_name, 0, 80);
        }

        $user_id = (int) get_current_user_id();
        if ($display_name === '') {
            delete_user_meta($user_id, 'pax_live_hub_display_name');
        } else {
            update_user_meta($user_id, 'pax_live_hub_display_name', $display_name);
        }

        if (array_key_exists('spoken_languages', $params) && class_exists('PAXdesign_Language_Routing')) {
            $langs = is_array($params['spoken_languages']) ? $params['spoken_languages'] : array($params['spoken_languages']);
            PAXdesign_Language_Routing::save_user_spoken_languages($user_id, $langs);
        }

        return self::respond(self::profile_payload(wp_get_current_user()));
    }

    public static function route_sessions(WP_REST_Request $request) {
        return self::respond(self::live()->get_live_list_data());
    }

    public static function route_conversations_sync(WP_REST_Request $request) {
        $user_id = (int) wp_get_current_user()->ID;
        $live    = self::live()->get_live_list_data(true);
        if (is_wp_error($live)) {
            return $live;
        }
        $team = PAXdesign_Team_Messaging::list_sessions_for_user($user_id, true);
        return self::respond(array(
            'sessions'      => isset($live['sessions']) ? $live['sessions'] : array(),
            'live_count'    => isset($live['live_count']) ? (int) $live['live_count'] : 0,
            'team_sessions' => isset($team['sessions']) ? $team['sessions'] : array(),
            'threads'       => isset($live['threads']) ? $live['threads'] : array(),
            'team_threads'  => isset($team['threads']) ? $team['threads'] : array(),
        ));
    }

    public static function route_session(WP_REST_Request $request) {
        return self::respond(self::live()->get_session_detail_data($request['id']));
    }

    public static function route_poll(WP_REST_Request $request) {
        $full = self::request_wants_full_history($request);
        return self::respond(self::live()->get_poll_data(
            $request['id'],
            (int) $request->get_param('since'),
            $full
        ));
    }

    public static function route_takeover(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_takeover($request['id']));
    }

    public static function route_decline(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_decline_live_request($request['id']));
    }

    public static function route_close(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_close($request['id']));
    }

    public static function route_release(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_release($request['id']));
    }

    public static function route_reopen(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_reopen($request['id']));
    }

    public static function route_archive(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_archive_session($request['id']));
    }

    public static function route_delete(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_delete_session($request['id']));
    }

    public static function route_send_message(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $message  = isset($params['message']) ? $params['message'] : $request->get_param('message');
        $reply_to = isset($params['reply_to']) ? $params['reply_to'] : $request->get_param('reply_to');
        $client_id = isset($params['client_msg_id']) ? $params['client_msg_id'] : $request->get_param('client_msg_id');
        return self::respond(self::live()->admin_send_message($request['id'], $message, $reply_to, $client_id));
    }

    public static function route_send_image(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_SEND_IMAGES);
        if (is_wp_error($check)) {
            return $check;
        }
        $files = $request->get_file_params();
        if (empty($files['image'])) {
            return new WP_Error('invalid_payload', 'Kein Bild übermittelt.', array('status' => 400));
        }

        $params   = $request->get_body_params();
        $caption  = isset($params['caption']) ? $params['caption'] : $request->get_param('caption');
        $reply_to = isset($params['reply_to']) ? $params['reply_to'] : $request->get_param('reply_to');

        return self::respond(self::live()->admin_send_image(
            $request['id'],
            $files['image'],
            $caption,
            $reply_to
        ));
    }

    public static function route_typing(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $stop = !empty($params['stop']) || $request->get_param('stop');
        return self::respond(self::live()->admin_set_typing($request['id'], (bool) $stop));
    }

    public static function route_quick_replies(WP_REST_Request $request) {
        return self::respond(array(
            'quick_replies' => PAXdesign_Chat_Live::get_admin_quick_replies(),
        ));
    }

    public static function route_suggestions(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_USE_AI);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(self::live()->admin_get_suggestions(
            $request['id'],
            (int) $request->get_param('message_id')
        ));
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

        PAXdesign_APNS::register_device((int) get_current_user_id(), $token, $sandbox, $bundle, $params);
        return rest_ensure_response(array('ok' => true));
    }

    public static function route_devices_list(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return rest_ensure_response(array('devices' => array()));
        }

        $user_id = (int) get_current_user_id();
        $can_manage = PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            || PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        $filter = $can_manage ? (int) $request->get_param('user_id') : $user_id;
        $current_device_id = sanitize_text_field((string) $request->get_param('current_device_id'));

        if (!$can_manage) {
            $filter = $user_id;
        }

        $devices = array();
        try {
            $devices = PAXdesign_Device_Sessions::list_employee_devices($filter, $current_device_id);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PAXdesign Device Sessions] list failed: ' . $e->getMessage());
            }
        }

        if (!is_array($devices)) {
            $devices = array();
        }

        return self::respond(array(
            'devices'    => $devices,
            'can_manage' => $can_manage,
        ));
    }

    public static function route_devices_heartbeat(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return rest_ensure_response(array('ok' => true));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $device_id = isset($params['device_id']) ? sanitize_text_field($params['device_id']) : '';
        if ($device_id === '') {
            return new WP_Error('invalid_device', 'device_id required.', array('status' => 400));
        }

        return PAXdesign_Device_Sessions::heartbeat((int) get_current_user_id(), $device_id, $params);
    }

    public static function route_devices_revoke(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return new WP_Error('unavailable', 'Device management unavailable.', array('status' => 501));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $target_user = isset($params['user_id']) ? (int) $params['user_id'] : (int) get_current_user_id();
        return PAXdesign_Device_Sessions::revoke_device(
            (int) get_current_user_id(),
            $target_user,
            sanitize_text_field($request['device_id']),
            true
        );
    }

    public static function route_devices_approve(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return new WP_Error('unavailable', 'Device management unavailable.', array('status' => 501));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $target_user = isset($params['user_id']) ? (int) $params['user_id'] : (int) get_current_user_id();
        return PAXdesign_Device_Sessions::approve_device(
            (int) get_current_user_id(),
            $target_user,
            sanitize_text_field($request['device_id'])
        );
    }

    public static function route_devices_force_logout(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return new WP_Error('unavailable', 'Device management unavailable.', array('status' => 501));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $target_user = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $device_ids  = isset($params['device_ids']) && is_array($params['device_ids']) ? $params['device_ids'] : array();

        if ($target_user <= 0) {
            return new WP_Error('invalid_request', 'user_id required.', array('status' => 400));
        }

        if (empty($device_ids)) {
            return PAXdesign_Device_Sessions::force_logout_user((int) get_current_user_id(), $target_user);
        }

        $results = array();
        foreach ($device_ids as $device_id) {
            $results[] = PAXdesign_Device_Sessions::revoke_device(
                (int) get_current_user_id(),
                $target_user,
                sanitize_text_field($device_id),
                true
            );
        }

        return rest_ensure_response(array('ok' => true, 'results' => $results));
    }

    public static function route_onboarding_reset(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return new WP_Error('unavailable', 'Onboarding reset unavailable.', array('status' => 501));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $target_user = isset($params['user_id']) ? (int) $params['user_id'] : (int) get_current_user_id();
        return PAXdesign_Device_Sessions::reset_onboarding((int) get_current_user_id(), $target_user);
    }

    public static function route_onboarding_complete(WP_REST_Request $request) {
        $user_id = (int) get_current_user_id();
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $terms_accepted = isset($params['terms_accepted']) ? !empty($params['terms_accepted']) : true;
        if (!$terms_accepted) {
            return new WP_Error('terms_required', 'Terms must be accepted before continuing.', array('status' => 400));
        }

        $permissions = isset($params['permissions']) && is_array($params['permissions']) ? $params['permissions'] : array();
        $notifications = sanitize_text_field((string) ($permissions['notifications'] ?? 'unknown'));
        $location = sanitize_text_field((string) ($permissions['location'] ?? 'unknown'));
        $security = isset($params['security']) && is_array($params['security']) ? $params['security'] : array();
        $device_type = sanitize_text_field((string) ($security['device_type'] ?? 'unknown'));
        $biometric_available = !empty($security['biometric_available']);
        $biometric_enabled = !empty($security['biometric_enabled']);
        $pin_enabled = !empty($security['pin_enabled']);
        $password_confirmed = !empty($security['password_confirmed']);

        $existing_pin_enabled = (bool) get_user_meta($user_id, 'pax_live_security_pin_enabled', true);
        $existing_password_confirmed = (bool) get_user_meta($user_id, 'pax_live_security_password_confirmed', true);
        $security_ready = ($pin_enabled && $password_confirmed) || ($existing_pin_enabled && $existing_password_confirmed);
        if (!$security_ready) {
            return new WP_Error(
                'security_required',
                'Security setup (PIN/password confirmation) is required before onboarding completion.',
                array('status' => 400)
            );
        }

        update_user_meta($user_id, 'pax_live_onboarding_completed', 1);
        update_user_meta($user_id, 'pax_live_terms_accepted_at', time());
        update_user_meta($user_id, 'pax_live_permission_notifications', $notifications);
        update_user_meta($user_id, 'pax_live_permission_location', $location);
        update_user_meta($user_id, 'pax_live_security_device_type', $device_type);
        update_user_meta($user_id, 'pax_live_security_biometric_available', $biometric_available ? 1 : 0);
        update_user_meta($user_id, 'pax_live_security_biometric_enabled', $biometric_enabled ? 1 : 0);
        update_user_meta($user_id, 'pax_live_security_pin_enabled', $pin_enabled ? 1 : 0);
        update_user_meta($user_id, 'pax_live_security_password_confirmed', $password_confirmed ? 1 : 0);

        return self::respond(self::profile_payload(wp_get_current_user()));
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

    public static function route_staff_list(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        return rest_ensure_response(array('staff' => PAXdesign_Live_Chat_Permissions::list_staff_for_api()));
    }

    public static function route_staff_save(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $email   = isset($params['email']) ? sanitize_email($params['email']) : '';
        if ($user_id <= 0 && $email !== '') {
            $found = get_user_by('email', $email);
            if ($found) {
                $user_id = (int) $found->ID;
            }
        }

        if ($user_id <= 0) {
            return new WP_Error('invalid_user', 'Valid user_id or known email required.', array('status' => 400));
        }

        $profile_result = self::update_staff_user_details($user_id, $params);
        if (is_wp_error($profile_result)) {
            return $profile_result;
        }

        $result = PAXdesign_Live_Chat_Permissions::save_staff_record($user_id, array(
            'enabled'     => !empty($params['enabled']),
            'permissions' => isset($params['permissions']) ? $params['permissions'] : array(),
        ));
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('ok' => true));
    }

    public static function route_staff_remove(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        $result = PAXdesign_Live_Chat_Permissions::remove_staff((int) $request['user_id']);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('ok' => true));
    }

    public static function route_staff_force_logout(WP_REST_Request $request) {
        if (!class_exists('PAXdesign_Device_Sessions')) {
            return new WP_Error('unavailable', 'Device management unavailable.', array('status' => 501));
        }
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_TEAM_PERMISSIONS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }

        $target_user = (int) $request['user_id'];
        if ($target_user <= 0) {
            return new WP_Error('invalid_user', 'Valid user_id required.', array('status' => 400));
        }
        return PAXdesign_Device_Sessions::force_logout_user((int) get_current_user_id(), $target_user);
    }

    /**
     * @param int                  $user_id
     * @param array<string, mixed> $params
     * @return true|WP_Error
     */
    private static function update_staff_user_details($user_id, $params) {
        $user = get_user_by('id', (int) $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'User not found.', array('status' => 404));
        }

        $update_payload = array('ID' => (int) $user_id);
        $has_wp_update = false;

        if (array_key_exists('display_name', $params)) {
            $display_name = trim((string) sanitize_text_field($params['display_name']));
            if ($display_name !== '') {
                $update_payload['display_name'] = $display_name;
                $has_wp_update = true;
            }
        }

        if (array_key_exists('email', $params)) {
            $new_email = sanitize_email((string) $params['email']);
            if ($new_email !== '') {
                if (!is_email($new_email)) {
                    return new WP_Error('invalid_email', 'Invalid email format.', array('status' => 400));
                }
                $email_owner = get_user_by('email', $new_email);
                if ($email_owner && (int) $email_owner->ID !== (int) $user_id) {
                    return new WP_Error('email_taken', 'Email address already in use.', array('status' => 400));
                }
                $update_payload['user_email'] = $new_email;
                $has_wp_update = true;
            }
        }

        if ($has_wp_update) {
            $updated = wp_update_user($update_payload);
            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        if (array_key_exists('avatar_url', $params)) {
            $avatar_url = esc_url_raw((string) $params['avatar_url']);
            if ($avatar_url === '') {
                delete_user_meta((int) $user_id, 'pax_live_avatar_url');
            } else {
                update_user_meta((int) $user_id, 'pax_live_avatar_url', $avatar_url);
            }
        }

        if (array_key_exists('profile_title', $params)) {
            update_user_meta((int) $user_id, 'pax_live_profile_title', sanitize_text_field((string) $params['profile_title']));
        }
        if (array_key_exists('profile_phone', $params)) {
            update_user_meta((int) $user_id, 'pax_live_profile_phone', sanitize_text_field((string) $params['profile_phone']));
        }
        if (array_key_exists('profile_notes', $params)) {
            update_user_meta((int) $user_id, 'pax_live_profile_notes', sanitize_textarea_field((string) $params['profile_notes']));
        }

        if (!empty($params['password']) && is_string($params['password'])) {
            $password = trim((string) $params['password']);
            if (strlen($password) < 8) {
                return new WP_Error('weak_password', 'Password must contain at least 8 characters.', array('status' => 400));
            }
            wp_set_password($password, (int) $user_id);
        }

        return true;
    }

    public static function route_team_sessions(WP_REST_Request $request) {
        return self::respond(PAXdesign_Team_Messaging::list_sessions_for_user((int) wp_get_current_user()->ID));
    }

    public static function route_team_contacts(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        return rest_ensure_response(array(
            'staff' => PAXdesign_Live_Chat_Permissions::list_team_contacts_for_api(),
        ));
    }

    public static function route_team_open(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        $params  = $request->get_json_params();
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        if ($user_id <= 0) {
            return new WP_Error('pax_invalid', 'user_id required', array('status' => 400));
        }
        $result = PAXdesign_Team_Messaging::open_conversation((int) wp_get_current_user()->ID, $user_id);
        if (isset($result['error'])) {
            return new WP_Error('pax_team_error', (string) $result['error'], array('status' => 400));
        }
        return rest_ensure_response($result);
    }

    public static function route_team_poll(WP_REST_Request $request) {
        $full = self::request_wants_full_history($request);
        return self::respond(PAXdesign_Team_Messaging::poll_conversation(
            $request['id'],
            (int) wp_get_current_user()->ID,
            (int) $request->get_param('since'),
            $full
        ));
    }

    public static function route_team_send_message(WP_REST_Request $request) {
        $params  = $request->get_json_params();
        $content = isset($params['content']) ? (string) $params['content'] : '';
        $client_id = isset($params['client_msg_id']) ? (string) $params['client_msg_id'] : '';
        return self::respond(PAXdesign_Team_Messaging::send_message(
            $request['id'],
            (int) wp_get_current_user()->ID,
            $content,
            $client_id
        ));
    }

    public static function route_team_mark_read(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $seq = isset($params['seq']) ? (int) $params['seq'] : 0;
        return self::respond(PAXdesign_Team_Messaging::mark_read(
            $request['id'],
            (int) wp_get_current_user()->ID,
            $seq
        ));
    }

    public static function route_team_delete(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS);
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $mode = isset($params['mode']) ? sanitize_key($params['mode']) : 'hide';
        $user_id = (int) wp_get_current_user()->ID;
        if ($mode === 'purge_all') {
            return self::respond(PAXdesign_Team_Messaging::purge_conversation($request['id'], $user_id));
        }
        return self::respond(PAXdesign_Team_Messaging::hide_conversation($request['id'], $user_id));
    }

    public static function route_inbox_stream(WP_REST_Request $request) {
        $since = (int) $request->get_param('since');
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::stream_admin_sse((int) get_current_user_id(), '', 0, $since);
        }
        exit;
    }

    public static function route_event_ack(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $consumer = !empty($params['consumer_id'])
            ? sanitize_text_field($params['consumer_id'])
            : 'user:' . (int) get_current_user_id();
        return self::respond(PAXdesign_Message_Store::acknowledge(
            $consumer,
            isset($params['channel']) ? $params['channel'] : 'inbox:admins',
            isset($params['event_id']) ? (int) $params['event_id'] : 0,
            isset($params['seq']) ? (int) $params['seq'] : 0
        ));
    }

    public static function route_session_read(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        return self::respond(PAXdesign_Message_Store::acknowledge(
            'user:' . (int) get_current_user_id(),
            'session:' . sanitize_text_field($request['id']),
            0,
            isset($params['seq']) ? (int) $params['seq'] : 0
        ));
    }

    public static function route_session_stream(WP_REST_Request $request) {
        $since = (int) $request->get_param('since');
        $session_id = sanitize_text_field($request['id']);
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::stream_sse('session:' . $session_id, $since);
        }
        exit;
    }

    public static function route_team_stream(WP_REST_Request $request) {
        $since = (int) $request->get_param('since');
        $conv_id = sanitize_text_field($request['id']);
        if (class_exists('PAXdesign_Chat_Event_Bus')) {
            PAXdesign_Chat_Event_Bus::stream_sse('team:' . $conv_id, $since);
        }
        exit;
    }

    public static function route_platform_dashboard(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::dashboard_payload());
    }

    public static function route_platform_reports(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::reports_payload());
    }

    public static function route_platform_employee(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::employee_payload((int) wp_get_current_user()->ID));
    }

    public static function route_platform_notifications(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::notifications_summary());
    }

    public static function route_platform_permissions(WP_REST_Request $request) {
        return self::respond(array(
            'permissions' => PAXdesign_Live_Chat_Permissions::get_effective_permissions(wp_get_current_user()),
            'module_permissions' => PAXdesign_Platform_Store::module_permissions_for_user(wp_get_current_user()),
        ));
    }

    public static function route_platform_search(WP_REST_Request $request) {
        return self::respond(array(
            'results' => PAXdesign_Platform_Store::search((string) $request->get_param('q')),
        ));
    }

    public static function route_platform_tasks_list(WP_REST_Request $request) {
        $user_id = (int) get_current_user_id();
        $can_manage_all = PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS)
            || PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        return self::respond(array('tasks' => PAXdesign_Platform_Store::list_tasks($user_id, $can_manage_all)));
    }

    public static function route_platform_task_save(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_REPLY_CHATS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS);
        }
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $user_id = (int) get_current_user_id();
        $can_assign = PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS)
            || PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        return self::respond(PAXdesign_Platform_Store::save_task($params, $user_id, $can_assign));
    }

    public static function route_platform_task_delete(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(PAXdesign_Platform_Store::delete_task($request['id']));
    }

    public static function route_platform_team_members(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(array('members' => PAXdesign_Platform_Store::list_team_members()));
    }

    public static function route_platform_customers_list(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_CUSTOMER_PROFILES);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(array('customers' => PAXdesign_Platform_Store::list_customer_profiles()));
    }

    public static function route_platform_customer_save(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_CUSTOMER_PROFILES);
        if (is_wp_error($check)) {
            $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        }
        if (is_wp_error($check)) {
            return $check;
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        return self::respond(PAXdesign_Platform_Store::save_customer_profile($params));
    }

    public static function route_platform_calendar_list(WP_REST_Request $request) {
        return self::respond(array(
            'events' => PAXdesign_Platform_Store::list_events(),
            'upcoming' => PAXdesign_Platform_Store::upcoming_events(8),
        ));
    }

    public static function route_platform_calendar_save(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        return self::respond(PAXdesign_Platform_Store::save_event($params));
    }

    public static function route_platform_calendar_delete(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::delete_event($request['id']));
    }

    public static function route_platform_files_list(WP_REST_Request $request) {
        return self::respond(array('files' => PAXdesign_Platform_Store::list_files()));
    }

    public static function route_platform_file_save(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        return self::respond(PAXdesign_Platform_Store::save_file($params));
    }

    public static function route_platform_file_delete(WP_REST_Request $request) {
        return self::respond(PAXdesign_Platform_Store::delete_file($request['id']));
    }

    public static function route_platform_activity_list(WP_REST_Request $request) {
        $module = (string) $request->get_param('module');
        return self::respond(array('entries' => PAXdesign_Platform_Store::list_activity($module)));
    }

    public static function route_platform_activity_append(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        return self::respond(PAXdesign_Platform_Store::append_activity(
            isset($params['module']) ? (string) $params['module'] : 'system',
            isset($params['title']) ? (string) $params['title'] : '',
            isset($params['detail']) ? (string) $params['detail'] : '',
            isset($params['severity']) ? (string) $params['severity'] : 'info',
            isset($params['category']) ? (string) $params['category'] : ''
        ));
    }

    public static function route_platform_activity_clear(WP_REST_Request $request) {
        $check = self::require_perm(PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS);
        if (is_wp_error($check)) {
            return $check;
        }
        return self::respond(array('cleared' => PAXdesign_Platform_Store::clear_activity()));
    }

    public static function route_platform_settings_get(WP_REST_Request $request) {
        $user_id = (int) wp_get_current_user()->ID;
        return self::respond(array('settings' => PAXdesign_Platform_Store::get_user_settings($user_id)));
    }

    public static function route_platform_settings_save(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $settings = isset($params['settings']) && is_array($params['settings']) ? $params['settings'] : $params;
        $user_id = (int) wp_get_current_user()->ID;
        return self::respond(array('settings' => PAXdesign_Platform_Store::save_user_settings($user_id, $settings)));
    }

    public static function route_platform_sync(WP_REST_Request $request) {
        $user_id = (int) wp_get_current_user()->ID;
        return self::respond(array(
            'dashboard'    => PAXdesign_Platform_Store::dashboard_payload(),
            'reports'      => PAXdesign_Platform_Store::reports_payload(),
            'employee'     => PAXdesign_Platform_Store::employee_payload($user_id),
            'notifications'=> PAXdesign_Platform_Store::notifications_summary(),
            'tasks'        => PAXdesign_Platform_Store::list_tasks(
                $user_id,
                PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_ASSIGN_TEAM_TASKS)
                    || PAXdesign_Live_Chat_Permissions::can($user_id, PAXdesign_Live_Chat_Permissions::PERM_MANAGE_USERS)
            ),
            'calendar'     => PAXdesign_Platform_Store::list_events(),
            'upcoming'     => PAXdesign_Platform_Store::upcoming_events(8),
            'files'        => PAXdesign_Platform_Store::list_files(),
            'activity'     => PAXdesign_Platform_Store::list_activity(),
            'settings'     => PAXdesign_Platform_Store::get_user_settings($user_id),
            'permissions'  => array(
                'permissions' => PAXdesign_Live_Chat_Permissions::get_effective_permissions(wp_get_current_user()),
                'module_permissions' => PAXdesign_Platform_Store::module_permissions_for_user(wp_get_current_user()),
            ),
        ));
    }
}
