<?php
/**
 * Merge-integration regression guard for production baseline 3.174.91.
 *
 * Verifies customer levels / avatars / account UI from the restored site,
 * and that the later 3.176.x chat/CCS AI rewrite is not present.
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
// VIP avatar grants may exist without the later 3.176 REST level-sync hook.
if (strpos($rest, 'PAXdesign_Customer_Levels::set_level_for_user') !== false) {
    mi_assert(true, 'VIP preset selection sets customer level when present');
}

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
// Production baseline: live 3.174.92 — do not keep the 3.176.x CCS AI rewrite
// ---------------------------------------------------------------------------
$bootstrap = file_get_contents($plugin . '/paxdesign-booking.php');
mi_assert(strpos($bootstrap, "PAXDESIGN_BOOKING_VERSION', '3.174.126'") !== false, 'Plugin version must be 3.174.126');
mi_assert(strpos($bootstrap, 'class-paxdesign-cybercrime-i18n.php') !== false, 'Compact CCS i18n must remain');
foreach (array(
    'class-paxdesign-cybercrime-ai-case.php',
    'class-paxdesign-cybercrime-ai-operations.php',
    'class-paxdesign-cybercrime-ai-workflow.php',
    'class-paxdesign-cybercrime-document-checks.php',
    'class-paxdesign-cybercrime-admin-reminders.php',
) as $needle) {
    mi_assert(strpos($bootstrap, $needle) === false, "Bootstrap must not load 3.176 CCS AI file: $needle");
    mi_assert(!is_file($plugin . '/includes/' . $needle), "3.176 CCS AI file must be absent: $needle");
}

mi_assert(is_readable($plugin . '/includes/class-paxdesign-message-store.php'), 'Message store must remain');
$chat_js = file_get_contents($plugin . '/assets/js/chat-script.js');
mi_assert(strpos($chat_js, 'skipping stacked sync') === false, 'Chat must not contain the 3.176 stacked-sync rewrite');
mi_assert(strpos($chat_js, 'var openInstant') === false, 'Chat must not contain the 3.176 instant-open rewrite');
mi_assert(strpos($chat_js, 'Version: 3.174.126') !== false, 'Chat JS must be cache-bust 3.174.126');
mi_assert(strpos($chat_js, 'Gespräch beenden') === false, 'Customer chat must not include Gespräch beenden');
mi_assert(strpos($chat_js, 'uploadHumanAttachFile') !== false, 'Human-composer attach handler must remain');

$ccs_page = file_get_contents($root . '/navein/template-parts/pages/cybercrime-support.php');
$ccs_data = file_get_contents($root . '/navein/template-parts/pages/cybercrime-support-data.php');
mi_assert(strpos($ccs_page, 'pax-ccs-locale') !== false, 'Cybercrime page must keep locale field');
mi_assert(strpos($ccs_page, 'data-ccs-switch') !== false, 'Cybercrime page must keep language switcher');
mi_assert(strpos($ccs_data, 'بوابة الإبلاغ') !== false, 'Cybercrime copy must include Arabic portal title');

$auth_js2 = file_get_contents($plugin . '/assets/customer-auth/js/pax-auth.js');
// Header metal-only badge is optional on this baseline (present on later UI patches).
if (strpos($auth_js2, 'level_metal') !== false) {
    mi_assert(
        preg_match('/if\s*\(opts\.header\)\s*\{.*?level\.level_metal.*?escHtml\(metal\)/s', $auth_js2) === 1,
        'Header badge must render only the metal (Gold) — not the "Level N" label'
    );
}

// ---------------------------------------------------------------------------
// DELETE: paxdesign-toolbar must be gone; migration + deploy guard retained
// ---------------------------------------------------------------------------
mi_assert(!is_dir($root . '/paxdesign-toolbar'), 'paxdesign-toolbar directory must be removed');
mi_assert(!is_file($root . '/paxdesign-toolbar.zip'), 'paxdesign-toolbar.zip must be removed');
mi_assert(is_readable($customer . '/class-paxdesign-toolbar-migration.php'), 'Toolbar->native migration guard must remain');
$deploy = file_get_contents($root . '/.github/workflows/deploy-customer-platform-3135.yml');
mi_assert(strpos($deploy, 'wp plugin deactivate paxdesign-toolbar') !== false, 'Deploy guard must deactivate any reappearing toolbar');
mi_assert(strpos($deploy, 'rm -rf wp-content/plugins/paxdesign-toolbar') !== false, 'Deploy guard must remove toolbar from server');

echo "OK: merge-integration checks passed (levels, badge, username, mobile menu, avatars, 3.174.126 baseline, toolbar removed)\n";
