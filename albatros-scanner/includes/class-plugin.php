<?php

if (!defined('ABSPATH')) {
    exit;
}

class Alb_Plugin {
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'boot'));
        add_action('alb_scanner_daily_maintenance', array('Alb_Audit', 'purge_expired'));
        add_action('user_register', array('Alb_Capabilities', 'bootstrap_user'));
    }

    public static function boot() {
        Alb_Install::maybe_upgrade();
        Alb_Rest::init();
        Alb_Frontend::init();
    }
}
