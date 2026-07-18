<?php
/**
 * WordPress admin compatibility helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Admin_Compat {

    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'guard_block_widgets_editor'), 1);
    }

    /**
     * Prevent legacy editor assets from loading on the block-based Widgets screen.
     * WordPress 6.4+ warns when wp-editor is enqueued alongside the block widgets editor.
     */
    public static function guard_block_widgets_editor($hook) {
        if (!function_exists('wp_use_widgets_block_editor') || !wp_use_widgets_block_editor()) {
            return;
        }

        if (!in_array($hook, array('widgets.php', 'customize.php', 'site-editor.php'), true)) {
            return;
        }

        add_action('admin_enqueue_scripts', array(__CLASS__, 'dequeue_legacy_editor_assets'), 9999);
    }

    public static function dequeue_legacy_editor_assets() {
        foreach (array('wp-editor', 'editor', 'quicktags', 'media-editor', 'word-count') as $handle) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
        }
    }

    /**
     * @param string $hook Admin page hook suffix.
     */
    public static function is_block_widgets_screen($hook = '') {
        if (!function_exists('wp_use_widgets_block_editor') || !wp_use_widgets_block_editor()) {
            return false;
        }
        if ($hook !== '' && in_array($hook, array('widgets.php', 'customize.php', 'site-editor.php'), true)) {
            return true;
        }
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, array('widgets', 'customize', 'site-editor'), true)) {
                return true;
            }
        }
        return false;
    }
}
