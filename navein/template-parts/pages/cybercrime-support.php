<?php
/**
 * Cybercrime reporting portal — structured digital service intake.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$copy = include __DIR__ . '/cybercrime-support-data.php';

if ( ! function_exists( 'pax_ccs_text' ) ) {
	function pax_ccs_text( $node, $lang ) {
		if ( is_array( $node ) && isset( $node[ $lang ] ) ) {
			return $node[ $lang ];
		}
		return is_string( $node ) ? $node : '';
	}
}

if ( ! function_exists( 'pax_ccs_bilingual' ) ) {
	function pax_ccs_bilingual( $node ) {
		echo '<span class="pax-ccs-t" data-lang="ar">' . esc_html( pax_ccs_text( $node, 'ar' ) ) . '</span>';
		echo '<span class="pax-ccs-t" data-lang="de" hidden>' . esc_html( pax_ccs_text( $node, 'de' ) ) . '</span>';
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

	<div class="pax-ccs-portal__langbar">
		<div class="pax-ccs-portal__wrap pax-ccs-portal__langbar-inner">
			<div class="pax-ccs-portal__lang-toggle" role="group" aria-label="Language">
				<button type="button" class="pax-ccs-portal__lang-btn is-active" data-ccs-switch="ar" aria-pressed="true">العربية</button>
				<button type="button" class="pax-ccs-portal__lang-btn" data-ccs-switch="de" aria-pressed="false">Deutsch</button>
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
							<span class="pax-ccs-t" data-lang="ar" data-ccs-start-label="start"><?php pax_ccs_bilingual( $copy['welcome']['start'] ); ?></span>
							<span class="pax-ccs-t" data-lang="de" data-ccs-start-label="start" hidden><?php pax_ccs_bilingual( $copy['welcome']['start'] ); ?></span>
							<span class="pax-ccs-t" data-lang="ar" data-ccs-start-label="view" hidden><?php pax_ccs_bilingual( $copy['welcome']['view_report'] ); ?></span>
							<span class="pax-ccs-t" data-lang="de" data-ccs-start-label="view" hidden><?php pax_ccs_bilingual( $copy['welcome']['view_report'] ); ?></span>
						</button>
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
					<div class="pax-ccs-portal__active-report-head">
						<div>
							<h2 id="pax-ccs-active-report-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['active_report']['title'] ); ?></h2>
							<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['active_report']['intro'] ); ?></p>
						</div>
						<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" id="pax-ccs-refresh-report">
							<?php pax_ccs_bilingual( $copy['active_report']['refresh'] ); ?>
						</button>
					</div>

					<dl class="pax-ccs-portal__active-meta">
						<div class="pax-ccs-portal__active-meta-row">
							<dt><?php pax_ccs_bilingual( $copy['active_report']['reference'] ); ?></dt>
							<dd><code id="pax-ccs-active-ref"></code></dd>
						</div>
						<div class="pax-ccs-portal__active-meta-row">
							<dt><?php pax_ccs_bilingual( $copy['active_report']['status'] ); ?></dt>
							<dd><span class="pax-ccs-portal__status-badge" id="pax-ccs-active-status"></span></dd>
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

					<h3 class="pax-ccs-portal__subsection-title"><?php pax_ccs_bilingual( $copy['active_report']['attachments'] ); ?></h3>
					<ul id="pax-ccs-active-attachments" class="pax-ccs-portal__attachment-list"></ul>

					<h3 class="pax-ccs-portal__subsection-title"><?php pax_ccs_bilingual( $copy['active_report']['timeline'] ); ?></h3>
					<ol id="pax-ccs-active-timeline" class="pax-ccs-portal__timeline" aria-live="polite"></ol>

					<div id="pax-ccs-active-reply-wrap" class="pax-ccs-portal__reply-wrap">
						<label for="pax-ccs-active-reply" class="pax-ccs-portal__reply-label"><?php pax_ccs_bilingual( $copy['active_report']['reply_label'] ); ?></label>
						<textarea id="pax-ccs-active-reply" class="pax-ccs-portal__reply-input" rows="4"
							placeholder="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'ar' ) ); ?>"
							data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'ar' ) ); ?>"
							data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['active_report']['reply_placeholder'], 'de' ) ); ?>"></textarea>
						<p id="pax-ccs-active-reply-error" class="pax-ccs-portal__error" hidden role="alert"></p>
						<div class="pax-ccs-portal__actions">
							<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--primary" id="pax-ccs-active-reply-submit">
								<?php pax_ccs_bilingual( $copy['active_report']['reply_submit'] ); ?>
							</button>
							<button type="button" class="pax-ccs-portal__btn pax-ccs-portal__btn--ghost" id="pax-ccs-active-chat">
								<?php pax_ccs_bilingual( $copy['active_report']['chat'] ); ?>
							</button>
						</div>
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
					<h2 id="pax-ccs-step-1-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['identity']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['identity']['intro'] ); ?></p>

					<div class="pax-ccs-portal__grid">
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-full-name"><?php pax_ccs_bilingual( $copy['fields']['full_name']['label'] ); ?></label>
							<input type="text" id="pax-ccs-full-name" name="full_name" required autocomplete="name" placeholder="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'ar' ) ); ?>" data-placeholder-ar="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'ar' ) ); ?>" data-placeholder-de="<?php echo esc_attr( pax_ccs_text( $copy['fields']['full_name']['placeholder'], 'de' ) ); ?>">
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-email"><?php pax_ccs_bilingual( $copy['fields']['email']['label'] ); ?></label>
							<input type="email" id="pax-ccs-email" name="email" required autocomplete="email" inputmode="email">
						</div>
						<div class="pax-ccs-portal__field">
							<label for="pax-ccs-phone"><?php pax_ccs_bilingual( $copy['fields']['phone']['label'] ); ?></label>
							<input type="tel" id="pax-ccs-phone" name="phone" required autocomplete="tel" inputmode="tel">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-country"><?php pax_ccs_bilingual( $copy['fields']['country']['label'] ); ?></label>
							<input type="text" id="pax-ccs-country" name="country" required autocomplete="country-name">
						</div>
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-identity-doc"><?php pax_ccs_bilingual( $copy['fields']['identity_document']['label'] ); ?></label>
							<p class="pax-ccs-portal__hint"><?php pax_ccs_bilingual( $copy['fields']['identity_document']['hint'] ); ?></p>
							<input type="file" id="pax-ccs-identity-doc" name="identity_document" accept=".pdf,.jpg,.jpeg,.png,.heic,.heif">
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
					<h2 id="pax-ccs-step-2-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['incident']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['incident']['intro'] ); ?></p>

					<div class="pax-ccs-portal__grid">
						<div class="pax-ccs-portal__field pax-ccs-portal__field--full">
							<label for="pax-ccs-category"><?php pax_ccs_bilingual( $copy['fields']['category']['label'] ); ?></label>
							<select id="pax-ccs-category" name="category" required>
								<option value="">—</option>
								<?php foreach ( $copy['categories'] as $key => $labels ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" data-label-ar="<?php echo esc_attr( pax_ccs_text( $labels, 'ar' ) ); ?>" data-label-de="<?php echo esc_attr( pax_ccs_text( $labels, 'de' ) ); ?>"><?php echo esc_html( pax_ccs_text( $labels, 'ar' ) ); ?></option>
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
							<input type="text" id="pax-ccs-platforms" name="platforms" required>
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
							<label for="pax-ccs-currency">Currency</label>
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
									<option value="<?php echo esc_attr( $key ); ?>" data-label-ar="<?php echo esc_attr( pax_ccs_text( $labels, 'ar' ) ); ?>" data-label-de="<?php echo esc_attr( pax_ccs_text( $labels, 'de' ) ); ?>"><?php echo esc_html( pax_ccs_text( $labels, 'ar' ) ); ?></option>
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
					<h2 id="pax-ccs-step-3-title" class="pax-ccs-portal__section-title"><?php pax_ccs_bilingual( $copy['sections']['evidence']['title'] ); ?></h2>
					<p class="pax-ccs-portal__section-intro"><?php pax_ccs_bilingual( $copy['sections']['evidence']['intro'] ); ?></p>

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
						<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( $copy['success']['chat_button']['ar'] ); ?></span>
						<span class="pax-ccs-t" data-lang="de"><?php echo esc_html( $copy['success']['chat_button']['de'] ); ?></span>
					</button>
					<p class="pax-ccs-portal__success-chat-hint">
						<span class="pax-ccs-t" data-lang="ar"><?php echo esc_html( $copy['success']['chat_hint']['ar'] ); ?></span>
						<span class="pax-ccs-t" data-lang="de"><?php echo esc_html( $copy['success']['chat_hint']['de'] ); ?></span>
					</p>
				</div>
			</div>
		</section>
	</div>

</article>
