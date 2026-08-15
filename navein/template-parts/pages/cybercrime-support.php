<?php
/**
 * Cybercrime reporting portal — structured digital service intake.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'pax_ccs_bootstrap_locale_helpers' ) ) {
	pax_ccs_bootstrap_locale_helpers();
}

$copy = function_exists( 'pax_ccs_portal_copy' ) ? pax_ccs_portal_copy() : include __DIR__ . '/cybercrime-support-data.php';
$ccs_countries = function_exists( 'pax_ccs_countries' ) ? pax_ccs_countries() : array();
$ccs_phone_popular = array( 'AT', 'DE', 'CH', 'US', 'GB', 'AE', 'SA', 'FR', 'IT', 'ES', 'NL', 'BE', 'PL', 'TR', 'EG', 'JO', 'LB', 'QA', 'KW', 'BH', 'OM' );
$ccs_countries_by_code = array();
foreach ( $ccs_countries as $ccs_country_row ) {
	if ( ! empty( $ccs_country_row['code'] ) ) {
		$ccs_countries_by_code[ strtoupper( (string) $ccs_country_row['code'] ) ] = $ccs_country_row;
	}
}

if ( ! function_exists( 'pax_ccs_text' ) ) {
	function pax_ccs_text( $node, $lang ) {
		if ( is_array( $node ) && isset( $node[ $lang ] ) ) {
			return $node[ $lang ];
		}
		if ( is_array( $node ) && isset( $node['en'] ) ) {
			return $node['en'];
		}
		return is_string( $node ) ? $node : '';
	}
}

if ( ! function_exists( 'pax_ccs_bilingual' ) ) {
	function pax_ccs_bilingual( $node ) {
		foreach ( array( 'ar', 'de', 'en' ) as $lang ) {
			$hidden = $lang !== 'ar' ? ' hidden' : '';
			echo '<span class="pax-ccs-t" data-lang="' . esc_attr( $lang ) . '"' . $hidden . '>' . esc_html( pax_ccs_text( $node, $lang ) ) . '</span>';
		}
	}
}
?>
<article <?php post_class( 'pax-ccs-portal' ); ?> data-ccs-lang="ar" lang="ar" dir="rtl" data-ccs-phase="welcome">

	<a class="pax-ccs-portal__skip" href="#pax-ccs-main">
		<?php pax_ccs_bilingual( $copy['portal']['skip'] ); ?>
	</a>

	<div class="pax-ccs-portal__servicebar" role="banner">
		<div class="pax-ccs-portal__wrap pax-ccs-portal__servicebar-inner">
			<p class="pax-ccs-portal__service-name"><?php pax_ccs_bilingual( $copy['operator']['service'] ); ?></p>
			<p class="pax-ccs-portal__service-disclaimer"><?php pax_ccs_bilingual( $copy['operator']['disclaimer'] ); ?></p>
		</div>
	</div>

	<?php
	$ccs_platforms = isset( $copy['platforms_coverage']['platforms'] ) && is_array( $copy['platforms_coverage']['platforms'] )
		? $copy['platforms_coverage']['platforms']
		: array();
	?>
	<section class="pax-ccs-portal__coverage" aria-labelledby="pax-ccs-coverage-label">
		<div class="pax-ccs-portal__wrap pax-ccs-portal__coverage-head">
			<p id="pax-ccs-coverage-label" class="pax-ccs-portal__coverage-label"><?php pax_ccs_bilingual( $copy['platforms_coverage']['label'] ); ?></p>
		</div>
		<?php if ( ! empty( $ccs_platforms ) ) : ?>
			<?php $ccs_platforms_loop = array_merge( $ccs_platforms, $ccs_platforms ); ?>
			<div class="pax-ccs-portal__coverage-marquee">
				<div class="pax-ccs-portal__coverage-track">
					<?php for ( $dup = 0; $dup < 2; $dup++ ) : ?>
						<ul class="pax-ccs-portal__coverage-group"<?php echo 1 === $dup ? ' aria-hidden="true"' : ''; ?>>
							<?php foreach ( $ccs_platforms_loop as $platform ) : ?>
								<li class="pax-ccs-portal__coverage-item"><?php echo esc_html( (string) $platform ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endfor; ?>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<div class="pax-ccs-portal__langbar">
		<div class="pax-ccs-portal__wrap pax-ccs-portal__langbar-inner">
			<div class="pax-ccs-portal__lang-toggle" role="group" aria-label="Language">
				<button type="button" class="pax-ccs-portal__lang-btn is-active" data-ccs-switch="ar" aria-pressed="true">العربية</button>
				<button type="button" class="pax-ccs-portal__lang-btn" data-ccs-switch="de" aria-pressed="false">Deutsch</button>
				<button type="button" class="pax-ccs-portal__lang-btn" data-ccs-switch="en" aria-pressed="false">English</button>
			</div>
		</div>
	</div>

	<div id="pax-ccs-main">
		<header class="pax-ccs-portal__header">
			<div class="pax-ccs-portal__wrap">
				<p class="pax-ccs-portal__eyebrow"><?php pax_ccs_bilingual( $copy['portal']['eyebrow'] ); ?></p>
				<h1 class="pax-ccs-portal__title"><?php pax_ccs_bilingual( $copy['portal']['title'] ); ?></h1>
				<p class="pax-ccs-portal__subtitle"><?php pax_ccs_bilingual( $copy['portal']['subtitle'] ); ?></p>
				<ul class="pax-ccs-portal__status" aria-label="Service status">
					<?php foreach ( $copy['portal']['status'] as $chip ) : ?>
						<li class="pax-ccs-portal__status-item"><?php pax_ccs_bilingual( $chip['label'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</header>

		<section id="pax-ccs-welcome" class="pax-ccs-portal__welcome" aria-labelledby="pax-ccs-welcome-title">
			<div class="pax-ccs-portal__wrap pax-ccs-portal__wrap--wide">
				<div class="pax-ccs-portal__panel pax-ccs-portal__panel--welcome">
					<h2 id="pax-ccs-welcome-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['welcome']['heading'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro pax-ccs-portal__section-intro--lead"><?php pax_ccs_bilingual( $copy['welcome']['intro'] ); ?></p>

					<ol class="pax-ccs-portal__info-blocks">
						<?php foreach ( $copy['welcome']['blocks'] as $i => $block ) : ?>
							<li class="pax-ccs-portal__info-block">
								<span class="pax-ccs-portal__info-num" aria-hidden="true"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
								<div class="pax-ccs-portal__info-body">
									<h3 class="pax-ccs-portal__info-title"><?php pax_ccs_bilingual( $block['title'] ); ?></h3>
									<p class="pax-ccs-portal__info-text"><?php pax_ccs_bilingual( $block['body'] ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>

					<div class="pax-ccs-portal__trust">
						<h3 class="pax-ccs-portal__trust-heading"><?php pax_ccs_bilingual( $copy['welcome']['trust_heading'] ); ?></h3>
						<ul class="pax-ccs-portal__trust-grid">
							<?php foreach ( $copy['welcome']['trust'] as $pillar ) : ?>
								<li class="pax-ccs-portal__trust-item">
									<p class="pax-ccs-portal__trust-title"><?php pax_ccs_bilingual( $pillar['title'] ); ?></p>
									<p class="pax-ccs-portal__trust-text"><?php pax_ccs_bilingual( $pillar['text'] ); ?></p>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<p class="pax-ccs-portal__time-estimate"><?php pax_ccs_bilingual( $copy['welcome']['time'] ); ?></p>

					<div class="pax-ccs-portal__actions pax-ccs-portal__actions--welcome">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" id="pax-ccs-start" data-ccs-start>
							<span class="pax-ccs-t" data-lang="ar" data-ccs-start-label="start"><?php echo esc_html( pax_ccs_text( $copy['welcome']['start'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" data-ccs-start-label="start" hidden><?php echo esc_html( pax_ccs_text( $copy['welcome']['start'], 'de' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="en" data-ccs-start-label="start" hidden><?php echo esc_html( pax_ccs_text( $copy['welcome']['start'], 'en' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="ar" data-ccs-start-label="view" hidden><?php echo esc_html( pax_ccs_text( $copy['welcome']['view_report'], 'ar' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="de" data-ccs-start-label="view" hidden><?php echo esc_html( pax_ccs_text( $copy['welcome']['view_report'], 'de' ) ); ?></span>
							<span class="pax-ccs-t" data-lang="en" data-ccs-start-label="view" hidden><?php echo esc_html( pax_ccs_text( $copy['welcome']['view_report'], 'en' ) ); ?></span>
							<span id="pax-ccs-start-unread" class="pax-ccs-portal__unread-badge pax-ccs-portal__unread-badge--btn" hidden aria-hidden="true"></span>
						</button>
					</div>

					<div id="pax-ccs-report-history" class="pax-ccs-portal__history" hidden>
						<h3 class="pax-ccs-portal__history-title"><?php pax_ccs_bilingual( $copy['ticket_history']['heading'] ); ?></h3>
						<p class="pax-ccs-portal__history-intro"><?php pax_ccs_bilingual( $copy['ticket_history']['intro'] ); ?></p>
						<ul id="pax-ccs-history-list" class="pax-ccs-portal__history-list" aria-live="polite"></ul>
						<p class="pax-ccs-portal__history-hint"><?php pax_ccs_bilingual( $copy['ticket_history']['new_report_hint'] ); ?></p>
					</div>
				</div>
			</div>
		</section>

		<section id="pax-ccs-login-gate" class="pax-ccs-portal__login-gate" hidden aria-labelledby="pax-ccs-login-gate-title">
			<div class="pax-ccs-portal__wrap pax-ccs-portal__wrap--wide">
				<div class="pax-ccs-portal__panel pax-ccs-portal__panel--login-gate">
					<div class="pax-ccs-portal__login-gate-icon" aria-hidden="true"></div>
					<h2 id="pax-ccs-login-gate-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['login_gate']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro pax-ccs-portal__section-intro--lead"><?php pax_ccs_bilingual( $copy['login_gate']['message'] ); ?></p>
					<div class="pax-ccs-portal__actions pax-ccs-portal__actions--login-gate">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" id="pax-ccs-login-back" data-ccs-login-back>
							<?php pax_ccs_bilingual( $copy['login_gate']['back'] ); ?>
						</button>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" id="pax-ccs-login-continue">
							<?php pax_ccs_bilingual( $copy['login_gate']['button'] ); ?>
						</button>
					</div>
				</div>
			</div>
		</section>

		<section id="pax-ccs-active-report" class="pax-ccs-portal__active-report" hidden aria-labelledby="pax-ccs-active-report-title">
			<div class="pax-ccs-portal__wrap pax-ccs-portal__wrap--wide">
				<div class="pax-ccs-portal__panel pax-ccs-portal__panel--active-report">
					<div id="pax-ccs-active-status-badge" class="pax-ccs-portal__status-hero" role="status" aria-live="polite" hidden>
						<span class="pax-ccs-portal__status-hero-icon" id="pax-ccs-active-status-icon" aria-hidden="true"></span>
						<span class="pax-ccs-portal__status-hero-label" id="pax-ccs-active-status-label"></span>
					</div>
					<div id="pax-ccs-decision-card" class="pax-ccs-portal__decision" hidden>
						<div class="pax-ccs-portal__decision-status">
							<span class="pax-ccs-portal__decision-icon" id="pax-ccs-decision-icon" aria-hidden="true"></span>
							<strong class="pax-ccs-portal__decision-label" id="pax-ccs-decision-label"></strong>
						</div>
						<div class="pax-ccs-portal__decision-reason-wrap" id="pax-ccs-decision-reason-wrap" hidden>
							<h3 class="pax-ccs-portal__decision-heading" id="pax-ccs-decision-reason-heading"></h3>
							<p class="pax-ccs-portal__decision-reason" id="pax-ccs-decision-reason"></p>
						</div>
						<p class="pax-ccs-portal__decision-explanation" id="pax-ccs-decision-explanation" hidden></p>
						<div class="pax-ccs-portal__decision-next-wrap" id="pax-ccs-decision-next-wrap" hidden>
							<h3 class="pax-ccs-portal__decision-heading" id="pax-ccs-decision-next-heading"></h3>
							<p class="pax-ccs-portal__decision-next" id="pax-ccs-decision-next"></p>
						</div>
					</div>

					<div class="pax-ccs-portal__active-report-head">
						<div class="pax-ccs-portal__active-report-head-main">
							<h2 id="pax-ccs-active-report-title" class="pax-ccs-portal__section-title pax-ccs-portal__section-title--compact">
								<?php pax_ccs_bilingual( $copy['active_report']['title'] ); ?>
								<span id="pax-ccs-unread-badge" class="pax-ccs-portal__unread-badge" hidden aria-live="polite"></span>
							</h2>
							<p class="pax-ccs-portal__section-intro pax-ccs-portal__section-intro--compact"><?php pax_ccs_bilingual( $copy['active_report']['intro'] ); ?></p>
						</div>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost pax-ccs-portal__btn--compact" id="pax-ccs-back-history" hidden>
							<?php pax_ccs_bilingual( $copy['active_report']['back_history'] ); ?>
						</button>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost pax-ccs-portal__btn--compact" id="pax-ccs-refresh-report">
							<?php pax_ccs_bilingual( $copy['active_report']['refresh'] ); ?>
						</button>
					</div>

					<dl class="pax-ccs-portal__active-meta pax-ccs-portal__active-meta--compact">
						<div class="pax-ccs-portal__active-meta-row">
							<dt><?php pax_ccs_bilingual( $copy['active_report']['reference'] ); ?></dt>
							<dd><code id="pax-ccs-active-ref"></code></dd>
						</div>
						<div class="pax-ccs-portal__active-meta-row">
							<dt><?php pax_ccs_bilingual( $copy['active_report']['category'] ); ?></dt>
							<dd id="pax-ccs-active-category"></dd>
						</div>
						<div class="pax-ccs-portal__active-meta-row">
							<dt><?php pax_ccs_bilingual( $copy['active_report']['submitted'] ); ?></dt>
							<dd id="pax-ccs-active-submitted"></dd>
						</div>
					</dl>

					<div id="pax-ccs-case-dossier" class="pax-ccs-portal__dossier" hidden>
						<section class="pax-ccs-portal__dossier-block" id="pax-ccs-next-action-block">
							<h3 class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--compact"><?php pax_ccs_bilingual( $copy['active_report']['next_heading'] ); ?></h3>
							<p id="pax-ccs-next-action" class="pax-ccs-portal__dossier-text"></p>
							<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost pax-ccs-portal__btn--compact" id="pax-ccs-continue-form" hidden>
								<?php pax_ccs_bilingual( $copy['active_report']['continue_form'] ); ?>
							</button>
						</section>
						<section class="pax-ccs-portal__dossier-block">
							<h3 class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--compact"><?php pax_ccs_bilingual( $copy['active_report']['original_heading'] ); ?></h3>
							<dl id="pax-ccs-original-request" class="pax-ccs-portal__active-meta pax-ccs-portal__active-meta--compact"></dl>
						</section>
						<section class="pax-ccs-portal__dossier-block">
							<h3 class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--compact"><?php pax_ccs_bilingual( $copy['active_report']['checks_heading'] ); ?></h3>
							<p class="pax-ccs-portal__hint"><?php pax_ccs_bilingual( $copy['active_report']['checks_disclaimer'] ); ?></p>
							<ul id="pax-ccs-checks-list" class="pax-ccs-portal__checks"></ul>
						</section>
					</div>

					<details class="pax-ccs-portal__attachments-fold" id="pax-ccs-attachments-fold" hidden>
						<summary class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--fold"><?php pax_ccs_bilingual( $copy['active_report']['attachments'] ); ?></summary>
						<ul id="pax-ccs-active-attachments" class="pax-ccs-portal__attachment-list pax-ccs-portal__attachment-list--compact"></ul>
					</details>

					<div class="pax-ccs-portal__official-block">
						<h3 class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--compact"><?php pax_ccs_bilingual( $copy['active_report']['official_heading'] ); ?></h3>
						<p class="pax-ccs-portal__official-note"><?php pax_ccs_bilingual( $copy['active_report']['official_note'] ); ?></p>
						<div id="pax-ccs-active-timeline" class="pax-ccs-portal__accordion" aria-live="polite"></div>
					</div>

					<div id="pax-ccs-active-reply-wrap" class="pax-ccs-portal__reply-wrap pax-ccs-portal__reply-wrap--compact">
						<label for="pax-ccs-active-reply" class="pax-ccs-portal__reply-label"><?php pax_ccs_bilingual( $copy['active_report']['reply_label'] ); ?></label>
						<textarea id="pax-ccs-active-reply" class="pax-ccs-portal__reply-input" rows="3"
							placeholder="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'ar' ) ); ?>"
							data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'ar' ) ); ?>"
							data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'de' ) ); ?>"
							data-placeholder-en="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'en' ) ); ?>"></textarea>
						<div id="pax-ccs-resubmit" class="pax-ccs-portal__resubmit">
							<p class="pax-ccs-portal__hint"><?php pax_ccs_bilingual( $copy['active_report']['resubmit_hint'] ); ?></p>
							<label class="pax-ccs-portal__resubmit-label" for="pax-ccs-resubmit-identity"><?php pax_ccs_bilingual( $copy['active_report']['resubmit_identity'] ); ?></label>
							<input type="file" id="pax-ccs-resubmit-identity" name="identity_document" accept=".pdf,.jpg,.jpeg,.png,.heic,.heif">
							<label class="pax-ccs-portal__resubmit-label" for="pax-ccs-resubmit-evidence"><?php pax_ccs_bilingual( $copy['active_report']['resubmit_evidence'] ); ?></label>
							<input type="file" id="pax-ccs-resubmit-evidence" name="evidence_other[]" accept="image/*,.pdf,.txt,.csv,.zip,.doc,.docx" multiple>
						</div>
						<p id="pax-ccs-active-reply-error" class="pax-ccs-portal__error" hidden role="alert"></p>
						<div class="pax-ccs-portal__actions pax-ccs-portal__actions--compact">
							<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary pax-ccs-portal__btn--compact" id="pax-ccs-active-reply-submit">
								<?php pax_ccs_bilingual( $copy['active_report']['reply_submit'] ); ?>
							</button>
							<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost pax-ccs-portal__btn--compact" id="pax-ccs-resubmit-submit">
								<?php pax_ccs_bilingual( $copy['active_report']['resubmit_submit'] ); ?>
							</button>
						</div>
					</div>

					<div class="pax-ccs-portal__ai-block">
						<h3 class="pax-ccs-portal__subsection-title pax-ccs-portal__subsection-title--compact"><?php pax_ccs_bilingual( $copy['active_report']['ai_heading'] ); ?></h3>
						<p class="pax-ccs-portal__ai-note"><?php pax_ccs_bilingual( $copy['active_report']['ai_note'] ); ?></p>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost pax-ccs-portal__btn--compact" id="pax-ccs-active-chat">
							<?php pax_ccs_bilingual( $copy['active_report']['chat'] ); ?>
						</button>
					</div>

					<p id="pax-ccs-active-closed-note" class="pax-ccs-portal__closed-note" hidden><?php pax_ccs_bilingual( $copy['active_report']['closed_note'] ); ?></p>
				</div>
			</div>
		</section>

		<div class="pax-ccs-portal__workflow" id="pax-ccs-workflow" hidden>
			<div class="pax-ccs-portal__wrap pax-ccs-portal__wrap--wide">
				<p class="pax-ccs-portal__workflow-label"><?php pax_ccs_bilingual( $copy['workflow']['label'] ); ?></p>
				<ol class="pax-ccs-portal__progress" aria-label="Progress">
					<?php foreach ( $copy['steps'] as $i => $step ) : ?>
						<li class="pax-ccs-portal__progress-item<?php echo $i === 0 ? ' is-active' : ''; ?>" data-progress-step="<?php echo esc_attr( (string) ( $i + 1 ) ); ?>">
							<span class="pax-ccs-portal__progress-num"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
							<span class="pax-ccs-portal__progress-label"><?php pax_ccs_bilingual( $step['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</div>

		<form id="pax-ccs-intake-form" class="pax-ccs-portal__form" novalidate enctype="multipart/form-data" hidden>
			<input type="hidden" name="locale" id="pax-ccs-locale" value="ar">
			<input type="text" name="website_trap" value="" tabindex="-1" autocomplete="off" class="pax-ccs-portal__trap" aria-hidden="true">

			<!-- Step 1: Identity -->
			<section class="pax-ccs-portal__step is-active" data-step="1" aria-labelledby="pax-ccs-step-1-title">
				<div class="pax-ccs-portal__wrap pax-ccs-portal__panel">
					<p class="pax-ccs-portal__guide" data-ccs-guide="identity">
						<span class="pax-ccs-portal__guide-kicker"><?php pax_ccs_bilingual( $copy['guided']['ask'] ); ?></span>
						<span class="pax-ccs-portal__guide-q"><?php pax_ccs_bilingual( $copy['guided']['identity_q'] ); ?></span>
					</p>
					<p class="pax-ccs-portal__missing" id="pax-ccs-missing-1" hidden></p>
					<h2 id="pax-ccs-step-1-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['identity']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['identity']['intro'] ); ?></p>

					<div class="pax-ccs-portal__grid">
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-full-name"><?php pax_ccs_bilingual( $copy['fields']['full_name']['label'] ); ?></label>
							<input type="text" id="pax-ccs-full-name" name="full_name" required autocomplete="name" placeholder="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'ar' ) ); ?>" data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'ar' ) ); ?>" data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'de' ) ); ?>" data-placeholder-en="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'en' ) ); ?>">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-email"><?php pax_ccs_bilingual( $copy['fields']['email']['label'] ); ?></label>
							<input type="email" id="pax-ccs-email" name="email" required autocomplete="email" inputmode="email">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-phone-local"><?php pax_ccs_bilingual( $copy['fields']['phone']['label'] ); ?></label>
							<div class="pax-ccs-portal__phone-row">
								<div class="pax-ccs-portal__phone-code">
									<label class="pax-ccs-portal__sr-only" for="pax-ccs-phone-code"><?php pax_ccs_bilingual( $copy['fields']['phone_code']['label'] ); ?></label>
									<select id="pax-ccs-phone-code" name="phone_country_code" required autocomplete="tel-country-code">
										<?php if ( ! empty( $ccs_phone_popular ) ) : ?>
											<optgroup label="<?php echo esc_attr( pax_ccs_text( array( 'ar' => 'شائعة', 'de' => 'Häufig', 'en' => 'Popular' ), 'ar' ) ); ?>">
												<?php foreach ( $ccs_phone_popular as $popular_code ) :
													$row = $ccs_countries_by_code[ strtoupper( $popular_code ) ] ?? null;
													if ( ! $row ) {
														continue;
													}
													$label = ( $row['flag'] ?? '' ) . ' ' . ( $row['dial'] ?? '' ) . ' ' . pax_ccs_text( $row['name'] ?? array(), 'ar' );
													?>
													<option value="<?php echo esc_attr( $row['dial'] ?? '' ); ?>" data-country-code="<?php echo esc_attr( $row['code'] ?? '' ); ?>"<?php selected( strtoupper( $popular_code ), 'AT' ); ?>><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										<?php endif; ?>
										<optgroup label="<?php echo esc_attr( pax_ccs_text( array( 'ar' => 'جميع الدول', 'de' => 'Alle Länder', 'en' => 'All countries' ), 'ar' ) ); ?>">
											<?php foreach ( $ccs_countries as $row ) :
												if ( empty( $row['code'] ) || empty( $row['dial'] ) ) {
													continue;
												}
												$label = ( $row['flag'] ?? '' ) . ' ' . ( $row['dial'] ?? '' ) . ' ' . pax_ccs_text( $row['name'] ?? array(), 'ar' );
												?>
												<option value="<?php echo esc_attr( $row['dial'] ); ?>" data-country-code="<?php echo esc_attr( $row['code'] ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									</select>
								</div>
								<input type="tel" id="pax-ccs-phone-local" name="phone_local" required autocomplete="tel-national" inputmode="tel" placeholder="<?php echo esc_attr( pax_ccs_text( $copy['fields']['phone']['placeholder'], 'ar' ) ); ?>" data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['fields']['phone']['placeholder'], 'ar' ) ); ?>" data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['fields']['phone']['placeholder'], 'de' ) ); ?>" data-placeholder-en="<?php echo esc_attr( pax_ccs_text( $copy['fields']['phone']['placeholder'], 'en' ) ); ?>">
							</div>
							<input type="hidden" id="pax-ccs-phone" name="phone" value="">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-country-search"><?php pax_ccs_bilingual( $copy['fields']['country']['label'] ); ?></label>
							<div class="pax-ccs-portal__country-picker" id="pax-ccs-country-picker">
								<input type="search" id="pax-ccs-country-search" class="pax-ccs-portal__country-search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pax-ccs-country-list" aria-haspopup="listbox" placeholder="<?php echo esc_attr( pax_ccs_text( $copy['fields']['country']['placeholder'], 'ar' ) ); ?>" data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['fields']['country']['placeholder'], 'ar' ) ); ?>" data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['fields']['country']['placeholder'], 'de' ) ); ?>" data-placeholder-en="<?php echo esc_attr( pax_ccs_text( $copy['fields']['country']['placeholder'], 'en' ) ); ?>">
								<input type="hidden" id="pax-ccs-country" name="country" required value="">
								<ul id="pax-ccs-country-list" class="pax-ccs-portal__country-list" role="listbox" hidden></ul>
							</div>
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-identity-doc">
								<?php pax_ccs_bilingual( $copy['fields']['identity_document']['label'] ); ?>
								<span class="pax-ccs-portal__required" aria-hidden="true"><?php pax_ccs_bilingual( $copy['fields']['required']['label'] ); ?></span>
							</label>
							<p class="pax-ccs-portal__hint"><?php pax_ccs_bilingual( $copy['fields']['identity_document']['hint'] ); ?></p>
							<p class="pax-ccs-portal__hint pax-ccs-portal__hint--why"><?php pax_ccs_bilingual( $copy['guided']['id_why'] ); ?></p>
							<input type="file" id="pax-ccs-identity-doc" name="identity_document" required accept=".pdf,.jpg,.jpeg,.png,.heic,.heif">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label class="pax-ccs-portal__check">
								<input type="checkbox" name="identity_accuracy" id="pax-ccs-identity-accuracy" required value="1">
								<span><?php pax_ccs_bilingual( $copy['fields']['identity_accuracy']['label'] ); ?></span>
							</label>
						</div>
					</div>

					<div class="pax-ccs-portal__actions">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" data-ccs-back-welcome><?php pax_ccs_bilingual( $copy['actions']['back_welcome'] ); ?></button>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" data-ccs-next="2"><?php pax_ccs_bilingual( $copy['actions']['continue'] ); ?></button>
					</div>
				</div>
			</section>

			<!-- Step 2: Incident -->
			<section class="pax-ccs-portal__step" data-step="2" hidden aria-labelledby="pax-ccs-step-2-title">
				<div class="pax-ccs-portal__wrap pax-ccs-portal__panel">
					<p class="pax-ccs-portal__guide" data-ccs-guide="incident">
						<span class="pax-ccs-portal__guide-kicker"><?php pax_ccs_bilingual( $copy['guided']['ask'] ); ?></span>
						<span class="pax-ccs-portal__guide-q"><?php pax_ccs_bilingual( $copy['guided']['incident_q'] ); ?></span>
					</p>
					<p class="pax-ccs-portal__missing" id="pax-ccs-missing-2" hidden></p>
					<h2 id="pax-ccs-step-2-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['incident']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['incident']['intro'] ); ?></p>

					<div class="pax-ccs-portal__grid">
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-category"><?php pax_ccs_bilingual( $copy['fields']['category']['label'] ); ?></label>
							<p class="pax-ccs-portal__hint"><?php pax_ccs_bilingual( $copy['guided']['category_hint'] ); ?></p>
							<div class="pax-ccs-portal__cards" id="pax-ccs-category-cards" role="list">
								<?php foreach ( $copy['categories'] as $key => $labels ) : ?>
									<button type="button" class="pax-ccs-portal__card" role="listitem" data-ccs-category="<?php echo esc_attr( $key ); ?>">
										<?php pax_ccs_bilingual( $labels ); ?>
									</button>
								<?php endforeach; ?>
							</div>
							<select id="pax-ccs-category" name="category" required class="pax-ccs-portal__sr-select">
								<option value="">—</option>
								<?php foreach ( $copy['categories'] as $key => $labels ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" data-label-ar="<?php echo esc_attr( pax_ccs_text( $labels, 'ar' ) ); ?>" data-label-de="<?php echo esc_attr( pax_ccs_text( $labels, 'de' ) ); ?>" data-label-en="<?php echo esc_attr( pax_ccs_text( $labels, 'en' ) ); ?>"><?php echo esc_html( pax_ccs_text( $labels, 'ar' ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-incident-date"><?php pax_ccs_bilingual( $copy['fields']['incident_date']['label'] ); ?></label>
							<input type="date" id="pax-ccs-incident-date" name="incident_date" required>
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-incident-time"><?php pax_ccs_bilingual( $copy['fields']['incident_time']['label'] ); ?></label>
							<input type="time" id="pax-ccs-incident-time" name="incident_time">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-platforms"><?php pax_ccs_bilingual( $copy['fields']['platforms']['label'] ); ?></label>
							<div class="pax-ccs-portal__chips" id="pax-ccs-platform-chips">
								<?php
								$ccs_chip_platforms = array( 'Google', 'Gmail', 'Facebook', 'Instagram', 'WhatsApp', 'Apple', 'iCloud', 'Microsoft', 'PayPal', 'Binance' );
								foreach ( $ccs_chip_platforms as $chip ) :
									?>
									<button type="button" class="pax-ccs-portal__chip" data-ccs-platform="<?php echo esc_attr( $chip ); ?>"><?php echo esc_html( $chip ); ?></button>
								<?php endforeach; ?>
							</div>
							<input type="text" id="pax-ccs-platforms" name="platforms" required placeholder="<?php echo esc_attr( pax_ccs_text( $copy['fields']['platforms']['placeholder'], 'ar' ) ); ?>" data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['fields']['platforms']['placeholder'], 'ar' ) ); ?>" data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['fields']['platforms']['placeholder'], 'de' ) ); ?>" data-placeholder-en="<?php echo esc_attr( pax_ccs_text( $copy['fields']['platforms']['placeholder'], 'en' ) ); ?>">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-description"><?php pax_ccs_bilingual( $copy['fields']['description']['label'] ); ?></label>
							<textarea id="pax-ccs-description" name="description" rows="6" required minlength="20"></textarea>
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-financial-loss"><?php pax_ccs_bilingual( $copy['fields']['financial_loss']['label'] ); ?></label>
							<input type="text" id="pax-ccs-financial-loss" name="financial_loss" inputmode="decimal" placeholder="0.00">
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-currency"><?php pax_ccs_bilingual( $copy['fields']['financial_currency']['label'] ); ?></label>
							<select id="pax-ccs-currency" name="financial_currency">
								<option value="EUR">EUR</option>
								<option value="USD">USD</option>
								<option value="GBP">GBP</option>
								<option value="CHF">CHF</option>
							</select>
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-urgency"><?php pax_ccs_bilingual( $copy['fields']['urgency']['label'] ); ?></label>
							<select id="pax-ccs-urgency" name="urgency" required>
								<?php foreach ( $copy['urgency'] as $key => $labels ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" data-label-ar="<?php echo esc_attr( pax_ccs_text( $labels, 'ar' ) ); ?>" data-label-de="<?php echo esc_attr( pax_ccs_text( $labels, 'de' ) ); ?>" data-label-en="<?php echo esc_attr( pax_ccs_text( $labels, 'en' ) ); ?>"><?php echo esc_html( pax_ccs_text( $labels, 'ar' ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="pax-ccs-portal__actions">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" data-ccs-back="1"><?php pax_ccs_bilingual( $copy['actions']['back'] ); ?></button>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" data-ccs-next="3"><?php pax_ccs_bilingual( $copy['actions']['continue'] ); ?></button>
					</div>
				</div>
			</section>

			<!-- Step 3: Evidence -->
			<section class="pax-ccs-portal__step" data-step="3" hidden aria-labelledby="pax-ccs-step-3-title">
				<div class="pax-ccs-portal__wrap pax-ccs-portal__panel">
					<p class="pax-ccs-portal__guide" data-ccs-guide="evidence">
						<span class="pax-ccs-portal__guide-kicker"><?php pax_ccs_bilingual( $copy['guided']['ask'] ); ?></span>
						<span class="pax-ccs-portal__guide-q"><?php pax_ccs_bilingual( $copy['guided']['evidence_q'] ); ?></span>
					</p>
					<p class="pax-ccs-portal__missing" id="pax-ccs-missing-3" hidden></p>
					<h2 id="pax-ccs-step-3-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['evidence']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['evidence']['intro'] ); ?></p>
					<p id="pax-ccs-evidence-coach" class="pax-ccs-portal__coach" hidden></p>

					<div class="pax-ccs-portal__uploads">
						<div class="pax-ccs-portal__upload">
							<label for="pax-ccs-screenshots"><?php pax_ccs_bilingual( $copy['fields']['evidence_screenshots']['label'] ); ?></label>
							<input type="file" id="pax-ccs-screenshots" name="evidence_screenshots[]" accept="image/*,.pdf" multiple>
						</div>
						<div class="pax-ccs-portal__upload">
							<label for="pax-ccs-documents"><?php pax_ccs_bilingual( $copy['fields']['evidence_documents']['label'] ); ?></label>
							<input type="file" id="pax-ccs-documents" name="evidence_documents[]" accept=".pdf,.doc,.docx,.txt,.csv,.zip" multiple>
						</div>
						<div class="pax-ccs-portal__upload">
							<label for="pax-ccs-chats"><?php pax_ccs_bilingual( $copy['fields']['evidence_chats']['label'] ); ?></label>
							<input type="file" id="pax-ccs-chats" name="evidence_chats[]" accept=".txt,.csv,.zip,.pdf,image/*" multiple>
						</div>
						<div class="pax-ccs-portal__upload">
							<label for="pax-ccs-other"><?php pax_ccs_bilingual( $copy['fields']['evidence_other']['label'] ); ?></label>
							<input type="file" id="pax-ccs-other" name="evidence_other[]" multiple>
						</div>
					</div>

					<div class="pax-ccs-portal__actions">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" data-ccs-back="2"><?php pax_ccs_bilingual( $copy['actions']['back'] ); ?></button>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" data-ccs-next="4"><?php pax_ccs_bilingual( $copy['actions']['continue'] ); ?></button>
					</div>
				</div>
			</section>

			<!-- Step 4: Review & Declaration -->
			<section class="pax-ccs-portal__step" data-step="4" hidden aria-labelledby="pax-ccs-step-4-title">
				<div class="pax-ccs-portal__wrap pax-ccs-portal__panel">
					<p class="pax-ccs-portal__guide" data-ccs-guide="review">
						<span class="pax-ccs-portal__guide-kicker"><?php pax_ccs_bilingual( $copy['guided']['ask'] ); ?></span>
						<span class="pax-ccs-portal__guide-q"><?php pax_ccs_bilingual( $copy['guided']['review_q'] ); ?></span>
					</p>
					<h2 id="pax-ccs-step-4-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['review']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['review']['intro'] ); ?></p>

					<div id="pax-ccs-review" class="pax-ccs-portal__review" aria-live="polite"></div>

					<div class="pax-ccs-portal__declarations">
						<label class="pax-ccs-portal__check">
							<input type="checkbox" name="decl_truthful" id="pax-ccs-decl-truthful" required value="1">
							<span><?php pax_ccs_bilingual( $copy['declarations']['truthful'] ); ?></span>
						</label>
						<label class="pax-ccs-portal__check">
							<input type="checkbox" name="decl_false_reports" id="pax-ccs-decl-false" required value="1">
							<span><?php pax_ccs_bilingual( $copy['declarations']['false_reports'] ); ?></span>
						</label>
						<label class="pax-ccs-portal__check">
							<input type="checkbox" name="decl_verification" id="pax-ccs-decl-verify" required value="1">
							<span><?php pax_ccs_bilingual( $copy['declarations']['verification'] ); ?></span>
						</label>
					</div>

					<p id="pax-ccs-form-error" class="pax-ccs-portal__error" hidden role="alert"></p>

					<div class="pax-ccs-portal__actions">
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" data-ccs-back="3"><?php pax_ccs_bilingual( $copy['actions']['back'] ); ?></button>
						<button type="submit" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" id="pax-ccs-submit"><?php pax_ccs_bilingual( $copy['actions']['submit'] ); ?></button>
					</div>
				</div>
			</section>
		</form>

		<section id="pax-ccs-success" class="pax-ccs-portal__success" hidden>
			<div class="pax-ccs-portal__wrap pax-ccs-portal__panel pax-ccs-portal__panel--success">
				<div class="pax-ccs-portal__success-icon" aria-hidden="true"></div>
				<h2 class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['success']['title'] ); ?></h2>
				<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['success']['text'] ); ?></p>
				<p class="pax-ccs-portal__ref-label"><?php pax_ccs_bilingual( $copy['success']['ref_label'] ); ?></p>
				<p class="pax-ccs-portal__ref-value" id="pax-ccs-ref-value"></p>
				<div class="pax-ccs-portal__success-actions">
					<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" id="pax-ccs-chat-support">
						<?php pax_ccs_bilingual( $copy['success']['chat_button'] ); ?>
					</button>
					<p class="pax-ccs-portal__success-chat-hint">
						<?php pax_ccs_bilingual( $copy['success']['chat_hint'] ); ?>
					</p>
				</div>
			</div>
		</section>
	</div>

</article>
