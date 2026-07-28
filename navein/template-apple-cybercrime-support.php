<?php
/**
 * Template Name: Apple Cybercrime Support
 * Premium Apple-inspired Cybercrime Support page.
 *
 * @package NaveinTheme
 * @version 1.4.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-apple-app-wrap">
	<main id="dtr-primary-section" class="dtr-content-area pax-apple-app" aria-label="<?php esc_attr_e( 'Cybercrime Support', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/cybercrime-support' ); ?>
	</main>
</div>
<?php
get_footer();
