<?php
/**
 * WP-CLI eval-file: print recent Sign in with Apple web OAuth trace + log tail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

$trace = get_option( 'paxdesign_apple_oauth_trace', array() );
if ( ! is_array( $trace ) ) {
	$trace = array();
}

echo "=== Apple OAuth trace (last " . count( $trace ) . " events) ===\n";
foreach ( $trace as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	echo wp_json_encode( $row, JSON_UNESCAPED_SLASHES ) . "\n";
}

$debug_log = WP_CONTENT_DIR . '/debug.log';
echo "=== debug.log tail (apple/auth) ===\n";
if ( is_readable( $debug_log ) ) {
	$lines = @file( $debug_log, FILE_IGNORE_NEW_LINES );
	if ( is_array( $lines ) ) {
		$tail = array_slice( $lines, -400 );
		foreach ( $tail as $line ) {
			if ( stripos( $line, 'apple' ) === false && stripos( $line, 'PAXdesign Auth' ) === false ) {
				continue;
			}
			echo $line . "\n";
		}
	}
} else {
	echo "debug.log not readable\n";
}
