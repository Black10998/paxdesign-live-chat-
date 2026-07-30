<?php
/**
 * Cybercrime portal locale helpers — merge English copy and build JS i18n bundle.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pax_ccs_merge_locale_en' ) ) {
	/**
	 * @param array<string, mixed> $base
	 * @param mixed                $en
	 * @return array<string, mixed>
	 */
	function pax_ccs_merge_locale_en( $base, $en ) {
		if ( ! is_array( $base ) ) {
			return $base;
		}
		if ( isset( $base['ar'], $base['de'] ) && is_string( $en ) ) {
			$base['en'] = $en;
			return $base;
		}
		if ( is_array( $en ) ) {
			foreach ( $en as $key => $value ) {
				if ( array_key_exists( $key, $base ) ) {
					$base[ $key ] = pax_ccs_merge_locale_en( $base[ $key ], $value );
				}
			}
		}
		return $base;
	}
}

if ( ! function_exists( 'pax_ccs_portal_copy' ) ) {
	/**
	 * @return array<string, mixed>
	 */
	function pax_ccs_portal_copy() {
		static $copy = null;
		if ( is_array( $copy ) ) {
			return $copy;
		}
		$copy = include get_template_directory() . '/template-parts/pages/cybercrime-support-data.php';
		if ( ! is_array( $copy ) ) {
			$copy = array();
		}
		$en_path = get_template_directory() . '/template-parts/pages/cybercrime-support-en.php';
		if ( is_readable( $en_path ) ) {
			$en = include $en_path;
			if ( is_array( $en ) ) {
				$copy = pax_ccs_merge_locale_en( $copy, $en );
			}
		}
		return $copy;
	}
}

if ( ! function_exists( 'pax_ccs_pick_lang' ) ) {
	/**
	 * @param array<string, mixed>|string $node
	 * @param string                      $lang
	 * @return string
	 */
	function pax_ccs_pick_lang( $node, $lang ) {
		if ( is_array( $node ) && isset( $node[ $lang ] ) ) {
			return (string) $node[ $lang ];
		}
		if ( is_array( $node ) && isset( $node['en'] ) ) {
			return (string) $node['en'];
		}
		return is_string( $node ) ? $node : '';
	}
}

if ( ! function_exists( 'pax_ccs_portal_i18n' ) ) {
	/**
	 * Build client-side i18n bundle for the cybercrime portal.
	 *
	 * @return array<string, mixed>
	 */
	function pax_ccs_portal_i18n() {
		$copy = pax_ccs_portal_copy();
		$langs = array( 'ar', 'de', 'en' );

		$pick = static function ( $node ) use ( $langs ) {
			$out = array();
			foreach ( $langs as $lang ) {
				$out[ $lang ] = pax_ccs_pick_lang( $node, $lang );
			}
			return $out;
		};

		$categories = array();
		foreach ( (array) ( $copy['categories'] ?? array() ) as $key => $labels ) {
			$categories[ $key ] = $pick( $labels );
		}

		$urgency = array();
		foreach ( (array) ( $copy['urgency'] ?? array() ) as $key => $labels ) {
			$urgency[ $key ] = $pick( $labels );
		}

		$status_badges = array();
		foreach ( (array) ( $copy['status_badges'] ?? array() ) as $key => $badge ) {
			if ( ! is_array( $badge ) ) {
				continue;
			}
			$status_badges[ $key ] = array(
				'emoji' => (string) ( $badge['emoji'] ?? '' ),
				'label' => $pick( $badge['label'] ?? array() ),
			);
		}

		$subjects = array();
		foreach ( (array) ( $copy['timeline_i18n']['subjects'] ?? array() ) as $key => $labels ) {
			$subjects[ $key ] = $pick( $labels );
		}

		$errors = array();
		foreach ( (array) ( $copy['portal_js']['errors'] ?? array() ) as $key => $labels ) {
			$errors[ $key ] = $pick( $labels );
		}

		$review = array();
		foreach ( (array) ( $copy['portal_js']['review'] ?? array() ) as $key => $labels ) {
			$review[ $key ] = $pick( $labels );
		}

		return array(
			'langs'           => $langs,
			'supportTeam'     => $pick( $copy['timeline_i18n']['support_team'] ?? array() ),
			'customerFallback'=> $pick( $copy['timeline_i18n']['customer_fallback'] ?? array() ),
			'emptyTimeline'   => $pick( $copy['timeline_i18n']['empty_timeline'] ?? array() ),
			'subjects'        => $subjects,
			'statusBadges'    => $status_badges,
			'statusBadgeMap'  => (array) ( $copy['status_badge_map'] ?? array() ),
			'categories'      => $categories,
			'urgency'         => $urgency,
			'errors'          => $errors,
			'review'          => $review,
		);
	}
}
