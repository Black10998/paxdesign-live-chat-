<?php
/**
 * Template Name: Apple Impressum
 * Premium Apple-inspired legal imprint page.
 *
 * @package NaveinTheme
 * @version 1.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-legal-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-legal" aria-label="<?php esc_attr_e( 'Impressum', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/impressum' ); ?>
	</main>
</div>
<?php
get_footer();
