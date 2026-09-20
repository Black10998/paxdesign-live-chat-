<?php
/**
 * Guards against OpenAI key leakage in git, frontend, mobile, admin HTML, and CI.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function okh_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$plugin = $root . '/paxdesign-booking';
$chat = file_get_contents($plugin . '/includes/class-paxdesign-chat.php');
$mobile = file_get_contents($plugin . '/includes/class-paxdesign-live-chat-mobile-api.php');
$settings = file_get_contents($plugin . '/templates/settings-page.php');
$workflow = file_get_contents($root . '/.github/workflows/configure-wordpress-openai.yml');
$js = file_get_contents($plugin . '/assets/js/chat-script.js');
$overlay = file_get_contents($root . '/deploy-patches/restored-chat-human-ui/includes/class-paxdesign-chat.php');

okh_ok(strpos($chat, 'function openai_key_public_hint') !== false, 'chat exposes a non-identifying key hint helper');
okh_ok(strpos($chat, 'function record_openai_audit') !== false, 'chat records redacted OpenAI audit rows');
okh_ok(strpos($chat, 'x-request-id') !== false, 'chat captures OpenAI x-request-id');
okh_ok(strpos($chat, "preg_replace('/sk-[A-Za-z0-9_-]+/', '[API-KEY]'") !== false, 'chat redacts API keys from error text');
okh_ok(md5($chat) === md5($overlay), 'overlay chat.php stays identical to plugin chat.php');

okh_ok(strpos($mobile, "substr(\$api_key, -4)") === false, 'REST status does not return the last 4 key characters');
okh_ok(strpos($mobile, 'openai_key_public_hint') !== false, 'REST status uses the public key hint helper');
okh_ok(strpos($mobile, 'recent_audit') !== false, 'admin OpenAI status can return recent audit rows');

okh_ok(strpos($settings, 'echo esc_attr($chat_openai_key)') === false, 'admin settings do not echo the stored OpenAI key');
okh_ok(strpos($settings, 'echo esc_attr($chat_worker_secret)') === false, 'admin settings do not echo the worker secret');
okh_ok(strpos($settings, 'name="paxdesign_chat_openai_key"') !== false, 'admin settings still accept a replacement OpenAI key');

okh_ok(strpos($workflow, 'inputs.openai_api_key') === false, 'configure workflow no longer accepts a raw key input');
okh_ok(strpos($workflow, '::add-mask::') !== false, 'configure workflow masks the secret before export');
okh_ok(strpos($workflow, 'secrets.PAX_OPENAI_API_KEY') !== false, 'configure workflow reads the key only from GitHub secrets');

okh_ok(strpos($js, 'Version: 3.174.128') !== false, 'live chat JS baseline remains 3.174.128');
okh_ok(preg_match('/sk-(proj|svcacct|admin)-[A-Za-z0-9_-]{10,}/', $js) !== 1, 'chat-script.js has no live OpenAI secret');

$scan_roots = array(
    $plugin . '/assets',
    $plugin . '/includes',
    $plugin . '/templates',
    $plugin . '/ios-live-chat',
    $root . '/navein',
    $root . '/scripts',
    $root . '/.github',
    $root . '/tests',
);
$secret_hits = array();
$iterator_flags = FilesystemIterator::SKIP_DOTS;
foreach ($scan_roots as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, $iterator_flags));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'mp4', 'mov', 'pdf', 'ipa', 'zip'), true)) {
            continue;
        }
        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            continue;
        }
        if (preg_match('/sk-(proj|svcacct|admin)-[A-Za-z0-9_-]{16,}/', $contents)) {
            $secret_hits[] = substr($path, strlen($root) + 1);
        }
    }
}

okh_ok($secret_hits === array(), 'tracked source trees have no live OpenAI project/admin secrets' . ($secret_hits ? ' (' . implode(', ', $secret_hits) . ')' : ''));

$ios_hits = array();
$ios_dir = $plugin . '/ios-live-chat';
if (is_dir($ios_dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ios_dir, $iterator_flags));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($ext, array('swift', 'plist', 'json', 'xcconfig'), true)) {
            continue;
        }
        $contents = @file_get_contents($file->getPathname());
        if (!is_string($contents)) {
            continue;
        }
        if (preg_match('/api\\.openai\\.com|OPENAI_API_KEY|sk-(proj|svcacct|admin)-/', $contents)) {
            $ios_hits[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
}
okh_ok($ios_hits === array(), 'iOS sources do not embed OpenAI credentials or call OpenAI directly' . ($ios_hits ? ' (' . implode(', ', $ios_hits) . ')' : ''));

$boot = file_get_contents($plugin . '/paxdesign-booking.php');
okh_ok(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.128')") !== false, 'plugin baseline remains 3.174.128');
okh_ok(strpos($boot, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'does not load CCS AI workflow');

if ($fail > 0) {
    fwrite(STDERR, "$fail openai-key-hardening assertion(s) failed\n");
    exit(1);
}
echo "OpenAI key hardening guards passed.\n";
