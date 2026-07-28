<?php
/**
 * Apple-inspired App-Entwicklung page content.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_url = home_url( '/kontakt/' );
$phone       = '+43 681 20543638';
$email       = 'info@paxdesign.at';
$icon_url    = 'https://paxdesign.at/wp-content/uploads/2026/07/paxdesign-origin-app-icon.png';
?>
<article <?php post_class( 'pax-aap' ); ?>>

	<!-- Hero -->
	<section class="pax-aap-hero" data-aap-reveal>
		<div class="pax-aap-hero__inner">
			<p class="pax-aap-eyebrow">App-Entwicklung</p>
			<h1 class="pax-aap-hero__title">
				Native Apps für<br>
				<span class="pax-aap-hero__accent">iOS, Android &amp; TV.</span>
			</h1>
			<p class="pax-aap-hero__lede">
				Von mobilen Apps bis zu Smart‑TV‑Anwendungen — performant, intuitiv und für jede Plattform gedacht.
			</p>
			<div class="pax-aap-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Beratung anfragen</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#plattformen">Plattformen entdecken</a>
			</div>
		</div>
		<div class="pax-aap-hero__stage" aria-hidden="true">
			<div class="pax-aap-device pax-aap-device--phone">
				<div class="pax-aap-device__bezel">
					<div class="pax-aap-device__screen">
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="" width="120" height="120" loading="eager" decoding="async">
						<span>PAXdesign</span>
					</div>
				</div>
			</div>
			<div class="pax-aap-hero__glow"></div>
		</div>
	</section>

	<!-- Statement -->
	<section class="pax-aap-statement" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow">
			<p class="pax-aap-statement__text">
				Wir bauen digitale Produkte, die sich anfühlen wie selbstverständlich — klar in der Bedienung, stark in der Technik, bereit für Wachstum.
			</p>
		</div>
	</section>

	<!-- Platforms intro -->
	<section id="plattformen" class="pax-aap-section pax-aap-section--light" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Plattformen</p>
			<h2 class="pax-aap-display">Für jedes Gerät<br>die perfekte App.</h2>
		</div>
	</section>

	<!-- iOS -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">iOS</p>
				<h3 class="pax-aap-feature__title">iOS Apps</h3>
				<p class="pax-aap-feature__text">
					Native Entwicklung mit Swift und SwiftUI für iPhone und iPad — präzise, flüssig und tief in das Apple‑Ökosystem integriert.
				</p>
				<ul class="pax-aap-list">
					<li>App Store Optimierung</li>
					<li>Push Notifications</li>
					<li>In‑App Purchases</li>
					<li>Apple Watch Integration</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--ios">
					<span class="pax-aap-visual__mark">Swift</span>
					<span class="pax-aap-visual__mark">SwiftUI</span>
					<span class="pax-aap-visual__mark">iPhone</span>
					<span class="pax-aap-visual__mark">iPad</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Android -->
	<section class="pax-aap-feature pax-aap-feature--light" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid pax-aap-feature__grid--reverse">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow">Android</p>
				<h3 class="pax-aap-feature__title">Android Apps</h3>
				<p class="pax-aap-feature__text">
					Native Android‑Entwicklung mit Kotlin und Jetpack Compose — modern, skalierbar und bereit für den Google Play Store.
				</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Google Play Optimierung</li>
					<li>Firebase Integration</li>
					<li>Material Design 3</li>
					<li>Wear OS Support</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--android">
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Kotlin</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Compose</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Play</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Wear OS</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Smart TV -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Living Room</p>
				<h3 class="pax-aap-feature__title">Smart TV Apps</h3>
				<p class="pax-aap-feature__text">
					Große Screens, klare Navigation — Apps für Apple TV, Android TV, Fire TV und Samsung Tizen.
				</p>
				<ul class="pax-aap-list">
					<li>Apple tvOS</li>
					<li>Android TV</li>
					<li>Amazon Fire TV</li>
					<li>Samsung Tizen</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--tv">
					<div class="pax-aap-tv">
						<div class="pax-aap-tv__screen"></div>
						<div class="pax-aap-tv__stand"></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Cross / Backend / Care strip -->
	<section class="pax-aap-strip" data-aap-reveal>
		<div class="pax-aap-wrap">
			<div class="pax-aap-strip__row">
				<div class="pax-aap-strip__item">
					<h3>Cross‑Platform</h3>
					<p>Eine Codebasis für iOS und Android mit React Native oder Flutter — schnellere Entwicklung, klare Kosteneffizienz.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Backend &amp; API</h3>
					<p>Skalierbare Infrastruktur mit REST &amp; GraphQL, Cloud Hosting, Datenbank‑Design und Real‑time Features.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Wartung &amp; Updates</h3>
					<p>Bug Fixes, OS‑Updates, Feature‑Releases und Performance‑Monitoring — langfristig betreut.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Process -->
	<section class="pax-aap-section pax-aap-section--light pax-aap-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">Von der Idee<br>zur fertigen App.</h2>

			<ol class="pax-aap-process__list">
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">01</span>
					<div>
						<h3>Konzeption</h3>
						<p>Analyse Ihrer Anforderungen und ein klares App‑Konzept.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">02</span>
					<div>
						<h3>UI/UX Design</h3>
						<p>Wireframes, Prototypen und finales Design für echte Nutzer.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">03</span>
					<div>
						<h3>Entwicklung</h3>
						<p>Native oder Cross‑Platform mit modernen Best Practices.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">04</span>
					<div>
						<h3>Testing</h3>
						<p>QA auf Geräten und Betriebssystemversionen.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">05</span>
					<div>
						<h3>Launch</h3>
						<p>Veröffentlichung in App Store und Google Play.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">06</span>
					<div>
						<h3>Support</h3>
						<p>Wartung, Updates und technischer Support nach dem Launch.</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-aap-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-aap-cta__inner">
			<h2 class="pax-aap-display pax-aap-display--light">Bereit für<br>Ihre App?</h2>
			<p class="pax-aap-cta__text">Lassen Sie uns Ihre Idee gemeinsam zum Leben erwecken.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">Kostenlose Beratung</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
