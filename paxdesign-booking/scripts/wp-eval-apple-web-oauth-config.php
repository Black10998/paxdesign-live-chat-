<?php
/**
 * WP-CLI eval-file: configure Sign in with Apple web OAuth (Service ID + optional web key).
 *
 * Environment variables:
 *   PAX_APPLE_WEB_SERVICE_ID   — Service ID (default: at.paxdesign.web.login)
 *   PAX_APPLE_WEB_KEY_ID       — dedicated Sign in with Apple web Key ID
 *   PAX_APPLE_WEB_KEY_P8       — PEM .p8 contents (optional if BASE64 set)
 *   PAX_APPLE_WEB_KEY_P8_BASE64 — base64-encoded .p8 PEM (preferred for CI secrets)
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

/**
 * @return string
 */
function pax_apple_web_key_p8_from_env(): string {
	$raw = trim( (string) getenv( 'PAX_APPLE_WEB_KEY_P8' ) );
	if ( $raw !== '' ) {
		return $raw;
	}

	$b64 = trim( (string) getenv( 'PAX_APPLE_WEB_KEY_P8_BASE64' ) );
	if ( $b64 === '' ) {
		return '';
	}

	$decoded = base64_decode( preg_replace( '/\s+/', '', $b64 ), true );
	if ( ! is_string( $decoded ) || $decoded === '' ) {
		fwrite( STDERR, "Could not decode PAX_APPLE_WEB_KEY_P8_BASE64\n" );
		exit( 1 );
	}

	$decoded = str_replace( '\\n', "\n", trim( $decoded ) );
	if ( strpos( $decoded, '-----BEGIN PRIVATE KEY-----' ) === false ) {
		$decoded = "-----BEGIN PRIVATE KEY-----\n" . chunk_split( $decoded, 64, "\n" ) . "-----END PRIVATE KEY-----\n";
	}

	return $decoded;
}

$service_id = getenv( 'PAX_APPLE_WEB_SERVICE_ID' );
if ( ! is_string( $service_id ) || trim( $service_id ) === '' ) {
	$service_id = 'at.paxdesign.web.login';
}

$service_id = sanitize_text_field( trim( $service_id ) );
update_option( 'paxdesign_apple_web_service_id', $service_id, false );

$web_key_id = trim( (string) getenv( 'PAX_APPLE_WEB_KEY_ID' ) );
$web_key_p8 = pax_apple_web_key_p8_from_env();

if ( $web_key_id !== '' ) {
	update_option( 'paxdesign_apple_web_key_id', sanitize_text_field( $web_key_id ), false );
	echo 'Apple web Key ID: ' . $web_key_id . "\n";
} else {
	echo "Apple web Key ID: (unchanged — PAX_APPLE_WEB_KEY_ID not set)\n";
}

if ( $web_key_p8 !== '' ) {
	update_option( 'paxdesign_apple_web_key_p8', $web_key_p8, false );
	echo 'Apple web Key .p8: set(' . strlen( $web_key_p8 ) . " bytes)\n";
} else {
	echo "Apple web Key .p8: (unchanged — PAX_APPLE_WEB_KEY_P8 or PAX_APPLE_WEB_KEY_P8_BASE64 not set)\n";
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php';

$configured = PAXdesign_Auth_Apple::is_web_configured();
$callback   = PAXdesign_Auth_Apple::web_callback_url();
$web_cfg    = array();
if ( class_exists( 'PAXdesign_Auth_Apple' ) ) {
	$reflection = new ReflectionClass( 'PAXdesign_Auth_Apple' );
	$method     = $reflection->getMethod( 'web_config' );
	$method->setAccessible( true );
	$web_cfg = $method->invoke( null );
}

echo 'Apple web Service ID: ' . $service_id . "\n";
echo 'OAuth callback URL: ' . $callback . "\n";
echo 'Web OAuth active key_id: ' . (string) ( $web_cfg['key_id'] ?? '' ) . "\n";
echo 'Web OAuth configured: ' . ( $configured ? 'yes' : 'no (check Team ID and .p8 key)' ) . "\n";

if ( ! $configured ) {
	exit( 1 );
}
