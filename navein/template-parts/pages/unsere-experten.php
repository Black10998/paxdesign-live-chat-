<?php
/**
 * Apple-inspired Unsere Experten page — real profile only.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_url = home_url( '/kontakt/' );
$phone       = '+43 681 20543638';
$email       = 'info@paxdesign.at';
$portrait    = 'https://paxdesign.at/wp-content/uploads/2025/12/38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif';
$name        = 'Ahmad Al-Khalaf';
$role        = __( 'Gründer & Webentwickler', 'navein' );
?>
<article class="pax-experts" id="pax-apple-experts">

	<header class="pax-experts__hero">
		<div class="pax-experts__hero-inner">
			<p class="pax-experts__eyebrow"><?php esc_html_e( 'Unsere Experten', 'navein' ); ?></p>
			<h1 class="pax-experts__title">
				<?php esc_html_e( 'Die Expertise hinter', 'navein' ); ?>
				<span class="pax-experts__brand">PAXdesign</span>
			</h1>
			<p class="pax-experts__lede">
				<?php esc_html_e( 'Klare digitale Systeme, durchdachte Interfaces und zuverlässige Umsetzung, von der Idee bis zum Launch.', 'navein' ); ?>
			</p>
		</div>
	</header>

	<section class="pax-experts__profile" data-experts-reveal aria-labelledby="pax-expert-name">
		<div class="pax-experts__profile-inner">
			<figure class="pax-experts__portrait">
				<img
					src="<?php echo esc_url( $portrait ); ?>"
					alt="<?php echo esc_attr( $name ); ?>"
					width="720"
					height="720"
					loading="eager"
					decoding="async"
				>
			</figure>

			<div class="pax-experts__copy">
				<p class="pax-experts__role"><?php echo esc_html( $role ); ?></p>
				<h2 class="pax-experts__name" id="pax-expert-name"><?php echo esc_html( $name ); ?></h2>
				<p class="pax-experts__bio">
					<?php esc_html_e( 'Ahmad ist Gründer von PAXdesign und entwickelt moderne, responsive Weblösungen mit Fokus auf Detail, Performance und langfristige Wartbarkeit. Mit fundiertem Wissen in HTML5, CSS3, JavaScript und modernen Frameworks wie React entstehen skalierbare Architekturen, die visuell klar und technisch belastbar sind.', 'navein' ); ?>
				</p>
				<ul class="pax-experts__skills" aria-label="<?php esc_attr_e( 'Schwerpunkte', 'navein' ); ?>">
					<li>HTML5 &amp; CSS3</li>
					<li>JavaScript / React</li>
					<li><?php esc_html_e( 'Responsive Design', 'navein' ); ?></li>
					<li><?php esc_html_e( 'Web Performance', 'navein' ); ?></li>
				</ul>
				<div class="pax-experts__actions">
					<a class="pax-experts__btn pax-experts__btn--dark" href="<?php echo esc_url( $contact_url ); ?>">
						<?php esc_html_e( 'Projekt anfragen', 'navein' ); ?>
					</a>
					<a class="pax-experts__btn pax-experts__btn--text" href="<?php echo esc_url( 'mailto:' . $email ); ?>">
						<?php echo esc_html( $email ); ?>
					</a>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-experts__approach" data-experts-reveal>
		<div class="pax-experts__approach-inner">
			<p class="pax-experts__eyebrow"><?php esc_html_e( 'Arbeitsweise', 'navein' ); ?></p>
			<h2 class="pax-experts__approach-title"><?php esc_html_e( 'Qualität, die man merkt.', 'navein' ); ?></h2>
			<div class="pax-experts__approach-grid">
				<div class="pax-experts__approach-item">
					<h3><?php esc_html_e( 'Exzellenz', 'navein' ); ?></h3>
					<p><?php esc_html_e( 'Höchste Qualität in jedem Projekt, mit modernen Technologien und klaren Best Practices.', 'navein' ); ?></p>
				</div>
				<div class="pax-experts__approach-item">
					<h3><?php esc_html_e( 'Zuverlässigkeit', 'navein' ); ?></h3>
					<p><?php esc_html_e( 'Termine, Qualität und Support, auf die Sie sich verlassen können.', 'navein' ); ?></p>
				</div>
				<div class="pax-experts__approach-item">
					<h3><?php esc_html_e( 'Innovation', 'navein' ); ?></h3>
					<p><?php esc_html_e( 'Frische Ideen und aktuelle Technologien für Lösungen, die vorausdenken.', 'navein' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-experts__cta" data-experts-reveal>
		<div class="pax-experts__cta-inner">
			<h2 class="pax-experts__cta-title"><?php esc_html_e( 'Bereit für Ihr nächstes Projekt?', 'navein' ); ?></h2>
			<p class="pax-experts__cta-lede">
				<?php esc_html_e( 'Lassen Sie uns gemeinsam Ihre nächste digitale Lösung realisieren.', 'navein' ); ?>
			</p>
			<div class="pax-experts__cta-links">
				<a href="<?php echo esc_url( 'tel:' . preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				<span aria-hidden="true">·</span>
				<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<span aria-hidden="true">·</span>
				<a href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Kontakt', 'navein' ); ?></a>
			</div>
		</div>
	</section>

</article>
