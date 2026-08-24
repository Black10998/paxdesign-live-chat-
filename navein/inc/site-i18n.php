<?php
/**
 * Site-wide locale: de / en / ar / tr.
 *
 * Resolution: manual cookie > ?lang= > Accept-Language > default de.
 * Arabic sets dir=rtl. Manual choices persist for one year.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'navein_site_i18n_supported' ) ) {
	/**
	 * @return string[]
	 */
	function navein_site_i18n_supported() {
		return array( 'de', 'en', 'ar', 'tr' );
	}
}

if ( ! function_exists( 'navein_site_i18n_normalize' ) ) {
	/**
	 * @param string $raw
	 * @return string Empty when unsupported.
	 */
	function navein_site_i18n_normalize( $raw ) {
		$code = strtolower( trim( (string) $raw ) );
		if ( $code === '' ) {
			return '';
		}
		if ( strpos( $code, '_' ) !== false ) {
			$code = strstr( $code, '_', true );
		}
		if ( strpos( $code, '-' ) !== false ) {
			$code = strstr( $code, '-', true );
		}
		$code = substr( $code, 0, 2 );
		return in_array( $code, navein_site_i18n_supported(), true ) ? $code : '';
	}
}

if ( ! function_exists( 'navein_site_i18n_detect_accept_language' ) ) {
	/**
	 * @param string $header
	 * @return string
	 */
	function navein_site_i18n_detect_accept_language( $header = '' ) {
		$header = $header !== '' ? $header : ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '' );
		if ( $header === '' ) {
			return '';
		}
		$parts = explode( ',', $header );
		foreach ( $parts as $part ) {
			$tag = trim( (string) $part );
			if ( $tag === '' ) {
				continue;
			}
			if ( strpos( $tag, ';' ) !== false ) {
				$tag = strstr( $tag, ';', true );
			}
			$normalized = navein_site_i18n_normalize( $tag );
			if ( $normalized !== '' ) {
				return $normalized;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'navein_site_i18n_cookie_lang' ) ) {
	/**
	 * @return string
	 */
	function navein_site_i18n_cookie_lang() {
		$raw = isset( $_COOKIE['pax_site_lang'] ) ? (string) $_COOKIE['pax_site_lang'] : '';
		if ( $raw !== '' && function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return navein_site_i18n_normalize( $raw );
	}
}

if ( ! function_exists( 'navein_site_i18n_cookie_source' ) ) {
	/**
	 * @return string auto|manual|''
	 */
	function navein_site_i18n_cookie_source() {
		$src = isset( $_COOKIE['pax_site_lang_src'] ) ? strtolower( preg_replace( '/[^a-z]/', '', (string) $_COOKIE['pax_site_lang_src'] ) ) : '';
		return ( $src === 'manual' || $src === 'auto' ) ? $src : '';
	}
}

if ( ! function_exists( 'navein_site_i18n_query_lang' ) ) {
	/**
	 * @return string
	 */
	function navein_site_i18n_query_lang() {
		$raw = isset( $_GET['lang'] ) ? (string) $_GET['lang'] : '';
		if ( $raw !== '' && function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		return navein_site_i18n_normalize( $raw );
	}
}

if ( ! function_exists( 'navein_site_i18n_resolve' ) ) {
	/**
	 * @return array{lang:string,source:string}
	 */
	function navein_site_i18n_resolve() {
		static $resolved = null;
		if ( is_array( $resolved ) ) {
			return $resolved;
		}

		$query = navein_site_i18n_query_lang();
		if ( $query !== '' ) {
			$resolved = array(
				'lang'   => $query,
				'source' => 'manual',
			);
			return $resolved;
		}

		$cookie = navein_site_i18n_cookie_lang();
		$src    = navein_site_i18n_cookie_source();
		if ( $cookie !== '' ) {
			$resolved = array(
				'lang'   => $cookie,
				'source' => $src === 'manual' ? 'manual' : 'auto',
			);
			return $resolved;
		}

		$detected = navein_site_i18n_detect_accept_language();
		if ( $detected !== '' ) {
			$resolved = array(
				'lang'   => $detected,
				'source' => 'auto',
			);
			return $resolved;
		}

		$resolved = array(
			'lang'   => 'de',
			'source' => 'auto',
		);
		return $resolved;
	}
}

if ( ! function_exists( 'navein_site_lang' ) ) {
	/**
	 * @return string de|en|ar|tr
	 */
	function navein_site_lang() {
		$resolved = navein_site_i18n_resolve();
		return $resolved['lang'];
	}
}

if ( ! function_exists( 'navein_site_lang_source' ) ) {
	/**
	 * @return string auto|manual
	 */
	function navein_site_lang_source() {
		$resolved = navein_site_i18n_resolve();
		return $resolved['source'];
	}
}

if ( ! function_exists( 'navein_site_dir' ) ) {
	/**
	 * @return string rtl|ltr
	 */
	function navein_site_dir() {
		return navein_site_lang() === 'ar' ? 'rtl' : 'ltr';
	}
}

if ( ! function_exists( 'navein_site_i18n_wp_locale' ) ) {
	/**
	 * @param string $lang
	 * @return string
	 */
	function navein_site_i18n_wp_locale( $lang ) {
		switch ( $lang ) {
			case 'ar':
				return 'ar';
			case 'en':
				return 'en_US';
			case 'tr':
				return 'tr_TR';
			default:
				return 'de_DE';
		}
	}
}

if ( ! function_exists( 'navein_site_i18n_persist' ) ) {
	/**
	 * @param string $lang
	 * @param string $source
	 */
	function navein_site_i18n_persist( $lang, $source ) {
		$lang   = navein_site_i18n_normalize( $lang );
		$source = $source === 'manual' ? 'manual' : 'auto';
		if ( $lang === '' || headers_sent() ) {
			return;
		}
		$ttl  = defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000;
		$opts = array(
			'expires'  => time() + $ttl,
			'path'     => '/',
			'secure'   => function_exists( 'is_ssl' ) ? is_ssl() : ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ),
			'httponly' => false,
			'samesite' => 'Lax',
		);
		setcookie( 'pax_site_lang', $lang, $opts );
		setcookie( 'pax_site_lang_src', $source, $opts );
		$_COOKIE['pax_site_lang']     = $lang;
		$_COOKIE['pax_site_lang_src'] = $source;
	}
}

if ( ! function_exists( 'navein_site_i18n_strings' ) ) {
	/**
	 * @return array<string, array<string, string>>
	 */
	function navein_site_i18n_strings() {
		static $strings = null;
		if ( is_array( $strings ) ) {
			return $strings;
		}
		$strings = array();
		$paths   = array();
		if ( function_exists( 'get_template_directory' ) ) {
			$paths[] = get_template_directory() . '/inc/site-i18n-strings.php';
		}
		$paths[] = __DIR__ . '/site-i18n-strings.php';
		foreach ( $paths as $path ) {
			if ( is_readable( $path ) ) {
				$loaded = include $path;
				if ( is_array( $loaded ) ) {
					$strings = $loaded;
					break;
				}
			}
		}
		return $strings;
	}
}

if ( ! function_exists( 'navein_t' ) ) {
	/**
	 * @param string $key
	 * @param string $fallback
	 * @param string $lang
	 * @return string
	 */
	function navein_t( $key, $fallback = '', $lang = '' ) {
		$lang  = $lang !== '' ? navein_site_i18n_normalize( $lang ) : navein_site_lang();
		$pack  = navein_site_i18n_strings();
		$entry = isset( $pack[ $key ] ) && is_array( $pack[ $key ] ) ? $pack[ $key ] : array();
		if ( $lang !== '' && ! empty( $entry[ $lang ] ) ) {
			return (string) $entry[ $lang ];
		}
		if ( ! empty( $entry['en'] ) ) {
			return (string) $entry['en'];
		}
		if ( ! empty( $entry['de'] ) ) {
			return (string) $entry['de'];
		}
		return $fallback !== '' ? $fallback : $key;
	}
}

if ( ! function_exists( 'navein_site_i18n_phrase_entry' ) ) {
	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, string>|null
	 */
	function navein_site_i18n_phrase_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return null;
		}
		if ( empty( $entry['de'] ) && empty( $entry['en'] ) ) {
			return null;
		}
		return array(
			'de' => isset( $entry['de'] ) ? (string) $entry['de'] : '',
			'en' => isset( $entry['en'] ) ? (string) $entry['en'] : '',
			'ar' => isset( $entry['ar'] ) ? (string) $entry['ar'] : '',
			'tr' => isset( $entry['tr'] ) ? (string) $entry['tr'] : '',
		);
	}
}

if ( ! function_exists( 'navein_site_i18n_phrases' ) ) {
	/**
	 * Exact UI phrases for DOM / HTML chrome replacement.
	 *
	 * @return array<int, array<string, string>>
	 */
	function navein_site_i18n_phrases() {
		$out = array();
		foreach ( navein_site_i18n_strings() as $entry ) {
			$phrase = navein_site_i18n_phrase_entry( $entry );
			if ( $phrase ) {
				$out[] = $phrase;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'navein_site_i18n_chrome_keys' ) ) {
	/**
	 * Header/footer/menu keys only — never dump the homepage catalog into every page.
	 *
	 * @return string[]
	 */
	function navein_site_i18n_chrome_keys() {
		return array(
			'nav_pricing',
			'nav_references',
			'nav_services',
			'nav_contact',
			'nav_cybercrime',
			'cta_request_offer',
			'sign_in',
			'search',
			'menu',
			'close',
			'imprint',
			'privacy',
			'terms',
			'company',
			'team',
			'career',
			'connect',
			'newsletter',
			'subscribe',
			'security_quality',
			'copyright',
			'home',
			'login',
			'account',
			'settings',
			'github_private',
			'github_not_oss',
			'understood',
			'cookie_accept',
			'cookie_decline',
			'read_more',
			'language',
			'choose_language',
		);
	}
}

if ( ! function_exists( 'navein_site_i18n_chrome_phrases' ) ) {
	/**
	 * @return array<int, array<string, string>>
	 */
	function navein_site_i18n_chrome_phrases() {
		$pack = navein_site_i18n_strings();
		$out  = array();
		foreach ( navein_site_i18n_chrome_keys() as $key ) {
			if ( empty( $pack[ $key ] ) || ! is_array( $pack[ $key ] ) ) {
				continue;
			}
			$phrase = navein_site_i18n_phrase_entry( $pack[ $key ] );
			if ( $phrase ) {
				$out[] = $phrase;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'navein_site_i18n_languages' ) ) {
	/**
	 * @return array<int, array<string, string>>
	 */
	function navein_site_i18n_languages() {
		return array(
			array(
				'code'   => 'de',
				'native' => 'Deutsch',
				'label'  => navein_t( 'lang_de', 'Deutsch' ),
			),
			array(
				'code'   => 'en',
				'native' => 'English',
				'label'  => navein_t( 'lang_en', 'English' ),
			),
			array(
				'code'   => 'ar',
				'native' => 'العربية',
				'label'  => navein_t( 'lang_ar', 'العربية' ),
			),
			array(
				'code'   => 'tr',
				'native' => 'Türkçe',
				'label'  => navein_t( 'lang_tr', 'Türkçe' ),
			),
		);
	}
}

if ( ! function_exists( 'navein_site_lang_switcher_markup' ) ) {
	/**
	 * Apple-style language control. Compact globe + code; popover with native names.
	 *
	 * @param string $instance desktop|mobile
	 * @return string
	 */
	function navein_site_lang_switcher_markup( $instance = 'desktop' ) {
		$lang      = navein_site_lang();
		$id        = $instance === 'mobile' ? 'pax-site-lang-mobile' : 'pax-site-lang';
		$code      = strtoupper( $lang );
		$label     = navein_t( 'language', 'Language' );
		$aria      = sprintf( '%s: %s', $label, $code );
		$globe     = '<svg class="pax-site-lang__globe-icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 12h18M12 3c2.6 3 3.8 6 3.8 9s-1.2 6-3.8 9c-2.6-3-3.8-6-3.8-9s1.2-6 3.8-9z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>';
		$html      = '<div id="' . esc_attr( $id ) . '" class="pax-site-lang pax-site-lang--' . esc_attr( $instance ) . '" data-pax-lang="' . esc_attr( $lang ) . '">';
		$html     .= '<button type="button" class="pax-site-lang__btn" aria-haspopup="listbox" aria-expanded="false" aria-label="' . esc_attr( $aria ) . '">';
		$html     .= '<span class="pax-site-lang__globe" aria-hidden="true">' . $globe . '</span>';
		$html     .= '<span class="pax-site-lang__code">' . esc_html( $code ) . '</span>';
		$html     .= '</button>';
		$html     .= '<div class="pax-site-lang__menu" role="listbox" aria-label="' . esc_attr( $label ) . '" hidden>';
		foreach ( navein_site_i18n_languages() as $item ) {
			$active = $item['code'] === $lang ? ' is-active' : '';
			$pressed = $item['code'] === $lang ? 'true' : 'false';
			$html .= '<button type="button" class="pax-site-lang__option' . $active . '" role="option" data-lang="' . esc_attr( $item['code'] ) . '" aria-selected="' . $pressed . '" title="' . esc_attr( $item['native'] ) . '">';
			$html .= '<span class="pax-site-lang__option-name">' . esc_html( $item['label'] ) . '</span>';
			$html .= '<span class="pax-site-lang__option-code">' . esc_html( strtoupper( $item['code'] ) ) . '</span>';
			$html .= '</button>';
		}
		$html .= '</div></div>';
		return $html;
	}
}

if ( ! function_exists( 'navein_site_i18n_apply_pairs_outside_skips' ) ) {
	/**
	 * str_replace visible HTML only. Never uses PCRE on the full document.
	 *
	 * @param string                $html
	 * @param array<string, string> $pairs
	 * @return string
	 */
	function navein_site_i18n_apply_pairs_outside_skips( $html, $pairs ) {
		if ( ! is_string( $html ) || $html === '' || ! $pairs ) {
			return is_string( $html ) ? $html : '';
		}

		$len = strlen( $html );
		$pos = 0;
		$out = '';
		$tags = array( 'script', 'style', 'textarea', 'noscript' );

		while ( $pos < $len ) {
			$next  = $len;
			$close = '';
			foreach ( $tags as $tag ) {
				$found = stripos( $html, '<' . $tag, $pos );
				if ( $found !== false && $found < $next ) {
					$next  = $found;
					$close = '</' . $tag . '>';
				}
			}

			$chunk = substr( $html, $pos, $next - $pos );
			$out  .= str_replace( array_keys( $pairs ), array_values( $pairs ), $chunk );
			if ( $next >= $len ) {
				break;
			}

			$end = stripos( $html, $close, $next );
			if ( $end === false ) {
				$out .= substr( $html, $next );
				break;
			}
			$end += strlen( $close );
			$out .= substr( $html, $next, $end - $next );
			$pos  = $end;
		}

		return $out;
	}
}

if ( ! function_exists( 'navein_site_i18n_replace_chrome' ) ) {
	/**
	 * Replace known chrome phrases in HTML without touching scripts or URLs.
	 * Always returns the original string if rewriting cannot run.
	 *
	 * @param string $html
	 * @return string
	 */
	function navein_site_i18n_replace_chrome( $html ) {
		$original = is_string( $html ) ? $html : '';
		if ( $original === '' ) {
			return $original;
		}

		$lang = navein_site_lang();
		if ( $lang === 'de' ) {
			return $original;
		}

		$phrases = function_exists( 'navein_site_i18n_chrome_phrases' )
			? navein_site_i18n_chrome_phrases()
			: navein_site_i18n_phrases();
		$pairs   = array();
		foreach ( $phrases as $phrase ) {
			$target = isset( $phrase[ $lang ] ) ? $phrase[ $lang ] : '';
			if ( $target === '' ) {
				continue;
			}
			foreach ( array( 'de', 'en', 'ar', 'tr' ) as $from ) {
				$source = isset( $phrase[ $from ] ) ? $phrase[ $from ] : '';
				if ( $source === '' || $source === $target || strlen( $source ) < 3 ) {
					continue;
				}
				$pairs[ '>' . $source . '<' ] = '>' . $target . '<';
			}
		}
		if ( ! $pairs ) {
			return $original;
		}

		uksort(
			$pairs,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$rewritten = navein_site_i18n_apply_pairs_outside_skips( $original, $pairs );
		return is_string( $rewritten ) && $rewritten !== '' ? $rewritten : $original;
	}
}
