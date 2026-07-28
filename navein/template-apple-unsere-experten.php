<?php
/**
 * Template Name: Apple Unsere Experten
 * Premium Apple-inspired experts page.
 *
 * @package NaveinTheme
 * @version 1.4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-experts-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-experts" aria-label="<?php esc_attr_e( 'Unsere Experten', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/unsere-experten' ); ?>
	</main>
</div>
<?php
get_footer();
