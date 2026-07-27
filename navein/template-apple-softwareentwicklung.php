<?php
/**
 * Template Name: Apple Softwareentwicklung
 * Premium Apple-inspired layout for Softwareentwicklung.
 *
 * @package NaveinTheme
 * @version 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'Softwareentwicklung', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/softwareentwicklung' ); ?>
	</main>
</div>
<?php
get_footer();
