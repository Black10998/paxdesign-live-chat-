<?php
/**
 * Re-seed the customer services table from the live booking catalog.
 * Safe to run repeatedly. Does not bump plugin version or touch iOS.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via: wp eval-file wp-eval-sync-customer-services.php\n");
    return;
}

if (!class_exists('PAXdesign_Customer_DB') || !class_exists('PAXdesign_Customer_Services')) {
    echo "customer services classes missing\n";
    return;
}

PAXdesign_Customer_DB::maybe_upgrade();
delete_option('paxdesign_customer_services_seeded');
PAXdesign_Customer_Services::sync_from_booking_catalog();

$services = PAXdesign_Customer_Services::list_services();
$slugs = array();
foreach ($services as $service) {
    if (!empty($service['slug'])) {
        $slugs[] = $service['slug'];
    }
}

echo 'synced_services=' . count($services) . "\n";
echo 'sample_slugs=' . implode(',', array_slice($slugs, 0, 8)) . "\n";
echo 'has_website=' . (in_array('website', $slugs, true) ? '1' : '0') . "\n";
echo 'has_aiautomation=' . (in_array('aiautomation', $slugs, true) ? '1' : '0') . "\n";
echo 'has_gdpr=' . (in_array('gdpr', $slugs, true) ? '1' : '0') . "\n";
