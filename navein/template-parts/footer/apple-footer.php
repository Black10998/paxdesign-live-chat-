<?php
/**
 * Apple-inspired sitewide footer.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$year     = gmdate( 'Y' );
$phone    = '+43 681 20543638';
$phone_tel = '+4368120543638';
$email    = 'info@paxdesign.at';
$wa       = 'https://wa.me/4368120543638';

$services = array(
	array( __( 'Webentwicklung', 'navein' ), home_url( '/webentwicklung/' ) ),
	array( __( 'Softwareentwicklung', 'navein' ), home_url( '/softwareentwicklung/' ) ),
	array( __( 'App-Entwicklung', 'navein' ), home_url( '/app-entwicklung/' ) ),
	array( __( 'Advanced Website Systems', 'navein' ), home_url( '/advanced-website-systems/' ) ),
	array( __( 'Wartung & Support', 'navein' ), home_url( '/wartung-support/' ) ),
	array( __( 'IT-Consulting', 'navein' ), home_url( '/it-consulting/' ) ),
);

$company = array(
	array( __( 'Über uns', 'navein' ), home_url( '/ueber-uns/' ) ),
	array( __( 'Referenzen', 'navein' ), home_url( '/referenzen/' ) ),
	array( __( 'Karriere', 'navein' ), home_url( '/karriere/' ) ),
	array( __( 'Team', 'navein' ), home_url( '/team/' ) ),
	array( __( 'Kontakt', 'navein' ), home_url( '/kontakt/' ) ),
	array( __( 'Preise', 'navein' ), home_url( '/projektpreise/' ) ),
);

$legal = array(
	array( __( 'Impressum', 'navein' ), home_url( '/impressum/' ) ),
	array( __( 'Datenschutz', 'navein' ), home_url( '/datenschutz/' ) ),
	array( __( 'AGB', 'navein' ), home_url( '/agb/' ) ),
	array( __( 'Service-Doku', 'navein' ), home_url( '/service-dokumentation/' ) ),
);

$social = array(
	array(
		'label' => 'Instagram',
		'href'  => 'https://www.instagram.com/paxdes_webdesign?igsh=eTR2endvZTQ5ZzFt&utm_source=qr',
		'icon'  => 'instagram',
	),
	array(
		'label' => 'LinkedIn',
		'href'  => 'https://www.linkedin.com/in/ahmad-al-khalaf-26265435a?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=ios_app',
		'icon'  => 'linkedin',
	),
	array(
		'label' => 'WhatsApp',
		'href'  => $wa,
		'icon'  => 'whatsapp',
	),
	array(
		'label' => 'TikTok',
		'href'  => 'https://www.tiktok.com/@paxdesignaustria',
		'icon'  => 'tiktok',
	),
	array(
		'label' => 'Facebook',
		'href'  => 'https://www.facebook.com/share/1JuWezscEk/?mibextid=wwXIfr',
		'icon'  => 'facebook',
	),
);

$certs = array(
	array(
		'label' => 'Green Web',
		'href'  => 'https://www.thegreenwebfoundation.org/green-web-check/?url=https%3A%2F%2Fpaxdesign.at',
		'img'   => 'https://app.greenweb.org/api/v3/greencheckimage/paxdesign.at?nocache=true',
		'alt'   => 'Green Web Certificate',
	),
	array(
		'label' => 'Observatory',
		'href'  => 'https://developer.mozilla.org/en-US/observatory/analyze?host=paxdesign.at',
		'img'   => 'https://paxdesign.at/wp-content/uploads/2026/06/87144171-c582-4110-bcda-82b209fed5c6.png',
		'alt'   => 'Mozilla Observatory',
	),
	array(
		'label' => 'Security Headers',
		'href'  => 'https://securityheaders.com/?q=paxdesign.at&followRedirects=on',
		'img'   => 'https://paxdesign.at/wp-content/uploads/2026/06/Snyk_Security_Headers_Logo_Light.svg',
		'alt'   => 'Security Headers A+',
	),
	array(
		'label' => 'SSL Labs',
		'href'  => 'https://www.ssllabs.com/ssltest/analyze.html?d=paxdesign.at',
		'img'   => 'https://paxdesign.at/wp-content/uploads/2026/06/qualys-ssl-labs-logo.png',
		'alt'   => 'Qualys SSL Labs',
	),
);

/**
 * Inline social SVG.
 *
 * @param string $name Icon key.
 */
if ( ! function_exists( 'navein_apple_footer_icon' ) ) {
	function navein_apple_footer_icon( $name ) {
		$icons = array(
			'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.9.2 2.3.4.6.2 1 .5 1.5 1 .4.4.7.9 1 1.5.2.4.4 1.1.4 2.3.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.9-.4 2.3-.2.6-.5 1-1 1.5-.4.4-.9.7-1.5 1-.4.2-1.1.4-2.3.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.9-.2-2.3-.4-.6-.2-1-.5-1.5-1-.4-.4-.7-.9-1-1.5-.2-.4-.4-1.1-.4-2.3C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.9.4-2.3.2-.6.5-1 1-1.5.4-.4.9-.7 1.5-1 .4-.2 1.1-.4 2.3-.4C8.4 2.2 8.8 2.2 12 2.2zm0 1.8c-3.2 0-3.5 0-4.7.1-1.1 0-1.7.2-2.1.4-.5.2-.8.4-1.1.7-.3.3-.5.6-.7 1.1-.2.4-.3 1-.4 2.1-.1 1.2-.1 1.5-.1 4.7s0 3.5.1 4.7c0 1.1.2 1.7.4 2.1.2.5.4.8.7 1.1.3.3.6.5 1.1.7.4.2 1 .3 2.1.4 1.2.1 1.5.1 4.7.1s3.5 0 4.7-.1c1.1 0 1.7-.2 2.1-.4.5-.2.8-.4 1.1-.7.3-.3.5-.6.7-1.1.2-.4.3-1 .4-2.1.1-1.2.1-1.5.1-4.7s0-3.5-.1-4.7c0-1.1-.2-1.7-.4-2.1-.2-.5-.4-.8-.7-1.1-.3-.3-.6-.5-1.1-.7-.4-.2-1-.3-2.1-.4-1.2-.1-1.5-.1-4.7-.1zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 8.1a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.3-8.4a1.2 1.2 0 1 1-2.3 0 1.2 1.2 0 0 1 2.3 0z"/></svg>',
			'linkedin'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S.02 4.88.02 3.5 1.14 1 2.5 1s2.48 1.12 2.48 2.5zM.2 8.2h4.6V23H.2V8.2zM8.3 8.2h4.4v2h.1c.6-1.1 2.1-2.3 4.4-2.3 4.7 0 5.6 3.1 5.6 7.1V23h-4.6v-6.6c0-1.6 0-3.6-2.2-3.6s-2.5 1.7-2.5 3.5V23H8.3V8.2z"/></svg>',
			'whatsapp'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2a9.9 9.9 0 0 0-8.5 14.9L2 22l5.3-1.4A9.9 9.9 0 1 0 12.04 2zm5.8 14.1c-.2.7-1.3 1.2-2.1 1.4-.6.1-1.3.2-3.7-.8-3.1-1.3-5.1-4.5-5.2-4.7-.2-.2-1.3-1.7-1.3-3.3s.8-2.3 1.1-2.6c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .6l-.4.7c-.1.2-.3.4-.1.7.2.3.7 1.2 1.6 1.9 1.1 1 2.1 1.3 2.4 1.4.3.1.5.1.7-.1l.9-1.1c.2-.2.4-.2.6-.1l2 .9c.2.1.4.2.4.4 0 .1 0 .7-.2 1.4z"/></svg>',
			'tiktok'    => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 8.2a6.8 6.8 0 0 1-4-1.3v7.2a6.1 6.1 0 1 1-6.1-6.1c.3 0 .7 0 1 .1v3a3.2 3.2 0 1 0 2.2 3V2.1h3a3.8 3.8 0 0 0 3.7 3.7V8.2z"/></svg>',
			'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>',
			'mail'      => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4.2-8 5.1L4 8.2V6l8 5.1L20 6v2.2z"/></svg>',
			'phone'     => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.2 11.4 11.4 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .6 3.6 1 1 0 0 1-.3 1l-2.2 2.2z"/></svg>',
			'chevron'   => '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5.2 7.4a1 1 0 0 1 1.4 0L10 10.8l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.8a1 1 0 0 1 0-1.4z"/></svg>',
			'github'    => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 .5C5.65.5.5 5.65.5 12a11.5 11.5 0 0 0 7.86 10.93c.58.1.79-.25.79-.55v-1.94c-3.2.7-3.88-1.54-3.88-1.54-.53-1.34-1.3-1.7-1.3-1.7-1.06-.73.08-.72.08-.72 1.17.08 1.78 1.2 1.78 1.2 1.04 1.78 2.72 1.26 3.38.96.1-.76.4-1.26.72-1.55-2.56-.29-5.26-1.28-5.26-5.7 0-1.26.45-2.28 1.2-3.08-.12-.3-.52-1.5.12-3.1 0 0 .98-.32 3.2 1.18a11.1 11.1 0 0 1 5.82 0c2.22-1.5 3.2-1.18 3.2-1.18.64 1.6.24 2.8.12 3.1.75.8 1.2 1.82 1.2 3.08 0 4.44-2.7 5.4-5.28 5.68.4.34.76 1.02.76 2.06v3.05c0 .3.2.66.8.55A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z"/></svg>',
			'lock'      => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 9h-1V7a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-3 0H10V7a2 2 0 1 1 4 0v2z"/></svg>',
		);
		echo isset( $icons[ $name ] ) ? $icons[ $name ] : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
?>
<div class="pax-af" data-pax-af>
	<div class="pax-af__shell">

		<section class="pax-af__newsletter" data-pax-af-reveal aria-labelledby="pax-af-news-title">
			<div class="pax-af__newsletter-copy">
				<p class="pax-af__eyebrow"><?php esc_html_e( 'Newsletter', 'navein' ); ?></p>
				<h2 id="pax-af-news-title" class="pax-af__news-title"><?php esc_html_e( 'Bleiben Sie auf dem Laufenden.', 'navein' ); ?></h2>
				<p class="pax-af__news-lede"><?php esc_html_e( 'Produkt-Updates, Launch-Hinweise und klare Insights, kompakt und ohne Rauschen.', 'navein' ); ?></p>
			</div>
			<div class="pax-af__newsletter-form">
				<div class="paxmc-footer-subscribe" lang="de" dir="ltr">
					<form class="paxmc-fs-form" method="post" novalidate>
						<div class="input-wrapper">
							<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
								<path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4.2-8 5.1L4 8.2V6l8 5.1L20 6v2.2z"/>
							</svg>
							<input type="email" name="email" class="input" placeholder="<?php esc_attr_e( 'Ihre E‑Mail-Adresse', 'navein' ); ?>" autocomplete="email" required>
							<button type="submit" class="Subscribe-btn"><?php esc_html_e( 'Abonnieren', 'navein' ); ?></button>
						</div>
					</form>
					<div class="paxmc-fs-msg" role="status" aria-live="polite"></div>
				</div>
			</div>
		</section>

		<nav class="pax-af__directory" data-pax-af-reveal aria-label="<?php esc_attr_e( 'Footer', 'navein' ); ?>">
			<div class="pax-af__brand-col">
				<a class="pax-af__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">PAX<span>design</span></a>
				<p class="pax-af__tagline"><?php esc_html_e( 'Digitale Systeme mit Klarheit, Präzision und Premium-Feeling, entwickelt in Wien.', 'navein' ); ?></p>
				<ul class="pax-af__contact">
					<li>
						<a href="<?php echo esc_url( 'mailto:' . $email ); ?>">
							<span class="pax-af__contact-icon"><?php navein_apple_footer_icon( 'mail' ); ?></span>
							<span><?php echo esc_html( $email ); ?></span>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( 'tel:' . $phone_tel ); ?>">
							<span class="pax-af__contact-icon"><?php navein_apple_footer_icon( 'phone' ); ?></span>
							<span><?php echo esc_html( $phone ); ?></span>
						</a>
					</li>
				</ul>
			</div>

			<div class="pax-af__col" data-pax-af-acc>
				<button type="button" class="pax-af__col-toggle" aria-expanded="false">
					<span><?php esc_html_e( 'Leistungen', 'navein' ); ?></span>
					<span class="pax-af__col-chevron"><?php navein_apple_footer_icon( 'chevron' ); ?></span>
				</button>
				<p class="pax-af__col-title"><?php esc_html_e( 'Leistungen', 'navein' ); ?></p>
				<ul class="pax-af__links">
					<?php foreach ( $services as $item ) : ?>
						<li><a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="pax-af__col" data-pax-af-acc>
				<button type="button" class="pax-af__col-toggle" aria-expanded="false">
					<span><?php esc_html_e( 'Unternehmen', 'navein' ); ?></span>
					<span class="pax-af__col-chevron"><?php navein_apple_footer_icon( 'chevron' ); ?></span>
				</button>
				<p class="pax-af__col-title"><?php esc_html_e( 'Unternehmen', 'navein' ); ?></p>
				<ul class="pax-af__links">
					<?php foreach ( $company as $item ) : ?>
						<li><a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="pax-af__col pax-af__col--connect" data-pax-af-acc>
				<button type="button" class="pax-af__col-toggle" aria-expanded="false">
					<span><?php esc_html_e( 'Connect', 'navein' ); ?></span>
					<span class="pax-af__col-chevron"><?php navein_apple_footer_icon( 'chevron' ); ?></span>
				</button>
				<p class="pax-af__col-title"><?php esc_html_e( 'Connect', 'navein' ); ?></p>
				<ul class="pax-af__social">
					<?php foreach ( $social as $item ) : ?>
						<li>
							<a href="<?php echo esc_url( $item['href'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $item['label'] ); ?>">
								<span class="pax-af__social-icon"><?php navein_apple_footer_icon( $item['icon'] ); ?></span>
								<span><?php echo esc_html( $item['label'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
					<li>
						<button type="button" class="pax-af__github-btn" data-pax-github-open aria-haspopup="dialog" aria-controls="pax-af-github-modal">
							<span class="pax-af__social-icon"><?php navein_apple_footer_icon( 'github' ); ?></span>
							<span><?php esc_html_e( 'GitHub', 'navein' ); ?></span>
							<span class="pax-af__github-lock" aria-hidden="true"><?php navein_apple_footer_icon( 'lock' ); ?></span>
						</button>
					</li>
				</ul>
			</div>
		</nav>

		<section class="pax-af__trust" data-pax-af-reveal aria-label="<?php esc_attr_e( 'Zertifikate & Sicherheit', 'navein' ); ?>">
			<p class="pax-af__trust-label"><?php esc_html_e( 'Sicherheit & Qualität', 'navein' ); ?></p>
			<ul class="pax-af__trust-list">
				<?php foreach ( $certs as $cert ) : ?>
					<li>
						<a href="<?php echo esc_url( $cert['href'] ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $cert['img'] ); ?>" alt="<?php echo esc_attr( $cert['alt'] ); ?>" loading="lazy" decoding="async" width="120" height="40">
							<span><?php echo esc_html( $cert['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<div class="pax-af__legal" data-pax-af-reveal>
			<ul class="pax-af__legal-links">
				<?php foreach ( $legal as $item ) : ?>
					<li><a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<p class="pax-af__copy">
				&copy; <?php echo esc_html( $year ); ?> PAXdesign.
				<?php esc_html_e( 'Alle Rechte vorbehalten.', 'navein' ); ?>
				<span class="pax-af__locale">Austria</span>
			</p>
		</div>
	</div>

	<div
		class="pax-af-github-modal"
		id="pax-af-github-modal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="pax-af-github-modal-title"
		aria-hidden="true"
		hidden
	>
		<div class="pax-af-github-modal__backdrop" data-pax-github-close tabindex="-1"></div>
		<div class="pax-af-github-modal__panel">
			<button type="button" class="pax-af-github-modal__close" aria-label="<?php esc_attr_e( 'Schließen', 'navein' ); ?>" data-pax-github-close>&times;</button>
			<div class="pax-af-github-modal__icon" aria-hidden="true">
				<?php navein_apple_footer_icon( 'github' ); ?>
				<span><?php navein_apple_footer_icon( 'lock' ); ?></span>
			</div>
			<p class="pax-af-github-modal__badge"><?php esc_html_e( 'Private Repository', 'navein' ); ?></p>
			<h3 id="pax-af-github-modal-title" class="pax-af-github-modal__title"><?php esc_html_e( 'Powered by GitHub. Not Open Source', 'navein' ); ?></h3>
			<p class="pax-af-github-modal__text"><?php esc_html_e( 'PAXdesign nutzt GitHub für professionelle Entwicklung und Deployments. Unser Code ist proprietär, sicher verwaltet und nicht öffentlich verfügbar.', 'navein' ); ?></p>
			<p class="pax-af-github-modal__text"><?php esc_html_e( 'Dieses Projekt ist nicht Open Source. Zugang nur für autorisierte Teammitglieder.', 'navein' ); ?></p>
			<button type="button" class="pax-af-github-modal__btn" data-pax-github-close><?php esc_html_e( 'Verstanden', 'navein' ); ?></button>
		</div>
	</div>
</div>
