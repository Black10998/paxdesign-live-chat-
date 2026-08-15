<?php
/**
 * One-time WordPress cleanup: remove paxdesign-toolbar plugin and its options.
 *
 * Usage (on server, from WordPress root):
 *   wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-uninstall-toolbar.php
 *
 * Does NOT touch paxdesign-booking, customer chat tables, or cybercrime case data.
 */

if (!defined('ABSPATH')) {
    exit("Run via WP-CLI eval-file inside WordPress.\n");
}

$plugin_files = [
    'paxdesign-toolbar/paxdesign-toolbar.php',
];

$plugin_dirs = glob(WP_PLUGIN_DIR . '/paxdesign-toolbar*');
if (is_array($plugin_dirs)) {
    foreach ($plugin_dirs as $dir) {
        if (is_dir($dir)) {
            // Defer to WP plugin delete API when possible.
        }
    }
}

if (function_exists('deactivate_plugins')) {
    deactivate_plugins($plugin_files, true, is_network_admin());
}

if (function_exists('delete_plugins')) {
    @delete_plugins(['paxdesign-toolbar']);
}

$option_names = [
    'pdx_settings',
    'pdx_updater_last_checked',
    'pdx_updater_state',
    'pdx_github_release',
    'pdx_config_version',
    'pdx_event_log',
    'pdx_briefs',
    'pdx_stripe_secret_key',
];

foreach ($option_names as $name) {
    delete_option($name);
}

global $wpdb;
if ($wpdb instanceof wpdb) {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pdx_setup%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pdx_webhook%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pdx_recovery%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pdx_cf_%'");
}

$active = get_option('active_plugins', []);
if (is_array($active)) {
    $active = array_values(array_filter($active, static function ($basename) {
        return strpos($basename, 'paxdesign-toolbar') === false;
    }));
    update_option('active_plugins', $active);
}

if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

echo "paxdesign-toolbar cleanup complete.\n";
