<?php
/**
 * Guards for the stable Apple header and isolated Search control.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function hs_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$css = file_get_contents($root . '/navein/assets/css/apple-header-stable.css');
$functions = file_get_contents($root . '/navein/functions.php');
$style = file_get_contents($root . '/navein/style.css');
$js = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
$overlay_js = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/customer-auth/js/pax-auth.js');
$auth_css = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/css/pdx-auth.css');
$plugin = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');
$chat = file_get_contents($root . '/paxdesign-booking/assets/js/chat-script.js');
$workflow = file_get_contents($root . '/.github/workflows/deploy-header-stable.yml');

hs_ok(is_file($root . '/navein/assets/css/apple-header-stable.css'), 'stable header stylesheet exists');
hs_ok(strpos($functions, 'navein-apple-header-stable') !== false, 'functions.php enqueues the stable header CSS');
hs_ok(strpos($functions, 'apple-header-stable.css') !== false, 'stable header CSS path is registered');
hs_ok(preg_match('/Version:\\s*1\\.4\\.(\\d+)/', $style, $v) === 1 && (int) $v[1] >= 53, 'theme version is cache-busted to 1.4.53+');

hs_ok(strpos($css, 'dtr-search-modal-trigger') !== false, 'Search trigger is restyled');
hs_ok(strpos($css, 'border-left: 0.5px solid') !== false, 'Search is separated from the nav with a hairline');
hs_ok(strpos($css, 'max-height: var(--dtr-apple-header-height)') !== false, 'header height is locked');
hs_ok(strpos($css, 'flex-wrap: nowrap') !== false, 'header row cannot wrap onto a second line');
hs_ok(strpos($css, 'pdx-auth-bar--menu-open') !== false, 'open profile menu cannot change header height');
hs_ok(strpos($css, 'pdx-auth-menu--apple') !== false && strpos($css, 'position: fixed') !== false, 'profile dropdown stays a fixed overlay');
hs_ok(preg_match('/#dtr-header-global \\.main-navigation \\{[^}]*overflow:\\s*visible/', $css) === 1, 'nav overflow is visible so mega menus can paint');
hs_ok(preg_match('/#dtr-header-global \\.main-navigation \\{[^}]*overflow:\\s*hidden/', $css) !== 1, 'nav does not clip labels or hover dropdowns');
hs_ok(strpos($css, 'cybercrime-menu') !== false && strpos($css, 'white-space: nowrap') !== false, 'Cybercrime Support label cannot wrap or clip');
hs_ok(strpos($css, 'dtr-has-mega') !== false && strpos($css, 'dtr-mega-panel') !== false, 'mega-menu panels stay unclipped');
hs_ok(strpos($css, 'a.dtr-btn.dtr-header-btn') !== false && strpos($css, '--dtr-apple-header-control') !== false, 'Angebot anfordern is a compact Apple CTA');
hs_ok(strpos($css, '.dtr-header-btn .dtr-btn__icon') !== false && strpos($css, 'display: none !important') !== false, 'CTA theme icon is hidden so the pill stays compact');
hs_ok(strpos($css, '.pdx-header-user-name') !== false && strpos($css, 'font-size: 12px !important') !== false, 'logged-in name is reduced to 12px');
hs_ok(strpos($css, '.pdx-account-avatar--header') !== false && strpos($css, '24px') !== false, 'header avatar is scaled to 24px');
hs_ok(strpos($css, '.pdx-account-level-badge--header') !== false && strpos($css, 'font-size: 9px !important') !== false, 'level badge is secondary to the name');

hs_ok($js === $overlay_js, 'overlay pax-auth.js matches plugin');
hs_ok(strpos($js, '#dtr-header-global .dtr-header-global-content') !== false, 'auth bar mounts inside the glass header row');
hs_ok(strpos($js, 'pdx-auth-menu--apple') !== false, 'Apple profile dropdown markup is unchanged');
hs_ok(strpos($auth_css, 'pdx-auth-menu--apple') !== false, 'Apple profile dropdown CSS is unchanged');

hs_ok(strpos($plugin, "PAXDESIGN_BOOKING_VERSION', '3.174.128'") !== false, 'plugin baseline remains 3.174.128');
hs_ok(strpos($chat, 'Version: 3.174.128') !== false, 'chat JS remains 3.174.128');
hs_ok(strpos($chat, 'skipping stacked sync') === false, 'chat is not the 3.176 rewrite');
hs_ok(strpos($chat, 'Gespräch beenden') === false, 'chat has no Gespräch beenden');

hs_ok(is_file($root . '/.github/workflows/deploy-header-stable.yml'), 'surgical header deploy workflow exists');
hs_ok($workflow && strpos($workflow, 'rsync --delete') === false, 'header deploy does not rsync --delete');
hs_ok($workflow && strpos($workflow, 'apple-header-stable.css') !== false, 'header deploy copies the stable header CSS');
hs_ok($workflow && strpos($workflow, 'no iOS build') !== false, 'header deploy documents no iOS build');

if ($fail) {
	fwrite(STDERR, "$fail header-stable assertion(s) failed\n");
	exit(1);
}
echo "Header stability guards passed.\n";
