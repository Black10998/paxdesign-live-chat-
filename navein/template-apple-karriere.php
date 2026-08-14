<?php
/**
 * Template Name: Apple Karriere
 * Apple-inspired careers & job application page.
 *
 * @package NaveinTheme
 * @version 1.4.22
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-karriere-wrap">
	<main id="dtr-primary-section" class="dtr-content-area" aria-label="<?php esc_attr_e( 'Karriere', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/karriere' ); ?>
	</main>
</div>
<?php
get_footer();
