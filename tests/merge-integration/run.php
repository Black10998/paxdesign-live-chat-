<?php
/**
 * Merge-integration regression guard.
 *
 * Verifies the 12/08 UI + customer-levels restore coexists with main's
 * new Cybercrime CCS AI backend, and that paxdesign-toolbar stays gone.
 * Pure static verification (no DB / no WordPress runtime required).
 */

function mi_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 2);
$plugin = $root . '/paxdesign-booking';
$customer = $plugin . '/includes/customer';

echo "Merge-integration checks\n";

// --- PHP syntax of all restored + kept modules ---
foreach (glob($customer . '/*.php') as $file) {
    exec('php -l ' . escapeshellarg($file), $o, $c);
    mi_assert($c === 0, 'Syntax error in ' . basename($file));
}

// ---------------------------------------------------------------------------
// RESTORE: Customer Levels / VIP / Master Admin (from admin-customer-level-badge)
// ---------------------------------------------------------------------------
$levels = file_get_contents($customer . '/class-paxdesign-customer-levels.php');
mi_assert(strpos($levels, 'META_LEVEL') !== false, 'Customer levels must persist level meta');
mi_assert(strpos($levels, 'function profile_fields') !== false, 'Levels must expose profile_fields()');
mi_assert(strpos($levels, 'function level_for_user') !== false, 'Levels must resolve level for a user');

$defs = file_get_contents($customer . '/data/customer-level-definitions.php');
mi_assert(strpos($defs, 'metal') !== false, 'Level definitions must define metal tiers (Gold/etc.)');

$master = file_get_contents($customer . '/class-paxdesign-customer-master-admin.php');
mi_assert(strpos($master, 'function is_master_admin') !== false, 'Master admin gate must exist');

// ---------------------------------------------------------------------------
// FIX/INTEGRATION: level data must flow to the profile the frontend reads.
// get_profile() merges Avatar::profile_fields(), which must compose Levels.
// ---------------------------------------------------------------------------
$avatar = file_get_contents($customer . '/class-paxdesign-customer-avatar.php');
mi_assert(
    preg_match('/PAXdesign_Customer_Levels::profile_fields\(\s*\$user_id\s*\)/', $avatar) === 1,
    'Avatar::profile_fields must compose Levels::profile_fields so the badge has data'
);
$rest = file_get_contents($customer . '/class-paxdesign-customer-rest.php');
mi_assert(
    preg_match('/function get_profile\([^)]*\)\s*\{.*Customer_Avatar::profile_fields/s', $rest) === 1,
    'get_profile() must include avatar (level) fields in the profile response'
);
// Master admin selecting a VIP avatar must set the customer level.
mi_assert(strpos($rest, 'PAXdesign_Customer_Levels::set_level_for_user') !== false, 'VIP preset selection must set customer level');

// ---------------------------------------------------------------------------
// RESTORE: Username display + Level Badge in the account UI (pax-auth.js/css)
// ---------------------------------------------------------------------------
$auth_js = file_get_contents($plugin . '/assets/customer-auth/js/pax-auth.js');
mi_assert(strpos($auth_js, 'pdx-account-level-badge') !== false, 'Account JS must render the level badge');
mi_assert(strpos($auth_js, 'pdx-account-name-line') !== false, 'Account JS must render the username name line');
mi_assert(strpos($auth_js, 'has_customer_level') !== false, 'Account JS must read has_customer_level from profile');

$badge_css = file_get_contents($plugin . '/assets/customer-auth/css/pdx-verified-badge.css');
mi_assert(strpos($badge_css, 'pdx-account-name-line') !== false, 'Badge CSS must style the account name line');

// ---------------------------------------------------------------------------
// RESTORE: Mobile menu (☰) — account app + navein theme
// ---------------------------------------------------------------------------
$account_css = file_get_contents($plugin . '/assets/customer-auth/css/pdx-account-app.css');
mi_assert(strpos($account_css, 'pdx-account-mobile-menu') !== false, 'Account app must have a mobile menu (☰)');
mi_assert(strpos($auth_js, 'pdx-account-mobile-menu-icon--menu') !== false, 'Account JS must toggle the mobile menu open icon');
mi_assert(strpos($auth_js, 'pdx-account-mobile-menu-icon--close') !== false, 'Account JS must toggle the mobile menu close icon');

$theme_css = file_get_contents($root . '/navein/style.css');
mi_assert(strpos($theme_css, 'dtr-hamburger') !== false, 'Navein theme must keep the ☰ mobile hamburger');

// ---------------------------------------------------------------------------
// RESTORE: Avatars (100 + 10 VIP)
// ---------------------------------------------------------------------------
$avatar_gifs = glob($plugin . '/assets/customer-auth/images/avatars/pax-*.gif') ?: array();
mi_assert(count($avatar_gifs) === 100, 'Expected 100 restored avatar GIFs, got ' . count($avatar_gifs));
$vip_gifs = glob($plugin . '/assets/customer-auth/images/avatars-vip/pax-vip-*.gif') ?: array();
mi_assert(count($vip_gifs) === 10, 'Expected 10 restored VIP avatar GIFs, got ' . count($vip_gifs));

// ---------------------------------------------------------------------------
// KEEP: main's new Cybercrime CCS AI page + backend must remain intact
// ---------------------------------------------------------------------------
$ccs_page = file_get_contents($root . '/navein/template-parts/pages/cybercrime-support.php');
foreach (array('pax-ccs-decision-card', 'pax-ccs-case-dossier', 'pax-ccs-checks-list', 'data-ccs-guide', 'pax-ccs-category-cards') as $needle) {
    mi_assert(strpos($ccs_page, $needle) !== false, "New Cybercrime page must keep element: $needle");
}
$bootstrap = file_get_contents($plugin . '/paxdesign-booking.php');
foreach (array(
    'class-paxdesign-cybercrime-ai-case.php',
    'class-paxdesign-cybercrime-ai-operations.php',
    'class-paxdesign-cybercrime-ai-workflow.php',
    'class-paxdesign-cybercrime-document-checks.php',
    'class-paxdesign-cybercrime-i18n.php',
    'class-paxdesign-cybercrime-admin-reminders.php',
) as $needle) {
    mi_assert(strpos($bootstrap, $needle) !== false, "Bootstrap must keep CCS AI require: $needle");
}
mi_assert(strpos($bootstrap, "PAXDESIGN_BOOKING_VERSION', '3.176.1'") !== false, 'Merged plugin version must be 3.176.1');

// KEEP: messaging + chat reliability files still present
mi_assert(is_readable($plugin . '/includes/class-paxdesign-message-store.php'), 'Message store must remain');
$chat_js = file_get_contents($plugin . '/assets/js/chat-script.js');
mi_assert(strpos($chat_js, 'function assistantAlreadyShownForLatestTurn') !== false, 'Chat must keep one-bubble-per-turn logic');
mi_assert(strpos($chat_js, 'scrollToBottom(true)') !== false, 'Chat must keep pin-to-latest behavior');

// FIX: chat must open instantly (no blocking readiness overlay for a restored
// session); the session/history/poll sync runs in the background.
mi_assert(strpos($chat_js, 'var openInstant') !== false, 'Chat open must have the instant (non-blocking) fast path');
mi_assert(
    preg_match('/openInstant\s*=\s*!options\.force\s*&&\s*!!options\.reuseSession\s*&&\s*!!getSessionId\(\)\s*&&\s*sessionRestored/', $chat_js) === 1,
    'Instant open must trigger when a session is already restored'
);
mi_assert(
    preg_match('/if\s*\(openInstant\)\s*\{[^}]*return Promise\.resolve\(true\)/s', $chat_js) === 1,
    'Instant open must return immediately so the UI is interactive without waiting on the sync chain'
);
mi_assert(strpos($chat_js, 'runChatReadinessChecks(options)') !== false, 'Background readiness sync (session/history/poll) must still run');

// FIX: the chat bundle must warm on the first real (touch-friendly) interaction
// so the launcher opens instantly instead of lazy-loading ~200KB on click.
$loader_js = file_get_contents($plugin . '/assets/js/widget-loader.js');
mi_assert(strpos($loader_js, 'warmOnFirstInteraction') !== false, 'Widget loader must warm the chat bundle on first interaction');
mi_assert(strpos($loader_js, "'touchstart'") !== false || strpos($loader_js, '"touchstart"') !== false, 'Widget loader must warm on touchstart (mobile)');
mi_assert(strpos($loader_js, 'setTimeout(warmOnFirstInteraction, 1200)') !== false || strpos($loader_js, 'requestIdleCallback(warmOnFirstInteraction') !== false, 'Widget loader must warm shortly after load (no 4s cold gap)');
mi_assert(strpos($loader_js, 'setTimeout(preloadChat, 4000)') === false, 'Widget loader must not keep the old 4s cold preload gap');

// FIX: header level badge shows only the metal tier (e.g. "Gold") — no "Level N"
$auth_js2 = file_get_contents($plugin . '/assets/customer-auth/js/pax-auth.js');
mi_assert(strpos($auth_js2, 'level_metal') !== false, 'Header badge must use the metal tier field');
mi_assert(
    preg_match('/if\s*\(opts\.header\)\s*\{.*?level\.level_metal.*?escHtml\(metal\)/s', $auth_js2) === 1,
    'Header badge must render only the metal (Gold) — not the "Level N" label'
);
mi_assert(
    preg_match('/if\s*\(opts\.header\)\s*\{(?:(?!escHtml\(level\.level_label\)).)*?return/s', $auth_js2) === 1,
    'Header badge must not render the level_label ("PAXDesign Level NN — ...") in the header'
);

// ---------------------------------------------------------------------------
// DELETE: paxdesign-toolbar must be gone; migration + deploy guard retained
// ---------------------------------------------------------------------------
mi_assert(!is_dir($root . '/paxdesign-toolbar'), 'paxdesign-toolbar directory must be removed');
mi_assert(!is_file($root . '/paxdesign-toolbar.zip'), 'paxdesign-toolbar.zip must be removed');
mi_assert(is_readable($customer . '/class-paxdesign-toolbar-migration.php'), 'Toolbar->native migration guard must remain');
$deploy = file_get_contents($root . '/.github/workflows/deploy-customer-platform-3135.yml');
mi_assert(strpos($deploy, 'wp plugin deactivate paxdesign-toolbar') !== false, 'Deploy guard must deactivate any reappearing toolbar');
mi_assert(strpos($deploy, 'rm -rf wp-content/plugins/paxdesign-toolbar') !== false, 'Deploy guard must remove toolbar from server');

echo "OK: merge-integration checks passed (levels, badge, username, mobile menu, avatars, new cybercrime kept, toolbar removed)\n";
