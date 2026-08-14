<?php
/**
 * Apple product-page Leistungen experience.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pax_leistungen_icon' ) ) {
	/**
	 * SF-style inline SVG icons.
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
			'security'   => '<path d="M12 2.4 19.6 5.2v6.15c0 4.55-2.95 8.55-7.6 10.05C7.35 19.9 4.4 15.9 4.4 11.35V5.2L12 2.4zm0 2.35-5.4 2v6.6c0 3.35 2.1 6.3 5.4 7.45 3.3-1.15 5.4-4.1 5.4-7.45v-6.6L12 4.75zm2.15 4.2 1.5 1.5-4.55 4.55-2.9-2.9 1.5-1.5 1.4 1.4 3.05-3.05z"/>',
			'chevron'    => '<path d="M8.7 4.7a1.05 1.05 0 0 1 1.48 0l6.1 6.1a1.05 1.05 0 0 1 0 1.48l-6.1 6.1a1.05 1.05 0 1 1-1.48-1.48L13.97 12 8.7 6.18A1.05 1.05 0 0 1 8.7 4.7z"/>',
		);
		if ( ! isset( $icons[ $name ] ) ) {
			return;
		}
		echo '<svg class="pax-ls-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true">' . $icons[ $name ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'pax_leistungen_iphone' ) ) {
	/**
	 * Realistic iPhone 16 Pro frame. Screen uses object-fit: contain so images are never cropped.
	 *
	 * @param string $src      Image URL.
	 * @param string $modifier Extra class names.
	 * @param bool   $eager    Load the screen image immediately.
	 */
	function pax_leistungen_iphone( $src, $modifier = '', $eager = false ) {
		$class = trim( 'pax-ls-iphone ' . $modifier );
		$load  = $eager ? 'fetchpriority="high"' : 'loading="lazy"';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<div class="pax-ls-iphone__chassis">
				<span class="pax-ls-iphone__btn pax-ls-iphone__btn--silent"></span>
				<span class="pax-ls-iphone__btn pax-ls-iphone__btn--vol-up"></span>
				<span class="pax-ls-iphone__btn pax-ls-iphone__btn--vol-down"></span>
				<span class="pax-ls-iphone__btn pax-ls-iphone__btn--power"></span>
				<div class="pax-ls-iphone__bezel">
					<div class="pax-ls-iphone__glass">
						<span class="pax-ls-iphone__island" aria-hidden="true">
							<span class="pax-ls-iphone__lens"></span>
						</span>
						<div class="pax-ls-iphone__screen" style="background-image:url('<?php echo esc_url( $src ); ?>')">
							<img src="<?php echo esc_url( $src ); ?>" alt="" width="1179" height="2556" decoding="async" <?php echo $load; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

$contact  = home_url( '/kontakt/' );
$pricing  = home_url( '/preise/' );
$projects = home_url( '/referenzen/' );
$cases    = home_url( '/projekte-referenzen/' );
$phone    = '+43 681 20543638';
$email    = 'info@paxdesign.at';
$mega     = trailingslashit( get_template_directory_uri() ) . 'assets/img/mega/';
$hero_img = 'https://paxdesign.at/wp-content/uploads/2026/01/code-2558220_1280.avif';
$award    = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img6.avif';
$folio_a  = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img3.avif';
$folio_b  = 'https://paxdesign.at/wp-content/uploads/2025/02/folio-item-img4.avif';

$highlights = array(
	array(
		'kicker' => 'Web',
		'title'  => 'Websites, die sich selbstverständlich anfühlen.',
		'image'  => $hero_img,
		'href'   => home_url( '/webentwicklung/' ),
	),
	array(
		'kicker' => 'Apps',
		'title'  => 'iOS, Android und TV, nativ und präzise.',
		'image'  => $mega . 'app.jpg',
		'href'   => home_url( '/app-entwicklung/' ),
	),
	array(
		'kicker' => 'Software',
		'title'  => 'Systeme, gebaut für echte Prozesse.',
		'image'  => $mega . 'software.jpg',
		'href'   => home_url( '/softwareentwicklung/' ),
	),
	array(
		'kicker' => 'Design',
		'title'  => 'Marke, Interface und Richtung aus einem Guss.',
		'image'  => $mega . 'visual.jpg',
		'href'   => home_url( '/visuelles-design/' ),
	),
	array(
		'kicker' => 'Referenzen',
		'title'  => 'Arbeiten mit Fokus auf Präzision und Wirkung.',
		'image'  => $award,
		'href'   => $projects,
	),
);

$lineup = array(
	array(
		'title' => 'Webentwicklung',
		'lede'  => 'Moderne Websites und Web Apps.',
		'href'  => home_url( '/webentwicklung/' ),
		'image' => $hero_img,
		'tone'  => 'dark',
	),
	array(
		'title' => 'App-Entwicklung',
		'lede'  => 'iOS, Android und TV.',
		'href'  => home_url( '/app-entwicklung/' ),
		'image' => $mega . 'app.jpg',
		'tone'  => 'dark',
	),
	array(
		'title' => 'Softwareentwicklung',
		'lede'  => 'Individuelle Systeme.',
		'href'  => home_url( '/softwareentwicklung/' ),
		'image' => $mega . 'software.jpg',
		'tone'  => 'dark',
	),
	array(
		'title' => 'Advanced Website Systems',
		'lede'  => 'Skalierbare Web-Architekturen.',
		'href'  => home_url( '/advanced-website-systems/' ),
		'image' => $mega . 'systems.jpg',
		'tone'  => 'dark',
	),
	array(
		'title' => 'Wartung & Support',
		'lede'  => 'Betreuung, Updates, Betrieb.',
		'href'  => home_url( '/wartung-support/' ),
		'image' => $mega . 'consulting.jpg',
		'tone'  => 'light',
	),
	array(
		'title' => 'IT-Consulting',
		'lede'  => 'Technische Beratung und Planung.',
		'href'  => home_url( '/it-consulting/' ),
		'image' => $mega . 'consulting.jpg',
		'tone'  => 'light',
	),
);

$design = array(
	array(
		'title' => 'Visuelles Design',
		'lede'  => 'Klares, ästhetisches Design mit Fokus auf Markenidentität, Wiedererkennbarkeit und visuelle Wirkung.',
		'href'  => home_url( '/visuelles-design/' ),
		'image' => $mega . 'visual.jpg',
	),
	array(
		'title' => 'Digitales Branding',
		'lede'  => 'Strategischer Aufbau starker digitaler Marken, die Vertrauen schaffen und nachhaltig überzeugen.',
		'href'  => home_url( '/digitale-markenfuehrung/' ),
		'image' => $mega . 'branding.jpg',
	),
	array(
		'title' => 'Art Direction',
		'lede'  => 'Kreative Leitung und konzeptionelle Gestaltung für konsistente, ausdrucksstarke Markenauftritte.',
		'href'  => home_url( '/art-direction-strategie/' ),
		'image' => $mega . 'strategy.jpg',
	),
	array(
		'title' => 'UX-Forschung',
		'lede'  => 'Analyse von Nutzerverhalten zur Optimierung von Benutzerführung, Effizienz und Nutzerzufriedenheit.',
		'href'  => home_url( '/ux-forschung/' ),
		'image' => $mega . 'ux.jpg',
	),
	array(
		'title' => 'E-Commerce',
		'lede'  => 'Konzeption und Gestaltung performanter Online-Shops mit Fokus auf Conversion und Benutzererlebnis.',
		'href'  => home_url( '/e-commerce/' ),
		'image' => $mega . 'commerce.jpg',
	),
	array(
		'title' => 'Produktdesign',
		'lede'  => 'Ganzheitliches Produktdesign von der Idee bis zur Umsetzung: funktional, nutzerzentriert und skalierbar.',
		'href'  => home_url( '/produktdesign/' ),
		'image' => $mega . 'concept.jpg',
	),
);

$quotes = array(
	array(
		'text' => 'Die Zusammenarbeit mit PAXDESIGN war von Anfang an professionell und zielgerichtet. Das Team hat unsere Anforderungen genau verstanden und technisch wie visuell perfekt umgesetzt.',
		'name' => 'Mark R.',
		'role' => 'Project Manager, Digital Solutions',
	),
	array(
		'text' => 'PAXDESIGN hat unsere digitale Plattform technisch neu aufgebaut und visuell modernisiert. Performance, Sicherheit und Benutzerfreundlichkeit standen dabei jederzeit im Fokus.',
		'name' => 'Andrea D.',
		'role' => 'CEO, Technology Company',
	),
	array(
		'text' => 'Die Lösungen waren technisch durchdacht, stabil und exakt auf unsere Anforderungen abgestimmt. Besonders hervorzuheben sind die strukturierte Arbeitsweise und die hohe Qualität der Umsetzung.',
		'name' => 'Thomas L.',
		'role' => 'Senior Product Developer, Software Company',
	),
);
?>
<article class="pax-ls" lang="de">
	<a class="pax-ls-skip" href="#pax-ls-highlights">Zum Inhalt</a>

	<nav class="pax-ls-localnav" aria-label="Leistungen">
		<div class="pax-ls-localnav__inner">
			<a class="pax-ls-localnav__brand" href="#pax-ls-hero">Leistungen</a>
			<div class="pax-ls-localnav__links">
				<a href="#pax-ls-highlights">Highlights</a>
				<a href="#pax-ls-lineup">Lineup</a>
				<a href="#pax-ls-design">Design</a>
				<a href="#pax-ls-process">Prozess</a>
				<a href="#pax-ls-voices">Stimmen</a>
			</div>
			<a class="pax-ls-localnav__cta" href="<?php echo esc_url( $contact ); ?>">Beratung</a>
		</div>
	</nav>

	<header id="pax-ls-hero" class="pax-ls-hero" data-ls-reveal>
		<div class="pax-ls-hero__copy">
			<p class="pax-ls-kicker">PAXdesign</p>
			<h1 class="pax-ls-hero__title">Maßgeschneiderte Leistungen<br>für Ihre Bedürfnisse.</h1>
			<p class="pax-ls-hero__lede">Websites, Apps, Software und Design. Individuell entwickelt, performant und bereit für Wachstum.</p>
			<div class="pax-ls-actions pax-ls-actions--center">
				<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( $contact ); ?>">Beratung anfordern</a>
				<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( $pricing ); ?>">Preise ansehen<?php pax_leistungen_icon( 'chevron' ); ?></a>
			</div>
		</div>
		<div class="pax-ls-stage" data-ls-stage aria-hidden="true">
			<div class="pax-ls-laptop">
				<div class="pax-ls-laptop__lid">
					<div class="pax-ls-laptop__screen">
						<img src="<?php echo esc_url( $hero_img ); ?>" alt="" width="1280" height="720" decoding="async" fetchpriority="high">
					</div>
				</div>
				<div class="pax-ls-laptop__base"></div>
			</div>
			<?php pax_leistungen_iphone( $mega . 'visual.jpg', 'pax-ls-iphone--hero', true ); ?>
		</div>
	</header>

	<section id="pax-ls-highlights" class="pax-ls-highlights" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-highlights__head">
			<div>
				<p class="pax-ls-kicker pax-ls-kicker--on-dark">Get the highlights.</p>
				<h2 class="pax-ls-display pax-ls-display--light">Ein Blick auf das Wesentliche.</h2>
			</div>
			<div class="pax-ls-rail__nav" data-ls-rail-nav="highlights">
				<button type="button" class="pax-ls-rail__btn" data-ls-rail-prev aria-label="Zurück"><?php pax_leistungen_icon( 'chevron' ); ?></button>
				<button type="button" class="pax-ls-rail__btn" data-ls-rail-next aria-label="Weiter"><?php pax_leistungen_icon( 'chevron' ); ?></button>
			</div>
		</div>
		<div class="pax-ls-rail" data-ls-rail="highlights" tabindex="0" aria-label="Highlights">
			<?php foreach ( $highlights as $item ) : ?>
				<a class="pax-ls-hl" href="<?php echo esc_url( $item['href'] ); ?>">
					<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
					<span class="pax-ls-hl__veil"></span>
					<span class="pax-ls-hl__copy">
						<em><?php echo esc_html( $item['kicker'] ); ?></em>
						<strong><?php echo esc_html( $item['title'] ); ?></strong>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="pax-ls-film pax-ls-film--light" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-film__grid">
			<div class="pax-ls-film__copy">
				<p class="pax-ls-kicker">Webentwicklung</p>
				<h2 class="pax-ls-display">Moderne Weblösungen für digitale Exzellenz.</h2>
				<p class="pax-ls-lede">Corporate Websites, E-Commerce und Web Apps. Performant, sicher und für jedes Gerät gedacht.</p>
				<div class="pax-ls-actions">
					<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( home_url( '/webentwicklung/' ) ); ?>">Mehr erfahren</a>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( $contact ); ?>">Projekt anfragen<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
			</div>
			<div class="pax-ls-film__visual" aria-hidden="true">
				<div class="pax-ls-laptop pax-ls-laptop--solo">
					<div class="pax-ls-laptop__lid">
						<div class="pax-ls-laptop__screen">
							<img src="<?php echo esc_url( $hero_img ); ?>" alt="" width="1280" height="720" loading="lazy" decoding="async">
						</div>
					</div>
					<div class="pax-ls-laptop__base"></div>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-ls-film pax-ls-film--dark" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-film__grid pax-ls-film__grid--reverse">
			<div class="pax-ls-film__copy">
				<p class="pax-ls-kicker pax-ls-kicker--on-dark">App-Entwicklung</p>
				<h2 class="pax-ls-display pax-ls-display--light">iOS, Android und TV. Aus einem System.</h2>
				<p class="pax-ls-lede pax-ls-lede--on-dark">Native und cross-platform Anwendungen mit klarer Navigation, hoher Performance und Store-ready Umsetzung.</p>
				<div class="pax-ls-actions">
					<a class="pax-ls-btn pax-ls-btn--light" href="<?php echo esc_url( home_url( '/app-entwicklung/' ) ); ?>">Mehr erfahren</a>
					<a class="pax-ls-btn pax-ls-btn--ghost" href="<?php echo esc_url( $contact ); ?>">Projekt anfragen<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
			</div>
			<div class="pax-ls-film__visual" aria-hidden="true">
				<div class="pax-ls-phones">
					<?php pax_leistungen_iphone( $mega . 'visual.jpg', 'pax-ls-iphone--back' ); ?>
					<?php pax_leistungen_iphone( $mega . 'branding.jpg', 'pax-ls-iphone--front' ); ?>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-ls-film pax-ls-film--snow" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-film__grid">
			<div class="pax-ls-film__copy">
				<p class="pax-ls-kicker">Softwareentwicklung</p>
				<h2 class="pax-ls-display">Individuelle Systeme, die mitwachsen.</h2>
				<p class="pax-ls-lede">Keine Produkte von der Stange. Wir bauen funktionierende, sichere und skalierbare Software für komplexe Anforderungen.</p>
				<div class="pax-ls-actions">
					<a class="pax-ls-btn pax-ls-btn--fill" href="<?php echo esc_url( home_url( '/softwareentwicklung/' ) ); ?>">Mehr erfahren</a>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( home_url( '/advanced-website-systems/' ) ); ?>">Advanced Systems<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
			</div>
			<div class="pax-ls-film__visual" aria-hidden="true">
				<div class="pax-ls-windows">
					<figure class="pax-ls-window pax-ls-window--a">
						<img src="<?php echo esc_url( $mega . 'software.jpg' ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
					</figure>
					<figure class="pax-ls-window pax-ls-window--b">
						<img src="<?php echo esc_url( $mega . 'systems.jpg' ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
					</figure>
				</div>
			</div>
		</div>
	</section>

	<section id="pax-ls-lineup" class="pax-ls-lineup" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide">
			<h2 class="pax-ls-display">Explore the lineup.</h2>
			<p class="pax-ls-lede">Sechs Disziplinen. Ein Anspruch an Klarheit, Tempo und Präzision.</p>
			<div class="pax-ls-lineup__grid">
				<?php foreach ( $lineup as $item ) : ?>
					<article class="pax-ls-product">
						<a class="pax-ls-product__media pax-ls-product__media--<?php echo esc_attr( $item['tone'] ); ?>" href="<?php echo esc_url( $item['href'] ); ?>">
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" width="1280" height="720" loading="lazy" decoding="async">
						</a>
						<h3><?php echo esc_html( $item['title'] ); ?></h3>
						<p><?php echo esc_html( $item['lede'] ); ?></p>
						<div class="pax-ls-product__links">
							<a href="<?php echo esc_url( $item['href'] ); ?>">Mehr erfahren<?php pax_leistungen_icon( 'chevron' ); ?></a>
							<a href="<?php echo esc_url( $contact ); ?>">Anfragen<?php pax_leistungen_icon( 'chevron' ); ?></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="pax-ls-design" class="pax-ls-design" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide">
			<h2 class="pax-ls-display">Get to know Design.</h2>
			<p class="pax-ls-lede">Visuelle Systeme, die Marken stärken und Nutzer führen.</p>
		</div>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-tiles">
			<?php foreach ( $design as $item ) : ?>
				<a class="pax-ls-tile" href="<?php echo esc_url( $item['href'] ); ?>">
					<span class="pax-ls-tile__media">
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
					</span>
					<span class="pax-ls-tile__copy">
						<strong><?php echo esc_html( $item['title'] ); ?></strong>
						<em><?php echo esc_html( $item['lede'] ); ?></em>
						<span class="pax-ls-tile__go">Mehr erfahren<?php pax_leistungen_icon( 'chevron' ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="pax-ls-why" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide">
			<h2 class="pax-ls-display">Warum PAXDesign der richtige Ort ist.</h2>
			<div class="pax-ls-why__grid">
				<div>
					<span class="pax-ls-why__icon" aria-hidden="true"><?php pax_leistungen_icon( 'systems' ); ?></span>
					<h3>Alles aus einer Hand</h3>
					<p>Web, App, Software, Design und Betrieb. Ein Team, eine Sprache, ein System.</p>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( home_url( '/advanced-website-systems/' ) ); ?>">Mehr erfahren<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
				<div>
					<span class="pax-ls-why__icon" aria-hidden="true"><?php pax_leistungen_icon( 'support' ); ?></span>
					<h3>Persönliche Betreuung</h3>
					<p>Klare Kommunikation, termingerechte Umsetzung und Betreuung nach dem Launch.</p>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( home_url( '/wartung-support/' ) ); ?>">Wartung &amp; Support<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
				<div>
					<span class="pax-ls-why__icon" aria-hidden="true"><?php pax_leistungen_icon( 'security' ); ?></span>
					<h3>Sicherheit im Betrieb</h3>
					<p>Härtung, Monitoring und Cybercrime Support, wenn es ernst wird.</p>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( home_url( '/cybercrime-support/' ) ); ?>">Cybercrime Support<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
				<div>
					<span class="pax-ls-why__icon" aria-hidden="true"><?php pax_leistungen_icon( 'consulting' ); ?></span>
					<h3>Beratung, die entscheidet</h3>
					<p>Architektur, Scope und Technologie, bevor die erste Zeile Code entsteht.</p>
					<a class="pax-ls-btn pax-ls-btn--text" href="<?php echo esc_url( home_url( '/it-consulting/' ) ); ?>">IT-Consulting<?php pax_leistungen_icon( 'chevron' ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<section id="pax-ls-process" class="pax-ls-process" data-ls-reveal>
		<div class="pax-ls-wrap">
			<p class="pax-ls-kicker">Prozess</p>
			<h2 class="pax-ls-display">Von der Idee zur Umsetzung.</h2>
			<ol class="pax-ls-process__list">
				<li>
					<span>01</span>
					<div>
						<h3>Anfrage</h3>
						<p>Kontakt über Formular, Telefon oder Chat. Wir klären Ziel, Umfang und Rahmen.</p>
					</div>
				</li>
				<li>
					<span>02</span>
					<div>
						<h3>Analyse</h3>
						<p>Anforderungen verstehen, Potenziale finden und die richtige Architektur wählen.</p>
					</div>
				</li>
				<li>
					<span>03</span>
					<div>
						<h3>Angebot</h3>
						<p>Ein klares, maßgeschneidertes Angebot statt Standardpakete von der Stange.</p>
					</div>
				</li>
				<li>
					<span>04</span>
					<div>
						<h3>Umsetzung</h3>
						<p>Professionelle Entwicklung, Launch und langfristige Betreuung.</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<section class="pax-ls-pair" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--wide pax-ls-pair__grid">
			<a class="pax-ls-pair__tile" href="<?php echo esc_url( $projects ); ?>">
				<span class="pax-ls-pair__media">
					<img src="<?php echo esc_url( $folio_a ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
				</span>
				<span class="pax-ls-pair__copy">
					<em>Referenzen</em>
					<strong>Ausgewählte Projekte.</strong>
					<b>Alle Projekte ansehen<?php pax_leistungen_icon( 'chevron' ); ?></b>
				</span>
			</a>
			<a class="pax-ls-pair__tile" href="<?php echo esc_url( $cases ); ?>">
				<span class="pax-ls-pair__media">
					<img src="<?php echo esc_url( $folio_b ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async">
				</span>
				<span class="pax-ls-pair__copy">
					<em>Cases</em>
					<strong>Präzision in der Praxis.</strong>
					<b>Cases entdecken<?php pax_leistungen_icon( 'chevron' ); ?></b>
				</span>
			</a>
		</div>
	</section>

	<section id="pax-ls-voices" class="pax-ls-voices" data-ls-reveal>
		<div class="pax-ls-wrap pax-ls-wrap--narrow">
			<p class="pax-ls-kicker">Kundenstimmen</p>
			<h2 class="pax-ls-display">Was unsere Kunden sagen.</h2>
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
	</section>

	<section class="pax-ls-final" data-ls-reveal>
		<p class="pax-ls-kicker pax-ls-kicker--on-dark">PAXdesign</p>
		<h2 class="pax-ls-display pax-ls-display--light">Bereit für Ihr nächstes System?</h2>
		<p class="pax-ls-lede pax-ls-lede--on-dark pax-ls-lede--center">Lassen Sie uns Ihre Anforderungen in klare, skalierbare digitale Produkte übersetzen.</p>
		<div class="pax-ls-actions pax-ls-actions--center">
			<a class="pax-ls-btn pax-ls-btn--light" href="<?php echo esc_url( $contact ); ?>">Kostenlose Beratung</a>
			<a class="pax-ls-btn pax-ls-btn--ghost" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			<a class="pax-ls-btn pax-ls-btn--ghost" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
		</div>
	</section>
</article>
