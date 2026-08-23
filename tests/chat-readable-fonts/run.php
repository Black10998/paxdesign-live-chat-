<?php
/**
 * Chat window readable-font guards.
 * Only the live-chat window may leave Voga/Orbitron; homepage/login/CCS stay unchanged.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function crf_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$typo = file_get_contents($root . '/navein/assets/css/site-body-typography.css');
$home = file_get_contents($root . '/navein/assets/css/apple-homepage.css');
$ccs = file_get_contents($root . '/navein/assets/css/apple-cybercrime-support.css');
$booking = file_get_contents($root . '/paxdesign-booking/assets/css/booking-styles.css');
$overlay = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/css/booking-styles.css');
$tokens = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/css/pdx-tokens.css');
$style = file_get_contents($root . '/navein/style.css');

crf_ok(strpos($typo, '--pax-chat-read-font:') !== false, 'chat readable font token exists');
crf_ok(strpos($typo, '-apple-system, BlinkMacSystemFont, "Segoe UI"') !== false, 'chat uses a system UI font stack');
crf_ok(strpos($typo, 'Noto Sans Arabic') !== false, 'chat stack includes Arabic-capable fallback');
crf_ok(strpos($typo, '#paxdesignChatPanel') !== false, 'chat panel is targeted');
crf_ok(strpos($typo, '#paxdesignChatAuthGate') !== false, 'chat auth gate is targeted');
crf_ok(strpos($typo, '.paxdesign-booking-chat-input::placeholder') !== false, 'chat placeholders are targeted');
crf_ok(strpos($typo, '.paxdesign-booking-chat-message') !== false || strpos($typo, '#paxdesignChatPanel *') !== false, 'chat messages inherit the readable stack');
crf_ok(strpos($typo, 'Chat window — keep original Voga font') === false, 'chat no longer forces Voga');
crf_ok(strpos($typo, 'var(--pax-orbitron-display) !important') !== false, 'Orbitron remains on nav/auth actions');
crf_ok(strpos($typo, '#paxdesignBookingPanel') !== false, 'booking tab keeps the site body font');

$auth_idx = strpos($typo, '#paxdesign-booking-root .paxdesign-booking-chat-auth-login-btn');
crf_ok($auth_idx !== false, 'chat Sign In button is targeted');
if ($auth_idx !== false) {
	$chunk = substr($typo, $auth_idx, 2500);
	crf_ok(strpos($chunk, 'pax-chat-read-font') !== false || strpos($chunk, '-apple-system, BlinkMacSystemFont, "Segoe UI"') !== false, 'chat Sign In uses the readable stack');
	crf_ok(strpos($chunk, 'pax-voga-body') === false, 'chat Sign In does not use Voga');
	crf_ok(strpos($chunk, 'pax-orbitron-display') === false, 'chat Sign In does not use Orbitron');
}

crf_ok(strpos($home, '--ph-display: "Orbitron"') !== false, 'homepage display font stays Orbitron');
crf_ok(strpos($home, '--ph-text: var(--pax-voga-body') !== false || strpos($home, '"Exo 2"') !== false, 'homepage body text uses Exo 2');
crf_ok(strpos($ccs, '--ccs-font: "Exo 2"') !== false, 'cybercrime support body uses Exo 2');
crf_ok(strpos($tokens, '--pdx-font: "Exo 2"') !== false, 'login/dashboard tokens use Exo 2');
crf_ok(strpos($booking, 'button.paxdesign-booking-chat-auth-login-btn') !== false, 'plugin chat Sign In uses readable fonts');
crf_ok(strpos($booking, '-apple-system, BlinkMacSystemFont, "Segoe UI"') !== false, 'plugin chat CSS includes system UI stack');
crf_ok(strpos($booking, '.paxdesign-booking-chat-auth-github-btn') !== false, 'plugin css also overrides GitHub/Apple chat login buttons');
crf_ok(preg_match('/--pax-font:\s+"Exo 2"/', $booking) === 1, 'booking tab uses Exo 2 at the plugin root');
crf_ok(md5($booking) === md5($overlay), 'overlay booking-styles still matches plugin');
$theme_version_ok = preg_match('/Version:\\s*1\\.4\\.(\\d+)/', $style, $theme_version) === 1
	&& (int) $theme_version[1] >= 50;
crf_ok($theme_version_ok, 'theme version bumped for cache-bust');
crf_ok(strpos($typo, 'skipping stacked sync') === false, 'does not include 3.176 chat rewrite');

$workflow = $root . '/.github/workflows/deploy-chat-readable-fonts.yml';
crf_ok(is_file($workflow), 'surgical chat-font deploy workflow exists');
if (is_file($workflow)) {
	$wf = file_get_contents($workflow);
	crf_ok(strpos($wf, 'rsync --delete') === false, 'deploy does not rsync-delete the plugin tree');
	crf_ok(strpos($wf, 'site-body-typography.css') !== false, 'deploy copies the typography override');
	crf_ok(strpos($wf, 'class-paxdesign-chat-live.php') === false, 'deploy does not copy chat PHP');
}

if ($fail > 0) {
	fwrite(STDERR, "$fail chat-readable-font assertion(s) failed\n");
	exit(1);
}

echo "Chat readable-font guards passed.\n";
