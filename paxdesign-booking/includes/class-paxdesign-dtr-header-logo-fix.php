<?php
/**
 * Desktop header logo contrast/color fix for Paxdesign_Dtr_Header_Logo (SnipePHP snippet).
 *
 * Keeps the official logo markup and animation; only improves desktop "pax"
 * fluorescent green rendering and adds a subtle black shadow on light backgrounds.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PAXdesign_Dtr_Header_Logo_Fix {

    const VERSION = '1.0.0';

    public static function init() {
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 100);
        add_action('wp_footer', array(__CLASS__, 'print_footer_script'), 100);
    }

    public static function enqueue_assets() {
        $relative = 'assets/css/paxdesign-dtr-header-logo-fix.css';
        $path     = PAXDESIGN_BOOKING_PLUGIN_DIR . $relative;
        $url      = PAXDESIGN_BOOKING_PLUGIN_URL . $relative;
        $ver      = PAXDESIGN_BOOKING_VERSION . '.' . (is_readable($path) ? filemtime($path) : self::VERSION);
        $deps     = wp_style_is('paxdesign-dtr-logo', 'registered') ? array('paxdesign-dtr-logo') : array();

        wp_enqueue_style(
            'paxdesign-dtr-header-logo-fix',
            $url,
            $deps,
            $ver
        );
    }

    public static function print_footer_script() {
        $relative = 'assets/js/paxdesign-dtr-header-logo-fix.js';
        $path     = PAXDESIGN_BOOKING_PLUGIN_DIR . $relative;
        if (!is_readable($path)) {
            return;
        }

        $ver = PAXDESIGN_BOOKING_VERSION . '.' . filemtime($path);
        $src = PAXDESIGN_BOOKING_PLUGIN_URL . $relative;
        printf(
            '<script src="%s?ver=%s" defer></script>' . "\n",
            esc_url($src),
            esc_attr($ver)
        );
    }
}

PAXdesign_Dtr_Header_Logo_Fix::init();
