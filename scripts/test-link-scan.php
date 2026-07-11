<?php
/**
 * CLI tests for PAXdesign_Link_Scan_Service.
 *
 * Usage: php scripts/test-link-scan.php
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $default;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        if (strpos($url, 'urlhaus-api') !== false) {
            return array('body' => json_encode(array('query_status' => 'no_results')));
        }
        if (strpos($url, 'phishtank.com') !== false) {
            return array('body' => json_encode(array('results' => array('in_database' => false))));
        }
        return array('response' => array('code' => 200), 'body' => json_encode(array()));
    }
}
if (!function_exists('wp_remote_head')) {
    function wp_remote_head($url, $args = array()) {
        return array('response' => array('code' => 200), 'headers' => array('content-type' => 'text/html'));
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return is_array($response) && isset($response['response']['code']) ? (int) $response['response']['code'] : 200;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return is_array($response) && isset($response['body']) ? (string) $response['body'] : '';
    }
}
if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, $name) {
        if (!is_array($response) || empty($response['headers'])) {
            return '';
        }
        $headers = $response['headers'];
        $key = strtolower($name);
        return isset($headers[$key]) ? (string) $headers[$key] : '';
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

require_once $root . '/paxdesign-booking/includes/class-paxdesign-link-scanner.php';
require_once $root . '/paxdesign-booking/includes/class-paxdesign-link-scan-service.php';

if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

$passed = 0;
$failed = 0;

function assert_true($label, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "PASS: $label\n";
        $passed++;
    } else {
        echo "FAIL: $label\n";
        $failed++;
    }
}

// Safe URL — server probe on example.com
$safe = PAXdesign_Link_Scan_Service::scan_url_remote('https://example.com/');
assert_true('safe url resolves to safe or incomplete (not dangerous)', in_array($safe['status'], array('safe', 'incomplete', 'failed', 'timeout'), true));
assert_true('safe url has provider', $safe['provider'] !== '');

// Dangerous scheme
$scheme = PAXdesign_Link_Scan_Service::scan_url_remote('javascript:alert(1)');
assert_true('javascript scheme is dangerous', $scheme['status'] === PAXdesign_Link_Scan_Service::STATUS_DANGEROUS);

// Simulated service failure
$failure = PAXdesign_Link_Scan_Service::scan_url_remote('https://example.com/', array('simulate' => 'failure'));
assert_true('simulated failure returns failed', $failure['status'] === PAXdesign_Link_Scan_Service::STATUS_FAILED);

// Simulated timeout
$timeout = PAXdesign_Link_Scan_Service::scan_url_remote('https://example.com/', array('simulate' => 'timeout'));
assert_true('simulated timeout returns timeout', $timeout['status'] === PAXdesign_Link_Scan_Service::STATUS_TIMEOUT);

// begin_scan_meta sets checking only
$meta = PAXdesign_Link_Scan_Service::begin_scan_meta('see https://example.com', 'user', array());
assert_true('begin_scan_meta sets checking', $meta['link_scan_status'] === PAXdesign_Link_Scan_Service::STATUS_CHECKING);
assert_true('begin_scan_meta stores started_at', !empty($meta['link_scan_started_at']));
assert_true('begin_scan_meta has no completed_at', empty($meta['link_scan_completed_at']));

// worst_status aggregation
assert_true('worst status prefers dangerous', PAXdesign_Link_Scan_Service::worst_status('safe', 'dangerous') === 'dangerous');
assert_true('worst status prefers suspicious over safe', PAXdesign_Link_Scan_Service::worst_status('safe', 'suspicious') === 'suspicious');
assert_true('worst status prefers incomplete over safe', PAXdesign_Link_Scan_Service::worst_status('safe', 'incomplete') === 'incomplete');

// Suspicious raw IP via server probe
$suspicious = PAXdesign_Link_Scan_Service::scan_url_remote('http://192.0.2.1/test');
assert_true('raw ip url is suspicious or incomplete', in_array($suspicious['status'], array('suspicious', 'incomplete', 'failed', 'timeout'), true));

// Link scanner delegates to checking only
$metaUser = PAXdesign_Link_Scanner::attach_scan_meta('visit https://example.com', 'user', array());
assert_true('attach_scan_meta user with url sets checking', ($metaUser['link_scan_status'] ?? '') === 'checking');
$metaAdmin = PAXdesign_Link_Scanner::attach_scan_meta('visit https://example.com', 'admin', array());
assert_true('attach_scan_meta admin ignores urls', empty($metaAdmin['link_scan_status']));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
