<?php
/**
 * MySQL connection hygiene for $wpdb — prevents "Commands out of sync" during
 * WP-Cron, Action Scheduler shutdown hooks, and long-lived REST/SSE requests.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_DB {

    /**
     * Drain pending mysqli result sets and reset $wpdb cached state.
     */
    public static function drain_connection() {
        global $wpdb;

        if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
            return;
        }

        if (method_exists($wpdb, 'flush')) {
            $wpdb->flush();
        }

        if (isset($wpdb->dbh) && $wpdb->dbh instanceof mysqli) {
            while (@mysqli_more_results($wpdb->dbh)) {
                @mysqli_next_result($wpdb->dbh);
                if ($result = @mysqli_store_result($wpdb->dbh)) {
                    @mysqli_free_result($result);
                }
            }
        }
    }

    /**
     * Prepare the shared connection before transactional writes or named locks.
     */
    public static function prepare_for_write() {
        global $wpdb;

        self::drain_connection();

        if (isset($wpdb) && method_exists($wpdb, 'check_connection')) {
            $wpdb->check_connection();
        }
    }

    /**
     * @return int 1 when lock acquired, 0 when timeout, null on error.
     */
    public static function acquire_named_lock($name, $timeout = 10) {
        global $wpdb;

        self::prepare_for_write();
        $lock = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', (string) $name, max(1, (int) $timeout)));

        return $lock;
    }

    /**
     * @return int|null
     */
    public static function release_named_lock($name) {
        global $wpdb;

        $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', (string) $name));
        self::drain_connection();

        return $released === null ? null : (int) $released;
    }

    /**
     * @param mixed $response
     * @return mixed
     */
    public static function drain_connection_filter($response) {
        self::drain_connection();
        return $response;
    }

    public static function init() {
        add_filter('rest_post_dispatch', array(__CLASS__, 'drain_connection_filter'), 9999, 1);
        add_action('shutdown', array(__CLASS__, 'drain_connection'), PHP_INT_MAX);
        if (function_exists('as_enqueue_async_action')) {
            add_action('action_scheduler_after_execute', array(__CLASS__, 'drain_connection'), 10, 0);
            add_action('action_scheduler_queue_runner_complete', array(__CLASS__, 'drain_connection'), 10, 0);
        }
    }
}
