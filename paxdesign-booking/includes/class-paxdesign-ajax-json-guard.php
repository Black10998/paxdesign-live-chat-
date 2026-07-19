<?php
/**
 * Return valid JSON for PAXdesign admin-ajax handlers when PHP fatals before wp_send_json_*.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Ajax_JSON_Guard {

    /** @var bool */
    private static $registered = false;

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_register_shutdown'), 0);
    }

    public static function maybe_register_shutdown() {
        if (!(defined('DOING_AJAX') && DOING_AJAX) || self::$registered) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash((string) $_REQUEST['action'])) : '';
        if ($action === '' || strpos($action, 'paxdesign_') !== 0) {
            return;
        }

        self::$registered = true;
        register_shutdown_function(array(__CLASS__, 'emit_fatal_json'));
    }

    public static function emit_fatal_json() {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        status_header(500);
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode(array(
            'success' => false,
            'data'    => array(
                'message' => __('A server error occurred. Please try again.', 'paxdesign-booking'),
                'code'    => 'server_error',
            ),
        ));
        exit;
    }
}
