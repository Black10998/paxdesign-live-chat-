<?php
/**
 * German 404 for the public website.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main pax-404" role="main">
	<div class="pax-404__inner" style="max-width:720px;margin:12vh auto 16vh;padding:0 24px;text-align:center;">
		<p class="pax-404__eyebrow" style="margin:0 0 12px;letter-spacing:.08em;text-transform:uppercase;font-size:12px;color:#6e6e73;">404</p>
		<h1 class="pax-404__title" style="margin:0 0 16px;font-size:clamp(32px,5vw,48px);line-height:1.1;color:#1d1d1f;">Seite nicht gefunden</h1>
		<p class="pax-404__text" style="margin:0 0 28px;font-size:18px;line-height:1.5;color:#6e6e73;">
			Die angeforderte Seite existiert nicht oder wurde verschoben.
		</p>
		<p>
			<a class="pax-404__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;padding:12px 22px;border-radius:980px;background:#000;color:#fff;text-decoration:none;">Zur Startseite</a>
		</p>
	</div>
</main>
<?php
get_footer();
