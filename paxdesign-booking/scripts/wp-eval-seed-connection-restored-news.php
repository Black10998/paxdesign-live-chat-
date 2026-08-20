<?php
/**
 * WP-CLI eval-file: publish the app connection-restored customer notice.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via: wp eval-file ...\n");
    exit(1);
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/customer/class-paxdesign-customer-news.php';
require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/customer/class-paxdesign-customer-news-announcements.php';

$slug = PAXdesign_Customer_News_Announcements::CONNECTION_SLUG;
$result = PAXdesign_Customer_News_Announcements::publish_connection_restored_2026(true);
if (is_wp_error($result)) {
    fwrite(STDERR, 'News publish failed: ' . $result->get_error_message() . "\n");
    exit(1);
}

$row = PAXdesign_Customer_News::find_row_by_slug($slug, true);
if (!$row) {
    fwrite(STDERR, "News published but published row is missing.\n");
    exit(1);
}

echo 'News published ID ' . (int) $result . ' slug ' . $slug . "\n";
echo 'status=' . (string) ($row['status'] ?? '') . "\n";
echo 'audience=' . (string) ($row['audience'] ?? '') . "\n";
echo 'priority=' . (string) ($row['priority'] ?? '') . "\n";
echo 'push_on_publish=' . (int) ($row['push_on_publish'] ?? 0) . "\n";

foreach (array('de', 'en', 'ar') as $lang) {
    $localized = PAXdesign_Customer_News::get_published($slug, $lang);
    if (!$localized || empty($localized['title']) || empty($localized['excerpt']) || empty($localized['body'])) {
        fwrite(STDERR, "Missing localized copy for $lang\n");
        exit(1);
    }
    echo 'Title (' . $lang . '): ' . $localized['title'] . "\n";
    echo 'Excerpt (' . $lang . '): ' . $localized['excerpt'] . "\n";
}
