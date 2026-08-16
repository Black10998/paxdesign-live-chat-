<?php
/**
 * Guards for the restored-baseline chat / CCS patch.
 * These files are deployed surgically onto paxdesign.at and must not
 * contain the later GitHub chat rewrite.
 */
$root = dirname(__DIR__, 2) . '/deploy-patches/restored-chat-human-ui';
$fail = 0;

function assert_true($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$js = file_get_contents($root . '/assets/js/chat-script.js');
$bookingJs = file_get_contents($root . '/assets/js/booking-script.js');
$css = file_get_contents($root . '/assets/css/booking-styles.css');
$live = file_get_contents($root . '/includes/class-paxdesign-chat-live.php');
$store = file_get_contents($root . '/includes/class-paxdesign-message-store.php');
$boot = file_get_contents($root . '/paxdesign-booking.php');
$widget = file_get_contents($root . '/templates/booking-widget.php');
$knowledge = file_get_contents($root . '/includes/class-paxdesign-chat-knowledge.php');
$chat = file_get_contents($root . '/includes/class-paxdesign-chat.php');
$tickets = file_get_contents($root . '/includes/class-paxdesign-cybercrime-tickets.php');
$intake = file_get_contents($root . '/includes/class-paxdesign-cybercrime-intake.php');
$i18n = file_get_contents($root . '/includes/class-paxdesign-cybercrime-i18n.php');
$admin = file_get_contents($root . '/includes/customer/class-paxdesign-customer-admin.php');
$adminJs = file_get_contents($root . '/assets/js/cybercrime-admin.js');

assert_true(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.99')") !== false, 'plugin version 3.174.99');
assert_true(strpos($js, 'Version: 3.174.99') !== false, 'chat-script cache-bust 3.174.99');
assert_true(strpos($js, 'uploadHumanAttachFile') !== false, 'JS upload handler');
assert_true(strpos($js, 'paxdesign-chat-admin-active') !== false, 'JS human takeover class');
assert_true(strpos($js, 'paxdesign_chat_live_user_attach') !== false, 'JS posts attach action');
assert_true(strpos($js, '5 * 1024 * 1024') !== false, 'JS 5 MB image cap');
assert_true(strpos($js, '8 * 1024 * 1024') !== false, 'JS 8 MB file cap');
assert_true(preg_match('/function canCustomerEndChat\(\)\s*\{\s*return false;/', $js) === 1, 'end-chat disabled');
assert_true(strpos($js, "isLoggedIn() && isVerifiedAccount()") !== false, 'auth gate skips logged-in users');
assert_true(strpos($js, "context: 'page'") !== false, 'chat login uses the account-page form');
assert_true(strpos($widget, 'pdx-auth-page-form-wrap') !== false, 'chat login panel shares account-page form wrap');
assert_true(strpos($css, '--pax-mobile-widget-max-chat: none') !== false, 'mobile chat is a full phone sheet');
assert_true(strpos($css, 'min(84svh, calc(100svh - 20px))') !== false, 'mobile chat uses a compact svh sheet');
assert_true(strpos($css, 'text-align: center !important') !== false, 'login title/button is centered');
assert_true(strpos($bookingJs, 'function visualViewportBox') !== false, 'mobile layout reads the visual viewport box');
assert_true(strpos($bookingJs, 'box.height * 0.84') !== false, 'closed keyboard chat is reduced to 84% of the visible viewport');
assert_true(strpos($css, 'overflow-wrap: anywhere') !== false, 'mobile bubbles wrap instead of overflowing');
assert_true(strpos($css, 'paxdesign-chat-mode-active.paxdesign-mobile-chat-mode') !== false, 'mobile sheet beats the 520px desktop chat height');
assert_true(strpos($css, 'font-size: 22px') !== false, 'in-chat login title matches /account/');
assert_true(strpos($bookingJs, 'function fitWidgetToVisualViewport') !== false, 'mobile chat sizes to the visual viewport');
assert_true(strpos($bookingJs, 'function keyboardOcclusionPx') !== false, 'keyboard occlusion uses visualViewport');
assert_true(strpos($js, 'pinToLatestMessage: pinToLatestMessage') !== false, 'keyboard resize can pin to the latest message');
assert_true(strpos($css, 'border-radius: 12px 12px 0 0') !== false, 'keyboard-open composer sits flush above the keyboard');
assert_true(strpos($css, '#063226') !== false, 'CSS dark green composer');
assert_true(strpos($css, 'paxdesign-booking-chat-attach-menu') !== false, 'CSS attach menu');
assert_true(strpos($css, 'display: none !important') !== false && strpos($css, 'paxdesign-booking-chat-end-wrap') !== false, 'end-chat CSS hidden');
assert_true(strpos($live, 'handle_user_attach') !== false, 'PHP attach endpoint');
assert_true(strpos($store, "'file_url'") !== false, 'message store persists file_url');
assert_true(strpos($js, 'skipping stacked sync') === false, 'patch is not the later GitHub chat rewrite');
assert_true(strpos($js, 'var openInstant') === false, 'patch does not use 3.176 instant-open');
assert_true(strpos($js, 'var stickToBottom') !== false, 'WhatsApp stick-to-bottom is present');
assert_true(strpos($js, 'background: true, blockUi: false') !== false, 'open stays usable during background sync');
assert_true(strpos($widget, 'KI-generierte Antworten') === false, 'widget has no KI disclaimer');
assert_true(strpos($js, 'function pinToLatestMessage') !== false, 'open pins to latest message');
assert_true(strpos($widget, 'paxdesignChatEndWrap') === false, 'widget has no end-chat wrap');
assert_true(strpos($knowledge, 'Immer auf Deutsch') === false, 'knowledge prompt does not force German');
assert_true(strpos($knowledge, 'SAME language as the latest customer message') !== false, 'knowledge matches customer language');
assert_true(strpos($knowledge, 'the items below ARE that request') !== false, 'account context treats listed items as the submitted request');
assert_true(strpos($knowledge, 'Never ask them to describe or repeat a request') !== false, 'assistant must not re-ask a known request');
assert_true(strpos($chat, 'PAXdesign_Chat_Intent::detect') !== false, 'chat prompt runs intent detection');
assert_true(strpos($knowledge, 'ONE clear step at a time') !== false, 'CCS one-step guidance');
assert_true(strpos($knowledge, 'NEVER ask them to sign in') !== false, 'logged-in users are not asked to sign in');
assert_true(strpos($chat, 'This customer IS already logged in') !== false, 'chat prompt knows session auth');
assert_true(strpos($tickets, "'rejected'") !== false, 'tickets include rejected status');
assert_true(strpos($i18n, "'ar' => 'مرفوض'") !== false, 'i18n rejected label is Arabic مرفوض');
assert_true(strpos($admin, 'id="pax-cc-reject-ticket"') !== false, 'admin has مرفوض button');
assert_true(strpos($admin, '>مرفوض</button>') !== false, 'reject button text is مرفوض');
assert_true(strpos($adminJs, "saveStatus('rejected')") !== false, 'admin JS saves rejected');
assert_true(strpos($intake, 'email.submit.customer.body') !== false, 'intake emails the reporting customer');
assert_true(strpos($tickets, 'email_admin_status') !== false, 'status changes email admin');
assert_true(strpos($chat, 'class-paxdesign-cybercrime-ai-workflow') === false, 'does not pull in GitHub CCS AI workflow class');

$syntax = array(
    $root . '/paxdesign-booking.php',
    $root . '/includes/class-paxdesign-chat-live.php',
    $root . '/includes/class-paxdesign-message-store.php',
    $root . '/includes/class-paxdesign-chat-intent.php',
    $root . '/includes/class-paxdesign-chat.php',
    $root . '/includes/class-paxdesign-chat-knowledge.php',
    $root . '/includes/class-paxdesign-cybercrime-i18n.php',
    $root . '/includes/class-paxdesign-cybercrime-intake.php',
    $root . '/includes/class-paxdesign-cybercrime-tickets.php',
    $root . '/includes/customer/class-paxdesign-customer-admin.php',
    $root . '/templates/booking-widget.php',
);
foreach ($syntax as $file) {
    $out = array();
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    assert_true($code === 0, 'php -l ' . basename($file) . ' — ' . implode(' ', $out));
}

$jsCheck = array();
$jsCode = 0;
exec('node --check ' . escapeshellarg($root . '/assets/js/chat-script.js') . ' 2>&1', $jsCheck, $jsCode);
assert_true($jsCode === 0, 'node --check chat-script.js ' . implode(' ', $jsCheck));
$bookingJsCheck = array();
$bookingJsCode = 0;
exec('node --check ' . escapeshellarg($root . '/assets/js/booking-script.js') . ' 2>&1', $bookingJsCheck, $bookingJsCode);
assert_true($bookingJsCode === 0, 'node --check booking-script.js ' . implode(' ', $bookingJsCheck));
$adminJsCheck = array();
$adminJsCode = 0;
exec('node --check ' . escapeshellarg($root . '/assets/js/cybercrime-admin.js') . ' 2>&1', $adminJsCheck, $adminJsCode);
assert_true($adminJsCode === 0, 'node --check cybercrime-admin.js ' . implode(' ', $adminJsCheck));

if ($fail > 0) {
    fwrite(STDERR, "$fail assertion(s) failed\n");
    exit(1);
}

echo "All restored chat / CCS guards passed.\n";
