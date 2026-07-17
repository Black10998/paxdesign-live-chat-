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
        do_action('paxdesign_customer_platform_ready');
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
        require_once $base . 'class-paxdesign-customer-rest.php';
    }
}
