<?php
/**
 * The Title for single post — Apple-style minimal intro (no legacy banner card).
 *
 * @package NaveinTheme
 * @version 1.3.6
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_singular( 'dtr_testimonial' ) || is_singular( 'dtr_portfolio' ) ) {
	return;
}

if ( true != navein_get_theme_option( 'navein_enable_single_pagetitle_section', true ) ) {
	return;
}

$show_title = ( true == navein_get_theme_option( 'navein_enable_single_page_title', true ) );
$show_crumbs = ( true == navein_get_theme_option( 'navein_enable_single_breadcrumb', true ) );

if ( ! $show_title && ! $show_crumbs ) {
	return;
}
?>
<div class="dtr-apple-page-intro">
	<div class="dtr-apple-page-intro__inner">
		<?php if ( $show_title ) {
			the_title( '<h1 class="dtr-apple-page-intro__title dtr-single-post-title">', '</h1>' );
		} ?>
		<?php if ( $show_crumbs ) { ?>
			<div class="dtr-apple-page-intro__crumbs dtr-breadcrumb-wrapper">
				<?php get_template_part( '/template-parts/header/breadcrumb' ); ?>
			</div>
		<?php } ?>
	</div>
</div>
