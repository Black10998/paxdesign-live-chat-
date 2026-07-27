<?php
/**
 * Apple-inspired Softwareentwicklung page content.
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
			<p class="pax-aap-eyebrow">Softwareentwicklung</p>
			<h1 class="pax-aap-hero__title">
				Maßgeschneiderte Software<br>
				<span class="pax-aap-hero__accent">für Ihr Business.</span>
			</h1>
			<p class="pax-aap-hero__lede">
				Individuelle Software‑Lösungen — von Desktop‑Anwendungen bis Cloud‑Plattformen. Klar gedacht, robust gebaut, bereit für Wachstum.
			</p>
			<div class="pax-aap-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Beratung anfragen</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#loesungen">Lösungen entdecken</a>
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
						<div class="pax-aap-desktop__chrome pax-aap-desktop__chrome--code">
							<div class="pax-aap-desktop__nav">
								<span></span><span></span><span></span><span></span>
							</div>
							<div class="pax-aap-desktop__stage">
								<div class="pax-aap-code">
									<span class="pax-aap-code__line"><i></i><b></b><em></em></span>
									<span class="pax-aap-code__line pax-aap-code__line--indent"><i></i><b></b></span>
									<span class="pax-aap-code__line pax-aap-code__line--indent"><i></i><em></em><b></b></span>
									<span class="pax-aap-code__line"><i></i></span>
									<span class="pax-aap-code__line pax-aap-code__line--indent"><b></b><em></em></span>
									<span class="pax-aap-code__line pax-aap-code__line--indent"><i></i><b></b></span>
									<span class="pax-aap-code__line"><i></i></span>
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
				Wir entwickeln Software, die Prozesse vereinfacht, Teams entlastet und sich anfühlt wie ein natürlicher Teil Ihres Unternehmens.
			</p>
		</div>
	</section>

	<!-- Solutions intro -->
	<section id="loesungen" class="pax-aap-section pax-aap-section--light" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Lösungen</p>
			<h2 class="pax-aap-display">Software, die mitdenkt<br>und mitwächst.</h2>
		</div>
	</section>

	<!-- Enterprise -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Enterprise</p>
				<h3 class="pax-aap-feature__title">Enterprise Software</h3>
				<p class="pax-aap-feature__text">
					Komplexe Geschäftsprozesse in klaren Systemen abbilden — skalierbar, wartbar und integriert in Ihre bestehende Landschaft.
				</p>
				<ul class="pax-aap-list">
					<li>Individuelle Fachanwendungen</li>
					<li>Prozessautomatisierung</li>
					<li>System‑ &amp; Datenintegration</li>
					<li>Rollen, Rechte &amp; Workflows</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--ios">
					<span class="pax-aap-visual__mark">Java</span>
					<span class="pax-aap-visual__mark">.NET</span>
					<span class="pax-aap-visual__mark">Python</span>
					<span class="pax-aap-visual__mark">Go</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Desktop & Cloud -->
	<section class="pax-aap-feature pax-aap-feature--light" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid pax-aap-feature__grid--reverse">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow">Platforms</p>
				<h3 class="pax-aap-feature__title">Desktop &amp; Cloud</h3>
				<p class="pax-aap-feature__text">
					Native Desktop‑Software für Windows, macOS und Linux — oder cloud‑native Anwendungen mit Microservices und automatischer Skalierung.
				</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Native Desktop‑Apps</li>
					<li>Cloud‑native Architektur</li>
					<li>Microservices &amp; Container</li>
					<li>Automatische Skalierung</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--android">
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Desktop</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Cloud</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Docker</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">K8s</span>
				</div>
			</div>
		</div>
	</section>

	<!-- APIs & Data -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Integration</p>
				<h3 class="pax-aap-feature__title">APIs &amp; Daten</h3>
				<p class="pax-aap-feature__text">
					RESTful und GraphQL APIs, saubere Schnittstellen und Datenbankarchitekturen für schnelle Abfragen und hohe Verfügbarkeit.
				</p>
				<ul class="pax-aap-list">
					<li>REST &amp; GraphQL</li>
					<li>API‑first Integrationen</li>
					<li>Optimierte Datenbanken</li>
					<li>Echtzeit‑Datenaustausch</li>
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
					<h3>Tech Stack</h3>
					<p>Java, C#, Python, Go — mit Spring Boot, .NET Core, Django und FastAPI für stabile, moderne Backends.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>DevOps</h3>
					<p>Docker, Kubernetes und CI/CD mit Jenkins oder GitLab — reproduzierbare Builds und sichere Deployments.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Security</h3>
					<p>Höchste Sicherheitsstandards und Compliance mit DSGVO, Hardening und kontinuierlichem Monitoring.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Process -->
	<section class="pax-aap-section pax-aap-section--light pax-aap-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">Von der Anforderung<br>zur laufenden Software.</h2>

			<ol class="pax-aap-process__list">
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">01</span>
					<div>
						<h3>Analyse</h3>
						<p>Ziele, Prozesse und technische Rahmenbedingungen verstehen.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">02</span>
					<div>
						<h3>Konzeption</h3>
						<p>Architektur, Datenmodell und ein belastbares Lösungskonzept.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">03</span>
					<div>
						<h3>Design</h3>
						<p>UX und Interfaces, die komplexe Abläufe einfach machen.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">04</span>
					<div>
						<h3>Entwicklung</h3>
						<p>Sauberer Code, Tests und Integrationen — dokumentiert und wartbar.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">05</span>
					<div>
						<h3>Launch</h3>
						<p>Go‑Live mit Security‑Review, Monitoring und klarer Übergabe.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">06</span>
					<div>
						<h3>Weiterentwicklung</h3>
						<p>Betrieb, Updates und Ausbau — Ihre Software bleibt auf Niveau.</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-aap-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-aap-cta__inner">
			<h2 class="pax-aap-display pax-aap-display--light">Bereit für Ihre<br>Software‑Lösung?</h2>
			<p class="pax-aap-cta__text">Lassen Sie uns gemeinsam die passende Software für Ihr Unternehmen bauen.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">Kostenlose Beratung</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
