<?php
/**
 * Guard: GitHub paxdesign-booking must match the live 3.174.104 baseline.
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
$css = file_get_contents($plugin . '/assets/css/booking-styles.css');
$widget = file_get_contents($plugin . '/templates/booking-widget.php');

pb_ok(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.104')") !== false, 'plugin version 3.174.104');
pb_ok(strpos($js, 'Version: 3.174.104') !== false, 'chat-script cache-bust 3.174.104');
pb_ok(strpos($js, 'skipping stacked sync') === false, 'chat-script is not the 3.176 freeze/unfreeze rewrite');
pb_ok(strpos($js, 'var openInstant') === false, 'chat-script does not use the 3.176 instant-open rewrite');
pb_ok(strpos($js, 'var stickToBottom') !== false, 'WhatsApp stick-to-bottom is present');
pb_ok(strpos($js, 'var pollInFlight') !== false, 'overlapping poll lock is present');
pb_ok(strpos($js, 'function pinToLatestMessage') !== false, 'open pins to latest message without animation');
pb_ok(strpos($css, 'scroll-behavior: smooth') === false, 'messages do not use smooth history scrolling');
pb_ok(strpos($widget, 'KI-generierte Antworten') === false, 'disclaimer text is removed from chat UI');
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
pb_ok(strpos($css, '--pax-mobile-widget-max-chat: none') !== false, 'mobile chat is not capped at 380px');
pb_ok(strpos($css, 'min(420px, 58svh') !== false, 'mobile sheet uses compact svh card height');
pb_ok(strpos($css, 'width: 360px') !== false && strpos($css, 'height: 420px') !== false, 'desktop chat is a compact 360x420 card from the first frame');
pb_ok(strpos($css, '.paxdesign-booking-chat-auth-gate[hidden]') !== false && strpos($css, 'display: none !important') !== false, 'hidden login overlay is display:none so it cannot cover messages on PC or mobile');
pb_ok(strpos($css, 'top: auto') !== false, 'mobile sheet is not stretched from the top of the layout viewport');
pb_ok(strpos($css, 'display: none !important') !== false && strpos($css, 'paxdesign-booking-mode-switch') !== false, 'top Live Chat/Termin buchen tabs are hidden');
pb_ok(strpos($js, "data-widget-mode=\"booking\"") !== false, 'plus menu contains Termin buchen');
pb_ok(strpos($js, "data-widget-mode=\"chat\"") !== false, 'plus menu contains Live Chat');
pb_ok(strpos($css, '#paxdesign-booking-root .paxdesign-booking-chat-auth-gate') !== false && strpos($css, "background: #fff") !== false, 'chat login panel uses Apple light background');
pb_ok(strpos($widget, 'pdx-auth-page-form-wrap') !== false, 'chat login mounts the account-page form styles');
pb_ok(strpos($js, "context: 'page'") !== false, 'chat login uses the account-page auth form');
pb_ok(strpos($css, 'font-size: 16px') !== false, 'mobile composer uses 16px text to avoid overflow/zoom');
pb_ok(strpos($css, 'paxdesign-chat-mode-active.paxdesign-mobile-chat-mode') !== false, 'mobile sheet overrides the 520px desktop chat height');
pb_ok(strpos($css, 'font-size: 22px') !== false, 'chat login title matches the Apple account page');
pb_ok(strpos($css, 'paxdesign-chat-auth-locked .paxdesign-booking-container') !== false && strpos($css, 'flex-direction: column') !== false, 'login panel stacks below the header instead of overlapping it');
pb_ok(strpos($css, '.paxdesign-booking-chat-auth-gate-inner:has(.paxdesign-booking-chat-auth-inline .pdx-auth-form) .paxdesign-booking-chat-auth-gate-intro') !== false, 'duplicate login headline is hidden once the inline form is mounted');
pb_ok(strpos($css, '.paxdesign-booking-chat-auth-gate-inner') !== false && strpos($css, 'overflow: hidden') !== false, 'login panel does not rely on internal scrolling');
pb_ok(strpos($css, '.paxdesign-booking-chat-auth-gate-close') !== false && strpos($css, 'display: none !important') !== false, 'duplicate login Close button is hidden; header Close is the only one');
pb_ok(strpos($widget, 'paxdesignChatAuthClose') === false, 'widget template has no duplicate auth-gate Close button');
pb_ok(strpos($css, 'pdx-auth-link[data-view="forgot"]') !== false && strpos($css, 'display: inline') !== false, 'Forgot password link stays visible in compact login');
pb_ok(strpos($css, 'overflow-wrap: anywhere') !== false, 'login labels and links wrap instead of overlapping');
$booking_js = file_get_contents($plugin . '/assets/js/booking-script.js');
pb_ok(strpos($booking_js, 'function applyCompactWidgetFrame') !== false, 'chat sizes to a compact card on desktop and mobile');
pb_ok(strpos($booking_js, 'function keyboardOcclusionPx') !== false, 'keyboard occlusion is measured from visualViewport');
pb_ok(strpos($booking_js, 'COMPACT_HEIGHT = 420') !== false, 'closed keyboard chat is a 420px compact card, not 84% of the screen');
pb_ok(strpos($booking_js, 'kb > 50') !== false, 'keyboard-open layout waits for real visualViewport occlusion');
pb_ok(strpos($booking_js, 'mobileKeyboardWasOpen') !== false, 'mobile keyboard resize does not force pin-to-latest on every viewport event');
pb_ok(strpos($booking_js, "top: top + 'px'") !== false, 'mobile keyboard-open sheet anchors to the visual viewport top');
pb_ok(strpos($css, 'paxdesign-keyboard-open .paxdesign-booking-container') !== false && strpos($css, 'max-height: none') !== false, 'keyboard-open chat can shrink below the 420px container cap');
pb_ok(strpos($css, 'paxdesign-booking-body.paxdesign-booking-chat-body') !== false && strpos($css, 'overflow: hidden') !== false, 'only the message list scrolls, not the whole chat body');
pb_ok(strpos($js, 'pinToLatestMessage: pinToLatestMessage') !== false, 'chat exposes pinToLatestMessage for keyboard resize');
pb_ok(strpos($css, 'paxdesign-keyboard-open .paxdesign-booking-widget') !== false && strpos($css, 'border-radius: 0') !== false, 'keyboard-open sheet fills the visual viewport flush above the keyboard');

$overlay_files = array(
    'paxdesign-booking.php',
    'assets/js/chat-script.js',
    'assets/css/booking-styles.css',
    'assets/js/cybercrime-admin.js',
    'includes/class-paxdesign-chat-intent.php',
    'includes/class-paxdesign-chat.php',
    'includes/class-paxdesign-chat-knowledge.php',
    'includes/class-paxdesign-cybercrime-i18n.php',
    'includes/class-paxdesign-cybercrime-tickets.php',
    'includes/class-paxdesign-cybercrime-intake.php',
    'includes/customer/class-paxdesign-customer-admin.php',
    'templates/booking-widget.php',
    'assets/js/booking-script.js',
    'includes/auth/class-paxdesign-auth-github.php',
    'includes/auth/class-paxdesign-auth-module.php',
    'includes/auth/class-paxdesign-auth-rest.php',
    'includes/auth/class-paxdesign-auth-frontend.php',
    'assets/customer-auth/js/pax-auth.js',
    'assets/customer-auth/css/pdx-auth-page.css',
    'assets/customer-auth/css/pdx-auth.css',
    'templates/settings-page.php',
    'scripts/wp-eval-github-web-oauth-config.php',
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
echo "Production baseline 3.174.104 guards passed.\n";
