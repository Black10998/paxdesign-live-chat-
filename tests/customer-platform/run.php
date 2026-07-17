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
cx_assert_true(is_array($files) && count($files) >= 8, 'Expected customer platform PHP modules');

foreach ($files as $file) {
    exec('php -l ' . escapeshellarg($file), $out, $code);
    cx_assert_true($code === 0, 'Syntax error in ' . basename($file));
}

$rest = file_get_contents($customer_dir . '/class-paxdesign-customer-rest.php');
cx_assert_true(strpos($rest, '/customer/chat/stream') !== false, 'Missing /customer/chat/stream route');
cx_assert_true(strpos($rest, '/customer/news/') !== false, 'Missing /customer/news/{slug} route');
cx_assert_true(strpos($rest, 'chat_stream') !== false, 'Missing chat_stream handler');

$auth = file_get_contents($customer_dir . '/class-paxdesign-customer-auth.php');
cx_assert_true(strpos($auth, 'pax_customer') === false, 'Legacy pax_customer role must not remain');
cx_assert_true(strpos($auth, 'PDX_Auth') !== false, 'Customer auth must delegate to PDX_Auth');

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

$ios_api = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-booking/ios-customer-portal/PAXCustomerPortal/Core/CustomerAPIClient.swift');
cx_assert_true(strpos($ios_api, '/customer/chat/stream') !== false, 'iOS client must call customer chat stream endpoint');
cx_assert_true(strpos(strtolower($ios_api), 'paypal') === false, 'iOS customer client must not reference PayPal');

$toolbar_auth = file_get_contents(dirname(__DIR__, 2) . '/paxdesign-toolbar/includes/class-pdx-auth.php');
cx_assert_true(strpos($toolbar_auth, 'pdx_user_logged_in') !== false, 'Toolbar login must fire pdx_user_logged_in');

echo "OK: customer platform static verification passed (" . count($files) . " modules)\n";
