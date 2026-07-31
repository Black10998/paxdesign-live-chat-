<?php
/**
 * Lazy-load heavy widget scripts after idle / first interaction.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Widget_Performance {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'finalize_widget_assets'), 10050);
    }

    private static function is_account_auth_page() {
        return class_exists('PAXdesign_Auth_Page') && PAXdesign_Auth_Page::is_auth_page();
    }

    private static function page_has_live_chat_shortcode() {
        if (!is_singular()) {
            return false;
        }
        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return false;
        }
        return has_shortcode($post->post_content, 'paxdesign_live_chat');
    }

    public static function finalize_widget_assets() {
        if (is_admin() || self::is_account_auth_page()) {
            return;
        }

        if (!wp_script_is('paxdesign-booking-script', 'enqueued')) {
            return;
        }

        wp_script_add_data('paxdesign-booking-script', 'strategy', 'defer');

        if (self::page_has_live_chat_shortcode()) {
            if (wp_script_is('paxdesign-chat-script', 'registered') && !wp_script_is('paxdesign-chat-script', 'enqueued')) {
                wp_enqueue_script('paxdesign-chat-script');
            }
            return;
        }

        if (wp_script_is('paxdesign-chat-script', 'enqueued')) {
            wp_dequeue_script('paxdesign-chat-script');
        }

        wp_enqueue_script(
            'paxdesign-widget-loader',
            PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/widget-loader.js',
            array('paxdesign-booking-script'),
            PAXDESIGN_BOOKING_VERSION,
            array('strategy' => 'defer', 'in_footer' => true)
        );

        wp_localize_script(
            'paxdesign-widget-loader',
            'paxdesignWidgetLoader',
            array(
                'chatSrc' => PAXDESIGN_BOOKING_PLUGIN_URL . 'assets/js/chat-script.js?ver=' . rawurlencode(PAXDESIGN_BOOKING_VERSION),
            )
        );
    }
}

PAXdesign_Widget_Performance::init();
