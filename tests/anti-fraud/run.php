<?php
/**
 * Anti-fraud / anti-bot Device Risk guards.
 *
 * Static + pure-PHP score checks. Does not require WordPress or a database.
 */
$root = dirname(__DIR__, 2);
$plugin = $root . '/paxdesign-booking';
$overlay = $root . '/deploy-patches/restored-chat-human-ui';
$fail = 0;

function fg_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$score_file = $plugin . '/includes/auth/class-paxdesign-fraud-score.php';
$store_file = $plugin . '/includes/auth/class-paxdesign-fraud-store.php';
$guard_file = $plugin . '/includes/auth/class-paxdesign-fraud-guard.php';
$js_file    = $plugin . '/assets/js/pax-fraud-guard.js';
$module     = $plugin . '/includes/auth/class-paxdesign-auth-module.php';
$frontend   = $plugin . '/includes/auth/class-paxdesign-auth-frontend.php';
$rest       = $plugin . '/includes/auth/class-paxdesign-auth-rest.php';
$workflow   = $root . '/.github/workflows/deploy-anti-fraud.yml';
$boot       = $plugin . '/paxdesign-booking.php';
$chat_js    = $plugin . '/assets/js/chat-script.js';
$auth_js    = $plugin . '/assets/customer-auth/js/pax-auth.js';

foreach (array($score_file, $store_file, $guard_file, $js_file, $module, $frontend, $rest, $workflow) as $path) {
    fg_ok(is_file($path), 'exists ' . str_replace($root . '/', '', $path));
}

foreach (array($score_file, $store_file, $guard_file, $module, $frontend, $rest) as $path) {
    exec('php -l ' . escapeshellarg($path), $lint_out, $lint_code);
    fg_ok($lint_code === 0, 'php -l ' . basename($path));
}

$score_src = file_get_contents($score_file);
$store_src = file_get_contents($store_file);
$guard_src = file_get_contents($guard_file);
$js        = file_get_contents($js_file);
$module_src = file_get_contents($module);
$frontend_src = file_get_contents($frontend);
$rest_src  = file_get_contents($rest);
$wf        = file_get_contents($workflow);
$boot_src  = file_get_contents($boot);
$chat      = file_get_contents($chat_js);

fg_ok(strpos($score_src, 'THRESHOLD_CHALLENGE = 75') !== false, 'challenge threshold is 75');
fg_ok(strpos($score_src, 'ACTION_CHALLENGE') !== false, 'score engine has a challenge action');
fg_ok(strpos($score_src, 'bot_webdriver') !== false, 'webdriver is a bot signal');
fg_ok(strpos($score_src, 'multi_account_fingerprint') !== false, 'multi-account fingerprint is scored');
fg_ok(strpos($score_src, 'credential_abuse') !== false, 'credential abuse is scored');
fg_ok(strpos($score_src, 'scraping_pattern') !== false, 'scraping pattern is scored');
fg_ok(strpos($score_src, 'Audio') === false, 'score engine does not use audio');

fg_ok(strpos($store_src, 'pax_fraud_devices') !== false, 'device table is defined');
fg_ok(strpos($store_src, 'create_challenge') !== false, 'store can issue extra-verification challenges');
fg_ok(strpos($store_src, 'HTTP_CF_CONNECTING_IP') !== false, 'IP uses Cloudflare when present');

fg_ok(strpos($guard_src, '/auth/device-risk') !== false, 'device-risk REST route exists');
fg_ok(strpos($guard_src, '/auth/device-challenge') !== false, 'device-challenge REST route exists');
fg_ok(strpos($guard_src, 'pax_challenge_required') !== false, 'high risk returns extra verification, not a silent drop');
fg_ok(strpos($guard_src, '428') !== false, 'challenge uses HTTP 428');
fg_ok(strpos($guard_src, 'rest_pre_dispatch') !== false, 'API is guarded via rest_pre_dispatch');
fg_ok(strpos($guard_src, 'paxdesign_chat_live_user_send') !== false, 'chat send is guarded');
fg_ok(strpos($guard_src, 'paxdesign_chat_poll') === false, 'chat poll is not challenged per request');
fg_ok(strpos($guard_src, 'is_owner_account') !== false, 'owner accounts bypass extra verification');
fg_ok(strpos($guard_src, 'manage_options') !== false, 'site admins bypass extra verification');
fg_ok(strpos($guard_src, 'wp_check_password') !== false, 'login challenge runs only after credentials look valid');
fg_ok(strpos($guard_src, 'AudioContext') === false && strpos($guard_src, 'oscillator') === false, 'PHP guard does not use audio');

fg_ok(strpos($js, 'AudioContext') === false, 'collector JS has no AudioContext');
fg_ok(strpos($js, 'webkitAudioContext') === false, 'collector JS has no webkitAudioContext');
fg_ok(strpos($js, 'OfflineAudioContext') === false, 'collector JS has no OfflineAudioContext');
fg_ok(strpos($js, 'oscillator') === false && strpos($js, 'Oscillator') === false, 'collector JS has no oscillator');
fg_ok(strpos($js, 'new Audio') === false, 'collector JS does not construct Audio elements');
fg_ok(strpos($js, 'requestIdleCallback') !== false, 'collector waits for idle time');
fg_ok(strpos($js, 'canvas') !== false && strpos($js, 'webgl') !== false, 'collector captures canvas and WebGL');
fg_ok(strpos($js, 'timezone') !== false && strpos($js, 'hardwareConcurrency') !== false, 'collector captures timezone and hardware');
fg_ok(strpos($js, 'X-PAX-Device-Id') !== false, 'requests attach the Device Risk ID');
fg_ok(strpos($js, 'pax_challenge_required') !== false, 'JS handles extra verification');
fg_ok(strpos($js, 'keepalive: true') !== false, 'risk ingest uses keepalive so it does not block navigation');

fg_ok(strpos($module_src, 'class-paxdesign-fraud-guard.php') !== false, 'auth module loads the fraud guard');
fg_ok(strpos($module_src, 'PAXdesign_Fraud_Guard::init') !== false, 'auth module initializes the fraud guard');
fg_ok(strpos($frontend_src, 'pax-fraud-guard') !== false, 'frontend enqueues the collector');
fg_ok(strpos($frontend_src, 'paxFraudGuard') !== false, 'frontend localizes collector URLs');
fg_ok(strpos($frontend_src, "'pax-auth-verified-badge', 'pax-auth-customer-icons', 'pax-fraud-guard'") !== false, 'collector loads before pax-auth.js');
fg_ok(strpos($rest_src, 'fraud_gate') !== false, 'auth REST calls the fraud gate');
fg_ok(strpos($rest_src, "fraud_gate('login'") !== false, 'login is gated');
fg_ok(strpos($rest_src, "fraud_gate('register'") !== false, 'register is gated');
fg_ok(strpos($rest_src, "fraud_gate('mobile_login'") !== false, 'mobile login is gated');

fg_ok(strpos($boot_src, "PAXDESIGN_BOOKING_VERSION', '3.174.128'") !== false, 'plugin version stays 3.174.128');
fg_ok(strpos($boot_src, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'bootstrap still does not load CCS AI workflow');
fg_ok(strpos($chat, 'Version: 3.174.128') !== false, 'chat JS still reports 3.174.128');
fg_ok(strpos($chat, 'skipping stacked sync') === false, 'chat JS is not the 3.176 rewrite');
fg_ok(strpos($chat, 'Gespräch beenden') === false, 'chat JS has no Gespräch beenden');

$overlay_js = $overlay . '/assets/customer-auth/js/pax-auth.js';
fg_ok(is_file($overlay_js) && md5_file($overlay_js) === md5_file($auth_js), 'pax-auth.js still matches the restored overlay');
fg_ok(md5_file($overlay . '/paxdesign-booking.php') === md5_file($boot), 'plugin bootstrap still matches the restored overlay');
fg_ok(md5_file($overlay . '/assets/js/chat-script.js') === md5_file($chat_js), 'chat-script.js still matches the restored overlay');
fg_ok(md5_file($overlay . '/includes/class-paxdesign-chat-live.php') === md5_file($plugin . '/includes/class-paxdesign-chat-live.php'), 'chat-live.php still matches the restored overlay');

fg_ok(strpos($wf, 'rsync --delete') === false, 'deploy workflow does not rsync --delete the plugin tree');
fg_ok(strpos($wf, 'pax-fraud-guard.js') !== false, 'deploy copies the collector script');
fg_ok(strpos($wf, 'class-paxdesign-fraud-guard.php') !== false, 'deploy copies the fraud guard');
fg_ok(strpos($wf, 'Version: 3.174.128') !== false, 'deploy verifies live chat version');
fg_ok(strpos($wf, 'skipping stacked sync') !== false, 'deploy verifies chat is not the 3.176 rewrite');
fg_ok(strpos($wf, 'Gespräch beenden') !== false, 'deploy verifies chat has no Gespräch beenden');
fg_ok(strpos($wf, 'AudioContext') !== false, 'deploy verifies collector has no audio fingerprinting');

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}
require_once $score_file;

$bot = PAXdesign_Fraud_Score::evaluate(array(
    'webdriver' => true,
    'ua' => 'Mozilla/5.0 HeadlessChrome/120.0.0.0',
    'webgl_renderer' => 'Google SwiftShader',
    'canvas' => '',
    'hardware_concurrency' => 0,
    'languages' => '',
    'timezone' => '',
    'collected_ms' => 1,
), array(
    'fingerprint_accounts' => 5,
    'failed_logins' => 9,
    'ip_velocity' => 80,
    'scrape_pattern' => true,
));
fg_ok((int) $bot['score'] >= 75, 'bot + fraud signals score at or above the challenge threshold (' . $bot['score'] . ')');
fg_ok($bot['action'] === PAXdesign_Fraud_Score::ACTION_CHALLENGE, 'bot + fraud signals request extra verification');
fg_ok(in_array('bot_webdriver', $bot['reasons'], true), 'webdriver is listed as a reason');

$owner = PAXdesign_Fraud_Score::evaluate(array('webdriver' => true), array('owner' => true));
fg_ok((int) $owner['score'] === 0 && $owner['action'] === PAXdesign_Fraud_Score::ACTION_ALLOW, 'owner bypass stays allow with score 0');

$human = PAXdesign_Fraud_Score::evaluate(array(
    'webdriver' => false,
    'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    'platform' => 'MacIntel',
    'languages' => array('de-AT', 'de', 'en'),
    'timezone' => 'Europe/Vienna',
    'screen_w' => 1440,
    'screen_h' => 900,
    'color_depth' => 24,
    'hardware_concurrency' => 8,
    'device_memory' => 8,
    'canvas' => 'abc123canvas',
    'webgl_vendor' => 'Intel Inc.',
    'webgl_renderer' => 'Intel Iris Plus Graphics',
    'plugins' => 5,
    'vendor' => 'Google Inc.',
    'collected_ms' => 48,
), array(
    'missing_device' => false,
    'fingerprint_accounts' => 1,
    'ip_accounts' => 1,
    'ip_velocity' => 3,
    'failed_logins' => 0,
));
fg_ok((int) $human['score'] < 50, 'normal browser stays below watch threshold (' . $human['score'] . ')');
fg_ok($human['action'] === PAXdesign_Fraud_Score::ACTION_ALLOW, 'normal browser is allowed without extra verification');

$multi = PAXdesign_Fraud_Score::evaluate(array(
    'webdriver' => false,
    'ua' => 'Mozilla/5.0 Chrome/126.0.0.0',
    'canvas' => 'abc123canvas',
    'webgl_vendor' => 'Intel Inc.',
    'webgl_renderer' => 'Intel Iris Plus Graphics',
    'hardware_concurrency' => 8,
    'languages' => 'de',
    'timezone' => 'Europe/Vienna',
    'plugins' => 3,
    'collected_ms' => 20,
), array(
    'fingerprint_accounts' => 4,
    'ip_accounts' => 5,
));
fg_ok($multi['action'] === PAXdesign_Fraud_Score::ACTION_CHALLENGE || $multi['score'] >= 50, 'multiple accounts on one fingerprint raise risk');

$hash_a = PAXdesign_Fraud_Score::fingerprint_hash(array('canvas' => 'a', 'timezone' => 'Europe/Vienna', 'screen_w' => 1));
$hash_b = PAXdesign_Fraud_Score::fingerprint_hash(array('canvas' => 'b', 'timezone' => 'Europe/Vienna', 'screen_w' => 1));
fg_ok($hash_a !== $hash_b && strlen($hash_a) === 64, 'fingerprint hashes are stable 64-char SHA-256 values');

if ($fail > 0) {
    fwrite(STDERR, "$fail anti-fraud assertion(s) failed\n");
    exit(1);
}
echo "Anti-fraud / Device Risk guards passed.\n";
