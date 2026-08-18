<?php
/**
 * Isolated logic checks for public identity hardening (no WordPress runtime).
 */

if (!defined('ABSPATH')) {
	define('ABSPATH', sys_get_temp_dir() . '/paxdesign-pih-test/');
}

$PIH_CAN_LIST_USERS = false;
$PIH_LOGGED_IN = false;

if (!class_exists('WP_Error')) {
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct($code = '', $message = '', $data = array()) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}
	}
}

function current_user_can($cap) {
	global $PIH_CAN_LIST_USERS;
	return $cap === 'list_users' && $PIH_CAN_LIST_USERS;
}

function is_user_logged_in() {
	global $PIH_LOGGED_IN;
	return (bool) $PIH_LOGGED_IN;
}

function add_filter() {}
function add_action() {}
function remove_action() {}
function remove_filter() {}
function wp_unslash($value) { return $value; }
function __($text, $domain = '') { unset($domain); return $text; }

$PIH_ALLOWED_ORIGINS = array('https://paxdesign.at', 'https://www.paxdesign.at');
function is_allowed_http_origin($origin) {
	global $PIH_ALLOWED_ORIGINS;
	return in_array((string) $origin, $PIH_ALLOWED_ORIGINS, true);
}

require dirname(__DIR__, 2) . '/navein/inc/public-identity-hardening.php';

$fail = 0;
function pih_logic_ok($cond, $message) {
	global $fail;
	if ($cond) {
		echo "OK  $message\n";
		return;
	}
	echo "FAIL $message\n";
	$fail++;
}

$sample = array(
	'/wp/v2/users' => array('GET' => array()),
	'/wp/v2/users/(?P<id>[\\d]+)' => array('GET' => array()),
	'/wp/v2/users/me' => array('GET' => array()),
	'/wp/v2/pages' => array('GET' => array()),
	'/paxdesign/v1/profile' => array('GET' => array()),
);

$PIH_CAN_LIST_USERS = false;
$PIH_LOGGED_IN = false;
$anon = paxdesign_restrict_users_rest_endpoints($sample);
pih_logic_ok(!isset($anon['/wp/v2/users']), 'anonymous cannot list /wp/v2/users');
pih_logic_ok(!isset($anon['/wp/v2/users/(?P<id>[\\d]+)']), 'anonymous cannot read /wp/v2/users/{id}');
pih_logic_ok(!isset($anon['/wp/v2/users/me']), 'anonymous cannot read /wp/v2/users/me');
pih_logic_ok(isset($anon['/wp/v2/pages']), 'anonymous still has pages REST');
pih_logic_ok(isset($anon['/paxdesign/v1/profile']), 'custom REST routes stay registered');

$PIH_LOGGED_IN = true;
$customer = paxdesign_restrict_users_rest_endpoints($sample);
pih_logic_ok(!isset($customer['/wp/v2/users']), 'logged-in customer cannot list users');
pih_logic_ok(isset($customer['/wp/v2/users/me']), 'logged-in customer can use /users/me');
pih_logic_ok(isset($customer['/wp/v2/pages']), 'logged-in customer still has pages REST');

$PIH_CAN_LIST_USERS = true;
$staff = paxdesign_restrict_users_rest_endpoints($sample);
pih_logic_ok(isset($staff['/wp/v2/users']) && isset($staff['/wp/v2/users/me']), 'staff with list_users keep users REST');

$PIH_CAN_LIST_USERS = false;
$PIH_LOGGED_IN = false;
$_GET['rest_route'] = '/wp/v2/users';
$blocked = paxdesign_block_public_users_rest_dispatch('passthrough', null, null);
pih_logic_ok($blocked instanceof WP_Error, 'public users dispatch is rejected');
unset($_GET['rest_route']);
$allowed = paxdesign_block_public_users_rest_dispatch('passthrough', null, null);
pih_logic_ok($allowed === 'passthrough', 'non-users REST dispatch is unchanged');

pih_logic_ok(paxdesign_is_users_rest_route('/wp/v2/users/1') === true, 'users/1 is detected as a users route');
pih_logic_ok(paxdesign_is_users_rest_route('/wp/v2/pages') === false, 'pages is not a users route');

$sample['/wp/v2/media'] = array('GET' => array());
$sample['/wp/v2/media/(?P<id>[\\d]+)'] = array('GET' => array());
$PIH_CAN_LIST_USERS = false;
$PIH_LOGGED_IN = false;
$anon_media = paxdesign_restrict_media_rest_endpoints($sample);
pih_logic_ok(!isset($anon_media['/wp/v2/media']), 'anonymous cannot list /wp/v2/media');
pih_logic_ok(isset($anon_media['/wp/v2/media/(?P<id>[\\d]+)']), 'anonymous still has single-media REST');
$PIH_LOGGED_IN = true;
$customer_media = paxdesign_restrict_media_rest_endpoints($sample);
pih_logic_ok(isset($customer_media['/wp/v2/media']), 'logged-in users keep media collection REST');

pih_logic_ok(paxdesign_is_media_collection_rest_route('/wp/v2/media') === true, 'media collection route is detected');
pih_logic_ok(paxdesign_is_media_collection_rest_route('/wp/v2/media/93') === false, 'single media item is not the collection');

$PIH_LOGGED_IN = false;
$_GET['rest_route'] = '/wp/v2/media';
$media_blocked = paxdesign_block_public_media_collection_rest_dispatch('passthrough', null, null);
pih_logic_ok($media_blocked instanceof WP_Error, 'public media collection dispatch is rejected');
unset($_GET['rest_route']);

pih_logic_ok(paxdesign_rest_cors_origin_is_allowed('https://evil.example') === false, 'foreign Origin is not allowed for REST CORS');
pih_logic_ok(paxdesign_rest_cors_origin_is_allowed('https://paxdesign.at') === true, 'site Origin is allowed for REST CORS');
pih_logic_ok(paxdesign_rest_cors_origin_is_allowed('null') === false, 'null Origin is not allowed for REST CORS');
pih_logic_ok(paxdesign_rest_cors_origin_is_allowed('') === false, 'empty Origin is not allowed for REST CORS');

pih_logic_ok(paxdesign_login_error_enumerates_accounts('Unknown username. Check again or try your email address.') === true, 'unknown-username login error is genericized');
pih_logic_ok(paxdesign_login_error_enumerates_accounts('The password you entered for the username admin is incorrect.') === true, 'incorrect-password login error is genericized');
pih_logic_ok(paxdesign_login_error_enumerates_accounts('ERROR: Cookies are blocked or not supported.') === false, 'cookie login errors stay specific');

if ($fail > 0) {
	fwrite(STDERR, "$fail identity-hardening logic assertion(s) failed\n");
	exit(1);
}

echo "Identity-hardening logic checks passed.\n";
