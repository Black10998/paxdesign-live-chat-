<?php
/**
 * The Page header for archives
 *
 * @package NaveinTheme
 * @version 1.0.0
 */
// Page header hook
if( ! function_exists( 'navein_default_page_header_hook' ) ) {
	function navein_default_page_header_hook() {
		navein_page_header();
	}
}
// Build Page Title
if ( ! function_exists('navein_page_title') ) {
	function navein_page_title( $page_title='' ) {

		if ( is_home() ) {
			$page_title = navein_get_theme_option( 'navein_blog_title', esc_html__('Blog','navein') );
		}
		// Archives
		elseif ( is_archive() ) {
			// category
			if ( is_category() ) {
				$page_title = sprintf( single_cat_title( '', false ) );
			// tag
			} elseif ( is_tag() ) {
        		$page_title = sprintf( single_tag_title( '', false ) );
			// Daily archive
			} elseif ( is_day() ) {
				$page_title = sprintf( get_the_date() );
			// Monthly archive
			} elseif ( is_month() ) {
				$page_title = sprintf( get_the_date( _x( 'F Y', 'monthly archives date format', 'navein' ) ) );
			// Yearly archive
			} elseif ( is_year() ) {
				$page_title = sprintf( get_the_date( _x( 'Y', 'yearly archives date format', 'navein' ) ) );
			//Author
			} elseif ( is_author() ) {
         		$page_title = sprintf( get_the_author() );
			// Standard title
			} else {
				$page_title = single_term_title("", false);
				if ( $page_title == '' ) {
					$post_id = get_the_ID();
					$page_title = get_the_title( $post_id );
				}
			}
		// Search
		} elseif( is_search() ) {
			/* translators: %s: search query. */
			$page_title =  sprintf( esc_html__( ' Search Results : %s', 'navein' ), get_search_query() );
		// else
		} else {
			$post_id = get_the_ID();
			$page_title = get_the_title( $post_id );
		}
		return $page_title;
	}
}
// Page Header output
if ( ! function_exists( 'navein_page_header' ) ) {
	function navein_page_header() {
		$page_title = navein_page_title();

		if ( is_front_page() || is_page() || is_single() || is_404() ) {
			return;
		}
		if ( false == navein_get_theme_option( 'navein_enable_archive_pagetitle_section', true ) ) {
			return;
		}

		$show_title  = ( true == navein_get_theme_option( 'navein_enable_archive_page_title', true ) );
		$show_crumbs = ( true == navein_get_theme_option( 'navein_enable_archive_breadcrumb', true ) );

		if ( ! $show_title && ! $show_crumbs ) {
			return;
		}
		?>
<div class="dtr-apple-page-intro">
	<div class="dtr-apple-page-intro__inner">
		<?php if ( $show_title ) { ?>
			<h1 class="dtr-apple-page-intro__title dtr-blog-title"><?php echo esc_html( $page_title ); ?></h1>
		<?php } ?>
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