<?php
/**
 * WP-CLI eval-file: configure Sign in with Apple web OAuth Service ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

$service_id = getenv( 'PAX_APPLE_WEB_SERVICE_ID' );
if ( ! is_string( $service_id ) || trim( $service_id ) === '' ) {
	$service_id = 'at.paxdesign.web.login';
}

$service_id = sanitize_text_field( trim( $service_id ) );
update_option( 'paxdesign_apple_web_service_id', $service_id, false );

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php';

$configured = PAXdesign_Auth_Apple::is_web_configured();
$callback   = PAXdesign_Auth_Apple::web_callback_url();

echo 'Apple web Service ID: ' . $service_id . "\n";
echo 'OAuth callback URL: ' . $callback . "\n";
echo 'Web OAuth configured: ' . ( $configured ? 'yes' : 'no (check APNs Team ID, Key ID, and .p8 key)' ) . "\n";

if ( ! $configured ) {
	exit( 1 );
}
