<?php
/**
 * Complete Apple-origin homepage.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact   = home_url( '/kontakt/' );
$services  = home_url( '/projektpreise/' );
$about     = home_url( '/ueber-uns/' );
$projects  = home_url( '/referenzen/' );
$phone     = '+43 681 20543638';
$email     = 'info@paxdesign.at';
$hero_img  = 'https://paxdesign.at/wp-content/uploads/2026/01/code-2558220_1280.avif';

$service_links = array(
	array( 'Webentwicklung', 'Moderne Websites & Web Apps', home_url( '/webentwicklung/' ) ),
	array( 'App-Entwicklung', 'iOS, Android & TV', home_url( '/app-entwicklung/' ) ),
	array( 'Softwareentwicklung', 'Individuelle Systeme', home_url( '/softwareentwicklung/' ) ),
	array( 'Advanced Website Systems', 'Skalierbare Web-Architekturen', home_url( '/advanced-website-systems/' ) ),
	array( 'Wartung & Support', '24/7 Betreuung & Updates', home_url( '/wartung-support/' ) ),
	array( 'IT-Consulting', 'Technische Beratung', home_url( '/it-consulting/' ) ),
);
?>
<article <?php post_class( 'pax-home' ); ?>>

	<!-- Hero: brand first -->
	<section class="pax-home-hero" data-ph-reveal>
		<div class="pax-home-hero__media" aria-hidden="true">
			<img src="<?php echo esc_url( $hero_img ); ?>" alt="" width="1280" height="720" loading="eager" decoding="async" fetchpriority="high">
			<div class="pax-home-hero__veil"></div>
		</div>
		<div class="pax-home-hero__inner">
			<p class="pax-home-brand">PAXdesign</p>
			<h1 class="pax-home-hero__title">Digitale Systeme,<br>die wirklich funktionieren.</h1>
			<p class="pax-home-hero__lede">
				Websites, Apps, Software und sichere IT‑Systeme — individuell entwickelt, performant und bereit für Wachstum.
			</p>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--light" href="<?php echo esc_url( $services ); ?>">Leistungen entdecken</a>
				<a class="pax-home-btn pax-home-btn--ghost" href="<?php echo esc_url( $contact ); ?>">Jetzt starten</a>
			</div>
			<ul class="pax-home-tags" aria-label="Fokusbereiche">
				<li>Website &amp; Apps</li>
				<li>KI-Automatisierung</li>
				<li>IT-Sicherheit</li>
			</ul>
		</div>
	</section>

	<!-- Manifesto -->
	<section class="pax-home-manifesto" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow">
			<p class="pax-home-manifesto__text">
				Keine Produkte von der Stange — sondern sichere, skalierbare Systeme, die sich anfühlen wie selbstverständlich.
			</p>
		</div>
	</section>

	<!-- Services -->
	<section id="leistungen" class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Leistungen</p>
			<h2 class="pax-home-display">Alles aus einer Hand.<br>Klar entwickelt.</h2>
			<p class="pax-home-lede">Von der ersten Idee bis zum laufenden Betrieb — mit Links direkt in jede Disziplin.</p>
		</div>
		<div class="pax-home-services">
			<?php foreach ( $service_links as $i => $item ) : ?>
				<a class="pax-home-service" href="<?php echo esc_url( $item[2] ); ?>">
					<span class="pax-home-service__num"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<span class="pax-home-service__copy">
						<strong><?php echo esc_html( $item[0] ); ?></strong>
						<em><?php echo esc_html( $item[1] ); ?></em>
					</span>
					<span class="pax-home-service__go" aria-hidden="true">→</span>
				</a>
			<?php endforeach; ?>
		</div>
		<div class="pax-home-wrap pax-home-section__cta">
			<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $services ); ?>">Alle Leistungen</a>
			<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( $contact ); ?>">Angebot anfordern</a>
		</div>
	</section>

	<!-- Capabilities -->
	<section class="pax-home-caps" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow pax-home-eyebrow--light">Expertise</p>
			<h2 class="pax-home-display pax-home-display--light">Professionell.<br>Anspruchsvoll. Präzise.</h2>
		</div>
		<div class="pax-home-caps__grid">
			<div class="pax-home-cap">
				<span>01</span>
				<h3>Webdesign &amp; Webentwicklung</h3>
				<p>Moderne, performante Websites — individuell, skalierbar und auf Ihr Unternehmen zugeschnitten.</p>
				<a class="pax-home-btn pax-home-btn--ghost-light" href="<?php echo esc_url( home_url( '/webentwicklung/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-cap">
				<span>02</span>
				<h3>App- &amp; Softwareentwicklung</h3>
				<p>Native und individuelle Anwendungen für komplexe Anforderungen — von Mobile bis Enterprise.</p>
				<a class="pax-home-btn pax-home-btn--ghost-light" href="<?php echo esc_url( home_url( '/app-entwicklung/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-cap">
				<span>03</span>
				<h3>UI/UX &amp; Produkt</h3>
				<p>Klare Interfaces mit Fokus auf Effizienz, Vertrauen und professionelles Auftreten.</p>
				<a class="pax-home-btn pax-home-btn--ghost-light" href="<?php echo esc_url( home_url( '/visuelles-design/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-cap">
				<span>04</span>
				<h3>Technik &amp; Scale</h3>
				<p>Wartbarer Code, sichere Architektur und Systeme, die mit Ihrem Wachstum mithalten.</p>
				<a class="pax-home-btn pax-home-btn--ghost-light" href="<?php echo esc_url( home_url( '/advanced-website-systems/' ) ); ?>">Mehr erfahren</a>
			</div>
		</div>
	</section>

	<!-- Projects -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Referenzen</p>
			<h2 class="pax-home-display">Ausgewählte Projekte.</h2>
			<p class="pax-home-lede">Arbeiten, die Expertise, Präzision und Kreativität zeigen.</p>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $projects ); ?>">Alle Projekte ansehen</a>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/projekte-referenzen/' ) ); ?>">Cases entdecken</a>
			</div>
		</div>
		<div class="pax-home-project-stage" aria-hidden="true">
			<div class="pax-home-project-frame">
				<div class="pax-home-project-frame__bar"><i></i><i></i><i></i></div>
				<div class="pax-home-project-frame__body"></div>
			</div>
		</div>
	</section>

	<!-- About -->
	<section class="pax-home-about" data-ph-reveal>
		<div class="pax-home-wrap pax-home-about__grid">
			<div>
				<p class="pax-home-eyebrow">Über uns</p>
				<h2 class="pax-home-display">Wir sind<br>PAXdesign.</h2>
				<p class="pax-home-lede">Digitale Entwickler seit 2016 — Technologie, Design und Strategie für leistungsstarke Websites und individuelle Software.</p>
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $about ); ?>">Mehr erfahren</a>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/unsere-experten/' ) ); ?>">Unsere Experten</a>
			</div>
			<ul class="pax-home-stats">
				<li><strong>10+</strong><span>Jahre Erfahrung</span></li>
				<li><strong>150+</strong><span>Abgeschlossene Projekte</span></li>
				<li><strong>98%</strong><span>Kundenzufriedenheit</span></li>
			</ul>
		</div>
	</section>

	<!-- Awards -->
	<section class="pax-home-awards" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow">
			<p class="pax-home-eyebrow pax-home-eyebrow--light">Awards</p>
			<h2 class="pax-home-display pax-home-display--light">Ergebnisse, die für sich sprechen.</h2>
			<p class="pax-home-awards__text">Gewinner der German Web Awards 2021 &amp; 2022 und ausgezeichnet mit dem Deutschen Agenturpreis 2021.</p>
		</div>
	</section>

	<!-- Testimonials -->
	<section class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Kundenstimmen</p>
			<h2 class="pax-home-display">Was unsere Kunden sagen.</h2>
			<div class="pax-home-quotes">
				<blockquote>
					<p>PAXdesign hat uns geholfen, dass potenzielle Kunden klarer erkennen, wer wir sind. Der Auftritt wirkt deutlich professioneller.</p>
					<footer>Thomas Müller · CEO, TechStart GmbH</footer>
				</blockquote>
				<blockquote>
					<p>100% zufrieden. Modern, seriös, einfach zu bedienen — und dennoch besonders. Absolute Empfehlung.</p>
					<footer>Jannis Rettig · CEO, Rettig &amp; Partner</footer>
				</blockquote>
				<blockquote>
					<p>Qualität bei dieser Geschwindigkeit habe ich so noch nicht erlebt.</p>
					<footer>Gian-Marco Blum · CEO, Candidate Flow GmbH</footer>
				</blockquote>
			</div>
		</div>
	</section>

	<!-- Process -->
	<section class="pax-home-process" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Prozess</p>
			<h2 class="pax-home-display">Von der Idee<br>zur Umsetzung.</h2>
			<ol class="pax-home-process__list">
				<li>
					<span>01</span>
					<div>
						<strong>Analyse</strong>
						<p>Anforderungen verstehen, Ziele schärfen, Potenziale finden.</p>
					</div>
				</li>
				<li>
					<span>02</span>
					<div>
						<strong>Konzept &amp; Design</strong>
						<p>UI/UX, Wireframes und Prototypen für echte Nutzerpfade.</p>
					</div>
				</li>
				<li>
					<span>03</span>
					<div>
						<strong>Umsetzung &amp; Care</strong>
						<p>Entwicklung, Launch und langfristige Betreuung.</p>
					</div>
				</li>
			</ol>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--light" href="<?php echo esc_url( $contact ); ?>">Projekt starten</a>
				<a class="pax-home-btn pax-home-btn--ghost-light" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
			</div>
		</div>
	</section>

	<!-- Final CTA -->
	<section class="pax-home-final" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow">
			<p class="pax-home-brand pax-home-brand--dark">PAXdesign</p>
			<h2 class="pax-home-display">Bereit für Ihr<br>nächstes System?</h2>
			<p class="pax-home-lede">Sprechen Sie mit uns — wir übersetzen Ihre Anforderungen in klare, skalierbare digitale Produkte.</p>
			<div class="pax-home-actions pax-home-actions--center">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $contact ); ?>">Kostenlose Beratung</a>
				<a class="pax-home-btn pax-home-btn--text" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/karriere/' ) ); ?>">Karriere</a>
			</div>
		</div>
	</section>

</article>
