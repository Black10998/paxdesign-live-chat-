<?php
/**
 * Template Name: Apple Homepage
 * Complete Apple-origin marketing homepage.
 *
 * @package NaveinTheme
 * @version 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap pax-apple-home-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app pax-apple-home" aria-label="<?php esc_attr_e( 'PAXdesign Startseite', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/homepage' ); ?>
	</main>
</div>
<?php
get_footer();
