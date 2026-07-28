<?php
/**
 * Apple-inspired Cybercrime Support page content (Arabic default, German toggle).
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$copy        = include __DIR__ . '/cybercrime-support-data.php';
$contact_url = home_url( '/kontakt/' );
$phone       = '+43 681 20543638';
$email       = 'info@paxdesign.at';

if ( ! function_exists( 'pax_ccs_text' ) ) {
	/**
	 * @param array<string, string>|string $node
	 * @param string                     $lang
	 */
	function pax_ccs_text( $node, $lang ) {
		if ( is_array( $node ) && isset( $node[ $lang ] ) ) {
			return $node[ $lang ];
		}
		return is_string( $node ) ? $node : '';
	}
}
?>
<article <?php post_class( 'pax-aap pax-ccs' ); ?> data-ccs-lang="ar" lang="ar" dir="rtl">

	<div class="pax-ccs-langbar" data-aap-reveal>
		<div class="pax-aap-wrap pax-ccs-langbar__inner">
			<span class="pax-ccs-langbar__label pax-ccs-t" data-lang="ar">اللغة</span>
			<span class="pax-ccs-langbar__label pax-ccs-t" data-lang="de" hidden>Sprache</span>
			<div class="pax-ccs-langbar__toggle" role="group" aria-label="Language">
				<button type="button" class="pax-ccs-langbar__btn is-active" data-ccs-switch="ar" aria-pressed="true">العربية</button>
				<button type="button" class="pax-ccs-langbar__btn" data-ccs-switch="de" aria-pressed="false">Deutsch</button>
			</div>
		</div>
	</div>

	<!-- Hero -->
	<section class="pax-aap-hero pax-aap-hero--desktop pax-ccs-hero" data-aap-reveal>
		<div class="pax-aap-hero__inner">
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['eyebrow'], 'ar' ) ); ?></p>
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['eyebrow'], 'de' ) ); ?></p>
			<h1 class="pax-aap-hero__title">
				<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['title'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['title'], 'de' ) ); ?></span>
				<br>
				<span class="pax-aap-hero__accent pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['accent'], 'ar' ) ); ?></span>
				<span class="pax-aap-hero__accent pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['accent'], 'de' ) ); ?></span>
			</h1>
			<p class="pax-aap-hero__lede pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['lede'], 'ar' ) ); ?></p>
			<p class="pax-aap-hero__lede pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['lede'], 'de' ) ); ?></p>
			<div class="pax-aap-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="#overview">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['cta_primary'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['cta_primary'], 'de' ) ); ?></span>
				</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#process">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['hero']['cta_secondary'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['hero']['cta_secondary'], 'de' ) ); ?></span>
				</a>
			</div>
		</div>
		<div class="pax-aap-hero__stage pax-ccs-hero__stage" aria-hidden="true">
			<div class="pax-ccs-shield">
				<div class="pax-ccs-shield__ring"></div>
				<div class="pax-ccs-shield__core"></div>
				<div class="pax-ccs-shield__scan"></div>
			</div>
		</div>
	</section>

	<section class="pax-aap-statement" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow">
			<p class="pax-aap-statement__text pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['statement'], 'ar' ) ); ?></p>
			<p class="pax-aap-statement__text pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['statement'], 'de' ) ); ?></p>
		</div>
	</section>

	<section id="overview" class="pax-aap-section pax-aap-section--light" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['overview']['eyebrow'], 'ar' ) ); ?></p>
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['overview']['eyebrow'], 'de' ) ); ?></p>
			<h2 class="pax-aap-display">
				<span class="pax-ccs-t" data-lang="ar"><?php echo wp_kses_post( pax_ccs_text( $copy['overview']['title'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo wp_kses_post( pax_ccs_text( $copy['overview']['title'], 'de' ) ); ?></span>
			</h2>
			<div class="pax-ccs-overview">
				<?php foreach ( $copy['overview']['items'] as $item ) : ?>
					<div class="pax-ccs-overview__item">
						<h3>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $item['title'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $item['title'], 'de' ) ); ?></span>
						</h3>
						<p>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $item['text'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $item['text'], 'de' ) ); ?></span>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php
	foreach ( $copy['features'] as $index => $feature ) :
		$is_dark  = ( $feature['tone'] ?? 'dark' ) === 'dark';
		$reverse  = ( $index % 2 ) === 1;
		$section  = $is_dark ? 'pax-aap-feature--dark' : 'pax-aap-feature--light';
		$list_cls = $is_dark ? 'pax-aap-list' : 'pax-aap-list pax-aap-list--dark';
		$grid_cls = $reverse ? ' pax-aap-feature__grid--reverse' : '';
		?>
	<section id="<?php echo esc_attr( $feature['id'] ); ?>" class="pax-aap-feature <?php echo esc_attr( $section ); ?>" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid<?php echo esc_attr( $grid_cls ); ?>">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow<?php echo $is_dark ? ' pax-aap-eyebrow--light' : ''; ?> pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $feature['eyebrow'], 'ar' ) ); ?></p>
				<p class="pax-aap-eyebrow<?php echo $is_dark ? ' pax-aap-eyebrow--light' : ''; ?> pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $feature['eyebrow'], 'de' ) ); ?></p>
				<h3 class="pax-aap-feature__title">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $feature['title'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $feature['title'], 'de' ) ); ?></span>
				</h3>
				<p class="pax-aap-feature__text">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $feature['text'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $feature['text'], 'de' ) ); ?></span>
				</p>
				<ul class="<?php echo esc_attr( $list_cls ); ?>">
					<?php foreach ( $feature['items'] as $li ) : ?>
						<li>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $li, 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $li, 'de' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-ccs-visual pax-ccs-visual--<?php echo esc_attr( $feature['visual'] ); ?>"></div>
			</div>
		</div>
	</section>
	<?php endforeach; ?>

	<section id="process" class="pax-aap-section pax-aap-section--light pax-aap-process pax-ccs-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['process']['eyebrow'], 'ar' ) ); ?></p>
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['process']['eyebrow'], 'de' ) ); ?></p>
			<h2 class="pax-aap-display">
				<span class="pax-ccs-t" data-lang="ar"><?php echo wp_kses_post( pax_ccs_text( $copy['process']['title'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo wp_kses_post( pax_ccs_text( $copy['process']['title'], 'de' ) ); ?></span>
			</h2>
			<ol class="pax-aap-process__list">
				<?php
				$step_num = 1;
				foreach ( $copy['process']['steps'] as $step ) :
					?>
					<li class="pax-aap-process__step">
						<span class="pax-aap-process__num"><?php echo esc_html( sprintf( '%02d', $step_num ) ); ?></span>
						<div>
							<h3>
								<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $step['title'], 'ar' ) ); ?></span>
								<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $step['title'], 'de' ) ); ?></span>
							</h3>
							<p>
								<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $step['text'], 'ar' ) ); ?></span>
								<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $step['text'], 'de' ) ); ?></span>
							</p>
						</div>
					</li>
					<?php
					$step_num++;
				endforeach;
				?>
			</ol>
		</div>
	</section>

	<section id="faq" class="pax-aap-section pax-aap-section--light pax-ccs-faq" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['faq']['eyebrow'], 'ar' ) ); ?></p>
			<p class="pax-aap-eyebrow pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['faq']['eyebrow'], 'de' ) ); ?></p>
			<h2 class="pax-aap-display">
				<span class="pax-ccs-t" data-lang="ar"><?php echo wp_kses_post( pax_ccs_text( $copy['faq']['title'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo wp_kses_post( pax_ccs_text( $copy['faq']['title'], 'de' ) ); ?></span>
			</h2>
			<div class="pax-ccs-faq__list">
				<?php foreach ( $copy['faq']['items'] as $faq ) : ?>
					<details class="pax-ccs-faq__item">
						<summary>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $faq['q'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $faq['q'], 'de' ) ); ?></span>
						</summary>
						<p>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $faq['a'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $faq['a'], 'de' ) ); ?></span>
						</p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="trust" class="pax-aap-feature pax-aap-feature--dark pax-ccs-trust" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['trust']['eyebrow'], 'ar' ) ); ?></p>
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['trust']['eyebrow'], 'de' ) ); ?></p>
				<h3 class="pax-aap-feature__title pax-aap-display pax-aap-display--light">
					<span class="pax-ccs-t" data-lang="ar"><?php echo wp_kses_post( pax_ccs_text( $copy['trust']['title'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo wp_kses_post( pax_ccs_text( $copy['trust']['title'], 'de' ) ); ?></span>
				</h3>
				<p class="pax-aap-feature__text">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['trust']['text'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['trust']['text'], 'de' ) ); ?></span>
				</p>
				<ul class="pax-aap-list">
					<?php foreach ( $copy['trust']['points'] as $point ) : ?>
						<li>
							<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $point, 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $point, 'de' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-ccs-visual pax-ccs-visual--trust"></div>
			</div>
		</div>
	</section>

	<section class="pax-aap-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-aap-cta__inner">
			<h2 class="pax-aap-display pax-aap-display--light">
				<span class="pax-ccs-t" data-lang="ar"><?php echo wp_kses_post( pax_ccs_text( $copy['cta']['title'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo wp_kses_post( pax_ccs_text( $copy['cta']['title'], 'de' ) ); ?></span>
			</h2>
			<p class="pax-aap-cta__text">
				<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['cta']['text'], 'ar' ) ); ?></span>
				<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['cta']['text'], 'de' ) ); ?></span>
			</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">
					<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( pax_ccs_text( $copy['cta']['primary'], 'ar' ) ); ?></span>
					<span class="pax-ccs-t" data-lang="de" hidden><?php echo esc_html( pax_ccs_text( $copy['cta']['primary'], 'de' ) ); ?></span>
				</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
