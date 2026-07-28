<?php
/**
 * Apple-inspired Impressum (legal imprint) page content.
 * Keeps all required legal information; presentation only.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$phone = '+43 681 20543638';
$email = 'info@paxdesign.at';
$site  = 'https://paxdesign.at';
?>
<article class="pax-legal" id="pax-apple-impressum">

	<header class="pax-legal__hero">
		<div class="pax-legal__hero-inner">
			<p class="pax-legal__eyebrow"><?php esc_html_e( 'Rechtliches', 'navein' ); ?></p>
			<h1 class="pax-legal__title"><?php esc_html_e( 'Impressum', 'navein' ); ?></h1>
			<p class="pax-legal__lede">
				<?php esc_html_e( 'Pflichtangaben gemäß § 5 TMG und § 25 MedienG', 'navein' ); ?>
			</p>
		</div>
	</header>

	<div class="pax-legal__body">
		<div class="pax-legal__layout">

			<nav class="pax-legal__nav" aria-label="<?php esc_attr_e( 'Impressum Inhalt', 'navein' ); ?>">
				<p class="pax-legal__nav-label"><?php esc_html_e( 'Inhalt', 'navein' ); ?></p>
				<ol class="pax-legal__nav-list">
					<li><a href="#angaben"><?php esc_html_e( 'Angaben', 'navein' ); ?></a></li>
					<li><a href="#handelsregister"><?php esc_html_e( 'Handelsregister', 'navein' ); ?></a></li>
					<li><a href="#kontakt"><?php esc_html_e( 'Kontakt', 'navein' ); ?></a></li>
					<li><a href="#ust"><?php esc_html_e( 'USt-ID', 'navein' ); ?></a></li>
					<li><a href="#geschaeftsfuehrung"><?php esc_html_e( 'Geschäftsführung', 'navein' ); ?></a></li>
					<li><a href="#aufsicht"><?php esc_html_e( 'Aufsichtsbehörde', 'navein' ); ?></a></li>
					<li><a href="#berufsrecht"><?php esc_html_e( 'Berufsrecht', 'navein' ); ?></a></li>
					<li><a href="#streit"><?php esc_html_e( 'Streitschlichtung', 'navein' ); ?></a></li>
					<li><a href="#haftung"><?php esc_html_e( 'Haftung', 'navein' ); ?></a></li>
					<li><a href="#copyright"><?php esc_html_e( 'Copyright', 'navein' ); ?></a></li>
				</ol>
			</nav>

			<div class="pax-legal__content">

				<section class="pax-legal__section" id="angaben" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Angaben gemäß § 5 TMG', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Firmenname', 'navein' ); ?></dt>
							<dd>PrimoJob GmbH</dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Marke', 'navein' ); ?></dt>
							<dd>PAXdesign</dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Anschrift', 'navein' ); ?></dt>
							<dd>Franzensbrückenstraße 14, 1020 Wien, Österreich</dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="handelsregister" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Handelsregister', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Firmenbuchnummer', 'navein' ); ?></dt>
							<dd>FN 562574 s</dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Firmenbuchgericht', 'navein' ); ?></dt>
							<dd>Handelsgericht Wien</dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Rechtsform', 'navein' ); ?></dt>
							<dd><?php esc_html_e( 'Gesellschaft mit beschränkter Haftung (GmbH)', 'navein' ); ?></dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Sitz', 'navein' ); ?></dt>
							<dd><?php esc_html_e( 'Wien, Österreich', 'navein' ); ?></dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="kontakt" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Kontakt', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Telefon', 'navein' ); ?></dt>
							<dd><a href="<?php echo esc_url( 'tel:' . preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'E-Mail', 'navein' ); ?></dt>
							<dd><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Website', 'navein' ); ?></dt>
							<dd><a href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener">www.paxdesign.at</a></dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="ust" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Umsatzsteuer-Identifikation', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'UID-Nummer', 'navein' ); ?></dt>
							<dd><span class="pax-legal__mono">ATU76471707</span></dd>
						</div>
					</dl>
					<p class="pax-legal__note"><?php esc_html_e( 'Gemäß § 27a Umsatzsteuergesetz', 'navein' ); ?></p>
				</section>

				<section class="pax-legal__section" id="geschaeftsfuehrung" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Vertretungsberechtigte Geschäftsführung', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Geschäftsführer', 'navein' ); ?></dt>
							<dd>Alkhalaf idleb</dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="aufsicht" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Aufsichtsbehörde', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Zuständige Behörde', 'navein' ); ?></dt>
							<dd>
								<?php esc_html_e( 'Magistratisches Bezirksamt für den 2. Bezirk', 'navein' ); ?><br>
								Karmelitergasse 9, 1020 Wien, Österreich
							</dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="berufsrecht" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Berufsrechtliche Regelungen', 'navein' ); ?></h2>
					<dl class="pax-legal__facts">
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Unternehmensgegenstand', 'navein' ); ?></dt>
							<dd><?php esc_html_e( 'Webentwicklung, Softwareentwicklung, App-Entwicklung, IT-Consulting, Wartung & Support digitaler Systeme', 'navein' ); ?></dd>
						</div>
						<div class="pax-legal__fact">
							<dt><?php esc_html_e( 'Rechtsvorschriften', 'navein' ); ?></dt>
							<dd>
								<?php esc_html_e( 'Gewerbeordnung (GewO)', 'navein' ); ?>,
								<a href="https://www.ris.bka.gv.at" target="_blank" rel="noopener">www.ris.bka.gv.at</a>
							</dd>
						</div>
					</dl>
				</section>

				<section class="pax-legal__section" id="streit" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Streitschlichtung', 'navein' ); ?></h2>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:', 'navein' ); ?>
						<a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>
					</p>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.', 'navein' ); ?>
					</p>
				</section>

				<section class="pax-legal__section" id="haftung" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Haftungsausschluss', 'navein' ); ?></h2>
					<h3 class="pax-legal__subheading"><?php esc_html_e( 'Haftung für Inhalte', 'navein' ); ?></h3>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.', 'navein' ); ?>
					</p>
					<h3 class="pax-legal__subheading"><?php esc_html_e( 'Haftung für Links', 'navein' ); ?></h3>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber verantwortlich.', 'navein' ); ?>
					</p>
					<h3 class="pax-legal__subheading"><?php esc_html_e( 'Urheberrecht', 'navein' ); ?></h3>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem österreichischen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors.', 'navein' ); ?>
					</p>
				</section>

				<section class="pax-legal__section" id="copyright" data-legal-section>
					<h2 class="pax-legal__heading"><?php esc_html_e( 'Copyright', 'navein' ); ?></h2>
					<p class="pax-legal__prose">
						© 2025 PrimoJob GmbH / PAXdesign. <?php esc_html_e( 'Alle Rechte vorbehalten.', 'navein' ); ?>
					</p>
					<p class="pax-legal__prose">
						<?php esc_html_e( 'Alle auf dieser Website verwendeten Texte, Bilder, Grafiken und Designs sind urheberrechtlich geschützt und dürfen ohne ausdrückliche schriftliche Genehmigung nicht verwendet werden.', 'navein' ); ?>
					</p>
				</section>

				<footer class="pax-legal__meta">
					<span><?php esc_html_e( 'Stand: Dezember 2025', 'navein' ); ?></span>
					<span class="pax-legal__meta-sep" aria-hidden="true">·</span>
					<a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
				</footer>

			</div>
		</div>
	</div>

</article>
