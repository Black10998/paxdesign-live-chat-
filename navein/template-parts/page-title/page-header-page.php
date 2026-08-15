<?php
/**
 * The Title for page — Apple-style minimal intro (no legacy banner card).
 *
 * @package NaveinTheme
 * @version 1.3.6
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$skip_legacy_banner =
	is_home()
	|| is_front_page()
	|| is_page_template( 'template-no-page-header.php' )
	|| is_page_template( 'template-apple-homepage.php' )
	|| is_page_template( 'template-apple-app-entwicklung.php' )
	|| is_page_template( 'template-apple-advanced-website-systems.php' )
	|| is_page_template( 'template-apple-softwareentwicklung.php' )
	|| is_page_template( 'template-apple-wartung-support.php' )
	|| is_page_template( 'template-apple-webentwicklung.php' )
	|| is_page_template( 'template-apple-cybercrime-support.php' )
	|| is_page_template( 'template-apple-impressum.php' )
	|| is_page_template( 'template-apple-unsere-experten.php' )
	|| is_page_template( 'template-apple-leistungen.php' )
	|| is_page_template( 'template-apple-referenzen.php' )
	|| is_page( 'app-entwicklung' )
	|| is_page( 'advanced-website-systems' )
	|| is_page( 'softwareentwicklung' )
	|| is_page( 'wartung-support' )
	|| is_page( 'webentwicklung' )
	|| is_page( 'cybercrime-support' )
	|| is_page( 'impressum' )
	|| is_page( 'unsere-experten' )
	|| is_page( 'it-consulting' )
	|| is_page( 'leistungen' )
	|| is_page( 787 )
	|| is_page( 'referenzen' )
	|| is_page( 791 );

if ( $skip_legacy_banner ) {
	return;
}

if ( true != navein_get_theme_option( 'navein_enable_pagetitle_section', true ) ) {
	return;
}

$show_title = ( true == navein_get_theme_option( 'navein_enable_page_title', true ) );
$show_crumbs = ( true == navein_get_theme_option( 'navein_enable_page_breadcrumb', true ) );

if ( ! $show_title && ! $show_crumbs ) {
	return;
}
?>
<div class="dtr-apple-page-intro">
	<div class="dtr-apple-page-intro__inner">
		<?php if ( $show_title ) {
			the_title( '<h1 class="dtr-apple-page-intro__title">', '</h1>' );
		} ?>
		<?php if ( $show_crumbs ) { ?>
			<div class="dtr-apple-page-intro__crumbs dtr-breadcrumb-wrapper">
				<?php get_template_part( '/template-parts/header/breadcrumb' ); ?>
			</div>
		<?php } ?>
	</div>
</div>
