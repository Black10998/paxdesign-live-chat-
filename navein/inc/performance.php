<?php
/**
 * Theme performance helpers — conditional assets and non-blocking scripts.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'navein_needs_legacy_grid_scripts' ) ) {
	/**
	 * Portfolio/blog grid scripts (isotope, masonry) are only used on listing views.
	 */
	function navein_needs_legacy_grid_scripts() {
		return is_home() || is_archive() || is_search() || is_category() || is_tag();
	}
}

if ( ! function_exists( 'navein_is_apple_primary_route' ) ) {
	/**
	 * Primary Apple templates that replace legacy mobile nav (slicknav).
	 */
	function navein_is_apple_primary_route() {
		if ( is_front_page() ) {
			return true;
		}
		$slugs = array(
			'app-entwicklung',
			'advanced-website-systems',
			'softwareentwicklung',
			'wartung-support',
			'webentwicklung',
			'cybercrime-support',
			'impressum',
			'unsere-experten',
			'it-consulting',
		);
		foreach ( $slugs as $slug ) {
			if ( is_page( $slug ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'navein_dequeue_unused_frontend_assets' ) ) {
	function navein_dequeue_unused_frontend_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( ! navein_needs_legacy_grid_scripts() ) {
			wp_dequeue_script( 'imagesloaded' );
			wp_dequeue_script( 'masonry' );
			wp_dequeue_script( 'isotope' );
		}

		if ( navein_is_apple_primary_route() ) {
			wp_dequeue_script( 'slicknav' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'navein_dequeue_unused_frontend_assets', 100 );

if ( ! function_exists( 'navein_defer_theme_scripts' ) ) {
	function navein_defer_theme_scripts() {
		if ( is_admin() ) {
			return;
		}

		$defer_handles = array(
			'jquery-easing',
			'navein-custom-js',
			'navein-mega-menu',
			'navein-apple-sticky-header',
			'navein-apple-mobile-nav',
			'navein-apple-footer',
			'navein-apple-app-page',
			'navein-apple-cybercrime-support',
			'navein-apple-impressum',
			'navein-apple-experts',
			'navein-apple-homepage',
		);

		foreach ( $defer_handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'navein_defer_theme_scripts', 10001 );

if ( ! function_exists( 'navein_resource_hints' ) ) {
	function navein_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type || is_admin() ) {
			return $urls;
		}
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		return $urls;
	}
}
add_filter( 'wp_resource_hints', 'navein_resource_hints', 10, 2 );
