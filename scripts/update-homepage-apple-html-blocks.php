<?php
/**
 * Update Elementor HTML widgets on the homepage with redesigned Apple blocks.
 *
 * Env:
 * - PAX_HOME_PAGE_ID
 * - PAX_HOME_BLOCKS_DIR
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run via wp eval-file\n" );
	exit( 1 );
}

$page_id    = absint( getenv( 'PAX_HOME_PAGE_ID' ) );
$blocks_dir = (string) getenv( 'PAX_HOME_BLOCKS_DIR' );

if ( ! $page_id || ! $blocks_dir || ! is_dir( $blocks_dir ) ) {
	fwrite( STDERR, "Missing page id or blocks dir\n" );
	exit( 1 );
}

$map = array(
	'f532777' => $blocks_dir . '/01-hero.html',
	'7b41c73' => $blocks_dir . '/02-services.html',
	'094393f' => $blocks_dir . '/03-showcase.html',
	'96ca48a' => $blocks_dir . '/04-tech-marquee.html',
);

$html_by_id = array();
foreach ( $map as $id => $path ) {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Missing block file: {$path}\n" );
		exit( 1 );
	}
	$html_by_id[ $id ] = file_get_contents( $path );
}

$raw = get_post_meta( $page_id, '_elementor_data', true );
if ( empty( $raw ) ) {
	fwrite( STDERR, "No _elementor_data on page {$page_id}\n" );
	exit( 1 );
}

$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "Could not decode _elementor_data\n" );
	exit( 1 );
}

$updated = 0;

$walker = function ( &$elements ) use ( &$walker, &$updated, $html_by_id ) {
	if ( ! is_array( $elements ) ) {
		return;
	}
	foreach ( $elements as &$el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		$id = isset( $el['id'] ) ? (string) $el['id'] : '';
		$widget_type = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
		$el_type = isset( $el['elType'] ) ? (string) $el['elType'] : '';
		if ( $id && isset( $html_by_id[ $id ] ) && ( $widget_type === 'html' || $el_type === 'widget' ) ) {
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
				$el['settings'] = array();
			}
			$el['settings']['html'] = $html_by_id[ $id ];
			$el['widgetType'] = 'html';
			$updated++;
			WP_CLI::log( "Updated HTML widget {$id}" );
		}
		if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
			$walker( $el['elements'] );
		}
	}
};

$walker( $data );

if ( $updated < 1 ) {
	fwrite( STDERR, "No matching HTML widgets updated\n" );
	exit( 1 );
}

$encoded = wp_json_encode( $data );
if ( ! $encoded ) {
	fwrite( STDERR, "Failed encoding elementor data\n" );
	exit( 1 );
}

update_post_meta( $page_id, '_elementor_data', wp_slash( $encoded ) );
// Keep Elementor in charge of homepage (unlike product pages).
update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );

WP_CLI::success( "Homepage HTML blocks updated ({$updated})" );
