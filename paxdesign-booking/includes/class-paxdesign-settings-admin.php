<?php
/**
 * Settings page admin assets — CSS/JS loaded only on PAXDesign Booking → Einstellungen.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Settings_Admin {

    const PAGE_SLUG    = 'paxdesign-booking-settings';
    const HOOK_SUFFIX  = 'paxdesign-booking_page_paxdesign-booking-settings';
    const STYLE_HANDLE = 'paxdesign-booking-settings-admin';
    const SCRIPT_HANDLE = 'paxdesign-booking-settings-admin';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 999);
        add_filter('admin_body_class', array(__CLASS__, 'body_class'));
    }

    /**
     * Detect the plugin settings screen.
     *
     * @param string $hook Optional hook suffix from admin_enqueue_scripts.
     */
    public static function is_screen($hook = '') {
        if ($hook && $hook === self::HOOK_SUFFIX) {
            return true;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && !empty($screen->id) && $screen->id === self::HOOK_SUFFIX) {
                return true;
            }
        }

        if (is_admin() && isset($_GET['page'])) {
            $page = sanitize_key(wp_unslash($_GET['page']));
            if ($page === self::PAGE_SLUG) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append a body class on the settings screen.
     *
     * @param string $classes Space-separated admin body classes.
     * @return string
     */
    public static function body_class($classes) {
        if (self::is_screen()) {
            $classes .= ' paxdesign-settings-admin';
        }
        return $classes;
    }

    /**
     * Read settings admin CSS from the plugin directory.
     *
     * @return string
     */
    private static function get_css_contents() {
        static $css = null;

        if ($css !== null) {
            return $css;
        }

        $path = PAXDESIGN_BOOKING_PLUGIN_DIR . 'assets/css/settings-admin.css';
        if (!is_readable($path)) {
            $css = '';
            return $css;
        }

        $contents = file_get_contents($path);
        $css = is_string($contents) ? $contents : '';
        return $css;
    }

    /**
     * Enqueue settings-only admin CSS (inline) and JS.
     *
     * CSS is injected via wp_add_inline_style so it always renders — no external
     * asset URL dependency that can fail due to path, CDN, or host restrictions.
     *
     * @param string $hook Admin page hook suffix.
     */
    public static function enqueue_assets($hook = '') {
        if (!self::is_screen($hook)) {
            return;
        }

        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $css = self::get_css_contents();
        if ($css !== '') {
            wp_register_style(self::STYLE_HANDLE, false, array(), PAXDESIGN_BOOKING_VERSION);
            wp_enqueue_style(self::STYLE_HANDLE);
            wp_add_inline_style(self::STYLE_HANDLE, $css);
        }

        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'jquery-ui-sortable'),
            PAXDESIGN_BOOKING_VERSION,
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'paxdesignAdmin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('paxdesign_admin_nonce'),
            'smtpEnabled' => (bool) get_option('paxdesign_smtp_enabled', false),
            'notifEmail'  => get_option('paxdesign_booking_notification_email', 'info@paxdesign.at'),
        ));
    }
}
