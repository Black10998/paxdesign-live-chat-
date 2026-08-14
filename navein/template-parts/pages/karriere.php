<?php
/**
 * Karriere — careers portal with secure job application form.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$privacy_url = home_url( '/datenschutz/' );
?>
<article <?php post_class( 'pax-karriere' ); ?> id="pax-karriere" data-phase="form">

	<header class="pax-karriere__hero">
		<div class="pax-karriere__wrap">
			<p class="pax-karriere__eyebrow">PAXdesign Karriere</p>
			<h1 class="pax-karriere__title">Karriere bei PAXdesign</h1>
			<p class="pax-karriere__subtitle">Werde Teil unseres Teams und entwickle digitale Produkte mit uns, in einem Umfeld, das Qualität, Klarheit und Verantwortung verbindet.</p>
			<ul class="pax-karriere__badges" aria-label="Vorteile">
				<li class="pax-karriere__badge">Remote &amp; Hybrid</li>
				<li class="pax-karriere__badge">Moderne Tech-Stacks</li>
				<li class="pax-karriere__badge">Agile Teams</li>
			</ul>
		</div>
	</header>

	<section class="pax-karriere__values" aria-labelledby="pax-karriere-values-title">
		<div class="pax-karriere__wrap pax-karriere__wrap--wide">
			<h2 id="pax-karriere-values-title" class="pax-karriere__section-title">Warum PAXdesign?</h2>
			<div class="pax-karriere__value-grid">
				<div class="pax-karriere__value-card">
					<h3 class="pax-karriere__value-title">Impact</h3>
					<p class="pax-karriere__value-text">Du arbeitest an echten Projekten für Unternehmen und Organisationen, von Web-Apps bis zu komplexen Plattformen.</p>
				</div>
				<div class="pax-karriere__value-card">
					<h3 class="pax-karriere__value-title">Qualität</h3>
					<p class="pax-karriere__value-text">Apple-inspiriertes Design, sauberer Code und durchdachte Architektur stehen im Mittelpunkt unserer Arbeit.</p>
				</div>
				<div class="pax-karriere__value-card">
					<h3 class="pax-karriere__value-title">Wachstum</h3>
					<p class="pax-karriere__value-text">Wir fördern Weiterbildung, offenen Austausch und Verantwortung, damit du dich fachlich und persönlich entwickeln kannst.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="pax-karriere__form-section" aria-labelledby="pax-karriere-form-title">
		<div class="pax-karriere__wrap pax-karriere__wrap--wide">
			<div class="pax-karriere__form-head">
				<h2 id="pax-karriere-form-title" class="pax-karriere__section-title">Online bewerben</h2>
				<p class="pax-karriere__form-intro">Fülle das Formular aus und lade deine Unterlagen hoch. Deine Bewerbung wird sicher an unser HR-Team übermittelt.</p>
				<p class="pax-karriere__secure-badge" role="status">
					<span class="pax-karriere__secure-dot" aria-hidden="true"></span>
					Sichere Übermittlung
				</p>
			</div>

			<form id="pax-karriere-form" class="pax-karriere__form" novalidate enctype="multipart/form-data">
				<input type="text" name="website_trap" class="pax-karriere__trap" tabindex="-1" autocomplete="off" aria-hidden="true">

				<fieldset class="pax-karriere__fieldset">
					<legend class="pax-karriere__legend">Persönliche Daten</legend>
					<div class="pax-karriere__grid pax-karriere__grid--2">
						<div class="pax-karriere__field">
							<label for="pax-karriere-first-name">Vorname <span class="pax-karriere__req">*</span></label>
							<input type="text" id="pax-karriere-first-name" name="first_name" required autocomplete="given-name">
						</div>
						<div class="pax-karriere__field">
							<label for="pax-karriere-last-name">Nachname <span class="pax-karriere__req">*</span></label>
							<input type="text" id="pax-karriere-last-name" name="last_name" required autocomplete="family-name">
						</div>
					</div>
					<div class="pax-karriere__grid pax-karriere__grid--2">
						<div class="pax-karriere__field">
							<label for="pax-karriere-email">E-Mail <span class="pax-karriere__req">*</span></label>
							<input type="email" id="pax-karriere-email" name="email" required autocomplete="email" inputmode="email">
						</div>
						<div class="pax-karriere__field">
							<label for="pax-karriere-phone">Telefon <span class="pax-karriere__req">*</span></label>
							<input type="tel" id="pax-karriere-phone" name="phone" required autocomplete="tel">
						</div>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-address">Adresse</label>
						<input type="text" id="pax-karriere-address" name="address" autocomplete="street-address">
					</div>
					<div class="pax-karriere__grid pax-karriere__grid--2">
						<div class="pax-karriere__field">
							<label for="pax-karriere-city">Stadt</label>
							<input type="text" id="pax-karriere-city" name="city" autocomplete="address-level2">
						</div>
						<div class="pax-karriere__field">
							<label for="pax-karriere-zip">PLZ</label>
							<input type="text" id="pax-karriere-zip" name="zip" autocomplete="postal-code">
						</div>
					</div>
				</fieldset>

				<fieldset class="pax-karriere__fieldset">
					<legend class="pax-karriere__legend">Position &amp; Verfügbarkeit</legend>
					<div class="pax-karriere__grid pax-karriere__grid--2">
						<div class="pax-karriere__field">
							<label for="pax-karriere-position">Gewünschte Position <span class="pax-karriere__req">*</span></label>
							<select id="pax-karriere-position" name="desired_position" required>
								<option value="">Bitte wählen…</option>
								<option value="full_stack">Full Stack Developer</option>
								<option value="frontend">Frontend Developer</option>
								<option value="backend">Backend Developer</option>
								<option value="ui_ux">UI/UX Designer</option>
								<option value="devops">DevOps Engineer</option>
								<option value="project_manager">Project Manager</option>
							</select>
						</div>
						<div class="pax-karriere__field">
							<label for="pax-karriere-available">Verfügbar ab</label>
							<input type="text" id="pax-karriere-available" name="available_from" placeholder="z. B. sofort, 01.09.2026">
						</div>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-salary">Gehaltsvorstellung (optional)</label>
						<input type="text" id="pax-karriere-salary" name="salary_expectation" placeholder="z. B. € 55.000 brutto/Jahr">
					</div>
				</fieldset>

				<fieldset class="pax-karriere__fieldset">
					<legend class="pax-karriere__legend">Dokumente</legend>
					<div class="pax-karriere__field pax-karriere__field--file">
						<label for="pax-karriere-cv">Lebenslauf (PDF, max. 5&nbsp;MB) <span class="pax-karriere__req">*</span></label>
						<div class="pax-karriere__dropzone" data-dropzone="cv">
							<input type="file" id="pax-karriere-cv" name="cv" accept="application/pdf,.pdf" required>
							<span class="pax-karriere__dropzone-label">Datei auswählen oder hierher ziehen</span>
							<span class="pax-karriere__dropzone-name" aria-live="polite"></span>
						</div>
					</div>
					<div class="pax-karriere__field pax-karriere__field--file">
						<label for="pax-karriere-cover">Anschreiben (PDF, optional)</label>
						<div class="pax-karriere__dropzone" data-dropzone="cover_letter">
							<input type="file" id="pax-karriere-cover" name="cover_letter" accept="application/pdf,.pdf">
							<span class="pax-karriere__dropzone-label">Datei auswählen oder hierher ziehen</span>
							<span class="pax-karriere__dropzone-name" aria-live="polite"></span>
						</div>
					</div>
					<div class="pax-karriere__field pax-karriere__field--file">
						<label for="pax-karriere-certs">Zeugnisse/Zertifikate (PDF, optional, max. 5 Dateien)</label>
						<div class="pax-karriere__dropzone" data-dropzone="certificates">
							<input type="file" id="pax-karriere-certs" name="certificates[]" accept="application/pdf,.pdf" multiple>
							<span class="pax-karriere__dropzone-label">Datei(en) auswählen oder hierher ziehen</span>
							<span class="pax-karriere__dropzone-name" aria-live="polite"></span>
						</div>
					</div>
				</fieldset>

				<fieldset class="pax-karriere__fieldset">
					<legend class="pax-karriere__legend">Screening-Fragen</legend>
					<div class="pax-karriere__grid pax-karriere__grid--2">
						<div class="pax-karriere__field">
							<label for="pax-karriere-experience">Wie viele Jahre Berufserfahrung hast du?</label>
							<select id="pax-karriere-experience" name="experience_years">
								<option value="">Bitte wählen…</option>
								<option value="0-1">0-1 Jahre</option>
								<option value="1-3">1-3 Jahre</option>
								<option value="3-5">3-5 Jahre</option>
								<option value="5+">5+ Jahre</option>
							</select>
						</div>
						<div class="pax-karriere__field">
							<label for="pax-karriere-work-model">Bevorzugtes Arbeitsmodell?</label>
							<select id="pax-karriere-work-model" name="work_model">
								<option value="">Bitte wählen…</option>
								<option value="remote">Remote</option>
								<option value="hybrid">Hybrid</option>
								<option value="onsite">Vor Ort</option>
								<option value="flexible">Flexibel</option>
							</select>
						</div>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-agile">Hast du Erfahrung mit agilen Methoden?</label>
						<select id="pax-karriere-agile" name="agile_experience">
							<option value="">Bitte wählen…</option>
							<option value="experienced">Ja, umfangreiche Erfahrung</option>
							<option value="basic">Ja, grundlegende Kenntnisse</option>
							<option value="willing">Nein, aber lernbereit</option>
						</select>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-skills">Deine wichtigsten technischen Skills <span class="pax-karriere__req">*</span></label>
						<textarea id="pax-karriere-skills" name="skills" rows="4" required placeholder="z. B. React, TypeScript, Node.js, WordPress…"></textarea>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-motivation">Warum möchtest du bei PAXdesign arbeiten? <span class="pax-karriere__req">*</span></label>
						<textarea id="pax-karriere-motivation" name="motivation" rows="4" required></textarea>
					</div>
					<div class="pax-karriere__field">
						<label for="pax-karriere-portfolio">Portfolio / GitHub / LinkedIn (optional)</label>
						<input type="url" id="pax-karriere-portfolio" name="portfolio_url" placeholder="https://…" inputmode="url">
					</div>
				</fieldset>

				<fieldset class="pax-karriere__fieldset pax-karriere__fieldset--legal">
					<legend class="pax-karriere__legend">Datenschutz</legend>
					<div class="pax-karriere__check">
						<input type="checkbox" id="pax-karriere-privacy" name="privacy_accepted" value="1" required>
						<label for="pax-karriere-privacy">
							Ich habe die <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und akzeptiere die Verarbeitung meiner Daten gemäß DSGVO. <span class="pax-karriere__req">*</span>
						</label>
					</div>
					<div class="pax-karriere__check">
						<input type="checkbox" id="pax-karriere-talent" name="talent_pool" value="1">
						<label for="pax-karriere-talent">Ich bin damit einverstanden, dass meine Daten für zukünftige Stellenangebote gespeichert werden (optional).</label>
					</div>
					<div class="pax-karriere__check">
						<input type="checkbox" id="pax-karriere-newsletter" name="newsletter" value="1">
						<label for="pax-karriere-newsletter">Ich möchte den PAXdesign Newsletter erhalten (optional).</label>
					</div>
				</fieldset>

				<p id="pax-karriere-form-error" class="pax-karriere__error" hidden role="alert"></p>

				<div class="pax-karriere__actions">
					<button type="submit" class="pax-karriere__submit" id="pax-karriere-submit">
						<span class="pax-karriere__submit-label">Bewerbung absenden</span>
						<span class="pax-karriere__submit-spinner" aria-hidden="true"></span>
					</button>
				</div>
			</form>

			<div id="pax-karriere-success" class="pax-karriere__success" hidden role="status" aria-live="polite">
				<div class="pax-karriere__success-icon" aria-hidden="true"></div>
				<h2 class="pax-karriere__success-title">Bewerbung erfolgreich gesendet!</h2>
				<p class="pax-karriere__success-text">Vielen Dank für dein Interesse an PAXdesign. Wir haben deine Unterlagen erhalten und melden uns in Kürze bei dir.</p>
				<p class="pax-karriere__success-ref">Referenznummer: <strong id="pax-karriere-ref"></strong></p>
				<p class="pax-karriere__success-note">Eine Bestätigung wurde an deine E-Mail-Adresse gesendet.</p>
			</div>
		</div>
	</section>
</article>
