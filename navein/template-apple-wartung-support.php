<?php
/**
 * Template Name: Apple Wartung & Support
 * Premium Apple-inspired layout for Wartung & Support.
 *
 * @package NaveinTheme
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'Wartung & Support', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/wartung-support' ); ?>
	</main>
</div>
<?php
get_footer();
