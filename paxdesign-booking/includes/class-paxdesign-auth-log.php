<?php
/**
 * Production-safe structured logging for auth/API events.
 *
 * Default policy (when WP_DEBUG_LOG is enabled on production hosts):
 * - error: failures requiring investigation
 * - warn: unusual but recoverable conditions
 * - info: disabled unless verbose mode is explicitly enabled
 * - never log successful routine API polling, typing, or heartbeats
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_Log {

    const OPTION_VERBOSE_API = 'paxdesign_verbose_api_log';
    const RATE_WINDOW_SECONDS = 300;
    const MAX_LOG_BYTES = 5242880; // 5 MB

    /** @var array<string, int> */
    private static $rate_buckets = array();

    /** @var array<string, int> */
    private static $dedupe_hashes = array();

    public static function init() {
        add_filter('rest_post_dispatch', array(__CLASS__, 'log_rest_dispatch'), 20, 3);
    }

    /**
     * Explicit verbose API logging for development (off by default).
     */
    public static function verbose_api_logging_enabled() {
        return (bool) get_option(self::OPTION_VERBOSE_API, false);
    }

    /**
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response
     * @param WP_REST_Server                                    $server
     * @param WP_REST_Request                                   $request
     * @return mixed
     */
    public static function log_rest_dispatch($response, $server, $request) {
        if (!self::logging_available() || !($request instanceof WP_REST_Request)) {
            return $response;
        }

        $route = (string) $request->get_route();
        if (!self::is_pax_route($route)) {
            return $response;
        }

        $status = self::response_status($response);
        if (!self::should_log_rest_request($route, $request->get_method(), $status)) {
            return $response;
        }

        $event = strpos($route, '/pdx/v1/customer') === 0 ? 'customer_api_request' : 'staff_api_request';
        $level = self::level_for_status($status);
        $context = array(
            'route'   => $route,
            'method'  => $request->get_method(),
            'status'  => $status,
            'user_id' => (int) get_current_user_id(),
        );

        if ($status === 401 && (int) get_current_user_id() === 0) {
            $context['auth_state'] = 'unauthenticated';
        }

        self::event($event, $context, $level);

        return $response;
    }

    /**
     * @param string               $event   Short event slug, e.g. mobile_login_success.
     * @param array<string, mixed> $context Safe context (never passwords/tokens).
     * @param string               $level   info|warn|error
     */
    public static function event($event, array $context = array(), $level = 'info') {
        if (!self::logging_available()) {
            return;
        }

        $level = self::normalize_level($level);
        if (!self::should_log_event($event, $level)) {
            return;
        }

        $safe = self::sanitize_context($context);
        $safe['level'] = $level;

        $payload = '[PAXdesign Auth [' . $event . ']] ' . wp_json_encode($safe);
        if (self::is_duplicate($event, $payload)) {
            return;
        }

        self::maybe_rotate_log();
        error_log($payload);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function sanitize_context(array $context) {
        $deny = array(
            'password',
            'app_password',
            'token',
            'push_token',
            'apns_token',
            'device_token',
            'code',
            'verification_code',
            'authorization',
            'secret',
            'session_id',
            'message',
            'content',
            'body',
            'email',
        );
        foreach ($deny as $key) {
            unset($context[$key]);
        }
        return $context;
    }

    private static function logging_available() {
        return defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }

    private static function is_pax_route($route) {
        return strpos($route, '/pdx/v1/customer') === 0
            || strpos($route, '/paxdesign/v1/live-admin') === 0;
    }

    private static function response_status($response) {
        if ($response instanceof WP_REST_Response) {
            return (int) $response->get_status();
        }
        if ($response instanceof WP_HTTP_Response) {
            return (int) $response->get_status();
        }
        if ($response instanceof WP_Error) {
            $data = $response->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                return (int) $data['status'];
            }
        }
        return 0;
    }

    private static function should_log_rest_request($route, $method, $status) {
        if (self::is_routine_route($route, $method)) {
            return $status >= 400 && !self::is_expected_auth_status($status);
        }

        if ($status >= 500) {
            return true;
        }
        if ($status >= 400) {
            return !self::is_expected_auth_status($status);
        }
        if ($status >= 200 && $status < 300) {
            return self::verbose_api_logging_enabled();
        }
        return false;
    }

    private static function is_routine_route($route, $method) {
        $method = strtoupper((string) $method);
        $patterns = array(
            '/chat/typing',
            '/chat/events',
            '/chat/messages',
            '/chat/poll',
            '/push/register',
            '/push/apns',
            '/devices',
            '/notifications',
            '/dashboard',
            '/profile',
            '/sessions',
            '/typing',
        );
        foreach ($patterns as $needle) {
            if (strpos($route, $needle) !== false) {
                return true;
            }
        }
        if ($method === 'GET' && (
            strpos($route, '/customer/') === 0
            || strpos($route, '/live-admin/') !== false
        )) {
            return true;
        }
        return false;
    }

    private static function is_expected_auth_status($status) {
        return in_array((int) $status, array(401, 403), true);
    }

    private static function should_log_event($event, $level) {
        if ($level === 'error' || $level === 'warn') {
            return true;
        }
        if ($level === 'info') {
            return self::verbose_api_logging_enabled();
        }
        return false;
    }

    private static function normalize_level($level) {
        $level = strtolower((string) $level);
        return in_array($level, array('info', 'warn', 'error'), true) ? $level : 'info';
    }

    private static function level_for_status($status) {
        if ($status >= 500) {
            return 'error';
        }
        if ($status >= 400) {
            return 'warn';
        }
        return 'info';
    }

    private static function is_duplicate($event, $payload) {
        $hash = md5($event . '|' . $payload);
        $now = time();
        if (isset(self::$dedupe_hashes[$hash]) && ($now - self::$dedupe_hashes[$hash]) < self::RATE_WINDOW_SECONDS) {
            return true;
        }
        self::$dedupe_hashes[$hash] = $now;
        return false;
    }

    private static function maybe_rotate_log() {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }
        $path = WP_CONTENT_DIR . '/debug.log';
        if (!is_file($path)) {
            return;
        }
        $size = (int) @filesize($path);
        if ($size <= self::MAX_LOG_BYTES) {
            return;
        }
        $archive = WP_CONTENT_DIR . '/debug.log.' . gmdate('Ymd-His') . '.old';
        @rename($path, $archive);
    }
}
