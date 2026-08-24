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

if ( ! function_exists( 'pax_ccs_merge_locale_lang' ) ) {
	/**
	 * @param array<string, mixed> $base
	 * @param mixed                $overlay
	 * @param string               $lang
	 * @return array<string, mixed>
	 */
	function pax_ccs_merge_locale_lang( $base, $overlay, $lang ) {
		if ( ! is_array( $base ) ) {
			return $base;
		}
		if ( isset( $base['ar'], $base['de'] ) && is_string( $overlay ) ) {
			$base[ $lang ] = $overlay;
			return $base;
		}
		if ( is_array( $overlay ) ) {
			foreach ( $overlay as $key => $value ) {
				if ( array_key_exists( $key, $base ) ) {
					$base[ $key ] = pax_ccs_merge_locale_lang( $base[ $key ], $value, $lang );
				}
			}
		}
		return $base;
	}
}

if ( ! function_exists( 'pax_ccs_ensure_lang' ) ) {
	/**
	 * Copy fallback locale into missing language slots.
	 *
	 * @param mixed  $node
	 * @param string $lang
	 * @param string $fallback
	 * @return mixed
	 */
	function pax_ccs_ensure_lang( $node, $lang, $fallback = 'en' ) {
		if ( is_array( $node ) && ( isset( $node['ar'] ) || isset( $node['de'] ) || isset( $node['en'] ) ) ) {
			if ( ! isset( $node[ $lang ] ) || $node[ $lang ] === '' ) {
				if ( isset( $node[ $fallback ] ) && $node[ $fallback ] !== '' ) {
					$node[ $lang ] = $node[ $fallback ];
				} elseif ( isset( $node['en'] ) ) {
					$node[ $lang ] = $node['en'];
				} elseif ( isset( $node['de'] ) ) {
					$node[ $lang ] = $node['de'];
				} elseif ( isset( $node['ar'] ) ) {
					$node[ $lang ] = $node['ar'];
				}
			}
			return $node;
		}
		if ( is_array( $node ) ) {
			foreach ( $node as $key => $value ) {
				$node[ $key ] = pax_ccs_ensure_lang( $value, $lang, $fallback );
			}
		}
		return $node;
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
		$copy = array();
		$data_paths = array(
			get_template_directory() . '/template-parts/pages/cybercrime-support-data.php',
			get_stylesheet_directory() . '/template-parts/pages/cybercrime-support-data.php',
		);
		foreach ( $data_paths as $data_path ) {
			if ( ! is_readable( $data_path ) ) {
				continue;
			}
			$loaded = include $data_path;
			if ( is_array( $loaded ) ) {
				$copy = $loaded;
				break;
			}
		}
		$en_paths = array(
			get_template_directory() . '/template-parts/pages/cybercrime-support-en.php',
			get_stylesheet_directory() . '/template-parts/pages/cybercrime-support-en.php',
		);
		foreach ( $en_paths as $en_path ) {
			if ( ! is_readable( $en_path ) ) {
				continue;
			}
			$en = include $en_path;
			if ( is_array( $en ) ) {
				$copy = pax_ccs_merge_locale_en( $copy, $en );
			}
			break;
		}
		$tr_paths = array(
			get_template_directory() . '/template-parts/pages/cybercrime-support-tr.php',
			get_stylesheet_directory() . '/template-parts/pages/cybercrime-support-tr.php',
		);
		foreach ( $tr_paths as $tr_path ) {
			if ( ! is_readable( $tr_path ) ) {
				continue;
			}
			$tr = include $tr_path;
			if ( is_array( $tr ) ) {
				$copy = pax_ccs_merge_locale_lang( $copy, $tr, 'tr' );
			}
			break;
		}
		$copy = pax_ccs_ensure_lang( $copy, 'en', 'de' );
		$copy = pax_ccs_ensure_lang( $copy, 'tr', 'en' );
		return $copy;
	}
}

if ( ! function_exists( 'pax_ccs_countries' ) ) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function pax_ccs_countries() {
		static $countries = null;
		if ( is_array( $countries ) ) {
			return $countries;
		}
		$countries = array();
		$paths = array(
			get_template_directory() . '/inc/cybercrime-countries.php',
			get_stylesheet_directory() . '/inc/cybercrime-countries.php',
		);
		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$loaded = include $path;
			if ( is_array( $loaded ) ) {
				$countries = $loaded;
				break;
			}
		}
		return $countries;
	}
}

if ( ! function_exists( 'pax_ccs_countries_for_js' ) ) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function pax_ccs_countries_for_js() {
		$out = array();
		foreach ( pax_ccs_countries() as $country ) {
			if ( empty( $country['code'] ) ) {
				continue;
			}
			$out[] = array(
				'code' => (string) $country['code'],
				'dial' => (string) ( $country['dial'] ?? '' ),
				'flag' => (string) ( $country['flag'] ?? '' ),
				'name' => array(
					'ar' => pax_ccs_pick_lang( $country['name'] ?? array(), 'ar' ),
					'de' => pax_ccs_pick_lang( $country['name'] ?? array(), 'de' ),
					'en' => pax_ccs_pick_lang( $country['name'] ?? array(), 'en' ),
					'tr' => pax_ccs_pick_lang( $country['name'] ?? array(), 'tr' ),
				),
			);
		}
		return $out;
	}
}

if ( ! function_exists( 'pax_ccs_country_by_code' ) ) {
	/**
	 * @param string $code ISO 3166-1 alpha-2.
	 * @return array<string, mixed>|null
	 */
	function pax_ccs_country_by_code( $code ) {
		$code = strtoupper( sanitize_text_field( (string) $code ) );
		if ( $code === '' ) {
			return null;
		}
		foreach ( pax_ccs_countries() as $country ) {
			if ( strtoupper( (string) ( $country['code'] ?? '' ) ) === $code ) {
				return $country;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'pax_ccs_guess_visitor_country' ) ) {
	/**
	 * Best-effort ISO 3166-1 alpha-2 guess for phone/residence defaults.
	 *
	 * @return string
	 */
	function pax_ccs_guess_visitor_country() {
		$code = '';
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$code = strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
		}
		if ( $code && function_exists( 'pax_ccs_country_by_code' ) && pax_ccs_country_by_code( $code ) ) {
			return $code;
		}
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( is_string( $locale ) && preg_match( '/[_-]([A-Z]{2})$/i', $locale, $m ) ) {
			$code = strtoupper( $m[1] );
			if ( function_exists( 'pax_ccs_country_by_code' ) && pax_ccs_country_by_code( $code ) ) {
				return $code;
			}
		}
		return 'AT';
	}
}

if ( ! function_exists( 'pax_ccs_pick_lang' ) ) {
	/**
	 * @param array<string, mixed>|string $node
	 * @param string                      $lang
	 * @return string
	 */
	function pax_ccs_pick_lang( $node, $lang ) {
		if ( is_array( $node ) && isset( $node[ $lang ] ) && $node[ $lang ] !== '' ) {
			return (string) $node[ $lang ];
		}
		if ( is_array( $node ) && isset( $node['en'] ) ) {
			return (string) $node['en'];
		}
		if ( is_array( $node ) && isset( $node['de'] ) ) {
			return (string) $node['de'];
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
		$langs = array( 'ar', 'de', 'en', 'tr' );

		$pick = function ( $node ) use ( $langs ) {
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

		$account_email = array();
		foreach ( (array) ( $copy['portal_js']['account_email'] ?? array() ) as $key => $labels ) {
			$account_email[ $key ] = $pick( $labels );
		}

		$history = array();
		foreach ( (array) ( $copy['ticket_history'] ?? array() ) as $key => $labels ) {
			$history[ $key ] = $pick( $labels );
		}

		$guided = array();
		foreach ( (array) ( $copy['guided'] ?? array() ) as $key => $labels ) {
			$guided[ $key ] = $pick( $labels );
		}

		$coach = array();
		foreach ( (array) ( $copy['evidence_coach'] ?? array() ) as $key => $labels ) {
			$coach[ $key ] = $pick( $labels );
		}

		return array(
			'langs'           => $langs,
			'supportTeam'     => $pick( $copy['timeline_i18n']['support_team'] ?? array() ),
			'customerFallback'=> $pick( $copy['timeline_i18n']['customer_fallback'] ?? array() ),
			'emptyTimeline'   => $pick( $copy['timeline_i18n']['empty_timeline'] ?? array() ),
			'timeline'        => array(
				'statusChanged' => $pick( $copy['timeline_i18n']['status_changed'] ?? array() ),
			),
			'subjects'        => $subjects,
			'statusBadges'    => $status_badges,
			'statusBadgeMap'  => (array) ( $copy['status_badge_map'] ?? array() ),
			'categories'      => $categories,
			'urgency'         => $urgency,
			'errors'          => $errors,
			'review'          => $review,
			'accountEmail'    => $account_email,
			'ticketHistory'   => $history,
			'guided'          => $guided,
			'evidenceCoach'   => $coach,
			'activeReport'    => array(
				'closed_title'       => $pick( $copy['active_report']['closed_title'] ?? array() ),
				'read_only'          => $pick( $copy['active_report']['read_only'] ?? array() ),
				'open_new_report'    => $pick( $copy['active_report']['open_new_report'] ?? array() ),
				'back_history'       => $pick( $copy['active_report']['back_history'] ?? array() ),
				'original_heading'   => $pick( $copy['active_report']['original_heading'] ?? array() ),
				'checks_heading'     => $pick( $copy['active_report']['checks_heading'] ?? array() ),
				'checks_disclaimer'  => $pick( $copy['active_report']['checks_disclaimer'] ?? array() ),
				'next_heading'       => $pick( $copy['active_report']['next_heading'] ?? array() ),
				'continue_form'      => $pick( $copy['active_report']['continue_form'] ?? array() ),
				'check_accepted'     => $pick( $copy['active_report']['check_accepted'] ?? array() ),
				'check_rejected'     => $pick( $copy['active_report']['check_rejected'] ?? array() ),
				'check_review'       => $pick( $copy['active_report']['check_review'] ?? array() ),
				'resubmit_heading'   => $pick( $copy['active_report']['resubmit_heading'] ?? array() ),
				'evidence_success'   => $pick( $copy['active_report']['evidence_success'] ?? array() ),
				'evidence_request_inline' => $pick( $copy['active_report']['evidence_request_inline'] ?? array() ),
				'evidence_request_hint' => $pick( $copy['active_report']['evidence_request_hint'] ?? array() ),
				'evidence_request_action' => $pick( $copy['active_report']['evidence_request_action'] ?? array() ),
				'rejection_heading'  => $pick( $copy['active_report']['rejection_heading'] ?? array() ),
				'rejected_next_heading' => $pick( $copy['active_report']['rejected_next_heading'] ?? array() ),
				'rejected_next'      => $pick( $copy['active_report']['rejected_next'] ?? array() ),
			),
		);
	}
}
