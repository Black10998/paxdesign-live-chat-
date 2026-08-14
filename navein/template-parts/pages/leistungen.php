<?php
/**
 * Apple-inspired Leistungen page.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pax_leistungen_icon' ) ) {
	/**
	 * Inline SVG icons (currentColor, 24 viewBox).
	 *
	 * @param string $name Icon key.
	 */
	function pax_leistungen_icon( $name ) {
		$icons = array(
			'web'        => '<path d="M4.2 5.1h15.6A2.2 2.2 0 0 1 22 7.3v9.4a2.2 2.2 0 0 1-2.2 2.2H4.2A2.2 2.2 0 0 1 2 16.7V7.3A2.2 2.2 0 0 1 4.2 5.1zm0 2.2v1.5h15.6V7.3H4.2zm0 9.4h15.6V10.4H4.2v6.3z"/>',
			'app'        => '<path d="M8.4 1.8h7.2A2.7 2.7 0 0 1 18.3 4.5v15a2.7 2.7 0 0 1-2.7 2.7H8.4A2.7 2.7 0 0 1 5.7 19.5v-15A2.7 2.7 0 0 1 8.4 1.8zm2.1 1.9a.85.85 0 0 0 0 1.7h3a.85.85 0 0 0 0-1.7h-3zM12 17.35a1.35 1.35 0 1 0 0 2.7 1.35 1.35 0 0 0 0-2.7z"/>',
			'software'   => '<path d="M8.85 7.35a1.15 1.15 0 0 1 0 1.63L6.1 11.75l2.75 2.77a1.15 1.15 0 1 1-1.63 1.63l-3.55-3.58a1.15 1.15 0 0 1 0-1.63l3.55-3.58a1.15 1.15 0 0 1 1.63 0zm6.3 0a1.15 1.15 0 0 1 1.63 0l3.55 3.58a1.15 1.15 0 0 1 0 1.63l-3.55 3.58a1.15 1.15 0 1 1-1.63-1.63l2.75-2.77-2.75-2.77a1.15 1.15 0 0 1 0-1.63z"/>',
			'systems'    => '<path d="M12.05 3.1 20.4 7.1a1.1 1.1 0 0 1 0 1.95l-8.35 4a1.15 1.15 0 0 1-1 0l-8.35-4a1.1 1.1 0 0 1 0-1.95l8.35-4a1.15 1.15 0 0 1 1 0zm8.35 8.15-1.7.8-6.15 2.95a1.7 1.7 0 0 1-1.5 0L4.9 12.05l-1.7-.8a1.05 1.05 0 0 0-.95 1.85l8.35 4a1.7 1.7 0 0 0 1.5 0l8.35-4a1.05 1.05 0 0 0-.95-1.85zm0 3.85-1.7.8-6.15 2.95a1.7 1.7 0 0 1-1.5 0L4.9 15.9l-1.7-.8a1.05 1.05 0 0 0-.95 1.85l8.35 4a1.7 1.7 0 0 0 1.5 0l8.35-4a1.05 1.05 0 0 0-.95-1.85z"/>',
			'support'    => '<path d="M12 2.4a9.6 9.6 0 1 1 0 19.2 9.6 9.6 0 0 1 0-19.2zm0 2.2a7.4 7.4 0 1 0 0 14.8 7.4 7.4 0 0 0 0-14.8zm.9 3.1v3.55l2.55 1.85a1.05 1.05 0 1 1-1.2 1.72l-3.05-2.22A1.05 1.05 0 0 1 10.7 10.7V7.7a1.05 1.05 0 0 1 2.1 0z"/>',
			'consulting' => '<path d="M5.4 4.2h13.2A2.4 2.4 0 0 1 21 6.6v7.1a2.4 2.4 0 0 1-2.4 2.4h-7.55L6.2 19.7a1 1 0 0 1-1.6-.85v-2.75A2.4 2.4 0 0 1 3 13.7V6.6A2.4 2.4 0 0 1 5.4 4.2zm2.3 4.7a1 1 0 0 0 0 2h8.6a1 1 0 1 0 0-2H7.7zm0 3.4a1 1 0 0 0 0 2h5.4a1 1 0 1 0 0-2H7.7z"/>',
			'visual'     => '<path d="M12 5.2c4.6 0 8.4 3.2 9.6 6.1a1.2 1.2 0 0 1 0 1.4C20.4 15.6 16.6 18.8 12 18.8S3.6 15.6 2.4 12.7a1.2 1.2 0 0 1 0-1.4C3.6 8.4 7.4 5.2 12 5.2zm0 2.4a4.4 4.4 0 1 0 0 8.8 4.4 4.4 0 0 0 0-8.8zm0 2.2a2.2 2.2 0 1 1 0 4.4 2.2 2.2 0 0 1 0-4.4z"/>',
			'branding'   => '<path d="M12 2.6l2.35 5.55 6.05.55-4.6 3.95 1.45 5.85L12 15.95 6.75 18.5l1.45-5.85-4.6-3.95 6.05-.55L12 2.6z"/>',
			'direction'  => '<path d="M12 2.5 20.7 21a.9.9 0 0 1-1.32 1.05L12 17.6l-7.38 4.45A.9.9 0 0 1 3.3 21L12 2.5z"/>',
			'ux'         => '<path d="M11 3.4a7.6 7.6 0 0 1 5.95 12.3l3.55 3.55a1.15 1.15 0 0 1-1.63 1.63l-3.55-3.55A7.6 7.6 0 1 1 11 3.4zm0 2.3a5.3 5.3 0 1 0 0 10.6 5.3 5.3 0 0 0 0-10.6z"/>',
			'commerce'   => '<path d="M3.2 4.2h2.45c.5 0 .94.34 1.07.82L7.3 7.4h12.35c.9 0 1.55.85 1.3 1.7l-1.7 5.75a1.9 1.9 0 0 1-1.82 1.35H8.55a1.9 1.9 0 0 1-1.85-1.45L4.55 5.9H3.2a1.15 1.15 0 1 1 0-2.3zm5.75 14.1a1.85 1.85 0 1 1 0 3.7 1.85 1.85 0 0 1 0-3.7zm8.1 0a1.85 1.85 0 1 1 0 3.7 1.85 1.85 0 0 1 0-3.7z"/>',
			'product'    => '<path d="M12 2.6a6.7 6.7 0 0 1 3.85 12.15c-.55.4-.95.95-1.1 1.6h-5.5c-.15-.65-.55-1.2-1.1-1.6A6.7 6.7 0 0 1 12 2.6zM9.55 17.85h4.9c.45 0 .8.35.8.8v.35c0 .9-.55 1.7-1.4 2.05-.2.55-.7.95-1.35.95h-1c-.65 0-1.15-.4-1.35-.95-.85-.35-1.4-1.15-1.4-2.05v-.35c0-.45.35-.8.8-.8z"/>',
			'security'   => '<path d="M12 2.4 19.6 5.2v6.15c0 4.55-2.95 8.55-7.6 10.05C7.35 19.9 4.4 15.9 4.4 11.35V5.2L12 2.4zm0 2.35-5.4 2v6.6c0 3.35 2.1 6.3 5.4 7.45 3.3-1.15 5.4-4.1 5.4-7.45v-6.6L12 4.75zm2.15 4.2 1.5 1.5-4.55 4.55-2.9-2.9 1.5-1.5 1.4 1.4 3.05-3.05z"/>',
			'speed'      => '<path d="M12 3.2a8.8 8.8 0 1 1-7.5 4.2 1.15 1.15 0 0 1 1.96 1.2A6.5 6.5 0 1 0 12 5.5a1.15 1.15 0 0 1 0-2.3zm.85 4.15 3.9 6.75a1.15 1.15 0 0 1-1.55 1.6l-4.85-3.55A1.15 1.15 0 0 1 10.9 10.2V7.35a1.15 1.15 0 0 1 1.95-.1z"/>',
			'ai'         => '<path d="M12 2.2a1.1 1.1 0 0 1 1.05.78l.85 2.7 2.7.85a1.1 1.1 0 0 1 0 2.1l-2.7.85-.85 2.7a1.1 1.1 0 0 1-2.1 0l-.85-2.7-2.7-.85a1.1 1.1 0 0 1 0-2.1l2.7-.85.85-2.7A1.1 1.1 0 0 1 12 2.2zm6.6 8.4a.9.9 0 0 1 .85.62l.45 1.45 1.45.45a.9.9 0 0 1 0 1.72l-1.45.45-.45 1.45a.9.9 0 0 1-1.7 0l-.45-1.45-1.45-.45a.9.9 0 0 1 0-1.72l1.45-.45.45-1.45a.9.9 0 0 1 .85-.62zM5.7 11.1a.9.9 0 0 1 .85.62l.4 1.3 1.3.4a.9.9 0 0 1 0 1.72l-1.3.4-.4 1.3a.9.9 0 0 1-1.7 0l-.4-1.3-1.3-.4a.9.9 0 0 1 0-1.72l1.3-.4.4-1.3a.9.9 0 0 1 .85-.62z"/>',
			'backend'    => '<path d="M4.2 4.3h15.6A1.8 1.8 0 0 1 21.6 6.1v3.2a1.8 1.8 0 0 1-1.8 1.8H4.2A1.8 1.8 0 0 1 2.4 9.3V6.1A1.8 1.8 0 0 1 4.2 4.3zm1.7 2.15a.95.95 0 1 0 0 1.9.95.95 0 0 0 0-1.9zm2.5 0a.95.95 0 1 0 0 1.9.95.95 0 0 0 0-1.9zM4.2 12.9h15.6a1.8 1.8 0 0 1 1.8 1.8v3.2a1.8 1.8 0 0 1-1.8 1.8H4.2A1.8 1.8 0 0 1 2.4 17.9v-3.2a1.8 1.8 0 0 1 1.8-1.8zm1.7 2.15a.95.95 0 1 0 0 1.9.95.95 0 0 0 0-1.9zm2.5 0a.95.95 0 1 0 0 1.9.95.95 0 0 0 0-1.9z"/>',
			'chevron'    => '<path d="M8.7 4.7a1.05 1.05 0 0 1 1.48 0l6.1 6.1a1.05 1.05 0 0 1 0 1.48l-6.1 6.1a1.05 1.05 0 1 1-1.48-1.48L13.97 12 8.7 6.18A1.05 1.05 0 0 1 8.7 4.7z"/>',
		);
		if ( ! isset( $icons[ $name ] ) ) {
			return;
		}
		echo '<svg class="pax-ls-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true">' . $icons[ $name ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

$contact  = home_url( '/kontakt/' );
$pricing  = home_url( '/preise/' );
$projects = home_url( '/referenzen/' );
$cases    = home_url( '/projekte-referenzen/' );
$phone    = '+43 681 20543638';
$email    = 'info@paxdesign.at';
$award    = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img6.avif';

$systems = array(
	array(
		'title' => 'Webentwicklung',
		'lede'  => 'Moderne, performante Websites und Web Apps, individuell und auf Ihr Unternehmen zugeschnitten.',
		'href'  => home_url( '/webentwicklung/' ),
		'icon'  => 'web',
		'size'  => 'feature',
	),
	array(
		'title' => 'App-Entwicklung',
		'lede'  => 'Native und cross-platform Anwendungen für iOS, Android und TV.',
		'href'  => home_url( '/app-entwicklung/' ),
		'icon'  => 'app',
		'size'  => 'feature',
	),
	array(
		'title' => 'Softwareentwicklung',
		'lede'  => 'Individuelle Systeme für komplexe Prozesse und Wachstum.',
		'href'  => home_url( '/softwareentwicklung/' ),
		'icon'  => 'software',
		'size'  => 'tile',
	),
	array(
		'title' => 'Advanced Website Systems',
		'lede'  => 'Skalierbare Web-Architekturen mit klarer Struktur.',
		'href'  => home_url( '/advanced-website-systems/' ),
		'icon'  => 'systems',
		'size'  => 'tile',
	),
	array(
		'title' => 'Wartung & Support',
		'lede'  => 'Updates, Monitoring und 24/7 Betreuung im laufenden Betrieb.',
		'href'  => home_url( '/wartung-support/' ),
		'icon'  => 'support',
		'size'  => 'tile',
	),
	array(
		'title' => 'IT-Consulting',
		'lede'  => 'Technische Beratung, Architektur und klare Entscheidungen.',
		'href'  => home_url( '/it-consulting/' ),
		'icon'  => 'consulting',
		'size'  => 'tile',
	),
);

$design = array(
	array(
		'title' => 'Visuelles Design',
		'lede'  => 'Klares, ästhetisches Design mit Fokus auf Markenidentität, Wiedererkennbarkeit und visuelle Wirkung.',
		'href'  => home_url( '/visuelles-design/' ),
		'icon'  => 'visual',
	),
	array(
		'title' => 'Digitales Branding',
		'lede'  => 'Strategischer Aufbau starker digitaler Marken, die Vertrauen schaffen und nachhaltig überzeugen.',
		'href'  => home_url( '/digitale-markenfuehrung/' ),
		'icon'  => 'branding',
	),
	array(
		'title' => 'Art Direction',
		'lede'  => 'Kreative Leitung und konzeptionelle Gestaltung für konsistente, ausdrucksstarke Markenauftritte.',
		'href'  => home_url( '/art-direction-strategie/' ),
		'icon'  => 'direction',
	),
	array(
		'title' => 'UX-Forschung',
		'lede'  => 'Analyse von Nutzerverhalten zur Optimierung von Benutzerführung, Effizienz und Nutzerzufriedenheit.',
		'href'  => home_url( '/ux-forschung/' ),
		'icon'  => 'ux',
	),
	array(
		'title' => 'E-Commerce',
		'lede'  => 'Konzeption und Gestaltung performanter Online-Shops mit Fokus auf Conversion und Benutzererlebnis.',
		'href'  => home_url( '/e-commerce/' ),
		'icon'  => 'commerce',
	),
	array(
		'title' => 'Produktdesign',
		'lede'  => 'Ganzheitliches Produktdesign von der Idee bis zur Umsetzung: funktional, nutzerzentriert und skalierbar.',
		'href'  => home_url( '/produktdesign/' ),
		'icon'  => 'product',
	),
);

$more = array(
	array(
		'title' => 'Cybercrime Support',
		'lede'  => 'Professionelle Hilfe bei digitalem Missbrauch, Betrug und Sicherheitsvorfällen.',
		'href'  => home_url( '/cybercrime-support/' ),
		'icon'  => 'security',
	),
	array(
		'title' => 'UI/UX Design',
		'lede'  => 'Interfaces und UX-Konzepte, die Besucher schnell zur richtigen Entscheidung führen.',
		'href'  => home_url( '/ux-forschung/' ),
		'icon'  => 'visual',
	),
	array(
		'title' => 'Backend & DevOps',
		'lede'  => 'APIs, Infrastruktur, CI/CD und Betrieb, projektspezifisch und wartbar.',
		'href'  => home_url( '/softwareentwicklung/' ),
		'icon'  => 'backend',
	),
	array(
		'title' => 'AI Automation',
		'lede'  => 'KI-gestützte Automatisierung von Prozessen, Assistenten und Reports.',
		'href'  => home_url( '/softwareentwicklung/' ),
		'icon'  => 'ai',
	),
	array(
		'title' => 'Website-Geschwindigkeit',
		'lede'  => 'Performance, Core Web Vitals und schnellere Ladezeiten für höhere Conversion.',
		'href'  => home_url( '/webentwicklung/' ),
		'icon'  => 'speed',
	),
	array(
		'title' => 'IT-Sicherheit',
		'lede'  => 'Analyse, Härtung und Schutz digitaler Systeme im realen Betrieb.',
		'href'  => home_url( '/it-consulting/' ),
		'icon'  => 'security',
	),
);

$quotes = array(
	array(
		'text'   => 'Die Zusammenarbeit mit PAXDESIGN war von Anfang an professionell und zielgerichtet. Das Team hat unsere Anforderungen genau verstanden und technisch wie visuell perfekt umgesetzt. Besonders überzeugt haben uns die klare Kommunikation, die hohe Qualität und die termingerechte Umsetzung.',
		'name'   => 'Mark R.',
		'role'   => 'Project Manager, Digital Solutions',
	),
	array(
		'text'   => 'PAXDESIGN hat unsere digitale Plattform technisch neu aufgebaut und visuell modernisiert. Performance, Sicherheit und Benutzerfreundlichkeit standen dabei jederzeit im Fokus. Die Umsetzung erfolgte strukturiert, transparent und auf höchstem technischen Niveau.',
		'name'   => 'Andrea D.',
		'role'   => 'CEO, Technology Company',
	),
	array(
		'text'   => 'PAXDESIGN hat uns bei der Entwicklung und Optimierung digitaler Produkte professionell unterstützt. Die Lösungen waren technisch durchdacht, stabil und exakt auf unsere Anforderungen abgestimmt. Besonders hervorzuheben sind die strukturierte Arbeitsweise und die hohe Qualität der Umsetzung.',
		'name'   => 'Thomas L.',
		'role'   => 'Senior Product Developer, Software Company',
	),
);
?>
<article class="pax-ls" lang="de">
	<a class="pax-ls-skip" href="#pax-ls-systems">Zum Inhalt</a>

	<nav class="pax-ls-localnav" aria-label="Seitenbereiche">
		<div class="pax-ls-localnav__inner">
			<p class="pax-ls-localnav__brand">Leistungen</p>
			<div class="pax-ls-localnav__links" role="list">
				<a href="#pax-ls-systems" role="listitem">Systeme</a>
				<a href="#pax-ls-design" role="listitem">Design</a>
				<a href="#pax-ls-more" role="listitem">Expertise</a>
				<a href="#pax-ls-process" role="listitem">Prozess</a>
				<a href="#pax-ls-voices" role="listitem">Stimmen</a>
			</div>
			<a class="pax-ls-localnav__cta" href="<?php echo esc_url( $contact ); ?>">Beratung</a>
		</div>
	</nav>

	<header class="pax-ls-hero" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">PAXdesign</p>
			<h1 class="pax-ls-hero__title">Maßgeschneiderte Leistungen für Ihre Bedürfnisse.</h1>
			<p class="pax-ls-hero__lede">
				Websites, Apps, Software und Design, individuell entwickelt, performant und bereit für Wachstum.
			</p>
			<div class="pax-ls-actions">
				<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( $contact ); ?>">Beratung anfordern</a>
				<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( $pricing ); ?>">Preise ansehen</a>
			</div>
		</div>
	</header>

	<section class="pax-ls-statement" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--narrow">
			<p>Keine Produkte von der Stange, sondern Systeme, die klar, sicher und selbstverständlich wirken.</p>
		</div>
	</section>

	<section id="pax-ls-systems" class="pax-ls-section" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">Digitale Systeme</p>
			<h2 class="pax-ls-display">Alles aus einer Hand.</h2>
			<p class="pax-ls-lede">Von der ersten Idee bis zum laufenden Betrieb, direkt in jede Disziplin.</p>
		</div>
		<div class="pax-ls-wrap">
			<div class="pax-ls-bento">
				<?php foreach ( $systems as $item ) : ?>
					<a class="pax-ls-card pax-ls-card--<?php echo esc_attr( $item['size'] ); ?>" href="<?php echo esc_url( $item['href'] ); ?>">
						<span class="pax-ls-card__icon" aria-hidden="true"><?php pax_leistungen_icon( $item['icon'] ); ?></span>
						<span class="pax-ls-card__copy">
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<em><?php echo esc_html( $item['lede'] ); ?></em>
						</span>
						<span class="pax-ls-card__go">
							Mehr erfahren
							<?php pax_leistungen_icon( 'chevron' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="pax-ls-design" class="pax-ls-section pax-ls-section--snow" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">Design &amp; Marke</p>
			<h2 class="pax-ls-display">Klar. Präzise. Wiedererkennbar.</h2>
			<p class="pax-ls-lede">Visuelle Systeme, die Marken stärken und Nutzer führen.</p>
		</div>
		<div class="pax-ls-wrap">
			<div class="pax-ls-grid">
				<?php foreach ( $design as $item ) : ?>
					<a class="pax-ls-card" href="<?php echo esc_url( $item['href'] ); ?>">
						<span class="pax-ls-card__icon" aria-hidden="true"><?php pax_leistungen_icon( $item['icon'] ); ?></span>
						<span class="pax-ls-card__copy">
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<em><?php echo esc_html( $item['lede'] ); ?></em>
						</span>
						<span class="pax-ls-card__go">
							Mehr erfahren
							<?php pax_leistungen_icon( 'chevron' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="pax-ls-more" class="pax-ls-section" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">Weitere Expertise</p>
			<h2 class="pax-ls-display">Sicherheit, Tempo und Intelligenz.</h2>
			<p class="pax-ls-lede">Ergänzende Leistungen für Betrieb, Schutz und Automatisierung.</p>
		</div>
		<div class="pax-ls-wrap">
			<div class="pax-ls-grid pax-ls-grid--compact">
				<?php foreach ( $more as $item ) : ?>
					<a class="pax-ls-card pax-ls-card--compact" href="<?php echo esc_url( $item['href'] ); ?>">
						<span class="pax-ls-card__icon" aria-hidden="true"><?php pax_leistungen_icon( $item['icon'] ); ?></span>
						<span class="pax-ls-card__copy">
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<em><?php echo esc_html( $item['lede'] ); ?></em>
						</span>
						<span class="pax-ls-card__go">
							Mehr erfahren
							<?php pax_leistungen_icon( 'chevron' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="pax-ls-process" class="pax-ls-section pax-ls-section--snow" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">Prozess</p>
			<h2 class="pax-ls-display">Von der Idee zur Umsetzung.</h2>
			<ol class="pax-ls-steps">
				<li>
					<span>01</span>
					<strong>Anfrage</strong>
					<p>Kontakt über Formular, Telefon oder Chat. Wir klären Ziel, Umfang und Rahmen.</p>
				</li>
				<li>
					<span>02</span>
					<strong>Analyse</strong>
					<p>Anforderungen verstehen, Potenziale finden und die richtige Architektur wählen.</p>
				</li>
				<li>
					<span>03</span>
					<strong>Angebot</strong>
					<p>Ein klares, maßgeschneidertes Angebot statt Standardpakete von der Stange.</p>
				</li>
				<li>
					<span>04</span>
					<strong>Umsetzung</strong>
					<p>Professionelle Entwicklung, Launch und langfristige Betreuung.</p>
				</li>
			</ol>
		</div>
	</section>

	<section class="pax-ls-section" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-split">
			<div class="pax-ls-split__copy">
				<p class="pax-ls-eyebrow">Referenzen</p>
				<h2 class="pax-ls-display">Ausgewählte Projekte.</h2>
				<p class="pax-ls-lede">Arbeiten, die Expertise, Präzision und Wirkung zeigen.</p>
				<div class="pax-ls-actions">
					<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( $projects ); ?>">Alle Projekte ansehen</a>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( $cases ); ?>">Cases entdecken</a>
				</div>
			</div>
			<div class="pax-ls-split__visual">
				<img src="<?php echo esc_url( $award ); ?>" alt="Ausgewählte PAXDesign Referenzarbeit" width="900" height="700" loading="lazy" decoding="async">
			</div>
		</div>
	</section>

	<section id="pax-ls-voices" class="pax-ls-section pax-ls-section--snow" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-eyebrow">Kundenstimmen</p>
			<h2 class="pax-ls-display">Was unsere Kunden sagen.</h2>
			<div class="pax-ls-quotes">
				<?php foreach ( $quotes as $quote ) : ?>
					<blockquote>
						<p><?php echo esc_html( $quote['text'] ); ?></p>
						<footer>
							<strong><?php echo esc_html( $quote['name'] ); ?></strong>
							<span><?php echo esc_html( $quote['role'] ); ?></span>
						</footer>
					</blockquote>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="pax-ls-final" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--narrow pax-ls-center">
			<p class="pax-ls-eyebrow">PAXdesign</p>
			<h2 class="pax-ls-display">Bereit für Ihr nächstes System?</h2>
			<p class="pax-ls-lede pax-ls-lede--center">Lassen Sie uns Ihre Anforderungen in klare, skalierbare digitale Produkte übersetzen.</p>
			<div class="pax-ls-actions pax-ls-actions--center">
				<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( $contact ); ?>">Kostenlose Beratung</a>
				<a class="pax-ls-btn pax-ls-btn--text" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<a class="pax-ls-btn pax-ls-btn--text" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
			</div>
		</div>
	</section>
</article>
