<?php
/**
 * Guards for the Apple-style account / profile redesign.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function aau_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$js = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
$overlay_js = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/customer-auth/js/pax-auth.js');
$css = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/css/pdx-account-app.css');
$auth_css = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/css/pdx-auth.css');
$overlay_css = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/customer-auth/css/pdx-auth.css');
$l10n = file_get_contents($root . '/paxdesign-booking/includes/customer/data/account-ui-l10n.php');
$plugin = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');
$workflow = file_get_contents($root . '/.github/workflows/deploy-account-apple-ui.yml');
$homepage = file_get_contents($root . '/navein/assets/css/apple-homepage.css');
$chat = file_get_contents($root . '/paxdesign-booking/assets/js/chat-script.js');

aau_ok($js === $overlay_js, 'overlay pax-auth.js matches plugin');
aau_ok($auth_css === $overlay_css, 'overlay pdx-auth.css matches plugin');
aau_ok(strpos($plugin, "PAXDESIGN_BOOKING_VERSION', '3.174.128'") !== false, 'plugin baseline remains 3.174.128');
aau_ok(strpos($chat, 'Version: 3.174.128') !== false, 'chat JS remains 3.174.128');
aau_ok(strpos($chat, 'skipping stacked sync') === false, 'chat is not the 3.176 rewrite');
aau_ok(strpos($chat, 'Gespräch beenden') === false, 'chat has no Gespräch beenden');

aau_ok(strpos($js, "id: 'preferences'") !== false, 'account nav includes notification preferences');
aau_ok(strpos($js, "t('nav_security', 'Security & Privacy')") !== false, 'security nav is Security & Privacy');
aau_ok(strpos($js, "t('nav_settings', 'Account Settings')") !== false, 'settings nav is Account Settings');
aau_ok(strpos($js, "t('nav_notifications', 'Notifications')") !== false, 'alerts nav is Notifications');
aau_ok(strpos($js, 'pdx-account-sidebar-profile') !== false, 'sidebar identity is a single profile control');
aau_ok(strpos($js, 'pdx-account-sidebar-name') !== false, 'username remains in the sidebar identity');
aau_ok(strpos($js, 'function renderAccountPreferencesSection') !== false, 'notification preferences is its own section');
aau_ok(strpos($js, 'function renderAppleGroup') !== false, 'Apple grouped-list helper exists');
aau_ok(strpos($js, 'pdx-profile-overlay--apple') !== false, 'profile overlay uses the Apple sheet');

$personal_fn = null;
if (preg_match('/function renderAccountPersonalSection\([\s\S]*?\n  function /', $js, $m)) {
	$personal_fn = $m[0];
}
aau_ok(is_string($personal_fn), 'personal section renderer exists');
aau_ok($personal_fn && strpos($personal_fn, 'pdx-account-profile-name') === false, 'personal section does not repeat the username heading');
aau_ok($personal_fn && strpos($personal_fn, 'pdx-account-photo-hero') !== false, 'personal section uses a photo hero instead of a second identity chrome');

aau_ok(strpos($css, '.pdx-apple-group') !== false, 'account CSS has Apple grouped lists');
aau_ok(strpos($css, '.pdx-apple-row') !== false, 'account CSS has Apple rows');
aau_ok(strpos($css, 'background: #f5f5f7') !== false, 'account main uses Apple gray canvas');
aau_ok(strpos($css, '.pdx-account-nav-btn.is-active') !== false && strpos($css, 'background: #e8e8ed') !== false, 'selected nav uses Apple gray, not yellow/blue chips');
aau_ok(strpos($auth_css, 'pdx-profile-overlay--apple') !== false, 'auth CSS restyles the profile overlay');

aau_ok(strpos($l10n, "'nav_preferences'") !== false, 'l10n includes notification preferences');
aau_ok(strpos($l10n, 'Sicherheit & Datenschutz') !== false, 'German security label is updated');
aau_ok(strpos($l10n, 'Kontoeinstellungen') !== false, 'German account settings label is updated');
aau_ok(strpos($l10n, 'Mitteilungen') !== false, 'German notifications label is updated');

aau_ok(strpos($homepage, '--ph-display: "Orbitron"') !== false || strpos($homepage, '--ph-display:"Orbitron"') !== false || strpos($homepage, '"Orbitron"') !== false, 'homepage Orbitron display font is unchanged');
aau_ok(is_file($root . '/navein/assets/css/orbitron-display-fonts.css'), 'Orbitron heading stylesheet remains');

aau_ok(is_file($root . '/.github/workflows/deploy-account-apple-ui.yml'), 'surgical account UI deploy workflow exists');
aau_ok($workflow && strpos($workflow, 'rsync --delete') === false, 'account deploy does not rsync --delete');
aau_ok($workflow && strpos($workflow, 'pax-auth.js') !== false, 'account deploy copies pax-auth.js');
aau_ok($workflow && strpos($workflow, 'pdx-account-app.css') !== false, 'account deploy copies account CSS');
aau_ok($workflow && strpos($workflow, 'no iOS build') !== false, 'account deploy documents no iOS build');

if ($fail) {
	fwrite(STDERR, "$fail account-apple-ui assertion(s) failed\n");
	exit(1);
}
echo "Account Apple UI guards passed.\n";
