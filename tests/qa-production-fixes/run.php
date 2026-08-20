<?php
/**
 * Guards for production QA fixes that must land without an iOS rebuild.
 */

$root = dirname(__DIR__, 2);
$fail = 0;

function qa_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$services = file_get_contents($root . '/paxdesign-booking/includes/customer/class-paxdesign-customer-services.php');
$portfolio = file_get_contents($root . '/paxdesign-booking/includes/customer/class-paxdesign-customer-portfolio.php');
$showcase = file_get_contents($root . '/paxdesign-booking/includes/customer/class-paxdesign-customer-portfolio-showcase.php');
$content = file_get_contents($root . '/paxdesign-booking/includes/customer/class-paxdesign-customer-content.php');
$homepage = file_get_contents($root . '/navein/template-parts/pages/homepage.php');
$homepage_data = file_get_contents($root . '/paxdesign-booking/includes/customer/data/homepage-data.php');
$theme_fixes = file_get_contents($root . '/navein/inc/qa-production-fixes.php');
$htaccess = file_get_contents($root . '/scripts/patch-wp-htaccess-security.sh');
$widget = file_get_contents($root . '/paxdesign-booking/templates/booking-widget.php');
$chat = file_get_contents($root . '/paxdesign-booking/includes/class-paxdesign-chat.php');
$chat_js = file_get_contents($root . '/paxdesign-booking/assets/js/chat-script.js');
$auth_js = file_get_contents($root . '/paxdesign-booking/assets/customer-auth/js/pax-auth.js');
$auth_page = file_get_contents($root . '/paxdesign-booking/includes/auth/class-paxdesign-auth-page.php');
$footer_css = file_get_contents($root . '/navein/assets/css/apple-footer.css');
$notfound = file_get_contents($root . '/navein/404.php');
$boot = file_get_contents($root . '/paxdesign-booking/paxdesign-booking.php');

qa_ok(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.128')") !== false, 'plugin version stays 3.174.128');
qa_ok(strpos($services, 'services_from_booking_catalog') !== false, 'customer services fall back to booking catalog');
qa_ok(strpos($services, 'active_service_count') !== false, 'seed flag is not set on an empty table');
qa_ok(strpos($services, "add_action('plugins_loaded'") !== false, 'service seed retries after schema install');
qa_ok(strpos($portfolio, 'function list_wordpress_items') !== false, 'portfolio exposes WordPress CPT list');
qa_ok(strpos($portfolio, 'list_wordpress_items($limit, $category)') !== false, 'portfolio prefers WordPress items before JSON seed');
qa_ok(strpos($showcase, 'list_wordpress_items(200, \'\')') !== false, 'showcase payload uses WordPress items when published');
qa_ok(strpos($showcase, 'function json_payload') !== false, 'JSON seed remains available as fallback');
qa_ok(strpos($content, "'de' => 'Leistungen'") !== false, 'navigation services title is Leistungen in German');
qa_ok(strpos($content, 'localized_section_title($key, (string) $config[\'title\'], $lang)') !== false, 'navigation titles honor request lang');
qa_ok(strpos($homepage, "home_url( '/projektpreise/' )") === false, 'homepage Alle Leistungen does not use /projektpreise/');
qa_ok(strpos($homepage, "home_url( '/preise/' )") !== false, 'homepage services link to /preise/');
qa_ok(strpos($homepage, '+43 681 2054 3638') !== false, 'homepage phone uses spaced contact format');
qa_ok(strpos($homepage_data, 'Menschen, Handwerk und Haltung hinter PAXdesign') !== false, 'homepage about_teaser has a German subtitle');
qa_ok(strpos($theme_fixes, "'projektpreise'") !== false && strpos($theme_fixes, "'team'") !== false, 'broken marketing paths redirect');
qa_ok(strpos($theme_fixes, "'en'") !== false && strpos($theme_fixes, "'ar'") !== false, '/en/ and /ar/ redirect to the German homepage');
qa_ok(strpos($theme_fixes, 'cybercrime-support') !== false, 'CCS nav slug is rewritten to a readable label');
qa_ok(strpos($theme_fixes, 'bei uns') !== false, 'Karriere document title grammar is corrected');
qa_ok(strpos($theme_fixes, 'camera=(self), microphone=(self)') !== false, 'PHP sends a same-origin Permissions-Policy');
qa_ok(strpos($htaccess, 'Permissions-Policy') !== false, 'htaccess security block sets Permissions-Policy');
qa_ok(strpos($widget, 'Weiter zum Live-Chat') !== false, 'chat gate title is German');
qa_ok(strpos($widget, 'Support-Nachricht') !== false, 'chat launcher label is German');
qa_ok(strpos($widget, '+43 681 2054 3638') !== false, 'booking phone placeholder uses the live contact number');
qa_ok(strpos($footer_css, '#appstore-popup') !== false, 'mobile App Store badge is lifted above Support Message');
$footer_js = file_get_contents($root . '/navein/assets/js/apple-footer.js');
qa_ok(strpos($footer_js, 'projektpreise') !== false, 'footer JS rewrites stale /projektpreise/ links');
qa_ok(strpos($theme_fixes, 'pax_qa_rewrite_legacy_markup') !== false, 'stored footer HTML rewrites stale marketing URLs');
qa_ok(strpos($chat, 'Weiter zum Live-Chat') !== false, 'chat JS authGate title is German');
qa_ok(strpos($chat_js, 'Mit GitHub anmelden') !== false, 'chat JS GitHub button uses German copy');
qa_ok(strpos($auth_js, 'htmlLang') !== false, 'account UI prefers document language over navigator');
qa_ok(strpos($auth_js, "t('sign_in', 'Anmelden')") !== false, 'header Sign In uses localized German fallback');
qa_ok(strpos($auth_page, 'Konto erstellen') !== false, 'account page first paint is German');
qa_ok(strpos($footer_css, '#appstore-popup') !== false, 'mobile App Store badge is lifted above Support Message');
qa_ok(strpos($notfound, 'Seite nicht gefunden') !== false, 'theme 404 is German');
qa_ok(strpos($boot, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'CCS AI workflow is still not loaded');

if ($fail > 0) {
    fwrite(STDERR, "$fail qa-production-fixes assertion(s) failed\n");
    exit(1);
}

echo "All qa-production-fixes checks passed\n";
