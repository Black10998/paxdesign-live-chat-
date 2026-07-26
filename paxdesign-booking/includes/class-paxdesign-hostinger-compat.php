<?php
/**
 * Hostinger / LiteSpeed edge compatibility — reduce WAF 403 from Authorization leakage.
 *
 * Root .htaccess often passes HTTP Authorization to every PHP request. Browsers or
 * proxies that attach Basic credentials to wp-admin can trigger ModSecurity / edge
 * blocks ("Access to this resource on the server is denied!"). We strip auth off
 * non-REST requests in PHP and scope .htaccess passthrough via fix-hostinger-wp-access.sh.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Hostinger_Compat {

    public static function init() {
        add_action('init', array(__CLASS__, 'strip_basic_auth_off_rest'), 0);
        add_filter('rest_pre_serve_request', array(__CLASS__, 'send_rest_no_cache_headers'), 10, 4);
    }

    /**
     * Remove Basic/Application Password headers from normal page loads (wp-admin, front-end).
     */
    public static function strip_basic_auth_off_rest() {
        if (self::is_rest_api_request()) {
            return;
        }

        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
    }

    /**
     * @return bool
     */
    private static function is_rest_api_request() {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && strpos($uri, '/wp-json/') !== false) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        return false;
    }

    /**
     * Prevent cached REST responses for live-admin and customer API (reduces stale auth probes).
     *
     * @param bool             $served
     * @param WP_HTTP_Response $result
     * @param WP_REST_Request  $request
     * @param WP_REST_Server   $server
     * @return bool
     */
    public static function send_rest_no_cache_headers($served, $result, $request, $server) {
        if (!$request instanceof WP_REST_Request) {
            return $served;
        }

        $route = (string) $request->get_route();
        $needs_no_cache = (
            strpos($route, '/paxdesign/v1/live-admin') === 0
            || strpos($route, '/pdx/v1/') === 0
        );
        if (!$needs_no_cache || headers_sent()) {
            return $served;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-PAXdesign-REST: 1');

        return $served;
    }
}

PAXdesign_Hostinger_Compat::init();
