<?php
/**
 * Reset a user's primary customer chat session handler to "ai" (production verification).
 *
 * Usage:
 *   PAX_RESET_LOGIN=admin@example.com wp eval-file wp-content/plugins/paxdesign-booking/scripts/wp-eval-reset-customer-chat-session.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file from the WordPress root.\n");
    exit(1);
}

$login = getenv('PAX_RESET_LOGIN') ?: '';
$user = $login ? get_user_by('login', $login) : false;
if (!$user && $login) {
    $user = get_user_by('email', $login);
}
if (!$user) {
    fwrite(STDERR, "user_not_found\n");
    exit(1);
}

$uid = (int) $user->ID;

if (!class_exists('PAXdesign_Customer_Chat_Bridge')) {
    fwrite(STDERR, "bridge_missing\n");
    exit(1);
}

$session_id = PAXdesign_Customer_Chat_Bridge::primary_session_id($uid);
PAXdesign_Chat_Live::get_instance()->ensure_session($session_id);

global $wpdb;
$table = PAXdesign_Chat_Log::table_name();
$wpdb->update(
    $table,
    array('handler' => 'ai'),
    array('session_id' => $session_id),
    array('%s'),
    array('%s')
);

echo $session_id;
