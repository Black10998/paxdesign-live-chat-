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
hs_ok(preg_match('/Version:\\s*1\\.4\\.(\\d+)/', $style, $v) === 1 && (int) $v[1] >= 60, 'theme version is cache-busted to 1.4.60+');

hs_ok(strpos($css, 'dtr-search-modal-trigger') !== false, 'Search trigger is restyled');
hs_ok(strpos($css, 'border-left: 0.5px solid') !== false, 'Search is separated from the nav with a hairline');
hs_ok(strpos($css, 'max-height: var(--dtr-apple-header-height)') !== false, 'header height is locked');
hs_ok(strpos($css, 'flex-wrap: nowrap') !== false, 'header row cannot wrap onto a second line');
hs_ok(strpos($css, 'pdx-auth-bar--menu-open') !== false, 'open profile menu cannot change header height');
hs_ok(strpos($css, 'pdx-auth-menu--apple') !== false && strpos($css, 'position: fixed') !== false, 'profile dropdown stays a fixed overlay');
hs_ok(preg_match('/#dtr-header-global \\.main-navigation(?:\\.ms-auto)? \\{[^}]*overflow:\\s*visible/', $css) === 1, 'nav overflow is visible so mega menus can paint');
hs_ok(preg_match('/#dtr-header-global \\.main-navigation \\{[^}]*overflow:\\s*hidden/', $css) !== 1, 'nav does not clip labels or hover dropdowns');
hs_ok(strpos($css, 'cybercrime-menu') !== false && strpos($css, 'white-space: nowrap') !== false, 'Cybercrime Support label cannot wrap or clip');
hs_ok(strpos($css, 'dtr-has-mega') !== false && strpos($css, 'dtr-mega-panel') !== false, 'mega-menu panels stay unclipped');
hs_ok(strpos($css, 'min-width: 0 !important') !== false && strpos($css, 'min-width: max-content') === false, 'nav can shrink instead of overlapping utilities');
hs_ok(strpos($css, 'margin-right: 0 !important') !== false, 'ms-auto no longer pushes nav into the utility cluster');
hs_ok(strpos($css, 'flex-shrink: 0 !important') !== false, 'Search, CTA, and auth never shrink under the nav');
hs_ok(strpos($css, 'a.dtr-btn.dtr-header-btn') !== false && strpos($css, '--dtr-apple-header-control') !== false, 'Angebot anfordern is a compact Apple CTA');
hs_ok(strpos($css, '.dtr-header-btn .dtr-btn__icon') !== false && strpos($css, 'display: none !important') !== false, 'CTA theme icon is hidden so the pill stays compact');
hs_ok(strpos($css, 'flex-direction: row !important') !== false && strpos($css, '.pdx-header-user-text') !== false, 'logged-in identity stays on one horizontal row');
hs_ok(strpos($css, '.pdx-header-user-name') !== false && strpos($css, 'font-size: 12px !important') !== false, 'logged-in name is reduced to 12px');
hs_ok(strpos($css, '.pdx-account-avatar--header') !== false && strpos($css, '24px') !== false, 'header avatar is scaled to 24px');
hs_ok(strpos($css, '.pdx-account-level-badge--header') !== false && strpos($css, 'background-image: none !important') !== false, 'header level badge drops unreadable gold gradient');
hs_ok(strpos($css, '.pdx-account-level-badge--header') !== false && strpos($css, 'color: #3a3a3c !important') !== false, 'header level badge has readable gray contrast');
hs_ok(strpos($css, 'position: relative !important') !== false && strpos($css, '#dtr-header-global #pdx-auth-bar') !== false, 'desktop auth bar stays in the header flex row');

hs_ok(strpos($functions, 'navein_apple_header_desktop_cascade_footer') !== false, 'functions.php prints final desktop header cascade CSS');
hs_ok(strpos($functions, 'navein-apple-header-desktop-cascade') !== false, 'desktop cascade style id is registered');
hs_ok(strpos($functions, 'position:relative!important') !== false || strpos($functions, "position\",\"relative\",\"important\"") !== false, 'desktop cascade resets fixed auth positioning');

hs_ok(strpos($css, '--dtr-apple-header-util-min') !== false, 'header reserves fixed width for Search, CTA, and auth');
hs_ok(strpos($css, 'justify-self: end') !== false, 'utility cluster stays in its own grid column');
hs_ok(strpos($css, '--dtr-apple-header-nav-gap') !== false, 'nav and utilities keep an explicit separator gap');
hs_ok(strpos($functions, 'navein_apple_header_utility_cluster_ob_start') !== false, 'header HTML wraps utilities in cluster server-side');
hs_ok(strpos($functions, 'navein-apple-header-utility-cluster-head') !== false, 'utility cluster is grouped before deferred auth JS');
hs_ok(strpos($css, 'dtr-header-global-content > .dtr-search-modal-trigger') !== false, 'loose utilities are hidden until clustered');
hs_ok(strpos($css, 'grid-template-columns') !== false && strpos($css, 'grid-area: util') !== false, 'desktop header uses isolated grid columns');
hs_ok(strpos($css, 'pdx-auth-bar--logged-out') !== false && strpos($css, '.pdx-auth-portal-btn') !== false, 'Customer Portal is hidden before login in the header bar');
hs_ok(strpos($css, '@media (max-width: 992px)') !== false && strpos($css, '#dtr-main-header') !== false && strpos($css, 'padding-top: 0 !important') !== false, 'mobile removes the blank bar under the header');

hs_ok(strpos($js, 'ensureHeaderUtilityCluster') !== false, 'pax-auth.js groups Search, CTA, and auth into a utility cluster');
hs_ok(strpos($js, 'syncHeaderAuthControls') !== false, 'pax-auth.js syncs header controls to auth state');
hs_ok(strpos($js, 'signupBtn.remove()') !== false, 'Anmelden button is removed from DOM when logged in');
hs_ok(strpos($css, 'pdx-auth-bar--logged-in .pdx-auth-signup-btn') !== false, 'logged-in CSS never styles the signup button as visible');
hs_ok(strpos($functions, 'pdx-auth-bar--logged-in .pdx-auth-signup-btn') !== false, 'footer cascade hides signup when logged in');
hs_ok(strpos($functions, 'pdx-auth-bar--logged-out .pdx-auth-signup-btn') !== false, 'footer cascade styles signup only when logged out');
hs_ok(strpos($js, 'pdx-auth-portal-btn') !== false && strpos($js, "node.remove()") !== false, 'Customer Portal never appears as a header pill');
hs_ok(strpos($functions, 'dtr-header-utility-cluster') !== false, 'footer cascade keeps the desktop utility cluster layout');
hs_ok(strpos($js, "authBar.closest('#dtr-header-global')") !== false || strpos($js, 'authBar.closest("#dtr-header-global")') !== false, 'desktop auth reset only applies inside the glass header');

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
