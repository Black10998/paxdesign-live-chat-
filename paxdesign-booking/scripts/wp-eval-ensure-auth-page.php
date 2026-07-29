<?php
/**
 * WP-CLI eval-file: ensure dedicated /account authentication page exists.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via: wp eval-file ...\n");
    exit(1);
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-page.php';

$page_id = PAXdesign_Auth_Page::ensure_page();
if ($page_id <= 0) {
    fwrite(STDERR, "Failed to create account page.\n");
    exit(1);
}

update_option('paxdesign_auth_page_version', '1', false);
echo 'Account page ready: ' . PAXdesign_Auth_Page::page_url() . ' (ID ' . $page_id . ")\n";
