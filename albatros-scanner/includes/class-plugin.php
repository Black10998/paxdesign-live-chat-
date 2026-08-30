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
        add_filter('pre_option_users_can_register', '__return_zero');
        add_filter('rest_pre_dispatch', array(__CLASS__, 'block_public_user_create'), 10, 3);
        Alb_Rest::init();
        Alb_Frontend::init();
    }

    public static function block_public_user_create($result, $server, $request) {
        unset($server);
        if ($result !== null) {
            return $result;
        }
        $route = $request->get_route();
        $method = $request->get_method();
        if (strpos($route, '/wp/v2/users') !== 0) {
            return $result;
        }
        if (!in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {
            return $result;
        }
        if (!Alb_Capabilities::current_user_can('users.manage')) {
            return new WP_Error('alb_forbidden', Alb_I18n::t('error.forbidden'), array('status' => 403));
        }
        return $result;
    }
}
