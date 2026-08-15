<?php
/**
 * Template Name: Apple Referenzen
 * Apple product-page presentation of PAXDesign references.
 *
 * @package NaveinTheme
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-referenzen-wrap">
	<main id="dtr-primary-section" class="dtr-content-area" aria-label="<?php esc_attr_e( 'Referenzen', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/referenzen' ); ?>
	</main>
</div>
<?php
get_footer();
