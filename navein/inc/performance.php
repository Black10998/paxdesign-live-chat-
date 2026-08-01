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

if ( ! function_exists( 'navein_page_has_contact_form' ) ) {
	/**
	 * Whether the current singular view embeds Contact Form 7.
	 */
	function navein_page_has_contact_form() {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}
		if ( has_shortcode( $post->post_content, 'contact-form-7' ) ) {
			return true;
		}
		return function_exists( 'has_block' ) && has_block( 'contact-form-7/contact-form-selector', $post );
	}
}

if ( ! function_exists( 'navein_home_lcp_image_url' ) ) {
	/**
	 * LCP hero image on the Apple homepage (must match homepage.php).
	 */
	function navein_home_lcp_image_url() {
		return 'https://paxdesign.at/wp-content/uploads/2026/01/code-2558220_1280.avif';
	}
}

if ( ! function_exists( 'navein_preload_lcp_image' ) ) {
	/**
	 * Preload the homepage hero image to improve mobile LCP.
	 */
	function navein_preload_lcp_image() {
		if ( ! is_front_page() ) {
			return;
		}
		$hero = navein_home_lcp_image_url();
		printf(
			'<link rel="preload" as="image" href="%s" type="image/avif" fetchpriority="high">' . "\n",
			esc_url( $hero )
		);
	}
}
add_action( 'wp_head', 'navein_preload_lcp_image', 1 );

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

		if ( is_front_page() ) {
			wp_dequeue_style( 'navein-apple-inner-page-title' );
		}

		if ( navein_is_apple_primary_route() ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'classic-theme-styles' );
			wp_dequeue_style( 'font-awesome-5-all' );
			wp_dequeue_style( 'font-awesome' );
		}

		if ( ! navein_page_has_contact_form() ) {
			wp_dequeue_style( 'contact-form-7' );
			wp_dequeue_script( 'contact-form-7' );
			wp_dequeue_script( 'swv' );
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
			'hoverIntent',
			'superfish',
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
			'contact-form-7',
			'swv',
		);

		foreach ( $defer_handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'navein_defer_theme_scripts', 10001 );

if ( ! function_exists( 'navein_async_stylesheet_handles' ) ) {
	/**
	 * Stylesheets safe to load asynchronously (below-fold / non-LCP).
	 *
	 * @return string[]
	 */
	function navein_async_stylesheet_handles() {
		return array(
			'bootstrap',
			'iconfont',
			'wp-block-library',
			'wp-block-library-theme',
			'classic-theme-styles',
			'contact-form-7',
			'font-awesome-5-all',
			'font-awesome',
			'navein-apple-footer',
			'navein-apple-hover',
			'navein-apple-mobile-nav',
			'navein-apple-inner-page-title',
			'navein-responsive',
			'navein-inline-style',
			'navein-fallback-font',
			'redux-fallback',
			'paxdesign-booking-styles',
		);
	}
}

if ( ! function_exists( 'navein_async_stylesheet_tag' ) ) {
	/**
	 * Load non-critical CSS without blocking first paint.
	 */
	function navein_async_stylesheet_tag( $html, $handle, $href, $media ) {
		if ( is_admin() || ! in_array( $handle, navein_async_stylesheet_handles(), true ) ) {
			return $html;
		}
		if ( false === strpos( $html, "rel='stylesheet'" ) && false === strpos( $html, 'rel="stylesheet"' ) ) {
			return $html;
		}

		$async = str_replace(
			array( "media='all'", 'media="all"' ),
			array( "media='print' onload=\"this.media='all'\"", 'media="print" onload="this.media=\'all\'"' ),
			$html
		);

		return $async . '<noscript>' . $html . '</noscript>';
	}
}
add_filter( 'style_loader_tag', 'navein_async_stylesheet_tag', 10, 4 );

if ( ! function_exists( 'navein_resource_hints' ) ) {
	function navein_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type || is_admin() ) {
			return $urls;
		}
		$urls[] = array(
			'href'        => 'https://fonts.googleapis.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
		return $urls;
	}
}
add_filter( 'wp_resource_hints', 'navein_resource_hints', 10, 2 );

if ( ! function_exists( 'navein_google_fonts_display_swap' ) ) {
	/**
	 * Ensure fallback Google Fonts use font-display: swap.
	 *
	 * @param string $url Fonts stylesheet URL.
	 * @return string
	 */
	function navein_google_fonts_display_swap( $url ) {
		if ( ! is_string( $url ) || $url === '' || false === strpos( $url, 'fonts.googleapis.com' ) ) {
			return $url;
		}
		return add_query_arg( 'display', 'swap', $url );
	}
}
add_filter( 'navein_fallback_fonts_url', 'navein_google_fonts_display_swap' );
