<?php
/**
 * Guards for the customer-facing app connection-restored notice.
 * Copy must stay non-technical and reach all app customers via news + push.
 */

$root = dirname(__DIR__, 2);
$fail = 0;

function crn_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

$data_file = $root . '/paxdesign-booking/includes/customer/data/news-connection-restored-2026.php';
$announcements = $root . '/paxdesign-booking/includes/customer/class-paxdesign-customer-news-announcements.php';
$eval_file = $root . '/paxdesign-booking/scripts/wp-eval-seed-connection-restored-news.php';
$workflow = $root . '/.github/workflows/deploy-connection-restored-notice.yml';
$plugin = $root . '/paxdesign-booking/paxdesign-booking.php';

crn_ok(is_file($data_file), 'news data file exists');
crn_ok(is_file($announcements), 'announcements publisher exists');
crn_ok(is_file($eval_file), 'wp eval-file seeder exists');
crn_ok(is_file($workflow), 'surgical deploy workflow exists');

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/pax-connection-notice-abspath/');
}
$data = include $data_file;
crn_ok(is_array($data), 'news data file returns an array');

$slug = (string) ($data['slug'] ?? '');
crn_ok($slug === 'app-connection-restored-2026', 'slug is app-connection-restored-2026');
crn_ok(($data['audience'] ?? '') === 'all_customers', 'audience is all_customers');
crn_ok(($data['priority'] ?? '') === 'high', 'priority is high');

$translations = isset($data['translations']) && is_array($data['translations']) ? $data['translations'] : array();
foreach (array('de', 'en', 'ar') as $lang) {
    crn_ok(isset($translations[$lang]) && is_array($translations[$lang]), "$lang translation block exists");
    foreach (array('title', 'excerpt', 'body') as $field) {
        $value = trim((string) ($translations[$lang][$field] ?? ''));
        crn_ok($value !== '', "$lang $field is present");
    }
}

$de = $translations['de'] ?? array();
$en = $translations['en'] ?? array();
$ar = $translations['ar'] ?? array();

crn_ok(stripos((string) ($de['title'] ?? ''), 'Entschuldigung') !== false, 'DE title apologizes');
crn_ok(stripos((string) ($de['excerpt'] ?? '') . (string) ($de['body'] ?? ''), 'technisches Problem') !== false, 'DE copy mentions a temporary technical issue');
crn_ok(stripos((string) ($de['excerpt'] ?? '') . (string) ($de['body'] ?? ''), 'behoben') !== false, 'DE copy says the issue is resolved');
crn_ok(stripos((string) ($de['body'] ?? ''), 'Unannehmlichkeiten') !== false, 'DE body apologizes for the inconvenience');
crn_ok(stripos((string) ($de['body'] ?? ''), 'ganz normal') !== false, 'DE body says service is working normally');

crn_ok(stripos((string) ($en['title'] ?? ''), 'Sorry') !== false, 'EN title apologizes');
crn_ok(stripos((string) ($en['excerpt'] ?? '') . (string) ($en['body'] ?? ''), 'temporary technical issue') !== false, 'EN copy mentions a temporary technical issue');
crn_ok(
    stripos((string) ($en['excerpt'] ?? '') . (string) ($en['body'] ?? ''), 'resolved') !== false,
    'EN copy says the issue is resolved'
);
crn_ok(stripos((string) ($en['body'] ?? ''), 'inconvenience') !== false, 'EN body apologizes for the inconvenience');
crn_ok(stripos((string) ($en['body'] ?? ''), 'working normally') !== false, 'EN body says service is working normally');

crn_ok(strpos((string) ($ar['title'] ?? ''), 'عذر') !== false, 'AR title apologizes');
crn_ok(strpos((string) ($ar['excerpt'] ?? '') . (string) ($ar['body'] ?? ''), 'مشكلة تقنية مؤقتة') !== false, 'AR copy mentions a temporary technical issue');
crn_ok(strpos((string) ($ar['excerpt'] ?? '') . (string) ($ar['body'] ?? ''), 'تم حل') !== false, 'AR copy says the issue is resolved');
crn_ok(strpos((string) ($ar['body'] ?? ''), 'نعتذر') !== false, 'AR body apologizes');
crn_ok(strpos((string) ($ar['body'] ?? ''), 'بشكل طبيعي') !== false, 'AR body says service is working normally');

$copy_blob = strtolower(
    (string) ($de['title'] ?? '') . "\n" . (string) ($de['excerpt'] ?? '') . "\n" . (string) ($de['body'] ?? '') . "\n"
    . (string) ($en['title'] ?? '') . "\n" . (string) ($en['excerpt'] ?? '') . "\n" . (string) ($en['body'] ?? '') . "\n"
    . (string) ($ar['title'] ?? '') . "\n" . (string) ($ar['excerpt'] ?? '') . "\n" . (string) ($ar['body'] ?? '')
);
$forbidden = array(
    '.htaccess',
    'htaccess',
    'litespeed',
    'lite speed',
    'rest_route',
    'wp-json',
    'permalink',
    'rewriteengine',
    'rewrite rule',
    'hostinger',
    'apache',
    'nginx',
    'wordpress rest',
    'php',
);
foreach ($forbidden as $term) {
    crn_ok(strpos($copy_blob, $term) === false, 'customer copy does not mention ' . $term);
}

$announcements_src = file_get_contents($announcements);
crn_ok(strpos($announcements_src, 'function publish_connection_restored_2026') !== false, 'publisher method exists');
crn_ok(strpos($announcements_src, "'push_on_publish'    => 1") !== false || strpos($announcements_src, "'push_on_publish' => 1") !== false, 'publisher enables push_on_publish');
crn_ok(strpos($announcements_src, 'all_customers') !== false, 'publisher keeps all_customers audience');
crn_ok(strpos($announcements_src, 'CONNECTION_SLUG') !== false, 'publisher uses a dedicated slug constant');
crn_ok(strpos($announcements_src, 'news-connection-restored-2026.php') !== false, 'publisher loads the connection notice data file');

$eval_src = file_get_contents($eval_file);
crn_ok(strpos($eval_src, 'publish_connection_restored_2026(true)') !== false, 'eval-file publishes the connection notice');
crn_ok(strpos($eval_src, 'push_on_publish') !== false, 'eval-file reports push_on_publish');

$workflow_src = file_get_contents($workflow);
crn_ok(strpos($workflow_src, 'rsync --delete') === false && !preg_match('/rsync\s+[^\n]*--delete[^\n]*paxdesign-booking/', $workflow_src), 'workflow does not rsync --delete the plugin tree');
crn_ok(strpos($workflow_src, 'news-connection-restored-2026.php') !== false, 'workflow copies the news data file');
crn_ok(strpos($workflow_src, 'class-paxdesign-customer-news-announcements.php') !== false, 'workflow copies the announcements class');
crn_ok(strpos($workflow_src, 'wp-eval-seed-connection-restored-news.php') !== false, 'workflow copies the eval-file seeder');
crn_ok(strpos($workflow_src, 'wp eval-file') !== false, 'workflow runs wp eval-file on production');
crn_ok(strpos($workflow_src, 'chat-script.js') === false, 'workflow does not ship chat JS');
crn_ok(strpos($workflow_src, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'workflow does not ship CCS AI rewrite files');

$boot = file_get_contents($plugin);
crn_ok(strpos($boot, 'Version: 3.174.128') !== false, 'plugin header stays 3.174.128');

$php_files = array($data_file, $announcements, $eval_file);
foreach ($php_files as $file) {
    exec('php -l ' . escapeshellarg($file), $out, $code);
    crn_ok($code === 0, 'php -l ' . basename($file));
}

if ($fail > 0) {
    fwrite(STDERR, "$fail connection-restored notice assertion(s) failed\n");
    exit(1);
}

echo "OK: connection-restored notice checks passed\n";
