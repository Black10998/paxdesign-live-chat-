<?php
/**
 * Static guards for public WordPress identity hardening.
 *
 * Confirms the defensive theme helper is wired, does not touch the
 * 3.174.128 plugin baseline, and does not introduce XML-RPC or REST
 * attack helpers.
 */
$root = dirname(__DIR__, 2);
$fail = 0;

function pih_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$hardening = $root . '/navein/inc/public-identity-hardening.php';
$functions = $root . '/navein/functions.php';
$style = $root . '/navein/style.css';
$plugin = $root . '/paxdesign-booking/paxdesign-booking.php';

pih_ok(is_file($hardening), 'hardening helper exists');
pih_ok(is_file($functions), 'theme functions.php exists');

exec('php -l ' . escapeshellarg($hardening), $lint_out, $lint_code);
pih_ok($lint_code === 0, 'hardening helper has valid PHP syntax');

$src = file_get_contents($hardening);
$fn = file_get_contents($functions);
$css = file_get_contents($style);

pih_ok(strpos($fn, "inc/public-identity-hardening.php") !== false, 'functions.php loads the hardening helper');
pih_ok(preg_match('/Version:\\s*1\\.4\\.47/', $css) === 1, 'theme version bumped for deploy cache-bust');

pih_ok(strpos($src, "add_filter( 'rest_endpoints'") !== false, 'restricts REST users endpoints');
pih_ok(strpos($src, 'paxdesign_restrict_users_rest_endpoints') !== false, 'users REST restriction callback exists');
pih_ok(strpos($src, 'paxdesign_block_public_users_rest_dispatch') !== false, 'users REST dispatch block exists');
pih_ok(strpos($src, 'paxdesign_redirect_public_author_archives') !== false, 'author archive redirect exists');
pih_ok(strpos($src, "add_action( 'template_redirect', 'paxdesign_redirect_public_author_archives', 1 )") !== false, 'author redirect runs before canonical');
pih_ok(strpos($src, "add_filter( 'redirect_canonical'") !== false, 'canonical author slug leak is disabled');
pih_ok(strpos($src, "add_filter( 'xmlrpc_enabled', '__return_false'") !== false, 'XML-RPC enabled flag is off');
pih_ok(strpos($src, "add_filter( 'xmlrpc_methods', '__return_empty_array'") !== false, 'XML-RPC methods are emptied');
pih_ok(strpos($src, 'paxdesign_disable_unused_xmlrpc') !== false, 'XML-RPC requests exit early');
pih_ok(strpos($src, "remove_action( 'wp_head', 'rsd_link' )") !== false, 'RSD discovery link is removed');
pih_ok(strpos($src, 'list_users') !== false, 'staff with list_users keep access');
pih_ok(strpos($src, 'users/me') !== false, 'logged-in /users/me is preserved');
pih_ok(strpos($src, 'paxdesign_replace_open_rest_cors') !== false, 'open REST CORS reflector is replaced');
pih_ok(strpos($src, 'paxdesign_rest_cors_origin_is_allowed') !== false, 'REST CORS uses an origin allowlist');
pih_ok(strpos($src, 'is_allowed_http_origin') !== false, 'REST CORS defers to WordPress allowed origins');
pih_ok(strpos($src, "remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' )") !== false, 'core rest_send_cors_headers is removed');
pih_ok(strpos($src, 'paxdesign_restrict_media_rest_endpoints') !== false, 'public media collection is restricted');
pih_ok(strpos($src, 'paxdesign_generic_login_error_message') !== false, 'wp-login username enumeration is genericized');
pih_ok(strpos($src, "add_filter( 'login_errors'") !== false, 'login_errors filter is wired');
pih_ok(strpos($src, "remove_action( 'wp_head', 'wp_generator' )") !== false, 'wp_generator is removed');

pih_ok(strpos($src, 'rsync --delete') === false, 'hardening file does not rsync-delete the plugin');
pih_ok(strpos($src, 'class-paxdesign-cybercrime-ai-workflow.php') === false, 'does not load CCS AI workflow');
pih_ok(strpos($src, 'skipping stacked sync') === false, 'does not include 3.176 chat rewrite');

$boot = file_get_contents($plugin);
pih_ok(strpos($boot, "define('PAXDESIGN_BOOKING_VERSION', '3.174.128')") !== false, 'plugin baseline remains 3.174.128');

$workflow = $root . '/.github/workflows/deploy-public-identity-hardening.yml';
pih_ok(is_file($workflow), 'surgical deploy workflow exists');
$wf = file_get_contents($workflow);
pih_ok(strpos($wf, 'rsync --delete') === false, 'deploy workflow does not rsync --delete');
pih_ok(strpos($wf, 'public-identity-hardening.php') !== false, 'deploy workflow copies the hardening helper');
pih_ok(strpos($wf, 'paxdesign-booking') === false, 'deploy workflow does not touch the plugin tree');

$sec_workflow = $root . '/.github/workflows/deploy-security-hardening-fixes.yml';
pih_ok(is_file($sec_workflow), 'security hardening deploy workflow exists');
$sec_wf = file_get_contents($sec_workflow);
pih_ok(strpos($sec_wf, 'rsync --delete') === false, 'security deploy workflow does not rsync --delete');
pih_ok(strpos($sec_wf, 'class-paxdesign-customer-platform.php') !== false, 'security deploy copies the chat login gate');
pih_ok(strpos($sec_wf, 'class-paxdesign-chat-live.php') !== false, 'security deploy copies chat live handlers');
pih_ok(strpos($sec_wf, 'public-identity-hardening.php') !== false, 'security deploy copies the hardening helper');
pih_ok(strpos($sec_wf, 'patch-wp-htaccess-security.sh') !== false, 'security deploy patches readme/llms deny rules');
pih_ok(strpos($sec_wf, 'verify-security-hardening.sh') !== false, 'security deploy verifies live closures');

$logic = $root . '/tests/public-identity-hardening/logic.php';
pih_ok(is_file($logic), 'logic test exists');
exec('php ' . escapeshellarg($logic), $logic_out, $logic_code);
echo implode("\n", $logic_out) . "\n";
pih_ok($logic_code === 0, 'anonymous users REST routes are removed; staff keep them');

if ($fail > 0) {
	fwrite(STDERR, "$fail public-identity-hardening assertion(s) failed\n");
	exit(1);
}

echo "Public identity hardening guards passed.\n";
