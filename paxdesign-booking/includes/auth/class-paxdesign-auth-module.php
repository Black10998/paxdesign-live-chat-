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
        require_once $base . 'class-paxdesign-auth.php';
        require_once $base . 'class-paxdesign-auth-rest.php';

        if (!class_exists('PDX_Auth')) {
            PAXdesign_Auth_Native::register_hooks();
        } else {
            PAXdesign_Auth::register_hooks();
        }
        PAXdesign_Auth_REST::init();
    }
}
