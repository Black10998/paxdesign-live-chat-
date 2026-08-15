<?php
/**
 * Apple product-page Referenzen experience.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pax_referenzen_icon' ) ) {
	/**
	 * Compact SF-style chevron.
	 *
	 * @param string $name Icon key.
	 */
	function pax_referenzen_icon( $name = 'chevron' ) {
		$icons = array(
			'chevron' => '<path d="M8.7 4.7a1.05 1.05 0 0 1 1.48 0l6.1 6.1a1.05 1.05 0 0 1 0 1.48l-6.1 6.1a1.05 1.05 0 1 1-1.48-1.48L13.97 12 8.7 6.18A1.05 1.05 0 0 1 8.7 4.7z"/>',
		);
		if ( ! isset( $icons[ $name ] ) ) {
			return;
		}
		echo '<svg class="pax-rf-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true">' . $icons[ $name ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

$contact  = home_url( '/kontakt/' );
$cases    = home_url( '/projekte-referenzen/' );
$services = home_url( '/leistungen/' );
$software = home_url( '/softwareentwicklung/' );
$design   = home_url( '/visuelles-design/' );
$uploads  = 'https://paxdesign.at/wp-content/uploads/2025/02/';

$works = array(
	array(
		'slug'     => 'xendou',
		'kicker'   => 'UX/UI',
		'client'   => 'Xendou',
		'title'    => 'UI-Design für digitale Produkte.',
		'heading'  => 'Xendou, UI-Design für digitale Produkte',
		'lede'     => 'Für Xendou haben wir eine moderne digitale Präsentation entwickelt, die das Produkt klar in den Mittelpunkt stellt. Der Fokus lag auf einer übersichtlichen Struktur, klaren Interfaces und einer visuellen Darstellung, die Vertrauen schafft.',
		'body'     => 'Xendou benötigte eine digitale Präsentation, die das Produkt verständlich, übersichtlich und visuell ansprechend darstellt. Ziel war es, Design und Struktur in Einklang zu bringen — komplexe Produktinformationen klar, ohne den Nutzer zu überfordern.',
		'result'   => 'Wir entwickelten eine klare Seitenstruktur mit fokussiertem UI-Design. Durch reduzierte Gestaltung, gezielte visuelle Akzente und eine saubere Layout-Struktur entstand eine benutzerfreundliche Produktpräsentation.',
		'services' => array( 'Produktpräsentation', 'UI- & Interface-Design', 'Webdesign & Struktur', 'Responsive Umsetzung' ),
		'cats'     => array( 'product', 'ux-ui' ),
		'image'    => $uploads . 'immg.avif',
		'href'     => home_url( '/portfolio/xendou/' ),
		'tone'     => 'light',
	),
	array(
		'slug'     => 'systems',
		'kicker'   => 'Systeme',
		'client'   => 'PAXdesign',
		'title'    => 'Maßgeschneiderte Web- & Softwarelösungen.',
		'heading'  => 'Maßgeschneiderte Web- & Softwarelösungen',
		'lede'     => 'Einblick in realisierte Software- und Systemprojekte: klare Architektur, sauberer Code und durchdachte Systeme, die langfristig stabil bleiben.',
		'body'     => 'Viele digitale Projekte sehen gut aus, funktionieren aber nicht nachhaltig. Wir setzen auf klare Architektur, sauberen Code und durchdachte Systeme, die mit dem Unternehmen wachsen.',
		'result'   => 'Jedes Projekt beginnt mit einem klaren Verständnis der technischen Anforderungen und geschäftlichen Ziele. Daraus entsteht eine strukturierte, wartbare Lösung.',
		'services' => array( 'Systemanalyse & Architektur', 'Individuelle Softwareentwicklung', 'Web- & Backend-Systeme', 'Performance & Sicherheit' ),
		'cats'     => array( 'ux-ui' ),
		'image'    => $uploads . 'original-e1767129502488.avif',
		'href'     => $software,
		'tone'     => 'dark',
	),
	array(
		'slug'     => 'blvck',
		'kicker'   => 'Software',
		'client'   => 'Web & Software',
		'title'    => 'Funktional. Sicher. Skalierbar.',
		'heading'  => 'Funktionale, sichere und skalierbare Web- & Softwarelösungen.',
		'lede'     => 'Viele digitale Projekte scheitern an fehlender Planung, schlechter Architektur oder nicht wartbarem Code. Wir übersetzen komplexe Anforderungen in stabile Systeme.',
		'body'     => 'Wir analysieren bestehende Systeme, identifizieren Schwachstellen und entwickeln eine strukturierte, nachhaltige Lösung — modular, performant und mit sauberen Schnittstellen.',
		'result'   => 'Klare Prozesse, moderne Technologien und besonderer Wert auf Performance, Sicherheit und eine zuverlässige Systembasis.',
		'services' => array( 'Systemanalyse & Architektur', 'Individuelle Softwareentwicklung', 'API- & Schnittstellenentwicklung', 'Performance & Sicherheit' ),
		'cats'     => array( 'ux-ui' ),
		'image'    => $uploads . 'image.png',
		'href'     => home_url( '/portfolio/blvck/' ),
		'tone'     => 'snow',
	),
	array(
		'slug'     => 'fredi',
		'kicker'   => 'Branding',
		'client'   => 'Fredi',
		'title'    => 'Branding & Webdesign.',
		'heading'  => 'Fredi, Branding & Webdesign',
		'lede'     => 'Für Fredi haben wir eine klare und wiedererkennbare digitale Präsenz entwickelt. Der Fokus lag auf einer konsistenten Markenwirkung und einer benutzerfreundlichen Webstruktur.',
		'body'     => 'Die Herausforderung bestand darin, eine junge Marke digital professionell darzustellen und gleichzeitig eine klare visuelle Identität zu schaffen, die sich abhebt und leicht wiedererkennbar ist.',
		'result'   => 'Ein Designkonzept, das Marke, Produkt und Nutzerführung verbindet. Strukturierte Webarchitektur und konsistentes UI-Design — visuell überzeugend und funktional.',
		'services' => array( 'Branding & visuelle Identität', 'Webdesign & Struktur', 'UI / UX Design', 'Responsive Umsetzung' ),
		'cats'     => array( 'branding' ),
		'image'    => $uploads . 'Product-Card-Mockup-0.avif',
		'href'     => home_url( '/portfolio/fredi/' ),
		'tone'     => 'light',
	),
	array(
		'slug'     => 'art-eco',
		'kicker'   => 'Product',
		'client'   => 'Visuelle Systeme',
		'title'    => 'Digitale Gestaltung als System.',
		'heading'  => 'Digitale Gestaltung & visuelle Systeme',
		'lede'     => 'Design als Teil digitaler Systeme. Konzepte, die logisch aufgebaut, konsistent umgesetzt und technisch sauber integrierbar sind.',
		'body'     => 'Digitale Produkte wachsen mit der Zeit — Design muss dabei mithalten. Die größte Herausforderung liegt darin, visuelle Einheitlichkeit zu bewahren, auch wenn Funktionen und Inhalte erweitert werden.',
		'result'   => 'Klare Designsysteme und wiederverwendbare Komponenten. Interfaces bleiben übersichtlich, wartbar und flexibel — unabhängig vom Umfang.',
		'services' => array( 'UI- & Interface-Design', 'Designsysteme & Komponenten', 'Web- & App-Gestaltung', 'Visuelle Konsistenz' ),
		'cats'     => array( 'product' ),
		'image'    => $uploads . 'immagepng.avif',
		'href'     => home_url( '/portfolio/art-eco/' ),
		'tone'     => 'dark',
	),
	array(
		'slug'     => 'cozmetic',
		'kicker'   => 'Branding',
		'client'   => 'Cozmetic',
		'title'    => 'Hochwertiges Produktbranding.',
		'heading'  => 'Cozmetic, Hochwertiges Produktbranding im digitalen Raum',
		'lede'     => 'Für Cozmetic haben wir eine hochwertige digitale Präsentation entwickelt, die das Produkt klar in den Mittelpunkt stellt — Premium-Look, saubere Struktur, visuelle Sprache mit Vertrauen.',
		'body'     => 'Die Herausforderung bestand darin, ein Kosmetikprodukt digital so zu inszenieren, dass es hochwertig wirkt und gleichzeitig schnell verständlich bleibt. Design, Typografie und Bildsprache mussten präzise abgestimmt werden.',
		'result'   => 'Ein konsistentes visuelles Konzept mit klarer Hierarchie: starke Produktfokussierung, reduzierte Elemente und gezielte Akzente. Elegant auf jedem Gerät.',
		'services' => array( 'Markenlook & visuelle Richtung', 'Digitale Produktpräsentation', 'Webdesign & Layout-Struktur', 'UI-Details & Typografie' ),
		'cats'     => array( 'branding', 'ux-ui' ),
		'image'    => $uploads . 'folio-item-img3.avif',
		'href'     => home_url( '/portfolio/cozmetic/' ),
		'tone'     => 'snow',
	),
);

$hero = $works[0];
?>
<article class="pax-rf" lang="de">
	<a class="pax-rf-skip" href="#pax-rf-highlights">Zum Inhalt</a>

	<nav class="pax-rf-localnav" aria-label="Referenzen">
		<div class="pax-rf-localnav__inner">
			<a class="pax-rf-localnav__brand" href="#pax-rf-hero">Referenzen</a>
			<div class="pax-rf-localnav__links">
				<a href="#pax-rf-highlights">Highlights</a>
				<a href="#pax-rf-works">Arbeiten</a>
				<a href="#pax-rf-lineup">Lineup</a>
				<a href="#pax-rf-look">Closer Look</a>
			</div>
			<a class="pax-rf-localnav__cta" href="<?php echo esc_url( $contact ); ?>">Projekt anfragen</a>
		</div>
	</nav>

	<header id="pax-rf-hero" class="pax-rf-hero" data-rf-reveal>
		<div class="pax-rf-hero__copy">
			<p class="pax-rf-kicker">PAXdesign</p>
			<h1 class="pax-rf-hero__title">Ausgewählte Arbeiten.</h1>
			<p class="pax-rf-hero__lede">Einblick in unsere realisierten Software- und Systemprojekte. Präzision, Wirkung, Produkt.</p>
			<div class="pax-rf-actions pax-rf-actions--center">
				<a class="pax-rf-btn pax-rf-btn--fill" href="<?php echo esc_url( $contact ); ?>">Projekt anfragen</a>
				<a class="pax-rf-btn pax-rf-btn--text" href="<?php echo esc_url( $cases ); ?>">Cases entdecken<?php pax_referenzen_icon(); ?></a>
			</div>
		</div>
		<figure class="pax-rf-hero__stage" data-rf-stage>
			<div class="pax-rf-canvas pax-rf-canvas--hero">
				<img
					src="<?php echo esc_url( $hero['image'] ); ?>"
					alt="<?php echo esc_attr( $hero['heading'] ); ?>"
					width="1600"
					height="1200"
					decoding="async"
					fetchpriority="high"
				>
			</div>
			<figcaption>
				<span><?php echo esc_html( $hero['client'] ); ?></span>
				<?php echo esc_html( $hero['title'] ); ?>
			</figcaption>
		</figure>
	</header>

	<section id="pax-rf-highlights" class="pax-rf-highlights" data-rf-reveal>
		<div class="pax-rf-wrap pax-rf-wrap--wide pax-rf-highlights__head">
			<div>
				<p class="pax-rf-kicker pax-rf-kicker--on-dark">Get the highlights.</p>
				<h2 class="pax-rf-display pax-rf-display--light">Sechs Arbeiten. Ein Anspruch.</h2>
			</div>
			<div class="pax-rf-rail__nav" data-rf-rail-nav="highlights">
				<button type="button" class="pax-rf-rail__btn" data-rf-rail-prev aria-label="Zurück"><?php pax_referenzen_icon(); ?></button>
				<button type="button" class="pax-rf-rail__btn" data-rf-rail-next aria-label="Weiter"><?php pax_referenzen_icon(); ?></button>
			</div>
		</div>
		<div class="pax-rf-rail" data-rf-rail="highlights" tabindex="0" aria-label="Projekt-Highlights">
			<?php foreach ( $works as $item ) : ?>
				<a class="pax-rf-hl" href="#pax-rf-<?php echo esc_attr( $item['slug'] ); ?>">
					<span class="pax-rf-hl__media">
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" width="1400" height="1050" loading="lazy" decoding="async">
					</span>
					<span class="pax-rf-hl__copy">
						<em><?php echo esc_html( $item['kicker'] ); ?></em>
						<strong><?php echo esc_html( $item['client'] ); ?></strong>
						<b><?php echo esc_html( $item['title'] ); ?></b>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<div id="pax-rf-works" class="pax-rf-works">
		<?php foreach ( $works as $item ) : ?>
			<?php
			$tone      = $item['tone'];
			$on_dark   = ( 'dark' === $tone );
			$kicker_cl = $on_dark ? 'pax-rf-kicker pax-rf-kicker--on-dark' : 'pax-rf-kicker';
			$title_cl  = $on_dark ? 'pax-rf-display pax-rf-display--light' : 'pax-rf-display';
			$lede_cl   = $on_dark ? 'pax-rf-lede pax-rf-lede--on-dark' : 'pax-rf-lede';
			$primary   = $on_dark ? 'pax-rf-btn pax-rf-btn--light' : 'pax-rf-btn pax-rf-btn--fill';
			$secondary = $on_dark ? 'pax-rf-btn pax-rf-btn--ghost' : 'pax-rf-btn pax-rf-btn--text';
			?>
			<section id="pax-rf-<?php echo esc_attr( $item['slug'] ); ?>" class="pax-rf-film pax-rf-film--<?php echo esc_attr( $tone ); ?>" data-rf-reveal>
				<div class="pax-rf-wrap pax-rf-wrap--wide pax-rf-film__grid">
					<div class="pax-rf-film__copy">
						<p class="<?php echo esc_attr( $kicker_cl ); ?>"><?php echo esc_html( $item['kicker'] ); ?></p>
						<h2 class="<?php echo esc_attr( $title_cl ); ?>"><?php echo esc_html( $item['client'] ); ?><br><?php echo esc_html( $item['title'] ); ?></h2>
						<p class="<?php echo esc_attr( $lede_cl ); ?>"><?php echo esc_html( $item['lede'] ); ?></p>
						<p class="<?php echo esc_attr( $lede_cl ); ?>"><?php echo esc_html( $item['body'] ); ?></p>
						<ul class="pax-rf-facts<?php echo $on_dark ? ' pax-rf-facts--on-dark' : ''; ?>">
							<?php foreach ( $item['services'] as $fact ) : ?>
								<li><?php echo esc_html( $fact ); ?></li>
							<?php endforeach; ?>
						</ul>
						<div class="pax-rf-actions">
							<a class="<?php echo esc_attr( $primary ); ?>" href="<?php echo esc_url( $item['href'] ); ?>">Mehr erfahren</a>
							<a class="<?php echo esc_attr( $secondary ); ?>" href="<?php echo esc_url( $contact ); ?>">Ähnliches Projekt<?php pax_referenzen_icon(); ?></a>
						</div>
					</div>
					<figure class="pax-rf-film__visual">
						<div class="pax-rf-canvas pax-rf-canvas--<?php echo esc_attr( $tone ); ?>">
							<img
								src="<?php echo esc_url( $item['image'] ); ?>"
								alt="<?php echo esc_attr( $item['heading'] ); ?>"
								width="1400"
								height="1050"
								loading="lazy"
								decoding="async"
							>
						</div>
					</figure>
				</div>
			</section>
		<?php endforeach; ?>
	</div>

	<section id="pax-rf-lineup" class="pax-rf-lineup" data-rf-reveal>
		<div class="pax-rf-wrap pax-rf-wrap--wide">
			<h2 class="pax-rf-display">Explore the lineup.</h2>
			<p class="pax-rf-lede">Dieselben Referenzen, neu gelesen. Filter nach Disziplin — ohne das Bild zu beschneiden.</p>
			<div class="pax-rf-tabs" role="tablist" aria-label="Kategorien">
				<button type="button" class="is-active" role="tab" aria-selected="true" data-rf-filter="all">Alle</button>
				<button type="button" role="tab" aria-selected="false" data-rf-filter="branding">Branding</button>
				<button type="button" role="tab" aria-selected="false" data-rf-filter="product">Product</button>
				<button type="button" role="tab" aria-selected="false" data-rf-filter="ux-ui">UX/UI</button>
			</div>
			<div class="pax-rf-lineup__grid">
				<?php foreach ( $works as $item ) : ?>
					<article class="pax-rf-product" data-rf-cats="<?php echo esc_attr( implode( ' ', $item['cats'] ) ); ?>">
						<a class="pax-rf-product__media" href="<?php echo esc_url( $item['href'] ); ?>">
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['heading'] ); ?>" width="1200" height="900" loading="lazy" decoding="async">
						</a>
						<p class="pax-rf-product__kicker"><?php echo esc_html( $item['kicker'] ); ?></p>
						<h3><?php echo esc_html( $item['heading'] ); ?></h3>
						<p><?php echo esc_html( $item['result'] ); ?></p>
						<div class="pax-rf-product__links">
							<a href="<?php echo esc_url( $item['href'] ); ?>">Mehr erfahren<?php pax_referenzen_icon(); ?></a>
							<a href="<?php echo esc_url( $contact ); ?>">Anfragen<?php pax_referenzen_icon(); ?></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="pax-rf-look" class="pax-rf-look" data-rf-reveal>
		<div class="pax-rf-wrap pax-rf-wrap--wide pax-rf-look__head">
			<div>
				<p class="pax-rf-kicker">Take a closer look.</p>
				<h2 class="pax-rf-display">Das Bild bleibt vollständig.</h2>
			</div>
			<div class="pax-rf-rail__nav pax-rf-rail__nav--on-light" data-rf-rail-nav="look">
				<button type="button" class="pax-rf-rail__btn" data-rf-rail-prev aria-label="Zurück"><?php pax_referenzen_icon(); ?></button>
				<button type="button" class="pax-rf-rail__btn" data-rf-rail-next aria-label="Weiter"><?php pax_referenzen_icon(); ?></button>
			</div>
		</div>
		<div class="pax-rf-rail pax-rf-rail--look" data-rf-rail="look" tabindex="0" aria-label="Projektansichten">
			<?php foreach ( $works as $item ) : ?>
				<figure class="pax-rf-shot">
					<div class="pax-rf-canvas pax-rf-canvas--snow">
						<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['heading'] ); ?>" width="1400" height="1050" loading="lazy" decoding="async">
					</div>
					<figcaption>
						<strong><?php echo esc_html( $item['client'] ); ?></strong>
						<span><?php echo esc_html( $item['kicker'] ); ?></span>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="pax-rf-final" data-rf-reveal>
		<div class="pax-rf-wrap pax-rf-wrap--narrow pax-rf-final__inner">
			<p class="pax-rf-kicker">PAXdesign</p>
			<h2 class="pax-rf-display">Bereit für Ihr nächstes System?</h2>
			<p class="pax-rf-lede pax-rf-lede--center">Dieselbe Präzision, die in diesen Referenzen sichtbar ist — für Web, Software und Marke.</p>
			<div class="pax-rf-actions pax-rf-actions--center">
				<a class="pax-rf-btn pax-rf-btn--fill" href="<?php echo esc_url( $contact ); ?>">Kostenlose Beratung</a>
				<a class="pax-rf-btn pax-rf-btn--text" href="<?php echo esc_url( $services ); ?>">Leistungen<?php pax_referenzen_icon(); ?></a>
				<a class="pax-rf-btn pax-rf-btn--text" href="<?php echo esc_url( $design ); ?>">Visuelles Design<?php pax_referenzen_icon(); ?></a>
			</div>
		</div>
	</section>
</article>
