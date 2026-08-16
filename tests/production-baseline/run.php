<?php
/**
 * Guard: GitHub paxdesign-booking must match the live 3.174.92 baseline.
 * Rejects the later 3.176.x chat rewrite and CCS AI form-fill classes.
 */
$root = dirname(__DIR__, 2);
$plugin = $root . '/paxdesign-booking';
$overlay = $root . '/deploy-patches/restored-chat-human-ui';
$fail = 0;

function pb_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$boot = file_get_contents($plugin . '/paxdesign-booking.php');
$js = file_get_contents($plugin . '/assets/js/chat-script.js');
$knowledge = file_get_contents($plugin . '/includes/class-paxdesign-chat-knowledge.php');

pb_ok(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.92')") !== false, 'plugin version 3.174.92');
pb_ok(strpos($js, 'Version: 3.174.92') !== false, 'chat-script cache-bust 3.174.92');
pb_ok(strpos($js, 'skipping stacked sync') === false, 'chat-script is not the 3.176 freeze/unfreeze rewrite');
pb_ok(strpos($js, 'var openInstant') === false, 'chat-script does not use the 3.176 instant-open rewrite');
pb_ok(strpos($js, 'var stickToBottom') !== false, 'WhatsApp stick-to-bottom is present');
pb_ok(strpos($js, 'var pollInFlight') !== false, 'overlapping poll lock is present');
pb_ok(strpos($js, 'background: true, blockUi: false') !== false, 'open does not block on history/sync');
pb_ok(strpos($js, 'clearHistoryDomState();') !== false && strpos($js, 'if (full && !hasPaintedThread)') !== false, 'full history fetch does not wipe a painted thread');
pb_ok(strpos($boot, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'bootstrap does not load CCS AI workflow');
pb_ok(strpos($boot, 'class-paxdesign-cybercrime-ai-case.php') === false, 'bootstrap does not load CCS AI case');
pb_ok(strpos($boot, 'class-paxdesign-cybercrime-ai-operations.php') === false, 'bootstrap does not load CCS AI operations');
pb_ok(!is_file($plugin . '/includes/class-paxdesign-cybercrime-ai-workflow.php'), 'CCS AI workflow class file is absent');
pb_ok(!is_file($plugin . '/includes/class-paxdesign-cybercrime-ai-case.php'), 'CCS AI case class file is absent');
pb_ok(!is_file($plugin . '/includes/class-paxdesign-cybercrime-ai-operations.php'), 'CCS AI operations class file is absent');
pb_ok(is_file($plugin . '/includes/class-paxdesign-cybercrime-i18n.php'), 'compact CCS i18n helper is present');
pb_ok(strpos($knowledge, 'Immer auf Deutsch') === false, 'knowledge prompt does not force German');
pb_ok(strpos($knowledge, 'ONE clear step at a time') !== false, 'CCS one-step guidance is present');
pb_ok(strpos($js, 'Gespräch beenden') === false, 'chat JS has no Gespräch beenden label');

$overlay_files = array(
    'paxdesign-booking.php',
    'assets/js/chat-script.js',
    'assets/css/booking-styles.css',
    'assets/js/cybercrime-admin.js',
    'includes/class-paxdesign-chat.php',
    'includes/class-paxdesign-chat-knowledge.php',
    'includes/class-paxdesign-cybercrime-i18n.php',
    'includes/class-paxdesign-cybercrime-tickets.php',
    'includes/class-paxdesign-cybercrime-intake.php',
    'includes/customer/class-paxdesign-customer-admin.php',
    'templates/booking-widget.php',
);
foreach ($overlay_files as $rel) {
    $a = $overlay . '/' . $rel;
    $b = $plugin . '/' . $rel;
    pb_ok(is_file($a) && is_file($b) && md5_file($a) === md5_file($b), 'overlay matches plugin: ' . $rel);
}

if ($fail > 0) {
    fwrite(STDERR, "$fail production-baseline assertion(s) failed\n");
    exit(1);
}
echo "Production baseline 3.174.92 guards passed.\n";
