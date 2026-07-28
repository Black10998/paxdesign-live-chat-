<?php
/**
 * Apple-inspired Webentwicklung page — unique product identity.
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
<article <?php post_class( 'pax-aap pax-aap--web' ); ?>>

	<!-- Asymmetric browser hero -->
	<section class="pax-web-hero" data-aap-reveal>
		<div class="pax-web-hero__copy">
			<p class="pax-aap-eyebrow">Webentwicklung</p>
			<h1 class="pax-web-hero__title">
				Moderne Weblösungen<br>
				<span>für digitale Exzellenz.</span>
			</h1>
			<p class="pax-web-hero__lede">
				Corporate Websites, E‑Commerce und Web Apps, performant, sicher und für jedes Gerät gedacht.
			</p>
			<div class="pax-web-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Projekt anfragen</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#leistungen">Leistungen entdecken</a>
			</div>
		</div>

		<div class="pax-web-hero__stage" aria-hidden="true">
			<div class="pax-web-browser pax-web-browser--main" data-web-float>
				<div class="pax-web-browser__chrome">
					<span></span><span></span><span></span>
					<em></em>
				</div>
				<div class="pax-web-browser__screen">
					<div class="pax-web-browser__hero-block"></div>
					<div class="pax-web-browser__cols">
						<i></i><i></i><i></i>
					</div>
				</div>
			</div>
			<div class="pax-web-phone" data-web-float-delay>
				<div class="pax-web-phone__bezel">
					<div class="pax-web-phone__screen">
						<span></span><span></span><span></span>
					</div>
				</div>
			</div>
			<div class="pax-web-hero__glow"></div>
		</div>
	</section>

	<!-- Statement band -->
	<section class="pax-web-band" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow">
			<p class="pax-web-band__text">
				Von der Idee bis zum Launch, transparente Prozesse, moderne Technologien und Weblösungen, die messbar performen.
			</p>
		</div>
	</section>

	<!-- Offerings intro -->
	<section id="leistungen" class="pax-web-intro" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Leistungen</p>
			<h2 class="pax-aap-display">Was wir<br>entwickeln.</h2>
		</div>
	</section>

	<!-- Offering bands -->
	<section class="pax-web-offer pax-web-offer--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-web-offer__grid">
			<div class="pax-web-offer__index">01</div>
			<div class="pax-web-offer__body">
				<h3>Corporate Websites</h3>
				<p>Markenstarke Auftritte mit klarer Struktur, starker Typografie und Conversion‑Fokus, gebaut für Vertrauen und Wachstum.</p>
				<ul class="pax-aap-list">
					<li>Informationsarchitektur</li>
					<li>CMS‑fähige Themes</li>
					<li>SEO‑Grundlagen</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="pax-web-offer pax-web-offer--snow" data-aap-reveal>
		<div class="pax-aap-wrap pax-web-offer__grid">
			<div class="pax-web-offer__index pax-web-offer__index--ink">02</div>
			<div class="pax-web-offer__body pax-web-offer__body--ink">
				<h3>E‑Commerce</h3>
				<p>Leistungsstarke Online‑Shops mit sicheren Zahlungssystemen, klaren Produktflows und Anbindung an Warenwirtschaft.</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Checkout &amp; Payments</li>
					<li>Produkt‑ &amp; Katalogsysteme</li>
					<li>Conversion‑Optimierung</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="pax-web-offer pax-web-offer--ink" data-aap-reveal>
		<div class="pax-aap-wrap pax-web-offer__grid">
			<div class="pax-web-offer__index">03</div>
			<div class="pax-web-offer__body">
				<h3>Web Apps</h3>
				<p>Komplexe Webanwendungen mit React, Vue oder Angular, interaktiv, wartbar und bereit für echte Business‑Prozesse.</p>
				<ul class="pax-aap-list">
					<li>Custom Interfaces</li>
					<li>API‑Integrationen</li>
					<li>Dashboards &amp; Workflows</li>
				</ul>
			</div>
		</div>
	</section>

	<!-- Quality pillars -->
	<section class="pax-web-pillars" data-aap-reveal>
		<div class="pax-web-pillars__col">
			<span>Responsive</span>
			<h3>Jedes Gerät. Eine Erfahrung.</h3>
			<p>Perfekte Darstellung vom Smartphone bis zum Desktop, flüssig, klar und konsistent.</p>
		</div>
		<div class="pax-web-pillars__col">
			<span>Performance</span>
			<h3>Schnell. Messbar. SEO‑stark.</h3>
			<p>Optimierte Ladezeiten und starke Core Web Vitals, für Nutzer und Suchmaschinen.</p>
		</div>
		<div class="pax-web-pillars__col">
			<span>Sicherheit</span>
			<h3>Schutz, der mitläuft.</h3>
			<p>SSL, sichere Authentifizierung und Hardening gegen typische Angriffsvektoren.</p>
		</div>
	</section>

	<!-- Tech strip -->
	<section class="pax-web-tech" data-aap-reveal aria-label="Technologien">
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Stack</p>
			<h2 class="pax-aap-display pax-aap-display--light">Unsere Technologien.</h2>
			<ul class="pax-web-tech__list">
				<li>HTML</li><li>CSS</li><li>JavaScript</li><li>TypeScript</li>
				<li>React</li><li>Vue</li><li>Angular</li><li>WordPress</li>
				<li>Node</li><li>PHP</li><li>REST</li><li>GraphQL</li>
			</ul>
		</div>
	</section>

	<!-- Process spine -->
	<section class="pax-web-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">Wie wir arbeiten.</h2>
			<ol class="pax-web-process__spine">
				<li>
					<span>01</span>
					<div>
						<strong>Analyse &amp; Konzept</strong>
						<em>Anforderungen, Ziele und ein maßgeschneidertes Konzept.</em>
					</div>
				</li>
				<li>
					<span>02</span>
					<div>
						<strong>Design &amp; Prototyping</strong>
						<em>UI/UX und interaktive Prototypen für echte Nutzerpfade.</em>
					</div>
				</li>
				<li>
					<span>03</span>
					<div>
						<strong>Entwicklung</strong>
						<em>Sauberer, wartbarer Code mit modernen Best Practices.</em>
					</div>
				</li>
				<li>
					<span>04</span>
					<div>
						<strong>Testing &amp; QA</strong>
						<em>Checks auf Geräten und Browsern für stabile Performance.</em>
					</div>
				</li>
				<li>
					<span>05</span>
					<div>
						<strong>Launch &amp; Care</strong>
						<em>Go‑Live, Wartung und kontinuierliche Optimierung.</em>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-web-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow">
			<p class="pax-aap-eyebrow">Next step</p>
			<h2 class="pax-aap-display">Bereit für Ihr<br>Webprojekt?</h2>
			<p class="pax-web-cta__text">Lassen Sie uns Ihre Website oder Web App als klares, skalierbares Produkt denken und bauen.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Kostenlose Beratung</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
