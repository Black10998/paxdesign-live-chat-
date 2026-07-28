<?php
/**
 * The template for displaying portfolio details.
 */
get_header();
if ( true == navein_get_theme_option( 'navein_enable_portfolio_pagetitle_section', true ) ) {
	$show_title  = ( true == navein_get_theme_option( 'navein_enable_portfolio_page_title', true ) );
	$show_crumbs = ( true == navein_get_theme_option( 'navein_enable_portfolio_breadcrumb', true ) );
	if ( $show_title || $show_crumbs ) {
		?>
<div class="dtr-apple-page-intro">
	<div class="dtr-apple-page-intro__inner">
		<?php if ( $show_title ) {
			the_title( '<h1 class="dtr-apple-page-intro__title">', '</h1>' );
		} ?>
		<?php if ( $show_crumbs ) { ?>
			<div class="dtr-apple-page-intro__crumbs dtr-breadcrumb-wrapper">
				<?php get_template_part( '/template-parts/header/breadcrumb' ); ?>
			</div>
		<?php } ?>
	</div>
</div>
		<?php
	}
}
?>
<!-- #page header -->
<div id="dtr-main-wrapper" class="clearfix dtr-fullwidth">
    <main id="dtr-primary-section" class="dtr-content-area">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <div class="container">
            <?php if ( true == navein_get_theme_option( 'navein_portfolio_single_image', true ) ) { ?>
            <div class="dtr-portfolio-thumb <?php echo esc_attr( navein_get_theme_option( 'navein_portfolio_single_image_corner', 'dtr-radius--rounded' ) ) ?>">
                <?php the_post_thumbnail(); ?>
            </div>
            <?php } ?>
            </div>
            <div class="entry-content">
                <?php the_content(); ?>
            </div>
        </article>
        <div class="container">
        <?php navein_post_nav(); ?>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </main>
</div>
<?php get_footer();