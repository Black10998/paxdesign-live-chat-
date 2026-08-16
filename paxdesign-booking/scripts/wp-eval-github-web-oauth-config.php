<?php
/**
 * WP-CLI eval-file: configure Sign in with GitHub web OAuth (Client ID + Secret).
 *
 * Environment variables:
 *   PAX_GITHUB_OAUTH_CLIENT_ID     — GitHub OAuth App Client ID
 *   PAX_GITHUB_OAUTH_CLIENT_SECRET — GitHub OAuth App Client Secret
 *   GITHUB_OAUTH_CLIENT_ID         — fallback Client ID
 *   GITHUB_OAUTH_CLIENT_SECRET     — fallback Client Secret
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

/**
 * @param array<int, string> $keys
 * @return string
 */
function pax_github_oauth_env( array $keys ): string {
	foreach ( $keys as $key ) {
		$value = getenv( $key );
		if ( is_string( $value ) && trim( $value ) !== '' ) {
			return trim( $value );
		}
	}
	return '';
}

$client_id = sanitize_text_field(
	pax_github_oauth_env( array( 'PAX_GITHUB_OAUTH_CLIENT_ID', 'GITHUB_OAUTH_CLIENT_ID' ) )
);
$client_secret = pax_github_oauth_env( array( 'PAX_GITHUB_OAUTH_CLIENT_SECRET', 'GITHUB_OAUTH_CLIENT_SECRET' ) );

if ( $client_id !== '' ) {
	update_option( 'paxdesign_github_oauth_client_id', $client_id, false );
	echo 'GitHub OAuth Client ID: ' . $client_id . "\n";
} else {
	echo "GitHub OAuth Client ID: (unchanged — secret not set)\n";
}

if ( $client_secret !== '' ) {
	update_option( 'paxdesign_github_oauth_client_secret', $client_secret, false );
	echo 'GitHub OAuth Client Secret: set(' . strlen( $client_secret ) . " chars)\n";
} else {
	echo "GitHub OAuth Client Secret: (unchanged — secret not set)\n";
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-github.php';

$configured = class_exists( 'PAXdesign_Auth_GitHub' ) && PAXdesign_Auth_GitHub::is_web_configured();
$callback   = class_exists( 'PAXdesign_Auth_GitHub' ) ? PAXdesign_Auth_GitHub::web_callback_url() : '';

echo 'configured=' . ( $configured ? 'yes' : 'no' ) . "\n";
echo 'callback=' . $callback . "\n";
echo 'start=' . ( class_exists( 'PAXdesign_Auth_GitHub' ) ? PAXdesign_Auth_GitHub::web_start_url() : '' ) . "\n";
