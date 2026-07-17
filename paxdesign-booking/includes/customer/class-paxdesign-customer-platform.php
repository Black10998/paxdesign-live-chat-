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
        foreach (self::guest_chat_ajax_actions() as $action) {
            add_action('wp_ajax_nopriv_' . $action, array(__CLASS__, 'maybe_block_guest_chat'), 0);
        }
        do_action('paxdesign_customer_platform_ready');
    }

    /**
     * All nopriv chat AJAX actions that must require login when the gate is enabled.
     *
     * @return string[]
     */
    public static function guest_chat_ajax_actions() {
        return array(
            'paxdesign_chat',
            'paxdesign_chat_log',
            'paxdesign_chat_poll',
            'paxdesign_chat_stream',
            'paxdesign_chat_live_user_send',
            'paxdesign_chat_live_request',
            'paxdesign_chat_live_user_typing',
            'paxdesign_chat_live_reaction',
            'paxdesign_chat_live_customer_close',
            'paxdesign_chat_live_rating',
            'paxdesign_chat_customer_history_list',
            'paxdesign_chat_customer_history_session',
        );
    }

    /**
     * Require account login before any guest chat interaction.
     */
    public static function maybe_block_guest_chat() {
        if (get_option('paxdesign_customer_require_login_for_chat', '') !== '1') {
            return;
        }
        if (is_user_logged_in()) {
            return;
        }
        wp_send_json_error(array(
            'message' => __('Sign in or create an account to use Live Chat.', 'paxdesign-booking'),
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
        require_once $base . 'class-paxdesign-customer-services-catalog.php';
        require_once $base . 'class-paxdesign-customer-homepage.php';
        require_once $base . 'class-paxdesign-customer-about.php';
        require_once $base . 'class-paxdesign-customer-contact.php';
        require_once $base . 'class-paxdesign-customer-legal.php';
        require_once $base . 'class-paxdesign-customer-avatar.php';
        require_once $base . 'class-paxdesign-customer-site-menu.php';
        require_once $base . 'class-paxdesign-customer-projects.php';
        require_once $base . 'class-paxdesign-customer-orders.php';
        require_once $base . 'class-paxdesign-customer-notifications.php';
        require_once $base . 'class-paxdesign-customer-news.php';
        require_once $base . 'class-paxdesign-customer-portfolio.php';
        require_once $base . 'class-paxdesign-customer-portfolio-showcase.php';
        require_once $base . 'class-paxdesign-customer-elementor.php';
        require_once $base . 'class-paxdesign-customer-content.php';
        require_once $base . 'class-paxdesign-customer-media.php';
        require_once $base . 'class-paxdesign-customer-rest.php';
        require_once $base . 'class-paxdesign-customer-staff-rest.php';
        require_once $base . 'class-paxdesign-customer-admin.php';
    }
}
