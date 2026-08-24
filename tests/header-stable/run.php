<?php
/**
 * Guards for the restored Apple header row.
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
hs_ok(preg_match('/Version:\\s*1\\.4\\.(\\d+)/', $style, $v) === 1 && (int) $v[1] >= 63, 'theme version is cache-busted to 1.4.63+');

hs_ok(strpos($css, 'dtr-search-modal-trigger') !== false, 'Search trigger is restyled');
hs_ok(strpos($css, 'border-left: 0.5px solid') !== false, 'Search cluster is separated from the nav with a hairline');
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

hs_ok(strpos($css, 'display: flex !important') !== false && strpos($css, '.dtr-header-global-content') !== false, 'header content uses the original flex row');
hs_ok(strpos($css, 'grid-template-columns: none !important') !== false, 'header container is not a 3-column grid');
hs_ok(strpos($css, '--dtr-apple-header-util-min') === false, 'header does not reserve a 320-440px empty utility column');
hs_ok(strpos($css, '.dtr-nav-lead-icon') !== false && strpos($css, 'display: none !important') !== false, 'top-level lead icons are hidden so names stay uncluttered');
hs_ok(strpos($css, '-webkit-mask:') !== false, 'Search uses a single SVG mask icon');
hs_ok(strpos($css, '.dtr-header-utility-cluster') !== false && strpos($css, 'margin-left: auto') !== false, 'desktop utility cluster keeps Search, CTA, and auth on the right');
hs_ok(strpos($css, '#pax-site-lang') !== false, 'language switcher is part of the stable header cluster');
hs_ok(strpos($functions, 'navein_site_lang_switcher_markup') !== false, 'language switcher is injected server-side');

hs_ok(strpos($functions, 'navein_apple_header_desktop_cascade_footer') !== false, 'functions.php prints final desktop header cascade CSS');
hs_ok(strpos($functions, 'navein-apple-header-desktop-cascade') !== false, 'desktop cascade style id is registered');
hs_ok(strpos($functions, 'position:relative!important') !== false, 'desktop cascade resets fixed auth positioning');
hs_ok(strpos($functions, 'navein_apple_header_utility_cluster_ob_start') !== false, 'header HTML wraps utilities in cluster server-side');
hs_ok(strpos($functions, 'navein_apple_header_reserved_auth_markup') !== false, 'header HTML reserves the Anmelden slot before JS');
hs_ok(strpos($functions, 'data-pdx-header-slot') !== false, 'reserved auth slot is marked in the HTML');
hs_ok(strpos($functions, 'MutationObserver') === false || strpos($functions, 'No MutationObserver') !== false, 'functions.php does not install a header MutationObserver');
hs_ok(strpos($functions, 'setProperty') === false, 'functions.php does not write inline header styles');
hs_ok(strpos($functions, 'navein-apple-header-utility-cluster-head') === false, 'head script that mutates the header after paint is gone');
hs_ok(strpos($css, 'pdx-auth-bar--logged-out') !== false && strpos($css, '.pdx-auth-portal-btn') !== false, 'Customer Portal is hidden before login in the header bar');
hs_ok(strpos($css, '@media (max-width: 992px)') !== false && strpos($css, '#dtr-main-header') !== false && strpos($css, 'padding-top: 0 !important') !== false, 'mobile removes the blank bar under the header');

hs_ok(strpos($js, 'ensureHeaderUtilityCluster') !== false, 'pax-auth.js can group Search, CTA, and auth into a utility cluster');
hs_ok(strpos($js, 'hydrateExistingAuthBar') !== false, 'pax-auth.js hydrates the reserved header auth slot');
hs_ok(strpos($js, 'syncHeaderAuthControls') !== false, 'pax-auth.js syncs header controls to auth state');
hs_ok(strpos($js, 'signupBtn.remove()') !== false, 'Anmelden button is removed from DOM when logged in');
hs_ok(strpos($css, 'pdx-auth-bar--logged-in .pdx-auth-signup-btn') !== false, 'logged-in CSS never styles the signup button as visible');
hs_ok(strpos($functions, 'pdx-auth-bar--logged-in .pdx-auth-signup-btn') !== false, 'footer cascade hides signup when logged in');
hs_ok(strpos($functions, 'pdx-auth-bar--logged-out .pdx-auth-signup-btn') !== false, 'footer cascade styles signup only when logged out');
hs_ok(strpos($js, 'pdx-auth-portal-btn') !== false && strpos($js, "node.remove()") !== false, 'Customer Portal never appears as a header pill');
hs_ok(strpos($functions, 'dtr-header-utility-cluster') !== false, 'footer cascade keeps the desktop utility cluster layout');
hs_ok(strpos($js, "authBar.parentNode !== mount") !== false, 'auth bar is not remounted when it is already in the cluster');

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

$sample_mobile = '<header><div id="dtr-responsive-header"><div class="container">'
	. '<a class="dtr-logo logo-default" href="/"> </a>'
	. '<button id="dtr-menu-button" class="dtr-hamburger" type="button"></button>'
	. '</div></div></header>';
$sample_actions = '<div id="pax-site-lang-mobile"></div>';
$sample_injected = preg_replace(
	'#(<div id="dtr-responsive-header"[\s\S]*?<div class="container">[\s\S]*?)(<button[^>]*id="dtr-menu-button")#',
	'$1' . $sample_actions . '$2',
	$sample_mobile,
	1
);
hs_ok(is_string($sample_injected) && strpos($sample_injected, 'pax-site-lang-mobile') !== false, 'mobile injection regex matches the live header skeleton');
hs_ok(is_string($sample_injected) && strpos($sample_injected, 'id="dtr-responsive-header"><div id="pax-site-lang-mobile"') === false, 'injected language control stays inside .container');
$lang_at = is_string($sample_injected) ? strpos($sample_injected, 'pax-site-lang-mobile') : false;
$btn_at = is_string($sample_injected) ? strpos($sample_injected, 'id="dtr-menu-button"') : false;
hs_ok($lang_at !== false && $btn_at !== false && $lang_at < $btn_at, 'language sits before the hamburger');
hs_ok(strpos($functions, 'dtr-search-modal-trigger--mobile') === false, 'mobile header does not inject a Search control');
hs_ok(strpos($css, '#dtr-responsive-header .dtr-search-modal-trigger') !== false && strpos($css, 'pointer-events: none !important') !== false, 'mobile Search is hidden and cannot collide with the header');
hs_ok(strpos($css, 'direction: ltr !important') !== false && strpos($css, 'flex-shrink: 0 !important') !== false, 'logo stays LTR and cannot shrink away in Arabic');
hs_ok(strpos($css, 'width: var(--paxlogo-mark-w, 118px)') !== false, 'mobile wordmark uses an explicit width so it cannot collapse');
hs_ok(strpos($functions, 'width:var(--paxlogo-mark-w,118px)') !== false, 'footer cascade keeps the mobile wordmark width explicit');
hs_ok(preg_match('/#dtr-responsive-header \\.dtr-logo svg,\\s*\\n\\s*html body\\.dtr-apple-sticky-header #dtr-responsive-header \\.dtr-logo \\.paxlogo-wrap/', $css) !== 1, 'mobile logo no longer shares width:auto with every inner svg');
hs_ok(strpos($functions, '#(<div id="dtr-responsive-header"[^>]*>)#') === false, 'mobile language is not injected as a sibling of .container');
hs_ok(strpos($css, 'margin-inline-end: auto') !== false, 'mobile logo keeps actions on the trailing edge');
hs_ok(strpos($css, '#dtr-menu-button.dtr-hamburger') !== false && strpos($css, 'margin-top: 0 !important') !== false, 'mobile hamburger stays in the flex row');
hs_ok(strpos($css, '#pax-site-lang-mobile') !== false && strpos($css, 'order: 2') !== false, 'mobile language control has a flex order');
hs_ok(strpos($functions, 'margin:0 6px 0 auto') === false, 'footer cascade no longer steals margin-left auto for the language button');
hs_ok(strpos($css, 'dtr-apple-mnav-open') !== false && strpos($functions, 'dtr-apple-mnav-open') !== false, 'open mobile nav hides Sign In so it cannot cover the close control');

if ($fail) {
	fwrite(STDERR, "$fail header-stable assertion(s) failed\n");
	exit(1);
}
echo "Header stability guards passed.\n";
