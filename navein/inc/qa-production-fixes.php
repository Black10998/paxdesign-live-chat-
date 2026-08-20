<?php
/**
 * Production QA content/URL/header fixes that do not require an iOS rebuild.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pax_qa_request_path' ) ) {
	/**
	 * @return string Request path without leading/trailing slashes.
	 */
	function pax_qa_request_path() {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
		return trim( $path, '/' );
	}
}

if ( ! function_exists( 'pax_qa_broken_path_redirects' ) ) {
	/**
	 * Send visitors from known-dead marketing URLs to the live pages.
	 */
	function pax_qa_broken_path_redirects() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$path = pax_qa_request_path();
		$map  = array(
			'projektpreise' => home_url( '/preise/' ),
			'team'          => home_url( '/unsere-experten/' ),
			'en'            => home_url( '/' ),
			'ar'            => home_url( '/' ),
		);
		if ( ! isset( $map[ $path ] ) ) {
			return;
		}
		wp_safe_redirect( $map[ $path ], 301 );
		exit;
	}
}
add_action( 'template_redirect', 'pax_qa_broken_path_redirects', 0 );

if ( ! function_exists( 'pax_qa_nav_menu_href_fix' ) ) {
	/**
	 * Rewrite stale menu URLs even if WordPress still stores the old slugs.
	 *
	 * @param array $atts Link attributes.
	 * @return array
	 */
	function pax_qa_nav_menu_href_fix( $atts ) {
		if ( empty( $atts['href'] ) || ! is_string( $atts['href'] ) ) {
			return $atts;
		}
		$path = trim( (string) wp_parse_url( $atts['href'], PHP_URL_PATH ), '/' );
		if ( $path === 'projektpreise' ) {
			$atts['href'] = home_url( '/preise/' );
		} elseif ( $path === 'team' ) {
			$atts['href'] = home_url( '/unsere-experten/' );
		}
		return $atts;
	}
}
add_filter( 'nav_menu_link_attributes', 'pax_qa_nav_menu_href_fix', 20 );

if ( ! function_exists( 'pax_qa_nav_menu_item_title' ) ) {
	/**
	 * Replace the raw CCS menu slug with a readable label.
	 *
	 * @param string $title Menu title.
	 * @return string
	 */
	function pax_qa_nav_menu_item_title( $title ) {
		$raw = trim( wp_strip_all_tags( (string) $title ) );
		if ( $raw === 'cybercrime-support' ) {
			return 'Cybercrime Support';
		}
		return $title;
	}
}
add_filter( 'nav_menu_item_title', 'pax_qa_nav_menu_item_title', 20 );
add_filter( 'the_title', 'pax_qa_nav_menu_item_title', 20 );

if ( ! function_exists( 'pax_qa_fix_karriere_title' ) ) {
	/**
	 * @param string $title Document title.
	 * @return string
	 */
	function pax_qa_fix_karriere_title( $title ) {
		if ( ! is_string( $title ) || $title === '' ) {
			return $title;
		}
		if ( stripos( $title, 'bei unsere' ) !== false ) {
			$title = preg_replace( '/bei unsere\b/iu', 'bei uns', $title );
		}
		return $title;
	}
}
add_filter( 'pre_get_document_title', 'pax_qa_fix_karriere_title', 99 );
add_filter( 'wp_title', 'pax_qa_fix_karriere_title', 99 );
add_filter( 'the_seo_framework_title_from_generation', 'pax_qa_fix_karriere_title', 99 );
add_filter( 'the_seo_framework_title_from_custom_field', 'pax_qa_fix_karriere_title', 99 );
add_filter( 'the_seo_framework_pre_add_title', 'pax_qa_fix_karriere_title', 99 );

if ( ! function_exists( 'pax_qa_fill_empty_menu_image_alts' ) ) {
	/**
	 * Mega-menu preview images often ship with alt="". Keep decorative empty
	 * alts that already sit inside aria-hidden wrappers.
	 *
	 * @param string $item_output Menu HTML.
	 * @param object $item Menu item.
	 * @return string
	 */
	function pax_qa_fill_empty_menu_image_alts( $item_output, $item ) {
		if ( ! is_string( $item_output ) || strpos( $item_output, 'alt=""' ) === false ) {
			return $item_output;
		}
		if ( preg_match( '/aria-hidden\s*=\s*["\']true["\']/i', $item_output ) ) {
			return $item_output;
		}
		$label = '';
		if ( is_object( $item ) && ! empty( $item->title ) ) {
			$label = wp_strip_all_tags( (string) $item->title );
		}
		if ( $label === '' ) {
			return $item_output;
		}
		return preg_replace( '/alt=""/', 'alt="' . esc_attr( $label ) . '"', $item_output, 1 );
	}
}
add_filter( 'walker_nav_menu_start_el', 'pax_qa_fill_empty_menu_image_alts', 20, 2 );

if ( ! function_exists( 'pax_qa_permissions_policy_header' ) ) {
	/**
	 * Allow same-origin camera/microphone for web chat on public pages.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	function pax_qa_permissions_policy_header( $headers ) {
		$headers['Permissions-Policy'] = 'camera=(self), microphone=(self), geolocation=(self)';
		return $headers;
	}
}
add_filter( 'wp_headers', 'pax_qa_permissions_policy_header', 20 );

if ( ! function_exists( 'pax_qa_send_permissions_policy_header' ) ) {
	function pax_qa_send_permissions_policy_header() {
		if ( headers_sent() ) {
			return;
		}
		header( 'Permissions-Policy: camera=(self), microphone=(self), geolocation=(self)', true );
	}
}
add_action( 'send_headers', 'pax_qa_send_permissions_policy_header', 20 );

if ( ! function_exists( 'pax_qa_rewrite_legacy_markup' ) ) {
	/**
	 * Replace stale marketing URLs and the unspaced phone display in stored HTML.
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	function pax_qa_rewrite_legacy_markup( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}
		$html = str_replace( 'https://paxdesign.at/projektpreise/', home_url( '/preise/' ), $html );
		$html = str_replace( 'http://paxdesign.at/projektpreise/', home_url( '/preise/' ), $html );
		$html = str_replace( 'https://paxdesign.at/team/', home_url( '/unsere-experten/' ), $html );
		$html = str_replace( 'http://paxdesign.at/team/', home_url( '/unsere-experten/' ), $html );
		$html = str_replace( '+43 681 20543638', '+43 681 2054 3638', $html );
		return $html;
	}
}
add_filter( 'widget_custom_html_content', 'pax_qa_rewrite_legacy_markup', 20 );
add_filter( 'widget_text', 'pax_qa_rewrite_legacy_markup', 20 );
add_filter( 'widget_block_content', 'pax_qa_rewrite_legacy_markup', 20 );

if ( ! function_exists( 'pax_qa_start_html_rewrite' ) ) {
	/**
	 * Rewrite stale URLs/phone in the final public HTML, including Custom HTML footer markup.
	 */
	function pax_qa_start_html_rewrite() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return;
		}
		ob_start( 'pax_qa_rewrite_legacy_markup' );
	}
}
add_action( 'template_redirect', 'pax_qa_start_html_rewrite', 1 );

if ( ! function_exists( 'pax_qa_footer_client_fixes' ) ) {
	function pax_qa_footer_client_fixes() {
		if ( is_admin() ) {
			return;
		}
		?>
<script>
(function () {
  document.querySelectorAll('.dtr-mega-feature__img[alt=""], .dtr-mega-panel img[alt=""]').forEach(function (img) {
    if (img.closest('[aria-hidden="true"]')) return;
    var card = img.closest('a');
    var title = card && card.querySelector('.dtr-mega-feature__title, .dtr-mega-title');
    var label = title ? String(title.textContent || '').replace(/\s+/g, ' ').trim() : '';
    if (label) img.setAttribute('alt', label);
  });
})();
</script>
		<?php
	}
}
add_action( 'wp_footer', 'pax_qa_footer_client_fixes', 99 );
