<?php
/**
 * Guards for the header profile dropdown (the popup from the name in the header).
 * This is not the /account page and not the account sidebar.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function hpm_ok($cond, $message) {
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
$css = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/css/pdx-auth.css');
$overlay_css = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/customer-auth/css/pdx-auth.css');
$plugin = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');
$chat = file_get_contents($root . '/paxdesign-booking/assets/js/chat-script.js');
$homepage = file_get_contents($root . '/navein/assets/css/apple-homepage.css');
$workflow = file_get_contents($root . '/.github/workflows/deploy-account-apple-ui.yml');

hpm_ok($js === $overlay_js, 'overlay pax-auth.js matches plugin');
hpm_ok($css === $overlay_css, 'overlay pdx-auth.css matches plugin');
hpm_ok(strpos($plugin, "PAXDESIGN_BOOKING_VERSION', '3.174.128'") !== false, 'plugin baseline remains 3.174.128');
hpm_ok(strpos($chat, 'Version: 3.174.128') !== false, 'chat JS remains 3.174.128');
hpm_ok(strpos($chat, 'skipping stacked sync') === false, 'chat is not the 3.176 rewrite');
hpm_ok(strpos($chat, 'Gespräch beenden') === false, 'chat has no Gespräch beenden');

hpm_ok(strpos($js, 'pdx-auth-menu--apple') !== false, 'header dropdown markup uses the Apple menu class');
hpm_ok(strpos($js, 'pdx-auth-menu-item__icon') !== false, 'header dropdown items render icons');
hpm_ok(strpos($js, 'pdx-auth-menu-item__chevron') !== false, 'header dropdown items render chevrons');
hpm_ok(strpos($js, 'pdx-auth-menu-footer') !== false, 'logout lives in a separated footer');
hpm_ok(strpos($js, 'function positionAuthMenu') !== false, 'header dropdown is positioned so the header cannot clip it');
hpm_ok(strpos($js, 'pdx-auth-menu-status--verified') !== false, 'verified status has its own class');
hpm_ok(strpos($js, "return accountStatusText(user.verified)") !== false, 'status label uses localized accountStatusText');

$create = null;
if (preg_match('/function createAuthBar\([\s\S]*?\n  function /', $js, $m)) {
	$create = $m[0];
}
hpm_ok(is_string($create), 'createAuthBar exists');
hpm_ok($create && strpos($create, "renderHeaderMenuItem('portal'") !== false, 'dropdown includes Kundenportal/portal');
hpm_ok($create && strpos($create, "renderHeaderMenuItem('profile'") !== false, 'dropdown includes Mein Profil');
hpm_ok($create && strpos($create, "renderHeaderMenuItem('account'") !== false, 'dropdown includes Mein Konto');
hpm_ok($create && strpos($create, "renderHeaderMenuItem('logout'") !== false, 'dropdown includes Abmelden');

hpm_ok(strpos($css, '#pdx-auth-bar.pdx-cx-shell .pdx-auth-menu.pdx-auth-menu--apple') !== false, 'auth CSS restyles the header dropdown with high specificity');
hpm_ok(strpos($css, '.pdx-auth-menu--apple .pdx-auth-menu-status--verified') !== false, 'verified status is a green Apple pill');
hpm_ok(strpos($css, '.pdx-auth-menu--apple .pdx-auth-menu-item--logout') !== false, 'logout row is restyled separately');
hpm_ok(preg_match('/pdx-auth-menu\\.pdx-auth-menu--apple\\s*\\{[^}]*background:\\s*#ffffff/', $css) === 1, 'Apple dropdown uses a white card');
hpm_ok(strpos($css, '#ffe0a6') === false || preg_match('/pdx-auth-menu--apple[\s\S]{0,800}#ffe0a6/', $css) !== 1, 'Apple dropdown does not reuse the gold status color');

hpm_ok(strpos($homepage, 'Orbitron') !== false, 'homepage Orbitron headings stay unchanged');
hpm_ok(is_file($root . '/navein/assets/css/orbitron-display-fonts.css'), 'Orbitron heading stylesheet remains');
hpm_ok($workflow && strpos($workflow, 'rsync --delete') === false, 'account deploy does not rsync --delete');
hpm_ok($workflow && strpos($workflow, 'pdx-auth-menu--apple') !== false, 'deploy workflow verifies the header dropdown class');
hpm_ok(is_file($root . '/tests/header-profile-menu/preview.html'), 'header dropdown preview fixture exists');

if ($fail) {
	fwrite(STDERR, "$fail header-profile-menu assertion(s) failed\n");
	exit(1);
}
echo "Header profile menu guards passed.\n";
