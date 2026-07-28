<?php
/**
 * Apple-inspired Advanced Website Systems page content.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_url = home_url( '/kontakt/' );
$phone       = '+43 681 20543638';
$email       = 'info@paxdesign.at';
?>
<article <?php post_class( 'pax-aap' ); ?>>

	<!-- Hero -->
	<section class="pax-aap-hero pax-aap-hero--desktop" data-aap-reveal>
		<div class="pax-aap-hero__inner">
			<p class="pax-aap-eyebrow">Advanced Website Systems</p>
			<h1 class="pax-aap-hero__title">
				Skalierbare Systeme<br>
				<span class="pax-aap-hero__accent">für das Web.</span>
			</h1>
			<p class="pax-aap-hero__lede">
				Individuelle Website‑Architekturen, performant, sicher und bereit für Wachstum. Keine Templates von der Stange, sondern Systeme, die mit Ihrem Unternehmen skalieren.
			</p>
			<div class="pax-aap-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Beratung anfragen</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#systeme">Systeme entdecken</a>
			</div>
		</div>
		<div class="pax-aap-hero__stage" aria-hidden="true">
			<div class="pax-aap-device pax-aap-device--desktop">
				<div class="pax-aap-desktop">
					<div class="pax-aap-desktop__bezel">
						<span class="pax-aap-desktop__dot"></span>
						<span class="pax-aap-desktop__dot"></span>
						<span class="pax-aap-desktop__dot"></span>
						<span class="pax-aap-desktop__url"></span>
					</div>
					<div class="pax-aap-desktop__screen">
						<div class="pax-aap-desktop__chrome">
							<div class="pax-aap-desktop__nav">
								<span></span><span></span><span></span><span></span>
							</div>
							<div class="pax-aap-desktop__stage">
								<div class="pax-aap-desktop__hero-block"></div>
								<div class="pax-aap-desktop__grid">
									<i></i><i></i><i></i>
								</div>
							</div>
						</div>
						<div class="pax-aap-desktop__glow"></div>
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
				Wir entwickeln Website‑Systeme, die Klarheit in der Oberfläche mit Stabilität in der Architektur verbinden, elegant für Nutzer, robust für Teams.
			</p>
		</div>
	</section>

	<!-- Systems intro -->
	<section id="systeme" class="pax-aap-section pax-aap-section--light" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Systeme</p>
			<h2 class="pax-aap-display">Gebaut für Leistung,<br>Sicherheit und Scale.</h2>
		</div>
	</section>

	<!-- Architecture -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Architecture</p>
				<h3 class="pax-aap-feature__title">Systemarchitektur</h3>
				<p class="pax-aap-feature__text">
					Saubere Strukturen, klare Module und zukunftssichere Grundlagen, von Corporate Sites bis komplexen Web‑Plattformen.
				</p>
				<ul class="pax-aap-list">
					<li>Modulare Informationsarchitektur</li>
					<li>Headless‑ &amp; Hybrid‑Setups</li>
					<li>API‑first Integrationen</li>
					<li>Skalierbare Hosting‑Strategien</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--ios">
					<span class="pax-aap-visual__mark">CMS</span>
					<span class="pax-aap-visual__mark">API</span>
					<span class="pax-aap-visual__mark">Cloud</span>
					<span class="pax-aap-visual__mark">Scale</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Experience -->
	<section class="pax-aap-feature pax-aap-feature--light" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid pax-aap-feature__grid--reverse">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow">Experience</p>
				<h3 class="pax-aap-feature__title">Design &amp; UX</h3>
				<p class="pax-aap-feature__text">
					Ruhige Typografie, klare Hierarchien und Interfaces, die sich selbst erklären, auf Desktop, Tablet und Mobile.
				</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Premium UI‑Systeme</li>
					<li>Responsive Layouts</li>
					<li>Accessibility‑Standards</li>
					<li>Conversion‑orientierte Flows</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--android">
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">UI</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">UX</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">A11y</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Motion</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Performance & Security -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Reliability</p>
				<h3 class="pax-aap-feature__title">Performance &amp; Security</h3>
				<p class="pax-aap-feature__text">
					Schnelle Ladezeiten, harte Sicherheitsstandards und eine technische Basis, die auch unter Last ruhig bleibt.
				</p>
				<ul class="pax-aap-list">
					<li>Core Web Vitals Optimierung</li>
					<li>CDN, Caching &amp; Bildpipelines</li>
					<li>Hardening &amp; Monitoring</li>
					<li>DSGVO‑konforme Setups</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--tv">
					<div class="pax-aap-tv">
						<div class="pax-aap-tv__screen pax-aap-tv__screen--metrics"></div>
						<div class="pax-aap-tv__stand"></div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Capabilities strip -->
	<section class="pax-aap-strip" data-aap-reveal>
		<div class="pax-aap-wrap">
			<div class="pax-aap-strip__row">
				<div class="pax-aap-strip__item">
					<h3>WordPress &amp; CMS</h3>
					<p>Individuelle Themes, saubere Editor‑Erlebnisse und Systeme, die Inhalte‑Teams wirklich bedienen können.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Integrationen</h3>
					<p>CRM, Buchung, Analytics, Automatisierung und Payment, sauber angebunden statt notdürftig angehängt.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Betrieb &amp; Care</h3>
					<p>Updates, Backups, Monitoring und Weiterentwicklung, damit Ihr System dauerhaft auf Niveau bleibt.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Process -->
	<section class="pax-aap-section pax-aap-section--light pax-aap-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">Vom Konzept<br>zum laufenden System.</h2>

			<ol class="pax-aap-process__list">
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">01</span>
					<div>
						<h3>Analyse</h3>
						<p>Ziele, Inhalte, technische Rahmenbedingungen und Prioritäten klären.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">02</span>
					<div>
						<h3>Konzeption</h3>
						<p>Informationsarchitektur, Wireframes und ein belastbares Systemkonzept.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">03</span>
					<div>
						<h3>Design</h3>
						<p>Visuelles System, Typografie und Interfaces mit klarer Hierarchie.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">04</span>
					<div>
						<h3>Entwicklung</h3>
						<p>Frontend, Backend und Integrationen, wartbar und dokumentiert.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">05</span>
					<div>
						<h3>Launch</h3>
						<p>Go‑Live mit Performance‑Checks, Security‑Review und sauberer Übergabe.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">06</span>
					<div>
						<h3>Weiterentwicklung</h3>
						<p>Messung, Iteration und Ausbau, Ihr System wächst mit.</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-aap-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-aap-cta__inner">
			<h2 class="pax-aap-display pax-aap-display--light">Bereit für ein<br>stärkeres System?</h2>
			<p class="pax-aap-cta__text">Lassen Sie uns Ihre Website als skalierbares Produkt denken und bauen.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">Kostenlose Beratung</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
