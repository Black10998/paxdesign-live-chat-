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

if ( ! function_exists( 'navein_site_i18n_resolve_from' ) ) {
	/**
	 * Manual cookie always wins. Query is an explicit choice only when no manual cookie exists.
	 * Auto-detect runs only before the visitor has a stored language.
	 *
	 * @param string $query
	 * @param string $cookie_lang
	 * @param string $cookie_src auto|manual|''
	 * @param string $detected
	 * @return array{lang:string,source:string}
	 */
	function navein_site_i18n_resolve_from( $query, $cookie_lang, $cookie_src, $detected ) {
		$query       = navein_site_i18n_normalize( $query );
		$cookie_lang = navein_site_i18n_normalize( $cookie_lang );
		$cookie_src  = ( $cookie_src === 'manual' || $cookie_src === 'auto' ) ? $cookie_src : '';
		$detected    = navein_site_i18n_normalize( $detected );

		if ( $cookie_lang !== '' && $cookie_src === 'manual' ) {
			return array(
				'lang'   => $cookie_lang,
				'source' => 'manual',
			);
		}

		if ( $query !== '' ) {
			return array(
				'lang'   => $query,
				'source' => 'manual',
			);
		}

		if ( $cookie_lang !== '' ) {
			return array(
				'lang'   => $cookie_lang,
				'source' => $cookie_src === 'manual' ? 'manual' : 'auto',
			);
		}

		if ( $detected !== '' ) {
			return array(
				'lang'   => $detected,
				'source' => 'auto',
			);
		}

		return array(
			'lang'   => 'de',
			'source' => 'auto',
		);
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

		$resolved = navein_site_i18n_resolve_from(
			navein_site_i18n_query_lang(),
			navein_site_i18n_cookie_lang(),
			navein_site_i18n_cookie_source(),
			navein_site_i18n_detect_accept_language()
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
		$files   = array( 'site-i18n-strings.php', 'site-i18n-pages.php', 'site-i18n-content.php', 'site-i18n-inner.php' );
		$bases   = array();
		if ( function_exists( 'get_template_directory' ) ) {
			$bases[] = get_template_directory() . '/inc';
		}
		$bases[] = __DIR__;
		foreach ( $files as $file ) {
			foreach ( $bases as $base ) {
				$path = $base . '/' . $file;
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$loaded = include $path;
				if ( is_array( $loaded ) ) {
					$strings = array_merge( $strings, $loaded );
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

if ( ! function_exists( 'navein_site_i18n_fold_text' ) ) {
	/**
	 * Normalize visible copy for catalog lookup (entities, NBSP, padding).
	 *
	 * @param string $s
	 * @return string
	 */
	function navein_site_i18n_fold_text( $s ) {
		$s = html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( "\xC2\xA0", ' ', $s );
		$folded = preg_replace( '/\s+/u', ' ', $s );
		return is_string( $folded ) ? trim( $folded ) : trim( $s );
	}
}

if ( ! function_exists( 'navein_site_i18n_replace_bounded' ) ) {
	/**
	 * Replace $needle in $haystack only on Unicode letter/number boundaries.
	 * Prevents "oder" from corrupting "moderne" in document titles.
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param string $replacement
	 * @return string
	 */
	function navein_site_i18n_replace_bounded( $haystack, $needle, $replacement ) {
		if ( ! is_string( $haystack ) || $haystack === '' || $needle === '' ) {
			return is_string( $haystack ) ? $haystack : '';
		}
		if ( strpos( $haystack, $needle ) === false ) {
			return $haystack;
		}
		$quoted = preg_quote( $needle, '/' );
		$out    = preg_replace( '/(?<![\p{L}\p{N}])' . $quoted . '(?![\p{L}\p{N}])/u', $replacement, $haystack );
		return is_string( $out ) ? $out : $haystack;
	}
}

if ( ! function_exists( 'navein_site_i18n_replace_trimmed_in_chunk' ) ) {
	/**
	 * Replace catalog phrases in one visible HTML chunk when they are the
	 * trimmed text of a tag, including padded header/button copy.
	 *
	 * @param string                $chunk
	 * @param array<string, string> $lookup exact source => target
	 * @return string
	 */
	function navein_site_i18n_replace_trimmed_in_chunk( $chunk, $lookup ) {
		if ( ! is_string( $chunk ) || $chunk === '' || ! $lookup ) {
			return is_string( $chunk ) ? $chunk : '';
		}

		$out = preg_replace_callback(
			'/>([^<]+)</',
			static function ( $m ) use ( $lookup ) {
				$inner = $m[1];
				$trim  = trim( $inner );
				if ( $trim === '' ) {
					return $m[0];
				}
				$decoded = html_entity_decode( $trim, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$folded  = function_exists( 'navein_site_i18n_fold_text' ) ? navein_site_i18n_fold_text( $inner ) : $decoded;
				$target  = '';
				if ( $folded !== '' && isset( $lookup[ $folded ] ) ) {
					$target = $lookup[ $folded ];
				} elseif ( isset( $lookup[ $decoded ] ) ) {
					$target = $lookup[ $decoded ];
				} elseif ( isset( $lookup[ $trim ] ) ) {
					$target = $lookup[ $trim ];
				}
				if ( $target === '' || $target === $decoded || $target === $trim || $target === $folded ) {
					return $m[0];
				}
				$lead  = strlen( $inner ) - strlen( ltrim( $inner ) );
				$trail = strlen( $inner ) - strlen( rtrim( $inner ) );
				$enc   = htmlspecialchars( $target, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$new   = substr( $inner, 0, $lead ) . $enc . ( $trail > 0 ? substr( $inner, -$trail ) : '' );
				return '>' . $new . '<';
			},
			$chunk
		);

		return is_string( $out ) && $out !== '' ? $out : $chunk;
	}
}

if ( ! function_exists( 'navein_site_i18n_apply_pairs_outside_skips' ) ) {
	/**
	 * str_replace visible HTML only. Never uses PCRE on the full document.
	 *
	 * @param string                $html
	 * @param array<string, string> $pairs
	 * @param array<string, string> $lookup
	 * @return string
	 */
	function navein_site_i18n_apply_pairs_outside_skips( $html, $pairs, $lookup = array() ) {
		if ( ! is_string( $html ) || $html === '' || ( ! $pairs && ! $lookup ) ) {
			return is_string( $html ) ? $html : '';
		}

		$len  = strlen( $html );
		$pos  = 0;
		$out  = '';
		$tags = array( 'script', 'style', 'textarea', 'noscript', 'svg' );

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
			if ( $pairs ) {
				$chunk = str_replace( array_keys( $pairs ), array_values( $pairs ), $chunk );
			}
			if ( $lookup ) {
				$chunk = navein_site_i18n_replace_trimmed_in_chunk( $chunk, $lookup );
			}
			$out .= $chunk;
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

		$phrases = navein_site_i18n_phrases();
		$pairs   = array();
		$lookup  = array();
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
				$folded                          = navein_site_i18n_fold_text( $source );
				$lookup[ $source ]               = $target;
				if ( $folded !== '' ) {
					$lookup[ $folded ] = $target;
				}
				$pairs[ '>' . $source . '<' ]    = '>' . $target . '<';
				$enc_source                      = htmlspecialchars( $source, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$enc_target                      = htmlspecialchars( $target, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( $enc_source !== $source ) {
					$lookup[ $enc_source ]                 = $target;
					$pairs[ '>' . $enc_source . '<' ]      = '>' . $enc_target . '<';
				}
				$amp_source = str_replace( '&amp;', '&#038;', $enc_source );
				$amp_target = str_replace( '&amp;', '&#038;', $enc_target );
				$pairs[ '="' . $source . '"' ] = '="' . $enc_target . '"';
				$pairs[ "='" . $source . "'" ] = "='" . $enc_target . "'";
				if ( $enc_source !== $source ) {
					$pairs[ '="' . $enc_source . '"' ] = '="' . $enc_target . '"';
					$pairs[ "='" . $enc_source . "'" ] = "='" . $enc_target . "'";
				}
				if ( $amp_source !== $enc_source ) {
					$pairs[ '="' . $amp_source . '"' ] = '="' . $amp_target . '"';
					$pairs[ "='" . $amp_source . "'" ] = "='" . $amp_target . "'";
					$pairs[ '>' . $amp_source . '<' ]  = '>' . $enc_target . '<';
				}
				if ( strlen( $source ) >= 18 ) {
					$pairs[ $source ]     = $target;
					$pairs[ $enc_source ] = $enc_target;
					if ( $amp_source !== $enc_source ) {
						$pairs[ $amp_source ] = $amp_target;
					}
				}
			}
		}
		if ( ! $pairs && ! $lookup ) {
			return $original;
		}

		uksort(
			$pairs,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$rewritten = navein_site_i18n_apply_pairs_outside_skips( $original, $pairs, $lookup );
		if ( ! is_string( $rewritten ) || $rewritten === '' ) {
			return $original;
		}
		if ( strlen( $rewritten ) < (int) ( strlen( $original ) * 0.5 ) ) {
			return $original;
		}

		$rewritten = preg_replace_callback(
			'#(<title>)([^<]*)(</title>)#i',
			static function ( $m ) use ( $phrases, $lang ) {
				$title = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$swaps = array();
				foreach ( $phrases as $phrase ) {
					$target = isset( $phrase[ $lang ] ) ? $phrase[ $lang ] : '';
					if ( $target === '' ) {
						continue;
					}
					foreach ( array( 'de', 'en', 'ar', 'tr' ) as $from ) {
						$source = isset( $phrase[ $from ] ) ? $phrase[ $from ] : '';
						if ( $source === '' || $source === $target || strlen( $source ) < 4 ) {
							continue;
						}
						$swaps[ $source ] = $target;
					}
				}
				uksort(
					$swaps,
					static function ( $a, $b ) {
						return strlen( $b ) - strlen( $a );
					}
				);
				foreach ( $swaps as $source => $target ) {
					$title = navein_site_i18n_replace_bounded( $title, $source, $target );
				}
				return $m[1] . htmlspecialchars( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . $m[3];
			},
			$rewritten,
			1
		);

		return is_string( $rewritten ) && $rewritten !== '' ? $rewritten : $original;
	}
}
