<?php
/**
 * Booking auth module bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Auth_Module {

    public static function init() {
        $base = PAXDESIGN_BOOKING_PLUGIN_DIR . 'includes/auth/';
        require_once $base . 'class-paxdesign-customers.php';
        require_once $base . 'class-paxdesign-auth-native.php';
        require_once $base . 'class-paxdesign-auth-apple.php';
        require_once $base . 'class-paxdesign-auth-github.php';
        require_once $base . 'class-paxdesign-auth.php';
        require_once $base . 'class-paxdesign-auth-rest.php';
        require_once $base . 'class-paxdesign-auth-frontend.php';
        require_once $base . 'class-paxdesign-auth-page.php';
        require_once $base . 'class-paxdesign-fraud-score.php';
        require_once $base . 'class-paxdesign-fraud-store.php';
        require_once $base . 'class-paxdesign-fraud-guard.php';

        PAXdesign_Auth::register_hooks();
        PAXdesign_Auth_Apple::register_hooks();
        PAXdesign_Auth_GitHub::register_hooks();
        PAXdesign_Auth_REST::init();
        PAXdesign_Auth_Frontend::init();
        PAXdesign_Auth_Page::init();
        PAXdesign_Fraud_Guard::init();
    }
}
