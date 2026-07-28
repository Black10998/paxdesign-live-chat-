<?php
/**
 * Apple-inspired Softwareentwicklung page — unique product identity.
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
<article <?php post_class( 'pax-aap pax-aap--software' ); ?>>

	<!-- Cinematic centered hero -->
	<section class="pax-sw-hero" data-aap-reveal>
		<div class="pax-sw-hero__glow" aria-hidden="true"></div>
		<div class="pax-sw-hero__inner">
			<p class="pax-aap-eyebrow">Softwareentwicklung</p>
			<h1 class="pax-sw-hero__title">
				Software, die<br>
				<span class="pax-sw-hero__accent">mit Ihrem Business denkt.</span>
			</h1>
			<p class="pax-sw-hero__lede">
				Individuelle Systeme für Desktop, Cloud und Integration — klar in der Architektur, präzise in der Umsetzung, gebaut für Wachstum.
			</p>
			<div class="pax-sw-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Projekt starten</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#engineering">Architektur entdecken</a>
			</div>
		</div>

		<div class="pax-sw-orbit" aria-hidden="true" data-sw-orbit>
			<span class="pax-sw-orbit__ring pax-sw-orbit__ring--a"></span>
			<span class="pax-sw-orbit__ring pax-sw-orbit__ring--b"></span>
			<span class="pax-sw-orbit__core">
				<em>PAX</em>
				<small>Systems</small>
			</span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:0"><b>API</b></span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:1"><b>Cloud</b></span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:2"><b>Data</b></span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:3"><b>Desktop</b></span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:4"><b>Security</b></span>
			<span class="pax-sw-orbit__node" data-sw-node style="--sw-i:5"><b>CI/CD</b></span>
		</div>
	</section>

	<!-- Oversized manifesto -->
	<section class="pax-sw-manifesto" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow">
			<p class="pax-sw-manifesto__kicker">Engineering principle</p>
			<p class="pax-sw-manifesto__text">
				Gute Software verschwindet im Alltag — und hinterlässt Klarheit, Geschwindigkeit und Kontrolle.
			</p>
		</div>
	</section>

	<!-- Chapter rail intro -->
	<section id="engineering" class="pax-sw-chapter-intro" data-aap-reveal>
		<div class="pax-aap-wrap">
			<div class="pax-sw-chapter-intro__row">
				<span class="pax-sw-chapter-intro__index">01</span>
				<div>
					<p class="pax-aap-eyebrow">Engineering</p>
					<h2 class="pax-aap-display">Drei Ebenen.<br>Ein System.</h2>
				</div>
			</div>
		</div>
	</section>

	<!-- Stacked chapters (unique vertical flow) -->
	<section class="pax-sw-chapter pax-sw-chapter--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-sw-chapter__grid">
			<div class="pax-sw-chapter__meta">
				<span class="pax-sw-chapter__num">01 / Architecture</span>
				<h3 class="pax-sw-chapter__title">Enterprise Foundations</h3>
				<p class="pax-sw-chapter__text">
					Modulare Fachanwendungen, saubere Domänengrenzen und Integrationen, die mitwachsen — statt monolithischer Kompromisse.
				</p>
				<ul class="pax-aap-list">
					<li>Domain‑driven Strukturen</li>
					<li>Workflow‑ &amp; Rechte‑Modelle</li>
					<li>System‑ und Datenintegration</li>
				</ul>
			</div>
			<div class="pax-sw-panel pax-sw-panel--arch" aria-hidden="true">
				<div class="pax-sw-panel__layers">
					<span></span><span></span><span></span>
				</div>
				<div class="pax-sw-panel__label">Layered Architecture</div>
			</div>
		</div>
	</section>

	<section class="pax-sw-chapter pax-sw-chapter--snow" data-aap-reveal>
		<div class="pax-aap-wrap pax-sw-chapter__grid pax-sw-chapter__grid--flip">
			<div class="pax-sw-chapter__meta">
				<span class="pax-sw-chapter__num pax-sw-chapter__num--dark">02 / Runtime</span>
				<h3 class="pax-sw-chapter__title pax-sw-chapter__title--ink">Desktop &amp; Cloud</h3>
				<p class="pax-sw-chapter__text pax-sw-chapter__text--ink">
					Native Desktop‑Software für Windows, macOS und Linux — oder cloud‑native Services mit Container, Skalierung und klaren Deployments.
				</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Native Desktop‑Clients</li>
					<li>Microservices &amp; Container</li>
					<li>Automatische Skalierung</li>
				</ul>
			</div>
			<div class="pax-sw-panel pax-sw-panel--runtime" aria-hidden="true">
				<div class="pax-sw-terminal" data-sw-terminal>
					<div class="pax-sw-terminal__bar">
						<span></span><span></span><span></span>
						<em>deploy.sh</em>
					</div>
					<pre class="pax-sw-terminal__body"><code><span class="pax-sw-terminal__prompt">$</span> build --release
<span class="pax-sw-terminal__ok">✔</span> compile · 42 modules
<span class="pax-sw-terminal__prompt">$</span> ship --env production
<span class="pax-sw-terminal__ok">✔</span> healthy · rolling update
<span class="pax-sw-terminal__caret" data-sw-caret="true"></span></code></pre>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-sw-chapter pax-sw-chapter--ink" data-aap-reveal>
		<div class="pax-aap-wrap pax-sw-chapter__grid">
			<div class="pax-sw-chapter__meta">
				<span class="pax-sw-chapter__num">03 / Interfaces</span>
				<h3 class="pax-sw-chapter__title">APIs &amp; Datenflüsse</h3>
				<p class="pax-sw-chapter__text">
					REST und GraphQL, robuste Datenmodelle und Echtzeit‑Pfade — damit Systeme sprechen, statt nebeneinander zu existieren.
				</p>
				<ul class="pax-aap-list">
					<li>API‑first Design</li>
					<li>Optimierte Datenbanken</li>
					<li>Event‑ &amp; Sync‑Pipelines</li>
				</ul>
			</div>
			<div class="pax-sw-panel pax-sw-panel--flow" aria-hidden="true">
				<svg class="pax-sw-flow" viewBox="0 0 360 220" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path class="pax-sw-flow__path" d="M40 110 H140 Q180 110 180 70 V40" />
					<path class="pax-sw-flow__path" d="M180 40 V180 Q180 210 220 210 H320" />
					<path class="pax-sw-flow__path pax-sw-flow__path--alt" d="M40 110 H140 Q180 110 180 150 V180" />
					<circle class="pax-sw-flow__node" cx="40" cy="110" r="8" />
					<circle class="pax-sw-flow__node" cx="180" cy="40" r="8" />
					<circle class="pax-sw-flow__node" cx="180" cy="180" r="8" />
					<circle class="pax-sw-flow__node" cx="320" cy="210" r="8" />
					<text x="20" y="90" class="pax-sw-flow__label">Client</text>
					<text x="152" y="28" class="pax-sw-flow__label">API</text>
					<text x="148" y="204" class="pax-sw-flow__label">DB</text>
					<text x="286" y="198" class="pax-sw-flow__label">Sync</text>
				</svg>
			</div>
		</div>
	</section>

	<!-- Tech marquee ribbon -->
	<section class="pax-sw-ribbon" data-aap-reveal aria-label="Tech Stack">
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

	<!-- Capability triptych (full-bleed columns, not cards) -->
	<section class="pax-sw-triptych" data-aap-reveal>
		<div class="pax-sw-triptych__col">
			<span class="pax-sw-triptych__label">Stack</span>
			<h3>Backend mit Substanz</h3>
			<p>Java, C#, Python und Go — mit Frameworks, die Enterprise‑Last tragen und trotzdem wartbar bleiben.</p>
		</div>
		<div class="pax-sw-triptych__col">
			<span class="pax-sw-triptych__label">DevOps</span>
			<h3>Vom Commit zum Release</h3>
			<p>Container, Pipelines und reproduzierbare Deployments — damit Releases ruhig und nachvollziehbar sind.</p>
		</div>
		<div class="pax-sw-triptych__col">
			<span class="pax-sw-triptych__label">Security</span>
			<h3>Sicherheit eingebaut</h3>
			<p>Hardening, DSGVO‑konforme Setups und Monitoring — Security als Teil der Architektur, nicht als Afterthought.</p>
		</div>
	</section>

	<!-- Horizontal process timeline -->
	<section class="pax-sw-timeline" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">Ein klarer Weg<br>zur laufenden Software.</h2>
			<ol class="pax-sw-timeline__list">
				<li>
					<span>01</span>
					<strong>Analyse</strong>
					<em>Ziele, Prozesse, Constraints</em>
				</li>
				<li>
					<span>02</span>
					<strong>Architektur</strong>
					<em>Domänen, Daten, Schnittstellen</em>
				</li>
				<li>
					<span>03</span>
					<strong>Build</strong>
					<em>Iterativ, getestet, dokumentiert</em>
				</li>
				<li>
					<span>04</span>
					<strong>Launch</strong>
					<em>Go‑Live mit Monitoring</em>
				</li>
				<li>
					<span>05</span>
					<strong>Evolve</strong>
					<em>Messen, schärfen, ausbauen</em>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-sw-cta" data-aap-reveal>
		<div class="pax-sw-cta__mesh" aria-hidden="true"></div>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-sw-cta__inner">
			<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Next step</p>
			<h2 class="pax-aap-display pax-aap-display--light">Bereit, Software<br>wie ein Produkt zu bauen?</h2>
			<p class="pax-sw-cta__text">Lassen Sie uns Ihre Anforderungen in eine Architektur übersetzen — und in Software, die hält.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">Kostenlose Beratung</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
