<?php
/**
 * Customer platform bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Customer_Platform {

    public static function init() {
        self::load_dependencies();
        PAXdesign_Customer_DB::init();
        PAXdesign_Customer_Auth::init();
        PAXdesign_Customer_Chat_Bridge::init();
        PAXdesign_Customer_Services::init();
        PAXdesign_Customer_REST::init();
        PAXdesign_Customer_Staff_REST::init();
        PAXdesign_Customer_Admin::init();
        add_action('paxdesign_session_sync', array(__CLASS__, 'maybe_notify_customer_chat_reply'), 10, 2);
        if (get_option('paxdesign_customer_require_login_for_chat', '') === '') {
            update_option('paxdesign_customer_require_login_for_chat', '1', false);
        }
        add_action('wp_ajax_nopriv_paxdesign_chat_log', array(__CLASS__, 'maybe_block_guest_chat'), 0);
        add_action('wp_ajax_nopriv_paxdesign_chat', array(__CLASS__, 'maybe_block_guest_chat'), 0);
        do_action('paxdesign_customer_platform_ready');
    }

    /**
     * Optional gate: require toolbar login before anonymous chat persistence.
     */
    public static function maybe_block_guest_chat() {
        if (get_option('paxdesign_customer_require_login_for_chat', '') !== '1') {
            return;
        }
        if (is_user_logged_in()) {
            return;
        }
        wp_send_json_error(array(
            'message' => __('Please use Sign Up in the site header to create an account or sign in. Live Chat is for messaging only.', 'paxdesign-booking'),
            'code'    => 'login_required',
        ), 401);
    }

    public static function maybe_notify_customer_chat_reply($session_id, $payload) {
        if (($payload['last_role'] ?? '') !== 'admin') {
            return;
        }
        global $wpdb;
        $table = PAXdesign_Chat_Log::table_name();
        $user_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM $table WHERE session_id = %s LIMIT 1",
            (string) $session_id
        ));
        if ($user_id <= 0) {
            return;
        }
        $preview = sanitize_text_field($payload['preview'] ?? __('New message from support', 'paxdesign-booking'));
        PAXdesign_Customer_Notifications::notify_user(
            $user_id,
            'chat',
            __('Support replied', 'paxdesign-booking'),
            $preview,
            'chat',
            (string) $session_id,
            '/chat/' . rawurlencode((string) $session_id)
        );
    }

    private static function load_dependencies() {
        $base = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/customer/';
        require_once $base . 'class-paxdesign-customer-db.php';
        require_once $base . 'class-paxdesign-customer-auth.php';
        require_once $base . 'class-paxdesign-customer-chat-bridge.php';
        require_once $base . 'class-paxdesign-customer-services.php';
        require_once $base . 'class-paxdesign-customer-projects.php';
        require_once $base . 'class-paxdesign-customer-orders.php';
        require_once $base . 'class-paxdesign-customer-notifications.php';
        require_once $base . 'class-paxdesign-customer-news.php';
        require_once $base . 'class-paxdesign-customer-media.php';
        require_once $base . 'class-paxdesign-customer-rest.php';
        require_once $base . 'class-paxdesign-customer-staff-rest.php';
        require_once $base . 'class-paxdesign-customer-admin.php';
    }
}
