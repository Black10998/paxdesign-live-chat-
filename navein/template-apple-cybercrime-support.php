<?php
/**
 * Template Name: Apple Cybercrime Support
 * Premium Apple-inspired Cybercrime Support page.
 *
 * @package NaveinTheme
 * @version 1.4.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth pax-ccs-portal-wrap">
	<main id="dtr-primary-section" class="dtr-content-area" aria-label="<?php esc_attr_e( 'Cybercrime Support', 'navein' ); ?>">
		<?php get_template_part( 'template-parts/pages/cybercrime-support' ); ?>
	</main>
</div>
<?php
get_footer();
