<?php
/**
 * Complete Apple-origin homepage (refined light language).
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact   = home_url( '/kontakt/' );
$services  = home_url( '/leistungen/' );
$pricing   = home_url( '/preise/' );
$about     = home_url( '/ueber-uns/' );
$projects  = home_url( '/referenzen/' );
$phone     = '+43 681 20543638';
$email     = 'info@paxdesign.at';
$hero_img  = 'https://paxdesign.at/wp-content/uploads/2026/01/code-2558220_1280.avif';
$award_img = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img6.avif';

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

	<!-- Tech marquee ribbon under header/nav -->
	<section class="pax-sw-ribbon" data-ph-reveal aria-label="Tech Stack">
		<div class="pax-sw-ribbon__track" data-sw-marquee>
			<div class="pax-sw-ribbon__group">
				<span>Java</span><span>C#</span><span>Python</span><span>Go</span>
				<span>Spring Boot</span><span>.NET Core</span><span>Django</span><span>FastAPI</span>
				<span>Docker</span><span>Kubernetes</span><span>GitLab CI</span><span>Jenkins</span>
			</div>
			<div class="pax-sw-ribbon__group" aria-hidden="true">
				<span>Java</span><span>C#</span><span>Python</span><span>Go</span>
				<span>Spring Boot</span><span>.NET Core</span><span>Django</span><span>FastAPI</span>
				<span>Docker</span><span>Kubernetes</span><span>GitLab CI</span><span>Jenkins</span>
			</div>
		</div>
	</section>

	<!-- Hero: brand first, full-bleed visual -->
	<section class="pax-home-hero" data-ph-reveal>
		<div class="pax-home-hero__media" aria-hidden="true">
			<img src="<?php echo esc_url( $hero_img ); ?>" alt="" width="1280" height="720" loading="eager" decoding="async" fetchpriority="high">
			<div class="pax-home-hero__veil"></div>
		</div>
		<div class="pax-home-hero__inner">
			<p class="pax-home-brand">PAXdesign</p>
			<h1 class="pax-home-hero__title">Digitale Systeme,<br>die wirklich funktionieren.</h1>
			<p class="pax-home-hero__lede">
				Websites, Apps, Software und sichere IT‑Systeme, individuell entwickelt, performant und bereit für Wachstum.
			</p>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--light" href="<?php echo esc_url( $pricing ); ?>">Leistungen entdecken</a>
				<a class="pax-home-btn pax-home-btn--ghost" href="<?php echo esc_url( $contact ); ?>">Jetzt starten</a>
			</div>
		</div>
	</section>

	<!-- Platform & partner logo marquee below hero (tech ticker above stays unchanged) -->
	<section class="pax-sw-ribbon pax-sw-ribbon--partners" data-ph-reveal aria-label="Plattformen und Partner">
		<div class="pax-sw-ribbon__track" data-sw-marquee>
			<?php
			$partner_logos = array(
				array( 'apple', 'Apple' ),
				array( 'android', 'Android' ),
				array( 'microsoft', 'Microsoft' ),
				array( 'visualstudio', 'Visual Studio' ),
				array( 'visualstudiocode', 'Visual Studio Code' ),
				array( 'github', 'GitHub' ),
				array( 'figma', 'Figma' ),
				array( 'adobe', 'Adobe' ),
				array( 'aws', 'AWS' ),
				array( 'googlecloud', 'Google Cloud' ),
				array( 'azure', 'Azure' ),
				array( 'firebase', 'Firebase' ),
				array( 'stripe', 'Stripe' ),
				array( 'openai', 'OpenAI' ),
				array( 'linux', 'Linux' ),
				array( 'ubuntu', 'Ubuntu' ),
				array( 'vmware', 'VMware' ),
				array( 'cloudflare', 'Cloudflare' ),
			);
			$partners_uri = trailingslashit( get_template_directory_uri() ) . 'assets/img/partners/';
			$partners_ver = rawurlencode( (string) wp_get_theme()->get( 'Version' ) );
			for ( $dup = 0; $dup < 2; $dup++ ) :
				?>
			<div class="pax-sw-ribbon__group"<?php echo 1 === $dup ? ' aria-hidden="true"' : ''; ?>>
				<?php foreach ( $partner_logos as $logo ) : ?>
					<span class="pax-sw-ribbon__logo">
						<img
							src="<?php echo esc_url( $partners_uri . $logo[0] . '.svg?ver=' . $partners_ver ); ?>"
							alt="<?php echo 0 === $dup ? esc_attr( $logo[1] ) : ''; ?>"
							width="32"
							height="32"
							loading="lazy"
							decoding="async"
						>
					</span>
				<?php endforeach; ?>
			</div>
			<?php endfor; ?>
		</div>
	</section>

	<!-- Soft statement -->
	<section class="pax-home-statement" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow">
			<p class="pax-home-statement__text">
				Keine Produkte von der Stange, sondern Systeme, die klar, sicher und selbstverständlich wirken.
			</p>
		</div>
	</section>

	<!-- Services -->
	<section id="leistungen" class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Leistungen</p>
			<h2 class="pax-home-display">Alles aus einer Hand.</h2>
			<p class="pax-home-lede">Von der ersten Idee bis zum laufenden Betrieb, direkt in jede Disziplin.</p>
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

	<!-- Capabilities: light editorial columns -->
	<section class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Expertise</p>
			<h2 class="pax-home-display">Professionell. Präzise.<br>Bereit für Scale.</h2>
		</div>
		<div class="pax-home-pillars">
			<div class="pax-home-pillar">
				<span>01</span>
				<h3>Webdesign &amp; Webentwicklung</h3>
				<p>Moderne, performante Websites, individuell und auf Ihr Unternehmen zugeschnitten.</p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/webentwicklung/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-pillar">
				<span>02</span>
				<h3>App- &amp; Softwareentwicklung</h3>
				<p>Native und individuelle Anwendungen für komplexe Anforderungen.</p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/app-entwicklung/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-pillar">
				<span>03</span>
				<h3>UI/UX &amp; Produkt</h3>
				<p>Klare Interfaces mit Fokus auf Effizienz und Vertrauen.</p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/visuelles-design/' ) ); ?>">Mehr erfahren</a>
			</div>
			<div class="pax-home-pillar">
				<span>04</span>
				<h3>Technik &amp; Scale</h3>
				<p>Wartbare Architektur und Systeme, die mit Ihrem Wachstum mithalten.</p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/advanced-website-systems/' ) ); ?>">Mehr erfahren</a>
			</div>
		</div>
	</section>

	<!-- Projects: real visual, no chrome/terminal -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap pax-home-split">
			<div class="pax-home-split__copy">
				<p class="pax-home-eyebrow">Referenzen</p>
				<h2 class="pax-home-display">Ausgewählte Projekte.</h2>
				<p class="pax-home-lede">Arbeiten, die Expertise, Präzision und Kreativität zeigen.</p>
				<div class="pax-home-actions">
					<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $projects ); ?>">Alle Projekte ansehen</a>
					<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/projekte-referenzen/' ) ); ?>">Cases entdecken</a>
				</div>
			</div>
			<div class="pax-home-split__visual" aria-hidden="true">
				<img src="<?php echo esc_url( $award_img ); ?>" alt="" width="900" height="700" loading="lazy" decoding="async">
			</div>
		</div>
	</section>

	<!-- About + stats -->
	<section class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap pax-home-about__grid">
			<div>
				<p class="pax-home-eyebrow">Über uns</p>
				<h2 class="pax-home-display">Wir sind<br>PAXdesign.</h2>
				<p class="pax-home-lede">Digitale Entwickler seit 2016, Technologie, Design und Strategie für leistungsstarke Websites und individuelle Software.</p>
				<div class="pax-home-actions">
					<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $about ); ?>">Mehr erfahren</a>
					<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/unsere-experten/' ) ); ?>">Unsere Experten</a>
				</div>
			</div>
			<ul class="pax-home-stats">
				<li><strong>10+</strong><span>Jahre Erfahrung</span></li>
				<li><strong>150+</strong><span>Abgeschlossene Projekte</span></li>
				<li><strong>98%</strong><span>Kundenzufriedenheit</span></li>
			</ul>
		</div>
	</section>

	<!-- Awards: light -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow pax-home-center">
			<p class="pax-home-eyebrow">Awards</p>
			<h2 class="pax-home-display">Ergebnisse, die für sich sprechen.</h2>
			<p class="pax-home-lede pax-home-lede--center">Gewinner der German Web Awards 2021 &amp; 2022 und ausgezeichnet mit dem Deutschen Agenturpreis 2021.</p>
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
					<p>100% zufrieden. Modern, seriös, einfach zu bedienen und dennoch besonders. Absolute Empfehlung.</p>
					<footer>Jannis Rettig · CEO, Rettig &amp; Partner</footer>
				</blockquote>
				<blockquote>
					<p>Qualität bei dieser Geschwindigkeit habe ich so noch nicht erlebt.</p>
					<footer>Gian-Marco Blum · CEO, Candidate Flow GmbH</footer>
				</blockquote>
			</div>
		</div>
	</section>

	<!-- Process: light -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow">Prozess</p>
			<h2 class="pax-home-display">Von der Idee zur Umsetzung.</h2>
			<ol class="pax-home-steps">
				<li>
					<span>01</span>
					<strong>Analyse</strong>
					<p>Anforderungen verstehen, Ziele schärfen, Potenziale finden.</p>
				</li>
				<li>
					<span>02</span>
					<strong>Konzept &amp; Design</strong>
					<p>UI/UX, Wireframes und Prototypen für echte Nutzerpfade.</p>
				</li>
				<li>
					<span>03</span>
					<strong>Umsetzung &amp; Care</strong>
					<p>Entwicklung, Launch und langfristige Betreuung.</p>
				</li>
			</ol>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $contact ); ?>">Projekt starten</a>
				<a class="pax-home-btn pax-home-btn--text" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
			</div>
		</div>
	</section>

	<!-- Sign Up: Apple-inspired account band -->
	<section class="pax-home-signup" id="pax-home-signup" data-ph-reveal>
		<div class="pax-home-signup__atmosphere" aria-hidden="true"></div>
		<div class="pax-home-wrap pax-home-wrap--narrow pax-home-center">
			<p class="pax-home-brand pax-home-brand--dark">PAXdesign</p>
			<h2 class="pax-home-display">Sign&nbsp;Up.</h2>
			<p class="pax-home-lede pax-home-lede--center">
				Erstellen Sie Ihr Konto für Live&nbsp;Chat und den Kundenbereich, klar, sicher und in wenigen Schritten.
			</p>
			<form class="pax-home-signup-form" data-pax-signup-form novalidate>
				<label class="pax-home-sr" for="pax-home-signup-email">E-Mail-Adresse</label>
				<div class="pax-home-signup-form__shell">
					<input
						id="pax-home-signup-email"
						class="pax-home-signup-form__input"
						type="email"
						name="email"
						autocomplete="email"
						inputmode="email"
						placeholder="name@email.com"
						required
					>
					<button type="submit" class="pax-home-signup-form__submit">Weiter</button>
				</div>
				<p class="pax-home-signup-form__note" data-pax-signup-note hidden></p>
			</form>
			<p class="pax-home-signup__meta">
				<button type="button" class="pax-home-signup__link" data-pax-signup>Konto erstellen</button>
				<span aria-hidden="true">·</span>
				<a href="<?php echo esc_url( $contact ); ?>">Kontakt</a>
			</p>
		</div>
	</section>

	<!-- Final CTA -->
	<section class="pax-home-final" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow pax-home-center">
			<p class="pax-home-brand pax-home-brand--dark">PAXdesign</p>
			<h2 class="pax-home-display">Bereit für Ihr<br>nächstes System?</h2>
			<p class="pax-home-lede pax-home-lede--center">Lassen Sie uns Ihre Anforderungen in klare, skalierbare digitale Produkte übersetzen.</p>
			<div class="pax-home-actions pax-home-actions--center">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $contact ); ?>">Kostenlose Beratung</a>
				<a class="pax-home-btn pax-home-btn--text" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/karriere/' ) ); ?>">Karriere</a>
			</div>
		</div>
	</section>

</article>
