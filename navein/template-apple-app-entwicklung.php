<?php
/**
 * Template Name: Apple App Entwicklung
 * Premium Apple-inspired layout for the App-Entwicklung page.
 * Does not render Elementor/card content — self-contained design.
 *
 * @package NaveinTheme
 * @version 1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'App-Entwicklung', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/app-entwicklung' ); ?>
	</main>
</div>
<?php
get_footer();
