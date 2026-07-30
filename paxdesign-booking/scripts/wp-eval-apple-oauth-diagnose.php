<?php
/**
 * WP-CLI eval-file: diagnose Sign in with Apple web OAuth (client secret + config).
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php';

$service_id = PAXdesign_Auth_Apple::web_service_id();
$team_id    = trim( (string) get_option( 'paxdesign_apns_team_id', '' ) );
$key_id     = trim( (string) get_option( 'paxdesign_apns_key_id', '' ) );
$key_p8     = trim( (string) get_option( 'paxdesign_apns_key_p8', '' ) );

echo "service_id={$service_id}\n";
echo 'callback=' . PAXdesign_Auth_Apple::web_callback_url() . "\n";
echo 'configured=' . ( PAXdesign_Auth_Apple::is_web_configured() ? 'yes' : 'no' ) . "\n";
echo 'team_id=' . ( $team_id !== '' ? 'set' : 'missing' ) . "\n";
echo 'key_id=' . ( $key_id !== '' ? 'set' : 'missing' ) . "\n";
echo 'key_p8=' . ( $key_p8 !== '' ? 'set(' . strlen( $key_p8 ) . ' bytes)' : 'missing' ) . "\n";

if ( ! PAXdesign_Auth_Apple::is_web_configured() ) {
	exit( 1 );
}

$reflection = new ReflectionClass( 'PAXdesign_Auth_Apple' );
$make_secret = $reflection->getMethod( 'make_client_secret' );
$make_secret->setAccessible( true );
$web_config = $reflection->getMethod( 'web_config' );
$web_config->setAccessible( true );
$cfg = $web_config->invoke( null );

$secret = $make_secret->invoke( null, $cfg );
if ( is_wp_error( $secret ) ) {
	echo 'client_secret_error=' . $secret->get_error_message() . "\n";
	exit( 1 );
}

$parts = explode( '.', $secret );
echo 'client_secret_parts=' . count( $parts ) . ' len=' . strlen( $secret ) . "\n";

$response = wp_remote_post(
	'https://appleid.apple.com/auth/token',
	array(
		'timeout' => 20,
		'headers' => array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded',
		),
		'body'    => http_build_query(
			array(
				'client_id'     => $service_id,
				'client_secret' => $secret,
				'code'          => 'diagnostic-invalid-code',
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => PAXdesign_Auth_Apple::web_callback_url(),
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		),
	)
);

if ( is_wp_error( $response ) ) {
	echo 'token_http_error=' . $response->get_error_message() . "\n";
	exit( 1 );
}

$status = (int) wp_remote_retrieve_response_code( $response );
$body   = json_decode( wp_remote_retrieve_body( $response ), true );
$error  = is_array( $body ) ? (string) ( $body['error'] ?? '' ) : '';
$desc   = is_array( $body ) ? (string) ( $body['error_description'] ?? '' ) : '';

echo "token_http_status={$status}\n";
echo "token_error={$error}\n";
echo "token_error_description={$desc}\n";

if ( $error === 'invalid_client' ) {
	echo "diagnosis=client_secret_or_service_id_misconfigured\n";
	exit( 1 );
}

if ( $error === 'invalid_grant' ) {
	echo "diagnosis=client_secret_ok (invalid_grant expected for fake code)\n";
	exit( 0 );
}

echo "diagnosis=unexpected_apple_response\n";
exit( 1 );
