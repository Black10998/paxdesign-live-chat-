<?php
/**
 * Guards for site-wide locale (de/en/ar/tr), Apple language switcher, and RTL.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function i18n_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$engine = file_get_contents($root . '/navein/inc/site-i18n.php');
$strings = file_get_contents($root . '/navein/inc/site-i18n-strings.php');
$js = file_get_contents($root . '/navein/assets/js/apple-site-i18n.js');
$css = file_get_contents($root . '/navein/assets/css/apple-site-rtl.css');
$header = file_get_contents($root . '/navein/assets/css/apple-header-stable.css');
$functions = file_get_contents($root . '/navein/functions.php');
$style = file_get_contents($root . '/navein/style.css');
$home = file_get_contents($root . '/navein/template-parts/pages/homepage.php');
$auth = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
$overlay_auth = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/assets/customer-auth/js/pax-auth.js');
$routing = file_get_contents($root . '/paxdesign-booking/includes/class-paxdesign-language-routing.php');
$chat = file_get_contents($root . '/paxdesign-booking/assets/js/chat-script.js');
$plugin = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');
$ccs_js = file_get_contents($root . '/navein/assets/js/apple-cybercrime-support.js');
$ccs_php = file_get_contents($root . '/navein/template-parts/pages/cybercrime-support.php');
$workflow = file_get_contents($root . '/.github/workflows/deploy-site-i18n.yml');

i18n_ok(is_file($root . '/navein/inc/site-i18n.php'), 'site i18n engine exists');
i18n_ok(is_file($root . '/navein/inc/site-i18n-strings.php'), 'site i18n strings exist');
i18n_ok(is_file($root . '/navein/assets/js/apple-site-i18n.js'), 'site i18n script exists');
i18n_ok(is_file($root . '/navein/assets/css/apple-site-rtl.css'), 'RTL stylesheet exists');
i18n_ok(strpos($engine, "'de', 'en', 'ar', 'tr'") !== false, 'supported languages include de/en/ar/tr');
i18n_ok(strpos($engine, 'pax_site_lang') !== false, 'language cookie is pax_site_lang');
i18n_ok(strpos($engine, 'pax_site_lang_src') !== false, 'manual/auto source cookie exists');
i18n_ok(strpos($engine, 'navein_site_lang_switcher_markup') !== false, 'Apple language switcher markup is server-rendered');
i18n_ok(strpos($js, 'pax_site_lang_src') !== false && strpos($js, 'manual') !== false, 'JS remembers a manual language choice');
i18n_ok(strpos($js, 'navigator.languages') !== false, 'JS auto-detects browser language');
i18n_ok(strpos($css, 'pax-site-lang__btn') !== false, 'Apple language button styles exist');
i18n_ok(strpos($css, 'pax-site-lang__menu') !== false, 'Apple language popover styles exist');
i18n_ok(strpos($css, 'border-right: 0.5px solid') !== false, 'RTL header cluster is mirrored');
i18n_ok(strpos($header, '#pax-site-lang') !== false, 'stable header CSS includes the language control');
i18n_ok(strpos($functions, 'navein-apple-site-i18n') !== false, 'functions.php enqueues the language script');
i18n_ok(strpos($functions, 'navein-apple-site-rtl') !== false, 'functions.php enqueues RTL CSS');
i18n_ok(strpos($functions, 'pax-site-lang-mobile') !== false, 'mobile header receives a language control');
i18n_ok(strpos($functions, 'language_attributes') !== false, 'html lang/dir are filtered');
i18n_ok(preg_match('/Version:\\s*1\\.4\\.(\\d+)/', $style, $v) === 1 && (int) $v[1] >= 63, 'theme version is cache-busted to 1.4.63+');
i18n_ok(strpos($home, "navein_t( 'home_hero_title'") !== false, 'homepage hero is localized');
i18n_ok(strpos($strings, "'ar' => 'الأسعار'") !== false, 'Arabic nav pricing translation exists');
i18n_ok(strpos($strings, "'tr' => 'Fiyatlar'") !== false, 'Turkish nav pricing translation exists');
i18n_ok(strpos($auth, "c === 'tr'") !== false, 'account JS recognizes Turkish');
i18n_ok(strpos($auth, 'data-pax-set-lang') !== false, 'account settings can change language');
i18n_ok($auth === $overlay_auth, 'overlay pax-auth.js matches plugin');
i18n_ok(strpos($routing, "'de', 'en', 'ar', 'tr'") !== false, 'chat language routing includes Turkish');
i18n_ok(strpos($chat, "htmlLang.indexOf('tr')") !== false, 'chat widget detects Turkish');
i18n_ok(strpos($plugin, "PAXDESIGN_BOOKING_VERSION', '3.174.128'") !== false, 'plugin baseline remains 3.174.128');
i18n_ok(strpos($chat, 'Version: 3.174.128') !== false, 'chat remains 3.174.128');
i18n_ok(strpos($chat, 'skipping stacked sync') === false, 'chat is not the 3.176 rewrite');
i18n_ok(strpos($chat, 'Gespräch beenden') === false, 'chat has no Gespräch beenden');
i18n_ok(strpos($engine, 'navein_site_i18n_apply_pairs_outside_skips') !== false, 'chrome rewrite skips scripts without full-document PCRE');
i18n_ok(strpos($engine, '$item[\'label\']') !== false, 'language badges use the current-language label');
i18n_ok(strpos($engine, "'code'   => 'de'") !== false && strpos($engine, "'code'   => 'ar'") !== false && strpos($engine, "'code'   => 'tr'") !== false, 'switcher lists DE/EN/AR/TR badges');
i18n_ok(strpos($js, "lang === 'tr' ? 'en'") === false, 'site i18n does not map Turkish CCS to English');
i18n_ok(strpos($ccs_js, "lang === 'de' || lang === 'en' ? lang : 'ar'") === false, 'CCS getLang no longer maps Turkish to Arabic');
i18n_ok(strpos($ccs_js, "lang === 'tr' || lang === 'ar'") !== false, 'CCS getLang keeps Turkish');
i18n_ok(is_file($root . '/navein/template-parts/pages/cybercrime-support-tr.php'), 'Turkish CCS overlay exists');
$ccs_tr = file_get_contents($root . '/navein/template-parts/pages/cybercrime-support-tr.php');
i18n_ok(strpos($ccs_tr, 'Bilgiler toplanıyor') !== false && strpos($ccs_tr, 'Kapalı') !== false, 'Turkish CCS status badges exist');
i18n_ok(strpos($ccs_php, 'pax_ccs_data_lang_attrs') !== false, 'CCS inputs carry AR/DE/EN/TR data attributes');
i18n_ok(strpos($engine, "'svg'") !== false, 'chrome rewrite skips SVG so the logo is untouched');
i18n_ok(strpos($js, '.dtr-logo') !== false && strpos($js, 'svg') !== false, 'JS walker skips the header logo');
i18n_ok($workflow && strpos($workflow, 'cybercrime-support-tr.php') !== false, 'i18n deploy copies Turkish CCS overlay');
i18n_ok(strpos($ccs_php, 'data-ccs-switch="tr"') !== false, 'CCS language bar includes Turkish');
i18n_ok(strpos($functions, 'MutationObserver') === false || strpos($functions, 'No MutationObserver') !== false, 'functions.php does not install a header MutationObserver');
i18n_ok(strpos($functions, 'grid-template-columns:none') !== false, 'header cascade still disables the collapsing grid');
i18n_ok(is_file($root . '/.github/workflows/deploy-site-i18n.yml'), 'surgical i18n deploy workflow exists');
i18n_ok($workflow && strpos($workflow, 'rsync --delete') === false, 'i18n deploy does not rsync --delete');
i18n_ok($workflow && strpos($workflow, '3.176') !== false, 'i18n deploy documents no 3.176 chat');

define( 'ABSPATH', '/' );
require $root . '/navein/inc/site-i18n.php';
i18n_ok(navein_site_i18n_detect_accept_language('ar-SA,ar;q=0.9,en;q=0.8') === 'ar', 'Accept-Language ar-SA resolves to ar');
i18n_ok(navein_site_i18n_detect_accept_language('tr-TR,tr;q=0.9') === 'tr', 'Accept-Language tr-TR resolves to tr');
i18n_ok(navein_site_i18n_detect_accept_language('en-US,en;q=0.9') === 'en', 'Accept-Language en-US resolves to en');
i18n_ok(navein_site_i18n_detect_accept_language('de-AT,de;q=0.9') === 'de', 'Accept-Language de-AT resolves to de');
i18n_ok(navein_site_i18n_normalize('xx') === '', 'unsupported language codes are rejected');
i18n_ok(navein_site_i18n_normalize('AR') === 'ar', 'AR normalizes to ar');
i18n_ok(navein_t('nav_pricing', 'Preise', 'ar') === 'الأسعار', 'navein_t returns Arabic pricing');
i18n_ok(navein_t('sign_in', 'Anmelden', 'tr') === 'Giriş yap', 'navein_t returns Turkish sign-in');
i18n_ok(navein_t('lang_ar', 'Arabic', 'de') === 'Arabisch', 'language badge Deutsch→Arabisch');
i18n_ok(navein_t('lang_tr', 'Turkish', 'ar') === 'التركية', 'language badge Arabic→Turkish');

$_GET['lang'] = 'en';
$payload = str_repeat('<script type="application/json">{"cta":"Angebot anfordern"}</script>', 400);
$sample  = '<!doctype html><html><body><header><a class="dtr-header-btn"><span class="dtr-btn__text">Angebot anfordern</span></a><nav>Preise</nav></header>' . $payload . '<footer>Impressum</footer></body></html>';
$rewritten = navein_site_i18n_replace_chrome($sample);
i18n_ok(is_string($rewritten) && strlen($rewritten) > 10000, 'chrome replace never returns empty HTML');
i18n_ok(strpos($rewritten, '>Request a quote<') !== false, 'chrome replace translates the header CTA');
i18n_ok(strpos($rewritten, '>Pricing<') !== false, 'chrome replace translates nav pricing');
i18n_ok(substr_count($rewritten, '"cta":"Angebot anfordern"') === 400, 'chrome replace leaves script JSON alone');
$logo_html = '<svg class="paxlogo-svg"><text>Suche</text></svg><nav>Preise</nav>';
$logo_out = navein_site_i18n_replace_chrome($logo_html);
i18n_ok(is_string($logo_out) && strpos($logo_out, '>Suche<') !== false, 'chrome replace leaves SVG logo text alone');
i18n_ok(is_string($logo_out) && strpos($logo_out, '>Pricing<') !== false, 'chrome replace still translates nav outside SVG');
i18n_ok(substr_count($rewritten, '"cta":"Angebot anfordern"') === 400, 'chrome replace leaves script JSON alone');
i18n_ok(count(navein_site_i18n_chrome_phrases()) < count(navein_site_i18n_phrases()), 'chrome phrase pack is smaller than the full catalog');

if ($fail) {
	fwrite(STDERR, "$fail site-i18n assertion(s) failed\n");
	exit(1);
}
echo "Site i18n guards passed.\n";
