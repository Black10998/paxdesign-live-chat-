<?php
/**
 * Structured auth/session logging for debug.log (no secrets).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_Log {

    public static function init() {
        add_filter('rest_post_dispatch', array(__CLASS__, 'log_rest_dispatch'), 20, 3);
    }

    /**
     * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response
     * @param WP_REST_Server                                    $server
     * @param WP_REST_Request                                   $request
     * @return mixed
     */
    public static function log_rest_dispatch($response, $server, $request) {
        if (!self::enabled() || !($request instanceof WP_REST_Request)) {
            return $response;
        }

        $route = (string) $request->get_route();
        $status = 0;
        if ($response instanceof WP_REST_Response) {
            $status = (int) $response->get_status();
        } elseif ($response instanceof WP_HTTP_Response) {
            $status = (int) $response->get_status();
        }

        if (strpos($route, '/pdx/v1/customer') === 0) {
            self::event('customer_api_request', array(
                'route'   => $route,
                'method'  => $request->get_method(),
                'status'  => $status,
                'user_id' => (int) get_current_user_id(),
            ));
        } elseif (strpos($route, '/paxdesign/v1/live-admin') === 0) {
            self::event('staff_api_request', array(
                'route'   => $route,
                'method'  => $request->get_method(),
                'status'  => $status,
                'user_id' => (int) get_current_user_id(),
            ));
        }

        return $response;
    }

    /**
     * @param string               $event   Short event slug, e.g. mobile_login_success.
     * @param array<string, mixed> $context Safe context (never passwords/tokens).
     * @param string               $level   info|warn|error
     */
    public static function event($event, array $context = array(), $level = 'info') {
        if (!self::enabled()) {
            return;
        }

        $safe = self::sanitize_context($context);
        $safe['level'] = $level;

        error_log('[PAXdesign Auth [' . $event . ']] ' . wp_json_encode($safe));
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
            'code',
            'verification_code',
            'authorization',
            'secret',
        );
        foreach ($deny as $key) {
            unset($context[$key]);
        }
        return $context;
    }

    private static function enabled() {
        return defined('WP_DEBUG') && WP_DEBUG
            && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
}
