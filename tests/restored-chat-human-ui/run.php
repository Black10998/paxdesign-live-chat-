<?php
/**
 * Guards for the restored-baseline chat human UI patch.
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
$css = file_get_contents($root . '/assets/css/booking-styles.css');
$live = file_get_contents($root . '/includes/class-paxdesign-chat-live.php');
$store = file_get_contents($root . '/includes/class-paxdesign-message-store.php');
$boot = file_get_contents($root . '/paxdesign-booking.php');

assert_true(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.90')") !== false, 'plugin version 3.174.90');
assert_true(strpos($js, 'uploadHumanAttachFile') !== false, 'JS upload handler');
assert_true(strpos($js, 'paxdesign-chat-admin-active') !== false, 'JS human takeover class');
assert_true(strpos($js, 'paxdesign_chat_live_user_attach') !== false, 'JS posts attach action');
assert_true(strpos($js, '5 * 1024 * 1024') !== false, 'JS 5 MB image cap');
assert_true(strpos($js, '8 * 1024 * 1024') !== false, 'JS 8 MB file cap');
assert_true(strpos($css, '#063226') !== false, 'CSS dark green composer');
assert_true(strpos($css, 'paxdesign-booking-chat-attach-menu') !== false, 'CSS attach menu');
assert_true(strpos($live, 'handle_user_attach') !== false, 'PHP attach endpoint');
assert_true(strpos($live, 'paxdesign_chat_live_user_attach') !== false, 'PHP ajax hook');
assert_true(strpos($live, '5242880') !== false, 'PHP 5 MB image cap');
assert_true(strpos($live, '8388608') !== false, 'PHP 8 MB file cap');
assert_true(strpos($store, "'file_url'") !== false, 'message store persists file_url');
assert_true(strpos($js, 'skipping stacked sync') === false, 'patch is not the later GitHub chat rewrite');

$syntax = array(
    $root . '/paxdesign-booking.php',
    $root . '/includes/class-paxdesign-chat-live.php',
    $root . '/includes/class-paxdesign-message-store.php',
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

if ($fail > 0) {
    fwrite(STDERR, "$fail assertion(s) failed\n");
    exit(1);
}

echo "All restored chat human UI guards passed.\n";
