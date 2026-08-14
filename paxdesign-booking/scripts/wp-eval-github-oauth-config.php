<?php
/**
 * WP-CLI eval-file: configure Continue with GitHub OAuth (server-side secret).
 *
 * Environment variables:
 *   PAX_GITHUB_CLIENT_ID     — public Client ID (default: Ov23lixDUzzbRfCy1a1a)
 *   PAX_GITHUB_CLIENT_SECRET — client secret (never printed)
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

$client_id = trim( (string) getenv( 'PAX_GITHUB_CLIENT_ID' ) );
if ( $client_id === '' ) {
	$client_id = 'Ov23lixDUzzbRfCy1a1a';
}
update_option( 'paxdesign_github_client_id', sanitize_text_field( $client_id ), false );

$secret = trim( (string) getenv( 'PAX_GITHUB_CLIENT_SECRET' ) );
if ( $secret !== '' ) {
	update_option( 'paxdesign_github_client_secret', sanitize_text_field( $secret ), false );
} elseif ( class_exists( 'PAXdesign_Auth_GitHub' ) ) {
	PAXdesign_Auth_GitHub::maybe_seed_credentials();
}

$configured = class_exists( 'PAXdesign_Auth_GitHub' ) && PAXdesign_Auth_GitHub::is_configured();
$callback   = class_exists( 'PAXdesign_Auth_GitHub' ) ? PAXdesign_Auth_GitHub::callback_url() : '';

echo 'github_client_id=' . $client_id . "\n";
echo 'github_secret_configured=' . ( $configured ? '1' : '0' ) . "\n";
echo 'github_callback_url=' . $callback . "\n";
