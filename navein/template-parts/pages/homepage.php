<?php
/**
 * Complete Apple-origin homepage (refined light language).
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'navein_t' ) ) {
	/**
	 * @param string $key
	 * @param string $fallback
	 * @return string
	 */
	function navein_t( $key, $fallback = '' ) {
		return $fallback !== '' ? $fallback : $key;
	}
}

$contact   = home_url( '/kontakt/' );
$services  = home_url( '/preise/' );
$pricing   = home_url( '/preise/' );
$about     = home_url( '/ueber-uns/' );
$projects  = home_url( '/referenzen/' );
$phone     = '+43 681 2054 3638';
$email     = 'info@paxdesign.at';
$hero_img  = 'https://paxdesign.at/wp-content/uploads/2026/01/code-2558220_1280.avif';
$award_img = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img6.avif';

$service_links = array(
	array( navein_t( 'service_web', 'Webentwicklung' ), navein_t( 'service_web_lede', 'Moderne Websites & Web Apps' ), home_url( '/webentwicklung/' ) ),
	array( navein_t( 'service_app', 'App-Entwicklung' ), navein_t( 'service_app_lede', 'iOS, Android & TV' ), home_url( '/app-entwicklung/' ) ),
	array( navein_t( 'service_software', 'Softwareentwicklung' ), navein_t( 'service_software_lede', 'Individuelle Systeme' ), home_url( '/softwareentwicklung/' ) ),
	array( navein_t( 'service_advanced', 'Advanced Website Systems' ), navein_t( 'service_advanced_lede', 'Skalierbare Web-Architekturen' ), home_url( '/advanced-website-systems/' ) ),
	array( navein_t( 'service_support', 'Wartung & Support' ), navein_t( 'service_support_lede', '24/7 Betreuung & Updates' ), home_url( '/wartung-support/' ) ),
	array( navein_t( 'service_consulting', 'IT-Consulting' ), navein_t( 'service_consulting_lede', 'Technische Beratung' ), home_url( '/it-consulting/' ) ),
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
			<h1 class="pax-home-hero__title"><?php echo esc_html( navein_t( 'home_hero_title', 'Digitale Systeme, die wirklich funktionieren.' ) ); ?></h1>
			<p class="pax-home-hero__lede">
				<?php echo esc_html( navein_t( 'home_hero_lede', 'Websites, Apps, Software und sichere IT‑Systeme, individuell entwickelt, performant und bereit für Wachstum.' ) ); ?>
			</p>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--light" href="<?php echo esc_url( $pricing ); ?>"><?php echo esc_html( navein_t( 'home_discover_services', 'Leistungen entdecken' ) ); ?></a>
				<a class="pax-home-btn pax-home-btn--ghost" href="<?php echo esc_url( $contact ); ?>"><?php echo esc_html( navein_t( 'home_start_now', 'Jetzt starten' ) ); ?></a>
			</div>
		</div>
	</section>

	<!-- Platform & partner logo marquee below hero (tech ticker above stays unchanged) -->
	<section class="pax-sw-ribbon pax-sw-ribbon--partners" data-ph-reveal aria-label="<?php echo esc_attr( navein_t( 'home_partners', 'Plattformen und Partner' ) ); ?>">
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
				<?php echo esc_html( navein_t( 'home_statement', 'Keine Produkte von der Stange, sondern Systeme, die klar, sicher und selbstverständlich wirken.' ) ); ?>
			</p>
		</div>
	</section>

	<!-- Services -->
	<section id="leistungen" class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'nav_services', 'Leistungen' ) ); ?></p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_services_display', 'Alles aus einer Hand.' ) ); ?></h2>
			<p class="pax-home-lede"><?php echo esc_html( navein_t( 'home_services_lede', 'Von der ersten Idee bis zum laufenden Betrieb, direkt in jede Disziplin.' ) ); ?></p>
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
			<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $services ); ?>"><?php echo esc_html( navein_t( 'home_all_services', 'Alle Leistungen' ) ); ?></a>
			<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( $contact ); ?>"><?php echo esc_html( navein_t( 'cta_request_offer', 'Angebot anfordern' ) ); ?></a>
		</div>
	</section>

	<!-- Capabilities: light editorial columns -->
	<section class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'home_expertise', 'Expertise' ) ); ?></p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_expertise_display', 'Professionell. Präzise. Bereit für Scale.' ) ); ?></h2>
		</div>
		<div class="pax-home-pillars">
			<div class="pax-home-pillar">
				<span>01</span>
				<h3><?php echo esc_html( navein_t( 'home_pillar_web', 'Webdesign & Webentwicklung' ) ); ?></h3>
				<p><?php echo esc_html( navein_t( 'home_pillar_web_p', 'Moderne, performante Websites, individuell und auf Ihr Unternehmen zugeschnitten.' ) ); ?></p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/webentwicklung/' ) ); ?>"><?php echo esc_html( navein_t( 'learn_more', 'Mehr erfahren' ) ); ?></a>
			</div>
			<div class="pax-home-pillar">
				<span>02</span>
				<h3><?php echo esc_html( navein_t( 'home_pillar_app', 'App- & Softwareentwicklung' ) ); ?></h3>
				<p><?php echo esc_html( navein_t( 'home_pillar_app_p', 'Native und individuelle Anwendungen für komplexe Anforderungen.' ) ); ?></p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/app-entwicklung/' ) ); ?>"><?php echo esc_html( navein_t( 'learn_more', 'Mehr erfahren' ) ); ?></a>
			</div>
			<div class="pax-home-pillar">
				<span>03</span>
				<h3><?php echo esc_html( navein_t( 'home_pillar_ux', 'UI/UX & Produkt' ) ); ?></h3>
				<p><?php echo esc_html( navein_t( 'home_pillar_ux_p', 'Klare Interfaces mit Fokus auf Effizienz und Vertrauen.' ) ); ?></p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/visuelles-design/' ) ); ?>"><?php echo esc_html( navein_t( 'learn_more', 'Mehr erfahren' ) ); ?></a>
			</div>
			<div class="pax-home-pillar">
				<span>04</span>
				<h3><?php echo esc_html( navein_t( 'home_pillar_tech', 'Technik & Scale' ) ); ?></h3>
				<p><?php echo esc_html( navein_t( 'home_pillar_tech_p', 'Wartbare Architektur und Systeme, die mit Ihrem Wachstum mithalten.' ) ); ?></p>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/advanced-website-systems/' ) ); ?>"><?php echo esc_html( navein_t( 'learn_more', 'Mehr erfahren' ) ); ?></a>
			</div>
		</div>
	</section>

	<!-- Projects: real visual, no chrome/terminal -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap pax-home-split">
			<div class="pax-home-split__copy">
				<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'nav_references', 'Referenzen' ) ); ?></p>
				<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_projects_display', 'Ausgewählte Projekte.' ) ); ?></h2>
				<p class="pax-home-lede"><?php echo esc_html( navein_t( 'home_projects_lede', 'Arbeiten, die Expertise, Präzision und Kreativität zeigen.' ) ); ?></p>
				<div class="pax-home-actions">
					<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $projects ); ?>"><?php echo esc_html( navein_t( 'home_all_projects', 'Alle Projekte ansehen' ) ); ?></a>
					<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/projekte-referenzen/' ) ); ?>"><?php echo esc_html( navein_t( 'home_discover_cases', 'Cases entdecken' ) ); ?></a>
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
				<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'home_about', 'Über uns' ) ); ?></p>
				<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_about_display', 'Wir sind PAXdesign.' ) ); ?></h2>
				<p class="pax-home-lede"><?php echo esc_html( navein_t( 'home_about_lede', 'Digitale Entwickler seit 2016, Technologie, Design und Strategie für leistungsstarke Websites und individuelle Software.' ) ); ?></p>
				<div class="pax-home-actions">
					<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $about ); ?>"><?php echo esc_html( navein_t( 'learn_more', 'Mehr erfahren' ) ); ?></a>
					<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/unsere-experten/' ) ); ?>"><?php echo esc_html( navein_t( 'home_experts', 'Unsere Experten' ) ); ?></a>
				</div>
			</div>
			<ul class="pax-home-stats">
				<li><strong>10+</strong><span><?php echo esc_html( navein_t( 'home_years', 'Jahre Erfahrung' ) ); ?></span></li>
				<li><strong>150+</strong><span><?php echo esc_html( navein_t( 'home_projects_stat', 'Abgeschlossene Projekte' ) ); ?></span></li>
				<li><strong>98%</strong><span><?php echo esc_html( navein_t( 'home_satisfaction', 'Kundenzufriedenheit' ) ); ?></span></li>
			</ul>
		</div>
	</section>

	<!-- Awards: light -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow pax-home-center">
			<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'home_awards', 'Awards' ) ); ?></p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_awards_display', 'Ergebnisse, die für sich sprechen.' ) ); ?></h2>
			<p class="pax-home-lede pax-home-lede--center"><?php echo esc_html( navein_t( 'home_awards_lede', 'Gewinner der German Web Awards 2021 & 2022 und ausgezeichnet mit dem Deutschen Agenturpreis 2021.' ) ); ?></p>
		</div>
	</section>

	<!-- Testimonials -->
	<section class="pax-home-section pax-home-section--snow" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'home_testimonials', 'Kundenstimmen' ) ); ?></p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_testimonials_display', 'Was unsere Kunden sagen.' ) ); ?></h2>
			<div class="pax-home-quotes">
				<blockquote>
					<p><?php echo esc_html( navein_t( 'home_quote_1', 'PAXdesign hat uns geholfen, dass potenzielle Kunden klarer erkennen, wer wir sind. Der Auftritt wirkt deutlich professioneller.' ) ); ?></p>
					<footer>Thomas Müller · CEO, TechStart GmbH</footer>
				</blockquote>
				<blockquote>
					<p><?php echo esc_html( navein_t( 'home_quote_2', '100% zufrieden. Modern, seriös, einfach zu bedienen und dennoch besonders. Absolute Empfehlung.' ) ); ?></p>
					<footer>Jannis Rettig · CEO, Rettig &amp; Partner</footer>
				</blockquote>
				<blockquote>
					<p><?php echo esc_html( navein_t( 'home_quote_3', 'Qualität bei dieser Geschwindigkeit habe ich so noch nicht erlebt.' ) ); ?></p>
					<footer>Gian-Marco Blum · CEO, Candidate Flow GmbH</footer>
				</blockquote>
			</div>
		</div>
	</section>

	<!-- Process: light -->
	<section class="pax-home-section" data-ph-reveal>
		<div class="pax-home-wrap">
			<p class="pax-home-eyebrow"><?php echo esc_html( navein_t( 'home_process', 'Prozess' ) ); ?></p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_process_display', 'Von der Idee zur Umsetzung.' ) ); ?></h2>
			<ol class="pax-home-steps">
				<li>
					<span>01</span>
					<strong><?php echo esc_html( navein_t( 'home_step_1', 'Analyse' ) ); ?></strong>
					<p><?php echo esc_html( navein_t( 'home_step_1_p', 'Anforderungen verstehen, Ziele schärfen, Potenziale finden.' ) ); ?></p>
				</li>
				<li>
					<span>02</span>
					<strong><?php echo esc_html( navein_t( 'home_step_2', 'Konzept & Design' ) ); ?></strong>
					<p><?php echo esc_html( navein_t( 'home_step_2_p', 'UI/UX, Wireframes und Prototypen für echte Nutzerpfade.' ) ); ?></p>
				</li>
				<li>
					<span>03</span>
					<strong><?php echo esc_html( navein_t( 'home_step_3', 'Umsetzung & Care' ) ); ?></strong>
					<p><?php echo esc_html( navein_t( 'home_step_3_p', 'Entwicklung, Launch und langfristige Betreuung.' ) ); ?></p>
				</li>
			</ol>
			<div class="pax-home-actions">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $contact ); ?>"><?php echo esc_html( navein_t( 'home_start_project', 'Projekt starten' ) ); ?></a>
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
				<?php echo esc_html( navein_t( 'home_signup_lede', 'Erstellen Sie Ihr Konto für Live Chat und den Kundenbereich, klar, sicher und in wenigen Schritten.' ) ); ?>
			</p>
			<form class="pax-home-signup-form" data-pax-signup-form novalidate>
				<label class="pax-home-sr" for="pax-home-signup-email"><?php echo esc_html( navein_t( 'home_email', 'E-Mail-Adresse' ) ); ?></label>
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
					<button type="submit" class="pax-home-signup-form__submit"><?php echo esc_html( navein_t( 'continue', 'Weiter' ) ); ?></button>
				</div>
				<p class="pax-home-signup-form__note" data-pax-signup-note hidden></p>
			</form>
			<p class="pax-home-signup__meta">
				<button type="button" class="pax-home-signup__link" data-pax-signup><?php echo esc_html( navein_t( 'create_account', 'Konto erstellen' ) ); ?></button>
				<span aria-hidden="true">·</span>
				<a href="<?php echo esc_url( $contact ); ?>"><?php echo esc_html( navein_t( 'nav_contact', 'Kontakt' ) ); ?></a>
			</p>
		</div>
	</section>

	<!-- Final CTA -->
	<section class="pax-home-final" data-ph-reveal>
		<div class="pax-home-wrap pax-home-wrap--narrow pax-home-center">
			<p class="pax-home-brand pax-home-brand--dark">PAXdesign</p>
			<h2 class="pax-home-display"><?php echo esc_html( navein_t( 'home_final_display', 'Bereit für Ihr nächstes System?' ) ); ?></h2>
			<p class="pax-home-lede pax-home-lede--center"><?php echo esc_html( navein_t( 'home_final_lede', 'Lassen Sie uns Ihre Anforderungen in klare, skalierbare digitale Produkte übersetzen.' ) ); ?></p>
			<div class="pax-home-actions pax-home-actions--center">
				<a class="pax-home-btn pax-home-btn--dark" href="<?php echo esc_url( $contact ); ?>"><?php echo esc_html( navein_t( 'home_free_consult', 'Kostenlose Beratung' ) ); ?></a>
				<a class="pax-home-btn pax-home-btn--text" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<a class="pax-home-btn pax-home-btn--text" href="<?php echo esc_url( home_url( '/karriere/' ) ); ?>"><?php echo esc_html( navein_t( 'career', 'Karriere' ) ); ?></a>
			</div>
		</div>
	</section>

</article>
