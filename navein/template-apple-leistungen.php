<?php
/**
 * Template Name: Apple Leistungen
 * Premium Apple-inspired Leistungen page.
 *
 * @package NaveinTheme
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-leistungen-wrap">
	<main id="dtr-primary-section" class="dtr-content-area" aria-label="<?php esc_attr_e( 'Leistungen', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/leistungen' ); ?>
	</main>
</div>
<?php
get_footer();
