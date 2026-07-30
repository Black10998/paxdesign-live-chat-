<?php
/**
 * WP-CLI eval-file: publish platform update 2026 news announcement.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via: wp eval-file ...\n");
    exit(1);
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/customer/class-paxdesign-customer-news.php';
require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/customer/class-paxdesign-customer-news-announcements.php';

$result = PAXdesign_Customer_News_Announcements::publish_platform_update_2026(true);
if (is_wp_error($result)) {
    fwrite(STDERR, 'News publish failed: ' . $result->get_error_message() . "\n");
    exit(1);
}

$item = PAXdesign_Customer_News::get_published('plattform-update-2026', 'de');
if (!$item || empty($item['image_url'])) {
    fwrite(STDERR, "News published but image_url missing.\n");
    exit(1);
}

echo 'News published ID ' . (int) $result . ' slug plattform-update-2026' . "\n";
echo 'Image: ' . $item['image_url'] . "\n";
echo 'Title (de): ' . ($item['title'] ?? '') . "\n";

foreach (array('en', 'ar') as $lang) {
    $localized = PAXdesign_Customer_News::get_published('plattform-update-2026', $lang);
    if (!$localized || empty($localized['title'])) {
        fwrite(STDERR, "Missing localized title for $lang\n");
        exit(1);
    }
    echo 'Title (' . $lang . '): ' . $localized['title'] . "\n";
}
