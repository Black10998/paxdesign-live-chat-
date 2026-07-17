<?php
/**
 * Customer platform automated checks (CI-safe, no production credentials required).
 */

function cx_assert_true($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

echo "Customer platform checks\n";

$customer_dir = dirname(__DIR__, 2) . '/paxdesign-booking/includes/customer';
$files = glob($customer_dir . '/*.php');
cx_assert_true(is_array($files) && count($files) >= 10, 'Expected customer platform PHP modules');

foreach ($files as $file) {
    exec('php -l ' . escapeshellarg($file), $out, $code);
    cx_assert_true($code === 0, 'Syntax error in ' . basename($file));
}

$rest = file_get_contents($customer_dir . '/class-paxdesign-customer-rest.php');
cx_assert_true(strpos($rest, '/customer/chat/stream') !== false, 'Missing /customer/chat/stream route');
cx_assert_true(strpos($rest, '/customer/news/') !== false, 'Missing /customer/news/{slug} route');
cx_assert_true(strpos($rest, '/customer/chat/messages/image') !== false, 'Missing customer chat image route');
cx_assert_true(strpos($rest, '/customer/projects/') !== false && strpos($rest, '/download') !== false, 'Missing project file download route');
cx_assert_true(strpos($rest, 'chat_stream') !== false, 'Missing chat_stream handler');

$staff = file_get_contents($customer_dir . '/class-paxdesign-customer-staff-rest.php');
cx_assert_true(strpos($staff, '/customer/staff/projects') !== false, 'Missing staff project routes');
cx_assert_true(strpos($staff, 'require_staff') !== false, 'Staff routes must use require_staff');

$auth = file_get_contents($customer_dir . '/class-paxdesign-customer-auth.php');
cx_assert_true(strpos($auth, 'pax_customer') === false, 'Legacy pax_customer role must not remain');
cx_assert_true(strpos($auth, 'PAXdesign_Auth') !== false, 'Customer auth must delegate to PAXdesign_Auth');

$forbidden_patterns = array(
    '/register_rest_route\s*\([^)]*(paypal|billing|checkout|commerce)/i',
    '/class\s+PDX_(Commerce|Billing|Access)/',
    '/\b(in_app_purchase|iap_checkout)\b/i',
);
foreach ($files as $file) {
    $contents = file_get_contents($file);
    foreach ($forbidden_patterns as $pattern) {
        cx_assert_true(!preg_match($pattern, $contents), basename($file) . ' exposes forbidden commerce pattern: ' . $pattern);
    }
}

$chat = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat.php');
cx_assert_true(strpos($chat, 'stream_authenticated_customer_chat') !== false, 'Missing authenticated customer AI stream method');

$live = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/class-paxdesign-chat-live.php');
cx_assert_true(strpos($live, 'public function sanitize_session_id') !== false, 'PAXdesign_Chat_Live::sanitize_session_id must be public for cross-class use');

$db = file_get_contents($customer_dir . '/class-paxdesign-customer-db.php');
cx_assert_true(strpos($db, 'schema_complete') !== false, 'Customer DB must verify schema completeness');
cx_assert_true(strpos($db, 'service_categories') !== false && strpos($db, 'services') !== false, 'Customer DB must define services tables');

$ios_api = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerAPIClient.swift');
cx_assert_true(strpos($ios_api, '/customer/chat/stream') !== false, 'iOS client must call customer chat stream endpoint');
cx_assert_true(strpos($ios_api, '/auth/register') !== false, 'iOS client must support auth registration');
cx_assert_true(strpos($ios_api, 'endpointURL') !== false, 'iOS client must build REST URLs with endpointURL()');
cx_assert_true(strpos($ios_api, '/customer/push/register') !== false, 'iOS client must register push tokens');
cx_assert_true(strpos(strtolower($ios_api), 'paypal') === false, 'iOS customer client must not reference PayPal');

$ios_push = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-live-chat/PAXDesignLiveChat/Features/CustomerPortal/Core/CustomerPushService.swift');
cx_assert_true(strpos($ios_push, 'registerForRemoteNotifications') !== false, 'iOS push service must register for remote notifications');

$platform = file_get_contents($customer_dir . '/class-paxdesign-customer-platform.php');
cx_assert_true(strpos($platform, 'paxdesign_customer_require_login_for_chat') !== false, 'Login-required chat option must be configured');

$booking_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-native.php');
cx_assert_true(strpos($booking_auth, 'pdx_user_logged_in') !== false, 'Booking auth must fire pdx_user_logged_in');

$auth_native = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-native.php');
cx_assert_true(strpos($auth_native, "current_user_can( 'manage_options' )") !== false || strpos($auth_native, "current_user_can('manage_options')") !== false, 'is_site_admin must use current_user_can(manage_options) for current user');
cx_assert_true(!preg_match('/user_can\s*\([^)]*,\s*manage_options\s*\)/', $auth_native), 'Auth native must not use unquoted manage_options constant');

$auth_frontend = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/includes/auth/class-paxdesign-auth-frontend.php');
cx_assert_true(strpos($auth_frontend, 'PAX_AUTH_CONFIG') !== false, 'Booking auth frontend must localize PAX_AUTH_CONFIG');

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
