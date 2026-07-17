<?php
/**
 * Run customer DB migrations via WP-CLI (avoids fragile inline wp eval quoting).
 *
 * Usage from WordPress root:
 *   wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-customer-db-migrate.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file from the WordPress root.\n");
    exit(1);
}

if (!class_exists('PAXdesign_Customer_DB')) {
    fwrite(STDERR, "PAXdesign_Customer_DB not loaded.\n");
    exit(1);
}

PAXdesign_Customer_DB::maybe_upgrade();

if (!PAXdesign_Customer_DB::schema_complete()) {
    fwrite(STDERR, "customer schema incomplete\n");
    exit(1);
}

echo "customer_db_ok\n";
