<?php
/**
 * Shadow-only readability fix for Paxdesign_Dtr_Header_Logo (SnipePHP snippet).
 *
 * Adds a subtle black shadow behind the visible "pax" letters on desktop and
 * mobile headers. Does not change logo colors, design, or structure.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PAXdesign_Dtr_Header_Logo_Fix {

    const VERSION = '2.0.0';

    public static function init() {
        if (is_admin()) {
            return;
        }

        add_action('wp_footer', array(__CLASS__, 'print_footer_script'), 100);
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
