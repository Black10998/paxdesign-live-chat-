<?php
/**
 * Apple-inspired Wartung & Support page content.
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
			<p class="pax-aap-eyebrow">Wartung &amp; Support</p>
			<h1 class="pax-aap-hero__title">
				Systeme, die<br>
				<span class="pax-aap-hero__accent">rund um die Uhr laufen.</span>
			</h1>
			<p class="pax-aap-hero__lede">
				Monitoring, Updates, Backups und Priority Support, damit Ihre Website und Software stabil, sicher und performant bleiben.
			</p>
			<div class="pax-aap-hero__cta">
				<a class="pax-aap-btn pax-aap-btn--dark" href="<?php echo esc_url( $contact_url ); ?>">Support anfragen</a>
				<a class="pax-aap-btn pax-aap-btn--ghost" href="#betreuung">Betreuung entdecken</a>
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
								<div class="pax-aap-status">
									<div class="pax-aap-status__row">
										<span class="pax-aap-status__pulse"></span>
										<span class="pax-aap-status__bar pax-aap-status__bar--wide"></span>
										<span class="pax-aap-status__badge"></span>
									</div>
									<div class="pax-aap-status__row">
										<span class="pax-aap-status__pulse pax-aap-status__pulse--ok"></span>
										<span class="pax-aap-status__bar"></span>
										<span class="pax-aap-status__badge pax-aap-status__badge--ok"></span>
									</div>
									<div class="pax-aap-status__row">
										<span class="pax-aap-status__pulse pax-aap-status__pulse--ok"></span>
										<span class="pax-aap-status__bar pax-aap-status__bar--mid"></span>
										<span class="pax-aap-status__badge pax-aap-status__badge--ok"></span>
									</div>
									<div class="pax-aap-status__metrics">
										<i></i><i></i><i></i>
									</div>
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
				Wir betreuen Ihre digitalen Systeme so, dass Probleme früh erkannt, Updates ruhig eingespielt und Performance dauerhaft auf Niveau gehalten wird.
			</p>
		</div>
	</section>

	<!-- Care intro -->
	<section id="betreuung" class="pax-aap-section pax-aap-section--light" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Betreuung</p>
			<h2 class="pax-aap-display">Umfassende Betreuung<br>für Ihre Systeme.</h2>
		</div>
	</section>

	<!-- Monitoring -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Monitoring</p>
				<h3 class="pax-aap-feature__title">24/7 Monitoring</h3>
				<p class="pax-aap-feature__text">
					Kontinuierliche Überwachung von Verfügbarkeit, Performance und kritischen Diensten, mit klaren Alerts, bevor Nutzer etwas merken.
				</p>
				<ul class="pax-aap-list">
					<li>Uptime‑ &amp; Response‑Monitoring</li>
					<li>Fehler‑ und Ausfallerkennung</li>
					<li>Proaktive Benachrichtigungen</li>
					<li>Status‑Übersicht für Ihr Team</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--ios">
					<span class="pax-aap-visual__mark">Uptime</span>
					<span class="pax-aap-visual__mark">Alerts</span>
					<span class="pax-aap-visual__mark">Logs</span>
					<span class="pax-aap-visual__mark">24/7</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Updates & Security -->
	<section class="pax-aap-feature pax-aap-feature--light" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid pax-aap-feature__grid--reverse">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow">Security</p>
				<h3 class="pax-aap-feature__title">Updates &amp; Security</h3>
				<p class="pax-aap-feature__text">
					Regelmäßige Updates, Sicherheitspatches und proaktives Security Management, für stabile Systeme und weniger Risiken.
				</p>
				<ul class="pax-aap-list pax-aap-list--dark">
					<li>Core‑, Plugin‑ &amp; Dependency‑Updates</li>
					<li>Security Hardening</li>
					<li>Malware‑ &amp; Threat‑Checks</li>
					<li>Schnelle Reaktion auf Vorfälle</li>
				</ul>
			</div>
			<div class="pax-aap-feature__visual" aria-hidden="true">
				<div class="pax-aap-visual pax-aap-visual--android">
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Patches</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">SSL</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Firewall</span>
					<span class="pax-aap-visual__mark pax-aap-visual__mark--dark">Audit</span>
				</div>
			</div>
		</div>
	</section>

	<!-- Backup & Performance -->
	<section class="pax-aap-feature pax-aap-feature--dark" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-feature__grid">
			<div class="pax-aap-feature__copy">
				<p class="pax-aap-eyebrow pax-aap-eyebrow--light">Reliability</p>
				<h3 class="pax-aap-feature__title">Backup &amp; Performance</h3>
				<p class="pax-aap-feature__text">
					Automatische Backups, schnelle Wiederherstellung und kontinuierliche Optimierung, damit Geschwindigkeit und Sicherheit Hand in Hand gehen.
				</p>
				<ul class="pax-aap-list">
					<li>Automatische Backup‑Routinen</li>
					<li>Recovery‑Pläne für den Notfall</li>
					<li>Caching &amp; Ladezeit‑Tuning</li>
					<li>Regelmäßige Performance‑Checks</li>
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
					<h3>Priority Support</h3>
					<p>Schneller, persönlicher Support durch erfahrene Techniker, mit klaren Reaktionszeiten und direkter Kommunikation.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Care‑Pakete</h3>
					<p>Basic, Professional oder Enterprise, abgestimmt auf Komplexität, kritische Systeme und gewünschte Reaktionsgeschwindigkeit.</p>
				</div>
				<div class="pax-aap-strip__item">
					<h3>Langfristiger Betrieb</h3>
					<p>Wartung, Dokumentation und Weiterentwicklung, damit Ihr System dauerhaft auf Premium‑Niveau bleibt.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Process -->
	<section class="pax-aap-section pax-aap-section--light pax-aap-process" data-aap-reveal>
		<div class="pax-aap-wrap">
			<p class="pax-aap-eyebrow">Prozess</p>
			<h2 class="pax-aap-display">So läuft<br>Ihre Betreuung.</h2>

			<ol class="pax-aap-process__list">
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">01</span>
					<div>
						<h3>Onboarding</h3>
						<p>Systeme, Zugänge und Prioritäten erfassen, sauber und dokumentiert.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">02</span>
					<div>
						<h3>Monitoring Setup</h3>
						<p>Überwachung, Alerts und Backup‑Routinen aktivieren.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">03</span>
					<div>
						<h3>Regelmäßige Wartung</h3>
						<p>Updates, Security‑Checks und Performance‑Optimierung.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">04</span>
					<div>
						<h3>Incident Response</h3>
						<p>Bei Störungen schnell handeln, kommunizieren und stabilisieren.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">05</span>
					<div>
						<h3>Reporting</h3>
						<p>Transparente Übersicht über Status, Maßnahmen und Empfehlungen.</p>
					</div>
				</li>
				<li class="pax-aap-process__step">
					<span class="pax-aap-process__num">06</span>
					<div>
						<h3>Weiterentwicklung</h3>
						<p>Kontinuierliche Verbesserungen statt reaktivem Flickwerk.</p>
					</div>
				</li>
			</ol>
		</div>
	</section>

	<!-- CTA -->
	<section class="pax-aap-cta" data-aap-reveal>
		<div class="pax-aap-wrap pax-aap-wrap--narrow pax-aap-cta__inner">
			<h2 class="pax-aap-display pax-aap-display--light">Benötigen Sie<br>sofortigen Support?</h2>
			<p class="pax-aap-cta__text">Sprechen Sie mit uns, wir kümmern uns um Stabilität, Sicherheit und Performance Ihrer Systeme.</p>
			<div class="pax-aap-cta__actions">
				<a class="pax-aap-btn pax-aap-btn--light" href="<?php echo esc_url( $contact_url ); ?>">Support anfragen</a>
				<a class="pax-aap-link" href="tel:+4368120543638"><?php echo esc_html( $phone ); ?></a>
				<a class="pax-aap-link" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			</div>
		</div>
	</section>

</article>
