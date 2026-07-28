<?php
/**
 * Template Name: Apple Webentwicklung
 * Premium Apple-inspired layout for Webentwicklung.
 *
 * @package NaveinTheme
 * @version 1.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'Webentwicklung', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/webentwicklung' ); ?>
	</main>
</div>
<?php
get_footer();
