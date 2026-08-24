<?php
/**
 * Navein WordPress Theme.
 * @package NaveinTheme
 * @version 1.1.7
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'navein_bootstrap_site_i18n' ) ) {
	function navein_bootstrap_site_i18n() {
		static $loaded = false;
		if ( $loaded ) {
			return;
		}
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $base ) {
			$path = $base . '/inc/site-i18n.php';
			if ( is_readable( $path ) ) {
				require_once $path;
				$loaded = true;
				return;
			}
		}
	}
}
navein_bootstrap_site_i18n();

if ( ! function_exists( 'pax_ccs_bootstrap_locale_helpers' ) ) {
	/**
	 * Load cybercrime locale helpers only when present (never fatal site-wide).
	 */
	function pax_ccs_bootstrap_locale_helpers() {
		static $loaded = false;
		if ( $loaded ) {
			return;
		}
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $base ) {
			$path = $base . '/inc/cybercrime-support-locale.php';
			if ( is_readable( $path ) ) {
				require_once $path;
				$loaded = true;
				return;
			}
		}
	}
}

// included plugins current versions
define( 'NAVEIN_CORE_PLUGIN_CURRENT_VERSION', '1.0.0' );
define( 'NAVEIN_ELEMENTOR_ADDON_PLUGIN_CURRENT_VERSION', '1.0.0' );

if ( ! function_exists( 'navein_theme_setup' ) ) :
/**
 * Theme setup
 */
function navein_theme_setup() {

	// Makes theme available for translation.
	load_theme_textdomain( 'navein', get_template_directory() . '/languages' );

	// Document title
	add_theme_support( 'title-tag' );

	// Logo support
	add_theme_support( 'custom-logo' );

	// Custom background
	add_theme_support( 'custom-background' );

    // Switches default core markup for below
	add_theme_support( 'html5', array( 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets', ) );

    // Adds thumbnail support
	add_theme_support( 'post-thumbnails' );

	// Set the default content width.
	$GLOBALS['content_width'] = 1320;

    // Adds RSS feed links to <head> for posts and comments.
	add_theme_support( 'automatic-feed-links' );

	// wp_nav_menu() locations
    register_nav_menus( array(
        'primary_menu'		=> 'Primary Menu',
    ) );

	// Adds theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Add support for Block Styles.
	add_theme_support( 'wp-block-styles' );

	// Add support for full and wide align images.
	add_theme_support( 'align-wide' );

	// editor style
	add_editor_style( 'assets/css/dtr-editor-style.css' );

	// Add support for responsive embedded content.
	add_theme_support( 'responsive-embeds' );

	// Add support for Editor color palette.
	$dark   = '#0e0f0f';
	$white 	= '#ffffff';
	$gray   = '#bbbaa6';
	$accent = '#d8fe00';

	add_theme_support(
		'editor-color-palette',
		array(
			array(
				'name'  => esc_html__( 'Dark', 'navein' ),
				'slug'  => 'dark',
				'color' => $dark,
			),
			array(
				'name'  => esc_html__( 'White', 'navein' ),
				'slug'  => 'white',
				'color' => $white,
			),
			array(
				'name'  => esc_html__( 'Mid Dark', 'navein' ),
				'slug'  => 'gray',
				'color' => $gray,
			),
			array(
				'name'  => esc_html__( 'Accent', 'navein' ),
				'slug'  => 'accent',
				'color' => $accent,
			),
		)
	);

	// Add custom editor font sizes.
	add_theme_support(
		'editor-font-sizes',
		array(
			array(
				'name'      => esc_html__( 'Extra small', 'navein' ),
				'shortName' => esc_html_x( 'XS', 'Font size', 'navein' ),
				'size'      => 10,
				'slug'      => 'extra-small',
			),
			array(
				'name'      => esc_html__( 'Small', 'navein' ),
				'shortName' => esc_html_x( 'S', 'Font size', 'navein' ),
				'size'      => 12,
				'slug'      => 'small',
			),
			array(
				'name'      => esc_html__( 'Normal', 'navein' ),
				'shortName' => esc_html_x( 'M', 'Font size', 'navein' ),
				'size'      => 18,
				'slug'      => 'normal',
			),
			array(
				'name'      => esc_html__( 'Large', 'navein' ),
				'shortName' => esc_html_x( 'L', 'Font size', 'navein' ),
				'size'      => 24,
				'slug'      => 'large',
			),
			array(
				'name'      => esc_html__( 'Extra large', 'navein' ),
				'shortName' => esc_html_x( 'XL', 'Font size', 'navein' ),
				'size'      => 40,
				'slug'      => 'extra-large',
			),
		)
	); // Add custom editor font sizes.

    // redux theme options
    require_once( get_template_directory() .'/includes/options/options-config.php' );

    // Ensure that Redux options are loaded into the global variable
    global $navein_theme_mod;
    // Populate global variable with Redux options
    $navein_theme_mod = get_option('navein_theme_mod', []);
}
endif; // navein_theme_setup
add_action( 'after_setup_theme', 'navein_theme_setup' );

require_once get_template_directory() . '/inc/performance.php';
require_once get_template_directory() . '/inc/public-identity-hardening.php';
$pax_qa_fixes = get_template_directory() . '/inc/qa-production-fixes.php';
if ( is_readable( $pax_qa_fixes ) ) {
	require_once $pax_qa_fixes;
}

/**
 * Enqueue Plugins Scripts and Styles
 *
 */
if ( ! function_exists( 'navein_plugin_scripts_styles' ) ) :
function navein_plugin_scripts_styles() {

	// enqueue scripts
	wp_enqueue_script( 'jquery-easing', get_template_directory_uri() . '/assets/js/jquery.easing.js', array('jquery'), '1.3.0', true );
	wp_enqueue_script( 'imagesloaded' );
	wp_enqueue_script( 'masonry' );
	wp_enqueue_script( 'isotope', get_template_directory_uri() . '/assets/js/isotope.js', array('jquery'), '3.0.6', true );
	wp_enqueue_script( 'slicknav', get_template_directory_uri() . '/assets/js/jquery.slicknav.js', array('jquery'), '1.0.10', true );
	wp_enqueue_script( 'hoverIntent', get_template_directory_uri() . '/assets/js/hoverIntent.js', array('jquery'), '1.10.1', true );
	wp_enqueue_script( 'superfish', get_template_directory_uri() . '/assets/js/superfish.js', array('jquery'), '1.7.10', true );

	// enqueue styles
	wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/assets/css/bootstrap.min.css' );
	wp_enqueue_style( 'iconfont', get_template_directory_uri() . '/fonts/iconfont.css' );

	// comments
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

}
endif;
add_action( 'wp_enqueue_scripts', 'navein_plugin_scripts_styles' );
// navein_plugin_scripts_styles ends

// Retrieve theme option values
// should be always called before dependencies
if ( ! function_exists('navein_get_theme_option') ) {
	function navein_get_theme_option($id, $fallback = false, $param = false ) {
		global $navein_theme_mod;
		if ( $fallback === false ) $fallback = '';
		$output = ( isset($navein_theme_mod[$id]) && $navein_theme_mod[$id] !== '' ) ? $navein_theme_mod[$id] : $fallback;

		if ( isset($navein_theme_mod[$id]) && is_array($navein_theme_mod[$id]) && $param && isset($navein_theme_mod[$id][$param]) ) {
			$output = $navein_theme_mod[$id][$param];
		}

		return $output;
	}
}

/**
 * Enqueue Custom Scripts and Styles
 *
 */
if ( ! function_exists( 'navein_custom_scripts_styles' ) ) :
function navein_custom_scripts_styles() {

	$theme_version = wp_get_theme()->get( 'Version' );

	// enqueue scripts
	wp_enqueue_script( 'navein-custom-js', get_template_directory_uri() . '/assets/js/custom.js', array( 'jquery' ), $theme_version, true );
	wp_enqueue_script(
		'navein-mega-menu',
		get_template_directory_uri() . '/assets/js/mega-menu.js',
		array( 'jquery', 'superfish', 'navein-custom-js' ),
		$theme_version,
		true
	);

	// enqueue main stylesheet and colors
	wp_enqueue_style( 'navein-style', get_stylesheet_uri(), array(), $theme_version );

	// Site-wide Exo 2 @font-face (loads early on all front-end pages).
	if ( ! is_admin() ) {
		wp_enqueue_style(
			'navein-voga-diamond-fonts',
			get_template_directory_uri() . '/assets/css/voga-diamond-fonts.css',
			array(),
			$theme_version
		);
		wp_enqueue_style(
			'navein-orbitron-display-fonts',
			get_template_directory_uri() . '/assets/css/orbitron-display-fonts.css',
			array(),
			$theme_version
		);
	}

	if ( 'header-v1' == navein_get_theme_option( 'navein_header_layout', 'header-v1' ) ) {
		wp_register_style( 'navein-header-v1', get_template_directory_uri() . '/assets/css/header-v1.css', array(), $theme_version );
		wp_enqueue_style( 'navein-header-v1' );
	} elseif ( 'header-v2' == navein_get_theme_option( 'navein_header_layout', 'header-v1' ) ) {
		wp_register_style( 'navein-header-v2', get_template_directory_uri() . '/assets/css/header-v2.css', array(), $theme_version );
		wp_enqueue_style( 'navein-header-v2' );
	} elseif ( 'header-v3' == navein_get_theme_option( 'navein_header_layout', 'header-v1' ) ) {
		wp_register_style( 'navein-header-v3', get_template_directory_uri() . '/assets/css/header-v3.css', array(), $theme_version );
		wp_enqueue_style( 'navein-header-v3' );
	} elseif ( 'header-v4' == navein_get_theme_option( 'navein_header_layout', 'header-v1' ) ) {
		wp_register_style( 'navein-header-v4', get_template_directory_uri() . '/assets/css/header-v4.css', array(), $theme_version );
		wp_enqueue_style( 'navein-header-v4' );
	}

	wp_enqueue_style(
		'navein-mega-menu',
		get_template_directory_uri() . '/assets/css/mega-menu.css',
		array( 'navein-style' ),
		$theme_version
	);

	// Apple-inspired sticky header (sitewide) — after header layout + mega menu.
	wp_enqueue_style(
		'navein-apple-sticky-header',
		get_template_directory_uri() . '/assets/css/apple-sticky-header.css',
		array( 'navein-style', 'navein-mega-menu' ),
		$theme_version
	);
	wp_enqueue_script(
		'navein-apple-sticky-header',
		get_template_directory_uri() . '/assets/js/apple-sticky-header.js',
		array( 'jquery', 'navein-custom-js' ),
		$theme_version,
		true
	);

	wp_enqueue_script(
		'navein-apple-site-i18n',
		get_template_directory_uri() . '/assets/js/apple-site-i18n.js',
		array(),
		$theme_version,
		false
	);
	if ( function_exists( 'navein_site_lang' ) ) {
		wp_localize_script(
			'navein-apple-site-i18n',
			'PAX_SITE_I18N',
			array(
				'lang'      => navein_site_lang(),
				'dir'       => navein_site_dir(),
				'source'    => navein_site_lang_source(),
				'supported' => navein_site_i18n_supported(),
				'phrases'   => function_exists( 'navein_site_i18n_chrome_phrases' ) ? navein_site_i18n_chrome_phrases() : navein_site_i18n_phrases(),
				'labels'    => array(
					'language' => navein_t( 'language', 'Language' ),
				),
			)
		);
		if ( navein_site_lang() === 'ar' ) {
			wp_enqueue_style(
				'navein-arabic-naskh',
				'https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap',
				array(),
				null
			);
		}
	}

	// Apple-style compact inner page titles (replaces legacy banner card).
	wp_enqueue_style(
		'navein-apple-inner-page-title',
		get_template_directory_uri() . '/assets/css/apple-inner-page-title.css',
		array( 'navein-style', 'navein-apple-sticky-header' ),
		$theme_version
	);

	// Apple-inspired full-screen mobile navigation (≤992px).
	wp_enqueue_style(
		'navein-apple-mobile-nav',
		get_template_directory_uri() . '/assets/css/apple-mobile-nav.css',
		array( 'navein-style', 'navein-mega-menu', 'navein-apple-sticky-header' ),
		$theme_version
	);
	wp_enqueue_script(
		'navein-apple-mobile-nav',
		get_template_directory_uri() . '/assets/js/apple-mobile-nav.js',
		array( 'jquery', 'navein-custom-js', 'navein-apple-sticky-header' ),
		$theme_version,
		true
	);

	wp_enqueue_style(
		'navein-apple-header-stable',
		get_template_directory_uri() . '/assets/css/apple-header-stable.css',
		array( 'navein-style', 'navein-apple-sticky-header', 'navein-apple-mobile-nav' ),
		$theme_version
	);
	wp_enqueue_style(
		'navein-apple-site-rtl',
		get_template_directory_uri() . '/assets/css/apple-site-rtl.css',
		array( 'navein-apple-header-stable' ),
		$theme_version
	);

	// Apple-inspired sitewide footer.
	wp_enqueue_style(
		'navein-apple-footer',
		get_template_directory_uri() . '/assets/css/apple-footer.css',
		array( 'navein-style', 'navein-apple-sticky-header' ),
		$theme_version
	);
	wp_enqueue_script(
		'navein-apple-footer',
		get_template_directory_uri() . '/assets/js/apple-footer.js',
		array(),
		$theme_version,
		true
	);

	// Apple-inspired product pages + complete homepage
	$is_apple_product_page = is_page_template( 'template-apple-app-entwicklung.php' )
		|| is_page_template( 'template-apple-advanced-website-systems.php' )
		|| is_page_template( 'template-apple-softwareentwicklung.php' )
		|| is_page_template( 'template-apple-wartung-support.php' )
		|| is_page_template( 'template-apple-webentwicklung.php' )
		|| is_page( 'app-entwicklung' )
		|| is_page( 'advanced-website-systems' )
		|| is_page( 'softwareentwicklung' )
		|| is_page( 'wartung-support' )
		|| is_page( 'webentwicklung' );
	if ( $is_apple_product_page ) {
		wp_enqueue_style(
			'navein-apple-app-page',
			get_template_directory_uri() . '/assets/css/apple-app-page.css',
			array( 'navein-style' ),
			$theme_version
		);
		// Beat Customizer non-home #dtr-main-wrapper 92vw / side-padding box.
		wp_add_inline_style(
			'navein-apple-app-page',
			'html body.page-template-template-apple-app-entwicklung #dtr-main-wrapper,' .
			'html body.page-template-template-apple-app-entwicklung-php #dtr-main-wrapper,' .
			'html body.page-template-template-apple-advanced-website-systems #dtr-main-wrapper,' .
			'html body.page-template-template-apple-advanced-website-systems-php #dtr-main-wrapper,' .
			'html body.page-template-template-apple-softwareentwicklung #dtr-main-wrapper,' .
			'html body.page-template-template-apple-softwareentwicklung-php #dtr-main-wrapper,' .
			'html body.page-template-template-apple-wartung-support #dtr-main-wrapper,' .
			'html body.page-template-template-apple-wartung-support-php #dtr-main-wrapper,' .
			'html body.page-template-template-apple-webentwicklung #dtr-main-wrapper,' .
			'html body.page-template-template-apple-webentwicklung-php #dtr-main-wrapper,' .
			'html body.page-template-template-apple-app-entwicklung #dtr-main-wrapper.pax-apple-app-wrap,' .
			'html body.page-template-template-apple-advanced-website-systems #dtr-main-wrapper.pax-apple-app-wrap,' .
			'html body.page-template-template-apple-softwareentwicklung #dtr-main-wrapper.pax-apple-app-wrap,' .
			'html body.page-template-template-apple-webentwicklung #dtr-main-wrapper.pax-apple-app-wrap{' .
			'width:100%!important;max-width:none!important;margin:0!important;' .
			'padding:0!important;padding-left:0!important;padding-right:0!important;' .
			'box-sizing:border-box!important;}'
		);
		wp_enqueue_script(
			'navein-apple-app-page',
			get_template_directory_uri() . '/assets/js/apple-app-page.js',
			array(),
			$theme_version,
			true
		);
	}

	$is_apple_cybercrime = is_page_template( 'template-apple-cybercrime-support.php' )
		|| is_page( 'cybercrime-support' );

	// Apple-style hover: underline only — no scale / pulse / glow (load last).
	wp_enqueue_style(
		'navein-apple-hover',
		get_template_directory_uri() . '/assets/css/apple-hover.css',
		array( 'navein-style', 'navein-apple-sticky-header', 'navein-apple-footer', 'navein-apple-mobile-nav' ),
		$theme_version
	);

	if ( $is_apple_cybercrime ) {
		wp_enqueue_style(
			'navein-apple-cybercrime-support',
			get_template_directory_uri() . '/assets/css/apple-cybercrime-support.css',
			array( 'navein-style', 'navein-apple-hover' ),
			$theme_version
		);
		wp_add_inline_style(
			'navein-apple-cybercrime-support',
			'html body.page-template-template-apple-cybercrime-support #dtr-main-wrapper,' .
			'html body.page-template-template-apple-cybercrime-support-php #dtr-main-wrapper,' .
			'html body.page-cybercrime-support #dtr-main-wrapper,' .
			'html body.page-template-template-apple-cybercrime-support #dtr-primary-section,' .
			'html body.page-template-template-apple-cybercrime-support-php #dtr-primary-section,' .
			'html body.page-cybercrime-support #dtr-primary-section,' .
			'html body.page-template-template-apple-cybercrime-support .dtr-content-area,' .
			'html body.page-template-template-apple-cybercrime-support-php .dtr-content-area,' .
			'html body.page-cybercrime-support .dtr-content-area,' .
			'html body.page-template-template-apple-cybercrime-support .pax-ccs-portal-wrap,' .
			'html body.page-template-template-apple-cybercrime-support-php .pax-ccs-portal-wrap,' .
			'html body.page-cybercrime-support .pax-ccs-portal-wrap{' .
			'width:100%!important;max-width:none!important;margin:0!important;' .
			'padding:0!important;padding-left:0!important;padding-right:0!important;' .
			'float:none!important;box-sizing:border-box!important;}'
		);
		wp_enqueue_script(
			'navein-apple-cybercrime-support',
			get_template_directory_uri() . '/assets/js/apple-cybercrime-support.js',
			array(),
			$theme_version,
			true
		);
		if ( class_exists( 'PAXdesign_Cybercrime_Intake' ) ) {
			pax_ccs_bootstrap_locale_helpers();
			$ccs_config = PAXdesign_Cybercrime_Intake::public_config();
			if ( function_exists( 'pax_ccs_portal_i18n' ) ) {
				try {
					$ccs_config['i18n'] = pax_ccs_portal_i18n();
				} catch ( Throwable $e ) {
					$ccs_config['i18n'] = array();
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[PAX CCS] i18n bundle failed: ' . $e->getMessage() );
					}
				}
			}
			if ( function_exists( 'pax_ccs_countries_for_js' ) ) {
				$ccs_config['countries']      = pax_ccs_countries_for_js();
				$ccs_config['phonePopular']   = array( 'AT', 'DE', 'CH', 'US', 'GB', 'AE', 'SA', 'FR', 'IT', 'ES', 'NL', 'BE', 'PL', 'TR', 'EG', 'JO', 'LB', 'QA', 'KW', 'BH', 'OM' );
				$ccs_config['defaultPhoneCountry'] = function_exists( 'pax_ccs_guess_visitor_country' )
					? pax_ccs_guess_visitor_country()
					: 'AT';
			}
			wp_localize_script(
				'navein-apple-cybercrime-support',
				'paxCybercrimeIntake',
				$ccs_config
			);
		}
	}

	// Apple-inspired Impressum (legal imprint) page.
	$is_apple_impressum = is_page_template( 'template-apple-impressum.php' )
		|| is_page( 'impressum' )
		|| is_page( 2838 );
	if ( $is_apple_impressum ) {
		wp_enqueue_style(
			'navein-apple-impressum',
			get_template_directory_uri() . '/assets/css/apple-impressum.css',
			array( 'navein-style', 'navein-apple-sticky-header' ),
			$theme_version
		);
		wp_enqueue_script(
			'navein-apple-impressum',
			get_template_directory_uri() . '/assets/js/apple-impressum.js',
			array(),
			$theme_version,
			true
		);
	}

	// Apple-inspired Unsere Experten page.
	$is_apple_experts = is_page_template( 'template-apple-unsere-experten.php' )
		|| is_page( 'unsere-experten' )
		|| is_page( 2818 );
	if ( $is_apple_experts ) {
		wp_enqueue_style(
			'navein-apple-experts',
			get_template_directory_uri() . '/assets/css/apple-experts.css',
			array( 'navein-style', 'navein-apple-sticky-header' ),
			$theme_version
		);
		wp_enqueue_script(
			'navein-apple-experts',
			get_template_directory_uri() . '/assets/js/apple-experts.js',
			array(),
			$theme_version,
			true
		);
	}

	// Apple IT Consulting (Elementor HTML block) — mobile full-bleed overrides.
	$is_apple_it_consulting = is_page( 'it-consulting' ) || is_page( 2798 );
	if ( $is_apple_it_consulting ) {
		wp_enqueue_style(
			'navein-apple-it-consulting',
			get_template_directory_uri() . '/assets/css/apple-it-consulting.css',
			array( 'navein-style', 'navein-apple-sticky-header' ),
			$theme_version
		);
		wp_add_inline_style(
			'navein-apple-it-consulting',
			'html body.page-id-2798 #dtr-main-wrapper,' .
			'html body.page-it-consulting #dtr-main-wrapper{' .
			'width:100%!important;max-width:none!important;margin:0!important;' .
			'padding:0!important;padding-left:0!important;padding-right:0!important;' .
			'box-sizing:border-box!important;}'
		);
	}

	// Homepage typography + layout: front page only (not other apple-homepage template uses).
	if ( is_front_page() ) {
		wp_enqueue_style(
			'navein-homepage-fonts',
			get_template_directory_uri() . '/assets/css/homepage-fonts.css',
			array(),
			$theme_version
		);
		wp_enqueue_style(
			'navein-apple-homepage',
			get_template_directory_uri() . '/assets/css/apple-homepage.css',
			array( 'navein-style', 'navein-homepage-fonts', 'navein-voga-diamond-fonts', 'navein-site-body-typography' ),
			$theme_version
		);
		wp_add_inline_style(
			'navein-apple-homepage',
			'html body.home #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-signup-btn{' .
			'background:#000!important;background-color:#000!important;background-image:none!important;' .
			'color:#fff!important;border:0!important;box-shadow:none!important;border-radius:980px!important;}'
		);
		wp_enqueue_script(
			'navein-apple-homepage',
			get_template_directory_uri() . '/assets/js/apple-homepage.js',
			array(),
			$theme_version,
			true
		);
	}

	// RTL support (WordPress locale or Arabic site language).
	if ( is_rtl() || ( function_exists( 'navein_site_lang' ) && navein_site_lang() === 'ar' ) ) {
		wp_enqueue_style( 'navein-rtl-style', get_template_directory_uri() . '/assets/css/rtl.css', array(), $theme_version );
	}

}
endif;
add_action( 'wp_enqueue_scripts', 'navein_custom_scripts_styles', 20 );

/**
 * Load after sitewide custom CSS so Cybercrime Support has no mobile header gap.
 */
if ( ! function_exists( 'navein_cybercrime_mobile_layout_fix' ) ) :
function navein_cybercrime_mobile_layout_fix() {
	if ( ! is_page_template( 'template-apple-cybercrime-support.php' ) && ! is_page( 'cybercrime-support' ) ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );
	wp_register_style( 'navein-cybercrime-mobile-layout-fix', false, array(), $theme_version );
	wp_enqueue_style( 'navein-cybercrime-mobile-layout-fix' );
	wp_add_inline_style(
		'navein-cybercrime-mobile-layout-fix',
		'@media (max-width:768px){' .
		'html body.page-template-template-apple-cybercrime-support #dtr-main-wrapper.pax-ccs-portal-wrap,' .
		'html body.page-template-template-apple-cybercrime-support-php #dtr-main-wrapper.pax-ccs-portal-wrap,' .
		'html body.page-cybercrime-support #dtr-main-wrapper.pax-ccs-portal-wrap{' .
		'padding:0!important;margin:0!important;}' .
		'html body.page-template-template-apple-cybercrime-support #dtr-primary-section,' .
		'html body.page-template-template-apple-cybercrime-support-php #dtr-primary-section,' .
		'html body.page-cybercrime-support #dtr-primary-section,' .
		'html body.page-template-template-apple-cybercrime-support .pax-ccs-portal,' .
		'html body.page-template-template-apple-cybercrime-support-php .pax-ccs-portal,' .
		'html body.page-cybercrime-support .pax-ccs-portal{' .
		'margin-top:0!important;padding-top:0!important;}' .
		'html body.page-template-template-apple-cybercrime-support .dtr-page-title__section,' .
		'html body.page-template-template-apple-cybercrime-support-php .dtr-page-title__section,' .
		'html body.page-cybercrime-support .dtr-page-title__section,' .
		'html body.page-template-template-apple-cybercrime-support .dtr-page-title--section,' .
		'html body.page-template-template-apple-cybercrime-support-php .dtr-page-title--section,' .
		'html body.page-cybercrime-support .dtr-page-title--section{' .
		'display:none!important;margin:0!important;padding:0!important;' .
		'min-height:0!important;height:0!important;overflow:hidden!important;}' .
		'}'
	);
}
endif;
add_action( 'wp_enqueue_scripts', 'navein_cybercrime_mobile_layout_fix', 999 );

/**
 * Site-wide Exo 2 body typography — loads after plugin CSS for consistent overrides.
 */
if ( ! function_exists( 'navein_site_body_typography' ) ) :
function navein_site_body_typography() {
	if ( is_admin() ) {
		return;
	}

	$theme_version = wp_get_theme()->get( 'Version' );
	$deps          = array( 'navein-style', 'navein-voga-diamond-fonts', 'navein-orbitron-display-fonts' );
	if ( wp_style_is( 'paxdesign-booking-styles', 'registered' ) ) {
		$deps[] = 'paxdesign-booking-styles';
	}
	wp_enqueue_style(
		'navein-site-body-typography',
		get_template_directory_uri() . '/assets/css/site-body-typography.css',
		$deps,
		$theme_version
	);
}
endif;
add_action( 'wp_enqueue_scripts', 'navein_site_body_typography', 1001 );

if ( ! function_exists( 'navein_cybercrime_mobile_layout_footer_fix' ) ) :
function navein_cybercrime_mobile_layout_footer_fix() {
	if ( ! is_page_template( 'template-apple-cybercrime-support.php' ) && ! is_page( 'cybercrime-support' ) ) {
		return;
	}
	echo '<style id="navein-cybercrime-mobile-layout-footer-fix">';
	echo '@media (max-width:768px){';
	echo 'html body.page-template-template-apple-cybercrime-support #dtr-main-wrapper.pax-ccs-portal-wrap,';
	echo 'html body.page-template-template-apple-cybercrime-support-php #dtr-main-wrapper.pax-ccs-portal-wrap,';
	echo 'html body.page-cybercrime-support #dtr-main-wrapper.pax-ccs-portal-wrap{';
	echo 'padding:0!important;margin:0!important;width:100%!important;max-width:none!important;}';
	echo 'html body.page-template-template-apple-cybercrime-support #dtr-primary-section,';
	echo 'html body.page-template-template-apple-cybercrime-support-php #dtr-primary-section,';
	echo 'html body.page-cybercrime-support #dtr-primary-section{';
	echo 'padding:0!important;margin:0!important;}';
	echo 'html body.page-template-template-apple-cybercrime-support .pax-ccs-portal__servicebar,';
	echo 'html body.page-template-template-apple-cybercrime-support-php .pax-ccs-portal__servicebar,';
	echo 'html body.page-cybercrime-support .pax-ccs-portal__servicebar{margin-top:0!important;}';
	echo '}';
	echo '</style>';
}
endif;
add_action( 'wp_footer', 'navein_cybercrime_mobile_layout_footer_fix', 9999 );

/**
 * Mark body for Apple footer styling (sitewide).
 *
 * @param array $classes Body classes.
 * @return array
 */
function navein_apple_footer_body_class( $classes ) {
	$classes[] = 'dtr-apple-footer';
	return $classes;
}
add_filter( 'body_class', 'navein_apple_footer_body_class' );

if ( ! function_exists( 'navein_site_i18n_locale' ) ) {
	function navein_site_i18n_locale( $locale ) {
		if ( is_admin() || ! function_exists( 'navein_site_lang' ) ) {
			return $locale;
		}
		return navein_site_i18n_wp_locale( navein_site_lang() );
	}
}
add_filter( 'locale', 'navein_site_i18n_locale', 20 );
add_filter( 'determine_locale', 'navein_site_i18n_locale', 20 );

if ( ! function_exists( 'navein_site_i18n_language_attributes' ) ) {
	function navein_site_i18n_language_attributes( $output ) {
		if ( is_admin() || ! function_exists( 'navein_site_lang' ) ) {
			return $output;
		}
		$lang = navein_site_lang();
		$dir  = navein_site_dir();
		return 'lang="' . esc_attr( $lang ) . '" dir="' . esc_attr( $dir ) . '"';
	}
}
add_filter( 'language_attributes', 'navein_site_i18n_language_attributes', 20 );

if ( ! function_exists( 'navein_site_i18n_body_class' ) ) {
	function navein_site_i18n_body_class( $classes ) {
		if ( ! function_exists( 'navein_site_lang' ) ) {
			return $classes;
		}
		$lang = navein_site_lang();
		$classes[] = 'pax-lang-' . $lang;
		if ( $lang === 'ar' ) {
			$classes[] = 'pax-dir-rtl';
			$classes[] = 'rtl';
		}
		return $classes;
	}
}
add_filter( 'body_class', 'navein_site_i18n_body_class' );

if ( ! function_exists( 'navein_site_i18n_persist_request' ) ) {
	function navein_site_i18n_persist_request() {
		if ( is_admin() || ! function_exists( 'navein_site_lang' ) ) {
			return;
		}
		navein_site_i18n_persist( navein_site_lang(), navein_site_lang_source() );
	}
}
add_action( 'init', 'navein_site_i18n_persist_request', 0 );

/**
 * Stable body class for Cybercrime Support template overrides.
 *
 * @param array $classes Body classes.
 * @return array
 */
function navein_cybercrime_support_body_class( $classes ) {
	if ( is_page_template( 'template-apple-cybercrime-support.php' ) || is_page( 'cybercrime-support' ) ) {
		$classes[] = 'page-cybercrime-support';
	}
	return $classes;
}
add_filter( 'body_class', 'navein_cybercrime_support_body_class' );
// navein_custom_scripts_styles ends

/**
 * Force Apple product-page templates even if Elementor tries to own the page.
 */
if ( ! function_exists( 'navein_force_apple_product_templates' ) ) :
	function navein_force_apple_product_templates( $template ) {
		if ( is_front_page() ) {
			$home = get_template_directory() . '/template-apple-homepage.php';
			if ( file_exists( $home ) ) {
				return $home;
			}
		}
		$map = array(
			'app-entwicklung'            => 'template-apple-app-entwicklung.php',
			'advanced-website-systems'   => 'template-apple-advanced-website-systems.php',
			'softwareentwicklung'        => 'template-apple-softwareentwicklung.php',
			'wartung-support'            => 'template-apple-wartung-support.php',
			'webentwicklung'             => 'template-apple-webentwicklung.php',
			'cybercrime-support'         => 'template-apple-cybercrime-support.php',
			'impressum'                  => 'template-apple-impressum.php',
			'unsere-experten'            => 'template-apple-unsere-experten.php',
		);
		foreach ( $map as $slug => $file ) {
			if ( is_page( $slug ) ) {
				$custom = get_template_directory() . '/' . $file;
				if ( file_exists( $custom ) ) {
					return $custom;
				}
			}
		}
		return $template;
	}
endif;
add_filter( 'template_include', 'navein_force_apple_product_templates', 99 );

// Back-compat alias if older hook name exists in opcode cache briefly.
if ( ! function_exists( 'navein_force_apple_app_template' ) ) :
	function navein_force_apple_app_template( $template ) {
		return navein_force_apple_product_templates( $template );
	}
endif;

/**
 * Enqueue Custom Scripts and Styles Overrides
 *
 */
if ( ! function_exists( 'navein_custom_scripts_override' ) ) :
function navein_custom_scripts_override() {
	wp_enqueue_style( 'navein-responsive', get_template_directory_uri() . '/assets/css/responsive.css' );
}
endif; // navein_custom_scripts_styles
add_action( 'wp_enqueue_scripts', 'navein_custom_scripts_override', 40 );
// navein_custom_scripts_override ends

/**
 * Recommend plugins for this theme via TGMPA script
 */
if ( ! function_exists( 'navein_plugin_setup' ) ) :
function navein_plugin_setup() {
	require_once( get_template_directory() .'/includes/include-plugins.php' );
}
endif; // navein_plugin_setup
add_action( 'after_setup_theme', 'navein_plugin_setup' );

/**
 * Helper Functions
 */
require_once( get_template_directory() .'/includes/helper-functions/pagination.php' );
require_once( get_template_directory() .'/includes/helper-functions/helper-functions.php' );
require_once( get_template_directory() .'/includes/helper-functions/blog-functions.php' );
require_once( get_template_directory() .'/includes/helper-functions/excerpt.php' );

/**
 * Apple-style mega menu walker (primary header nav)
 */
require_once( get_template_directory() . '/includes/class-navein-mega-menu-walker.php' );

/**
 * Customizer
 */
require_once( get_template_directory() .'/includes/customizer/customizer.php' );

/**
 * Elementor Settings
 */
require_once( get_template_directory() .'/includes/helper-functions/elementor-settings.php' );

/**
 * Body / layout classes
 */
require_once( get_template_directory() .'/includes/body-classes.php' );
require_once( get_template_directory() .'/includes/layout.php' );
require_once( get_template_directory() .'/template-parts/page-title/page-header.php' );

/**
 * Sidebars / Widgets
 */
require_once( get_template_directory() .'/includes/widgets/sidebars.php' );

// Custom styles
require_once ( get_template_directory() . '/includes/custom-styles.php' );

/**
 * One page nav walker
 */
function navein_one_page_nav_walker($output, $item, $depth, $args) {

	global $post;
	$front_id = get_option('page_on_front');

	if(is_object($post)) {

		$output = str_replace( 'http://frontpage_url/', get_permalink($front_id), $output);
		$output = str_replace( 'https://frontpage_url/', get_permalink($front_id), $output);
		$output = str_replace( get_permalink($post->ID).'#', '#', $output );

        // one page menu link
        if ( strpos( $output, '#' ) !== false ) {
            $current_url = get_permalink( $post->ID );

            if ( strpos( $output, $current_url ) !== false ) {
                $output = str_replace( $current_url . '/#', '#', $output );
            }
        }
	}
    return $output;
}
add_filter( 'walker_nav_menu_start_el', 'navein_one_page_nav_walker', 10, 4);

/**
 * Custom callback function for comment display
 */
if( ! function_exists('navein_comment' ) ) {
	function navein_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		switch ( $comment->comment_type ) :
			case 'pingback' :
			case 'trackback' :
		?>

<li class="post pingback">
	<p> <strong>
		<?php esc_html_e( 'Pingback:', 'navein' ); ?>
		</strong>
		<?php comment_author_link(); ?>
		<?php edit_comment_link( esc_html__( 'Edit', 'navein' ), '<span class="edit-link">', '</span>' ); ?>
	</p>
	<?php
			break;
			default :
		?>
<li id="comment-<?php comment_ID(); ?>" <?php comment_class(); ?>>
	<article id="div-comment-<?php comment_ID(); ?>" class="dtr-comment-body">
    <div class="dtr-comment-wrapper">
        <div class="dtr-comment-avatar vcard">
            <?php if ( 0 != $args['avatar_size'] ) echo get_avatar( $comment, $args['avatar_size']); ?>
        </div>
        <div class="dtr-comment-content">
            <div class="dtr-comment-meta-wrapper">
            	<div class="dtr-comment-meta">
                    <h5 class="dtr-comment-meta-author">
                        <?php /* translators: %s: author. */
                            printf( wp_kses( __( '<cite class="fn custom-fn">%s</cite>', 'navein' ), array( 'a' => array( 'href' => array() ) ) ), get_comment_author_link() ); ?>
                    </h5>
                    <a class="dtr-comment-date" href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
                    <?php  /* translators: 1: date, 2: time. */
                        printf( esc_html__( '%1$s at %2$s', 'navein' ), get_comment_date(),  get_comment_time() ); ?>
                    </a>
                    <?php edit_comment_link( esc_html__( 'Edit', 'navein' ), '<span>', '</span>'); ?>

                    </div>
            </div>
            <div class="dtr-comment-content-inner">
                <?php comment_text() ?>
                <?php if ( '0' == $comment->comment_approved ) : ?>
                <p class="comment-awaiting-moderation">
                    <?php esc_html_e( 'Your comment is awaiting moderation.', 'navein' ) ?>
                </p>
                <?php endif; ?>
            </div>
			<div class="dtr-reply"><?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
			</div>
        </div>
    </div>
</article>
	<?php
		break;
		endswitch;
	}
} // end comment callback function

if ( ! function_exists( 'navein_embed_allowed_tags' ) ) :
/**
 * Allowed tags for video / audio embed
 */
	function navein_embed_allowed_tags() {
	$navein_embed_allowed = array(
	'a' => array(
	'href' => array (),
	'title' => array ()),
	'b' => array(
	'style'=> array(),
	),
	);
	// iframe
	$navein_embed_allowed['iframe'] = array(
	'src' => array(),
	'height' => array(),
	'width' => array(),
	'frameborder' => array(),
	'allowfullscreen' => array(),
	);
	// video
	$navein_embed_allowed['video'] = array(
		'width' => true,
		'height' => true
	);
	// source
	$navein_embed_allowed['source'] = array(
		'src' => true,
		'type' => true
	);
	return $navein_embed_allowed;
	}
endif;

if ( ! function_exists( 'navein_wp_kses_extended_ruleset' ) ) :
/**
 * For svg escaping
 */
function navein_wp_kses_extended_ruleset() {
    $kses_defaults = wp_kses_allowed_html( 'post' );
    $svg_args = array(
        'svg'   => array(
            'class'           => true,
            'aria-hidden'     => true,
            'aria-labelledby' => true,
            'role'            => true,
            'xmlns'           => true,
            'width'           => true,
            'height'          => true,
            'viewbox'         => true, // <= Must be lower case!
        ),
        'g'     => array( 'fill' => true ),
        'title' => array( 'title' => true ),
        'path'  => array(
            'd'    => true,
            'fill' => true,
        ),
    );
    return array_merge( $kses_defaults, $svg_args );
}
endif;

/**
 *  Conatact form 7
 */
if ( ! function_exists( 'navein_wpcf7_select' ) ) :
	function navein_wpcf7_select($html) {
	$text = esc_html__( 'Please Select...', 'navein' );
	$html = str_replace('---', '' . $text . '', $html);
	return $html;
	}
	add_filter('wpcf7_form_elements', 'navein_wpcf7_select');
endif;

/**
 * navein demo data import
 */
if ( ! function_exists( 'navein_import_data' ) ) {
	function navein_import_data() {
	  return array(
		array(
		  'import_file_name'             => esc_html__( 'Navein Demo Data', 'navein' ),
		  'local_import_file'            => get_template_directory() . '/includes/imports/content.xml',
          'local_import_redux'           => array(
            array(
                'file_path'   =>  get_template_directory() . '/includes/imports/redux.json',
                'option_name' => 'navein_theme_mod',
            ),
          ),
		  'local_import_customizer_file' => get_template_directory() . '/includes/imports/customizer.dat',
		  'local_import_widget_file'     => get_template_directory() . '/includes/imports/widgets.wie',
		  'import_notice'                => wp_kses( __( '<span style="color: red"><strong>!! IMPORTANT !!</strong></span><br><br><strong>Make sure all the required / recommended plugins are Installed and Activated before demo import.</strong><br><br>After import, error log file may get generated. Minor errors like some media, sidebar,widget...failed to import...can be ignored.<br>Check if you have posts and pages imported fine.<br><br>============<br><br><strong>If demo import not working as expected</strong>...giving some error, internal server error or performed incomplete import;<br>Please ensure that the following <span style="color: red">both</span> limits are sufficient<br><br><strong>1. php memory limit (memory_limit)<br>2. WordPress memory limit (WP_MEMORY_LIMIT)</strong><br><a href="https://docs.tanshcreative.com/basic-wordpress-theme-plugin-requirements/" target="_blank"><span style="color: green">What these should be and How to check?</span></a><br><br>If demo import not working and you are not sure how to overcome....<strong>nothing to panic</strong><br>Just drop us a mail with your login details.<br><br>
			Once the demo is imported, you can effortlessly customize it with your content and have your beautiful website ready in no time.<br>A little patience now will lead to great results! :)<br><br><strong>Please understand that as it is a  server side issue and cannot be overcome via theme...we will need login details to check your setup.</strong>', 'olyve' ), array( 'span' => array('style' => array()), 'strong' => array(), 'br' => array(), 'a' => array( 'href' => array(), 'title' => array(), 'target' => array() ), ) ) // import notice
		),
	  );
	}
}
add_filter( 'ocdi/import_files', 'navein_import_data' );

// After import
if ( ! function_exists( 'navein_after_import_setup' ) ) {
	function navein_after_import_setup() {
		// set menu
		$main_menu		= get_term_by( 'name', 'Primary Menu', 'nav_menu' );
		set_theme_mod( 'nav_menu_locations', array(
				'primary_menu'		=> $main_menu->term_id,
			)
		);
		// set home and blog page
		$front_page_id = get_page_by_path( 'home' );
		$blog_page_id  = get_page_by_path( 'blog' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id->ID );
		update_option( 'page_for_posts', $blog_page_id->ID );
	}
}
add_action( 'ocdi/after_import', 'navein_after_import_setup' );

// Custom Header For Import
if ( ! function_exists( 'navein_plugin_page_setup' ) ) {
	function navein_plugin_page_setup( $default_settings ) {
		$default_settings['parent_slug'] = 'themes.php';
		$default_settings['page_title']  = esc_html__( 'Navein Demo Import', 'navein' );
		$default_settings['menu_title']  = esc_html__( 'Import Theme Demo Data', 'navein' );
		$default_settings['capability']  = 'import';

		return $default_settings;
	}
}
add_filter( 'ocdi/plugin_page_setup', 'navein_plugin_page_setup' );

// Branding
add_filter( 'ocdi/disable_pt_branding', '__return_true' );

// Import only original size images
add_filter( 'ocdi/regenerate_thumbnails_in_content_import', '__return_false' );

// google fonts fallback
if ( ! function_exists( 'navein_fallback_fonts_url' ) ) {
    function navein_fallback_fonts_url() {
        $fonts_url = '';
        $font_families = array();
		$font_families[] = 'Familjen Grotesk:400,500';
		$font_families[] = 'Syne:600';
        $query_args = array(
        'family' => urlencode( implode( '|', $font_families ) ),
        );
        $fonts_url = add_query_arg( $query_args, 'https://fonts.googleapis.com/css' );
        return esc_url_raw( apply_filters( 'navein_fallback_fonts_url', $fonts_url ) );
    }
}
// redux fallback
if ( ! function_exists( 'navein_fallback_scripts_styles' ) ) {
    function navein_fallback_scripts_styles() {
        if ( ! class_exists( 'Redux_Framework_Plugin' ) ) {
            wp_enqueue_style( 'navein-fallback-font', navein_fallback_fonts_url(), array(), null );
            wp_enqueue_style( 'redux-fallback', get_template_directory_uri() . '/assets/css/redux-fallback.css' );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'navein_fallback_scripts_styles', 20 );

// set default page template
if ( ! function_exists( 'navein_set_default_page_template' ) ) {
    function navein_set_default_page_template( $template ) {
        if ( is_page() ) {
            $elementor_page = get_post_meta( get_the_ID(), '_elementor_edit_mode', true );
            if ( empty( $elementor_page ) ) {
                $template = get_template_directory() . '/page.php';
            } else {
                $template = get_template_directory() . '/template-fullwidth.php';
            }
        }
        return $template;
    }
}
add_filter( 'template_include', 'navein_set_default_page_template' );

/**
 * Restore the original header row on the server: logo | nav | Search + CTA + account.
 * Reserved #pdx-auth-bar is in the HTML before JavaScript so the row cannot jump.
 */
if ( ! function_exists( 'navein_apple_header_reserved_auth_markup' ) ) {
	function navein_apple_header_reserved_auth_markup() {
		$signin = function_exists( 'navein_t' ) ? navein_t( 'sign_in', 'Anmelden' ) : 'Anmelden';
		return '<div id="pdx-auth-bar" class="pdx-cx-shell pdx-auth-bar--header pdx-auth-bar--logged-out" data-pdx-header-slot="1">'
			. '<div class="pdx-auth-bar-inner">'
			. '<button type="button" class="pdx-auth-signup-btn pdx-cx-btn pdx-auth-header-btn">' . esc_html( $signin ) . '</button>'
			. '<button type="button" class="pdx-auth-account-btn pdx-cx-btn pdx-cx-btn--ghost pdx-auth-header-btn" aria-haspopup="true" aria-expanded="false" hidden>'
			. '<span class="pdx-auth-account-identity"></span></button>'
			. '<div class="pdx-auth-menu pdx-auth-menu--apple" hidden></div>'
			. '</div></div>';
	}
}

if ( ! function_exists( 'navein_apple_header_utility_cluster_ob_start' ) ) {
	function navein_apple_header_utility_cluster_ob_filter( $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			return is_string( $html ) ? $html : '';
		}
		if ( strpos( $html, 'dtr-header-global-content' ) === false ) {
			return $html;
		}

		$has_auth = ( strpos( $html, 'id="pdx-auth-bar"' ) !== false || strpos( $html, "id='pdx-auth-bar'" ) !== false );
		$slot     = $has_auth ? '' : navein_apple_header_reserved_auth_markup();
		$lang     = function_exists( 'navein_site_lang_switcher_markup' ) ? navein_site_lang_switcher_markup( 'desktop' ) : '';
		$lang_m   = function_exists( 'navein_site_lang_switcher_markup' ) ? navein_site_lang_switcher_markup( 'mobile' ) : '';

		if ( strpos( $html, 'id="pax-site-lang"' ) === false && $lang !== '' ) {
			if ( strpos( $html, 'class="dtr-header-utility-cluster"' ) !== false ) {
				$with_lang = preg_replace(
					'#<div class="dtr-header-utility-cluster">#',
					'<div class="dtr-header-utility-cluster">' . $lang,
					$html,
					1
				);
				$html = is_string( $with_lang ) ? $with_lang : $html;
			}
		}

		if ( strpos( $html, 'id="pax-site-lang-mobile"' ) === false && $lang_m !== '' && strpos( $html, 'id="dtr-responsive-header"' ) !== false ) {
			$with_mobile = preg_replace(
				'#(<div id="dtr-responsive-header"[\s\S]*?<div class="container">[\s\S]*?)(<button[^>]*id="dtr-menu-button")#',
				'$1' . $lang_m . '$2',
				$html,
				1
			);
			if ( is_string( $with_mobile ) && $with_mobile !== $html ) {
				$html = $with_mobile;
			} else {
				$with_mobile = preg_replace(
					'#(<div id="dtr-responsive-header"[\s\S]*?<a[^>]*dtr-logo[\s\S]*?</a>)#',
					'$1' . $lang_m,
					$html,
					1
				);
				$html = is_string( $with_mobile ) ? $with_mobile : $html;
			}
		}

		if ( strpos( $html, 'class="dtr-header-utility-cluster"' ) !== false ) {
			if ( $slot !== '' ) {
				$with_slot = preg_replace(
					'#(<div class="dtr-header-utility-cluster">[\s\S]*?<a[^>]*dtr-header-btn[\s\S]*?</a>)#',
					'$1' . $slot,
					$html,
					1
				);
				$html = is_string( $with_slot ) ? $with_slot : $html;
			}
			if ( function_exists( 'navein_site_i18n_replace_chrome' ) ) {
				$rewritten = navein_site_i18n_replace_chrome( $html );
				if ( is_string( $rewritten ) && $rewritten !== '' ) {
					$html = $rewritten;
				}
			}
			return $html;
		}

		$wrapped = preg_replace_callback(
			'#(<div class="dtr-header-global-content">)([\s\S]*?)(</div>\s*</div>\s*</div>\s*<div id="dtr-responsive-header")#',
			static function ( $matches ) use ( $slot, $lang ) {
				$inner = $matches[2];
				if ( strpos( $inner, 'class="dtr-header-utility-cluster"' ) !== false ) {
					return $matches[0];
				}
				if ( ! preg_match( '#<a[^>]*dtr-search-modal-trigger#', $inner ) ) {
					return $matches[0];
				}

				$parts = preg_split( '#(?=<a[^>]*dtr-search-modal-trigger)#', $inner, 2 );
				if ( ! is_array( $parts ) || count( $parts ) !== 2 ) {
					return $matches[0];
				}

				$utilities = preg_replace(
					'#^(<a[^>]*dtr-search-modal-trigger[\s\S]*?<a[^>]*dtr-header-btn[\s\S]*?</a>)#',
					'<div class="dtr-header-utility-cluster">' . $lang . '$1' . $slot . '</div>',
					$parts[1],
					1
				);
				if ( ! is_string( $utilities ) ) {
					return $matches[0];
				}

				return $matches[1] . $parts[0] . $utilities . $matches[3];
			},
			$html,
			1
		);

		$wrapped = is_string( $wrapped ) ? $wrapped : $html;
		if ( function_exists( 'navein_site_i18n_replace_chrome' ) ) {
			$rewritten = navein_site_i18n_replace_chrome( $wrapped );
			if ( is_string( $rewritten ) && $rewritten !== '' ) {
				$wrapped = $rewritten;
			}
		}
		return $wrapped;
	}

	function navein_apple_header_utility_cluster_ob_start() {
		if ( is_admin() ) {
			return;
		}
		ob_start( 'navein_apple_header_utility_cluster_ob_filter' );
	}
}
add_action( 'template_redirect', 'navein_apple_header_utility_cluster_ob_start', 0 );

/**
 * One late cascade that beats Customizer position:fixed / yellow-glow snippets.
 * Matches the restored flex header. No MutationObserver. No inline style writes.
 */
if ( ! function_exists( 'navein_apple_header_desktop_cascade_footer' ) ) {
	function navein_apple_header_desktop_cascade_footer() {
		if ( is_admin() ) {
			return;
		}
		echo '<style id="navein-apple-header-desktop-cascade">'
			. 'html body.dtr-apple-sticky-header #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-account-btn,'
			. 'html body.dtr-apple-sticky-header #pdx-auth-bar.pdx-auth-bar--header .pdx-header-user-name,'
			. 'html body.dtr-apple-sticky-header #pdx-auth-bar.pdx-auth-bar--header .pdx-auth-account-label,'
			. 'html body.dtr-apple-sticky-header #pdx-auth-bar.pdx-auth-bar--header .pdx-name-with-badge,'
			. 'html body.dtr-apple-sticky-header header #pdx-auth-bar .pdx-auth-trigger,'
			. 'html body.dtr-apple-sticky-header header #pdx-auth-bar .pdx-auth-trigger-label{'
			. 'color:#000!important;text-shadow:none!important;}'
			. '@media (min-width:993px){'
			. 'html body.dtr-apple-sticky-header #dtr-header-global,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global.header-fixed{'
			. 'padding-top:0!important;padding-bottom:0!important;'
			. 'height:var(--dtr-apple-header-height,52px)!important;'
			. 'min-height:var(--dtr-apple-header-height,52px)!important;'
			. 'max-height:var(--dtr-apple-header-height,52px)!important;'
			. 'overflow:visible!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .container{'
			. 'display:block!important;grid-template-columns:none!important;grid-template-areas:none!important;'
			. 'width:100%!important;max-width:100%!important;height:var(--dtr-apple-header-height,52px)!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-global-content{'
			. 'display:flex!important;align-items:center!important;flex-wrap:nowrap!important;'
			. 'justify-content:flex-start!important;gap:var(--dtr-apple-header-gap,12px)!important;'
			. 'grid-template-columns:none!important;grid-template-areas:none!important;'
			. 'width:100%!important;min-width:0!important;'
			. 'height:var(--dtr-apple-header-height,52px)!important;overflow:visible!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .main-navigation{'
			. 'flex:1 1 auto!important;min-width:0!important;overflow:visible!important;margin-right:0!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-btn .dtr-btn__icon,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-btn .dtr-icon{'
			. 'display:none!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global a.dtr-btn.dtr-header-btn,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-btn{'
			. 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
			. 'height:28px!important;min-height:28px!important;max-height:28px!important;'
			. 'margin:0!important;padding:0 12px!important;border:0!important;border-radius:980px!important;'
			. 'background:#000!important;color:#fff!important;box-shadow:none!important;'
			. 'font-size:12px!important;white-space:nowrap!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--header,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--menu-open{'
			. 'position:relative!important;top:auto!important;right:auto!important;left:auto!important;'
			. 'bottom:auto!important;transform:none!important;display:flex!important;'
			. 'align-items:center!important;min-width:88px!important;height:52px!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--logged-out .pdx-auth-signup-btn{'
			. 'display:inline-flex!important;background:#000!important;color:#fff!important;'
			. 'padding:0 12px!important;border:0!important;border-radius:980px!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--logged-in .pdx-auth-signup-btn,'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #pdx-auth-bar.pdx-auth-bar--logged-in .pdx-auth-signup-btn{'
			. 'display:none!important;visibility:hidden!important;pointer-events:none!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-utility-cluster{'
			. 'display:inline-flex!important;align-items:center!important;flex:0 0 auto!important;'
			. 'flex-shrink:0!important;gap:10px!important;margin-left:auto!important;'
			. 'width:max-content!important;height:52px!important;'
			. 'padding-left:12px!important;border-left:.5px solid rgba(0,0,0,.18)!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .dtr-header-utility-cluster #pax-site-lang,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global .pax-site-lang{'
			. 'display:inline-flex!important;flex:0 0 auto!important;flex-shrink:0!important;margin:0!important;}'
			. 'html[dir=rtl] body.dtr-apple-sticky-header #dtr-header-global .dtr-header-utility-cluster,'
			. 'html[lang=ar] body.dtr-apple-sticky-header #dtr-header-global .dtr-header-utility-cluster{'
			. 'margin-left:0!important;margin-right:auto!important;padding-left:0!important;padding-right:12px!important;'
			. 'border-left:0!important;border-right:.5px solid rgba(0,0,0,.18)!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar .pdx-auth-portal-btn,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--logged-out .pdx-auth-account-btn,'
			. 'html body.dtr-apple-sticky-header #dtr-header-global #pdx-auth-bar.pdx-auth-bar--logged-out .pdx-auth-menu{'
			. 'display:none!important;visibility:hidden!important;}'
			. '}'
			. '@media (max-width:992px){'
			. 'html body.dtr-apple-sticky-header #dtr-main-header,'
			. 'html body.dtr-apple-sticky-header.show-onscroll #dtr-main-header{'
			. 'padding-top:0!important;margin-top:0!important;min-height:0!important;}'
			. 'html body.dtr-apple-sticky-header{padding-top:var(--dtr-apple-header-height,52px)!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-header-global{display:none!important;height:0!important;'
			. 'overflow:hidden!important;padding:0!important;margin:0!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header{display:block!important;'
			. 'position:fixed!important;top:0!important;left:0!important;right:0!important;'
			. 'height:var(--dtr-apple-header-height,52px)!important;padding:0!important;margin:0!important;'
			. 'overflow:visible!important;z-index:10050!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header .container{'
			. 'display:flex!important;align-items:center!important;flex-wrap:nowrap!important;'
			. 'gap:8px!important;width:100%!important;height:52px!important;padding:0 12px!important;margin:0!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header .dtr-logo,'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header a.dtr-logo{'
			. 'order:1;direction:ltr!important;unicode-bidi:isolate;margin-inline-end:auto!important;margin-inline-start:0!important;'
			. 'flex:0 0 auto!important;flex-shrink:0!important;min-width:88px!important;max-width:none!important;overflow:visible!important;opacity:1!important;visibility:visible!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #pax-site-lang-mobile{'
			. 'order:2;display:inline-flex!important;position:relative!important;margin:0!important;flex:0 0 auto!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header .dtr-search-modal-trigger,'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header a.dtr-search-modal-trigger{'
			. 'display:none!important;width:0!important;min-width:0!important;margin:0!important;padding:0!important;overflow:hidden!important;pointer-events:none!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #pdx-auth-bar,'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #pdx-auth-bar.pdx-auth-bar--header{'
			. 'order:3;position:relative!important;top:auto!important;right:auto!important;left:auto!important;'
			. 'bottom:auto!important;transform:none!important;margin:0!important;flex:0 0 auto!important;'
			. 'display:flex!important;align-items:center!important;align-self:center!important;z-index:2!important;}'
			. 'html body.dtr-apple-sticky-header.dtr-apple-mnav-open #dtr-responsive-header #pdx-auth-bar,'
			. 'html body.dtr-apple-sticky-header.dtr-apple-mnav-open #dtr-responsive-header #pax-site-lang-mobile{'
			. 'display:none!important;visibility:hidden!important;pointer-events:none!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #dtr-menu-button,'
			. 'html body.dtr-apple-sticky-header #dtr-responsive-header #dtr-menu-button.dtr-hamburger{'
			. 'order:4;position:relative!important;top:auto!important;right:auto!important;left:auto!important;'
			. 'margin:0!important;margin-top:0!important;margin-inline-start:4px!important;'
			. 'flex:0 0 44px!important;width:44px!important;height:44px!important;z-index:8!important;}'
			. 'html body.dtr-apple-sticky-header #dtr-main-wrapper{padding-top:0!important;margin-top:0!important;}'
			. '}'
			. '</style>' . "\n";
	}
}
add_action( 'wp_footer', 'navein_apple_header_desktop_cascade_footer', PHP_INT_MAX );
