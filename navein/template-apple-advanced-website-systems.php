<?php
/**
 * Template Name: Apple Advanced Website Systems
 * Premium Apple-inspired layout for Advanced Website Systems.
 *
 * @package NaveinTheme
 * @version 1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'Advanced Website Systems', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/advanced-website-systems' ); ?>
	</main>
</div>
<?php
get_footer();
