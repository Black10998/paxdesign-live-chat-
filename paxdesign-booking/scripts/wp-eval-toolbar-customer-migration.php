<?php
/**
 * Export toolbar-era customer data and register all accounts in the native portal registry.
 *
 * Usage from WordPress root:
 *   wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-toolbar-customer-migration.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file from the WordPress root.\n");
    exit(1);
}

if (!class_exists('PAXdesign_Toolbar_Migration')) {
    fwrite(STDERR, "PAXdesign_Toolbar_Migration not loaded.\n");
    exit(1);
}

$force = getenv('PAX_FORCE_TOOLBAR_MIGRATION') === '1';
$result = PAXdesign_Toolbar_Migration::run($force);

if (empty($result['ok'])) {
    fwrite(STDERR, wp_json_encode($result) . "\n");
    exit(1);
}

echo wp_json_encode($result) . "\n";
