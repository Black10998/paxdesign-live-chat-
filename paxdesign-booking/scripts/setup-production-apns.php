<?php
/**
 * Configure and verify WordPress APNs on the production server.
 *
 * Usage (on server, from WordPress root):
 *   wp eval-file wp-content/plugins/paxdesign-booking/scripts/setup-production-apns.php
 *
 * Environment variables (optional — skips update when unset):
 *   PAX_APNS_KEY_ID, PAX_APNS_TEAM_ID, PAX_APNS_BUNDLE_ID, PAX_APNS_KEY_P8
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run via wp eval-file from the WordPress root.\n");
    exit(1);
}

$key_id = trim((string) getenv('PAX_APNS_KEY_ID'));
$team_id = trim((string) getenv('PAX_APNS_TEAM_ID'));
$bundle_id = trim((string) getenv('PAX_APNS_BUNDLE_ID'));
$key_p8 = trim((string) getenv('PAX_APNS_KEY_P8'));

if ($key_id !== '' && $team_id !== '' && $key_p8 !== '') {
    update_option('paxdesign_apns_key_id', sanitize_text_field($key_id));
    update_option('paxdesign_apns_team_id', sanitize_text_field($team_id));
    update_option(
        'paxdesign_apns_bundle_id',
        $bundle_id !== '' ? sanitize_text_field($bundle_id) : 'at.paxdesign.livechat'
    );
    update_option('paxdesign_apns_key_p8', $key_p8);
    echo "Updated APNs options in wp_options.\n";
} else {
    echo "APNs env vars not provided; keeping existing wp_options values.\n";
}

if (!class_exists('PAXdesign_APNS')) {
    fwrite(STDERR, "ERROR: PAXdesign_APNS class not loaded.\n");
    exit(1);
}

$configured = PAXdesign_APNS::is_configured();
echo 'APNS_CONFIGURED=' . ($configured ? 'true' : 'false') . "\n";
if (!$configured) {
    fwrite(STDERR, "ERROR: APNs is not configured (key_id, team_id, key_p8 required).\n");
    exit(1);
}

$cfg = PAXdesign_APNS::get_config();
echo 'APNS_KEY_ID=' . $cfg['key_id'] . "\n";
echo 'APNS_TEAM_ID=' . $cfg['team_id'] . "\n";
echo 'APNS_BUNDLE_ID=' . $cfg['bundle_id'] . "\n";

$device_total = 0;
$admin_ids = PAXdesign_APNS::get_admin_user_ids();
foreach ($admin_ids as $uid) {
    $devices = PAXdesign_APNS::get_user_devices((int) $uid);
    $active = 0;
    foreach ($devices as $device) {
        if (!empty($device['revoked'])) {
            continue;
        }
        $active++;
        $device_total++;
    }
    $user = get_userdata((int) $uid);
    $label = $user ? $user->user_email : (string) $uid;
    echo "DEVICES user={$label} active={$active}\n";
}

echo "DEVICE_TOTAL={$device_total}\n";

if ($device_total <= 0) {
    echo "WARN: No active APNs device tokens registered yet. Open the iOS app, grant notifications, and log in.\n";
    exit(0);
}

$test_user_id = (int) $admin_ids[0];
$test_devices = PAXdesign_APNS::get_user_devices($test_user_id);
$test_token = '';
foreach ($test_devices as $device) {
    if (empty($device['revoked']) && !empty($device['token'])) {
        $test_token = (string) $device['token'];
        break;
    }
}

if ($test_token === '') {
    echo "WARN: Could not find a device token for test push.\n";
    exit(0);
}

$result = PAXdesign_APNS::send_to_user(
    $test_user_id,
    'PAXDesign Test',
    'Production APNs verification push from WordPress backend.',
    array(
        'type'       => 'message',
        'event'      => 'new_customer_message',
        'session_id' => 'apns_verify_' . time(),
        'preview'    => 'Backend APNs test',
    ),
    false
);

if (is_wp_error($result)) {
    fwrite(STDERR, 'ERROR: Test push failed: ' . $result->get_error_message() . "\n");
    exit(1);
}

echo "TEST_PUSH_SENT=true\n";
