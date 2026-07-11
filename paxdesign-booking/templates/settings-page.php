<?php
/**
 * Settings Page — PAXdesign Booking
 */
if (!defined('ABSPATH')) {
    exit;
}

$smtp_enabled       = (bool) get_option('paxdesign_smtp_enabled', false);
$smtp_host          = get_option('paxdesign_smtp_host', '');
$smtp_port          = get_option('paxdesign_smtp_port', '587');
$smtp_user          = get_option('paxdesign_smtp_user', '');
$smtp_pass          = get_option('paxdesign_smtp_pass', '');
$smtp_enc           = get_option('paxdesign_smtp_encryption', 'tls');
$smtp_from_email    = get_option('paxdesign_smtp_from_email', 'info@paxdesign.at');
$smtp_from_name     = get_option('paxdesign_smtp_from_name', 'PAXdesign');
$notif_email        = get_option('paxdesign_booking_notification_email', 'info@paxdesign.at');
$live_notify_emails  = get_option(
    'paxdesign_live_notify_emails',
    "info@paxdesign.at\nal.kahalaf.ahmad@gmail.com"
);
$live_whatsapp_phone = get_option('paxdesign_live_whatsapp_phone', '4368120543638');
$live_whatsapp_key  = get_option('paxdesign_live_whatsapp_callmebot_key', '3515631');
$apns_key_id        = get_option('paxdesign_apns_key_id', '');
$apns_team_id       = get_option('paxdesign_apns_team_id', '');
$apns_bundle_id     = get_option('paxdesign_apns_bundle_id', 'at.paxdesign.livechat');
$apns_key_p8        = get_option('paxdesign_apns_key_p8', '');
$live_agent_name    = get_option('paxdesign_live_chat_agent_name', PAXdesign_Chat_Live::get_agent_display_name());
$live_agent_avatar  = get_option('paxdesign_live_chat_agent_avatar', PAXdesign_Chat_Live::get_agent_avatar_url());
$live_agent_role    = get_option('paxdesign_live_chat_agent_role', PAXdesign_Chat_Live::get_agent_role());
$live_agent_tagline = get_option('paxdesign_live_chat_agent_tagline', PAXdesign_Chat_Live::get_agent_tagline());
$live_agent_bio     = get_option('paxdesign_live_chat_agent_bio', PAXdesign_Chat_Live::get_agent_bio());
$email_ahmad        = get_option('paxdesign_booking_email_ahmad', 'info@paxdesign.at');
$logo_url           = get_option('paxdesign_booking_logo_url', '');
$services_url       = get_option('paxdesign_booking_services_url', home_url('/'));
$contact_url        = get_option('paxdesign_booking_contact_url', home_url('/'));
$phone              = get_option('paxdesign_booking_phone', '+43 681 20543638');
$social_instagram   = get_option('paxdesign_booking_social_instagram', '');
$social_linkedin    = get_option('paxdesign_booking_social_linkedin', '');
$social_facebook    = get_option('paxdesign_booking_social_facebook', '');
$resolved_logo      = PAXdesign_Email_Templates::get_logo_url();
$smtp_ok            = $smtp_enabled && !empty($smtp_host);
$chat_enabled       = (bool) get_option('paxdesign_chat_enabled', true);
$chat_openai_key    = get_option('paxdesign_chat_openai_key', '');
$chat_model         = get_option('paxdesign_chat_model', 'gpt-4o');
$chat_worker_url    = get_option('paxdesign_chat_worker_url', '');
$chat_worker_secret = get_option('paxdesign_chat_worker_secret', '');
$chat_worker_ok     = !empty($chat_worker_url);
$chat_openai_ok     = !empty($chat_openai_key) || (defined('PAXDESIGN_OPENAI_API_KEY') && PAXDESIGN_OPENAI_API_KEY);
$chat_configured    = $chat_worker_ok || $chat_openai_ok;
$chat_last_model    = get_option('paxdesign_chat_last_model', '');
$chat_last_error    = get_option('paxdesign_chat_last_error', '');
$chat_last_test     = (int) get_option('paxdesign_chat_last_test', 0);
$chat_greeting      = get_option('paxdesign_chat_greeting', '');
$chat_response_style = get_option('paxdesign_chat_response_style', '');
$chat_show_prices   = (bool) get_option('paxdesign_chat_show_prices', false);
$chat_auto_booking  = (bool) get_option('paxdesign_chat_auto_booking', true);
$chat_phone         = get_option('paxdesign_chat_phone', '');
$chat_email         = get_option('paxdesign_chat_email', '');
$chat_primary       = get_option('paxdesign_chat_primary_services', '');
$chat_cta_text      = get_option('paxdesign_chat_cta_text', '');
$chat_price_hints   = get_option('paxdesign_chat_price_hints', '');
$chat_quick_links   = class_exists('PAXdesign_Chat_Quick_Links') ? PAXdesign_Chat_Quick_Links::get_links() : array();
?>
<div class="wrap pax-settings">

  <div class="ps-header">
    <div class="ps-header-main">
      <h1>Einstellungen</h1>
      <p class="ps-header-meta">PAXdesign Booking &middot; Version <?php echo esc_html(PAXDESIGN_BOOKING_VERSION); ?></p>
    </div>
    <div class="ps-header-badges">
      <?php if ($smtp_ok) : ?>
        <span class="ps-badge ps-badge--success"><span class="ps-badge-dot"></span>SMTP aktiv</span>
      <?php else : ?>
        <span class="ps-badge ps-badge--warning"><span class="ps-badge-dot"></span>Standard-Mailer</span>
      <?php endif; ?>
      <?php if ($chat_enabled && $chat_configured) : ?>
        <span class="ps-badge ps-badge--success"><span class="ps-badge-dot"></span>KI-Chat konfiguriert</span>
      <?php elseif ($chat_enabled) : ?>
        <span class="ps-badge ps-badge--warning"><span class="ps-badge-dot"></span>KI-Chat nicht konfiguriert</span>
      <?php else : ?>
        <span class="ps-badge ps-badge--neutral"><span class="ps-badge-dot"></span>KI-Chat inaktiv</span>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" action="options.php" id="paxdesignSettingsForm">
    <?php settings_fields('paxdesign_booking_settings'); ?>

    <!-- 1. Allgemein / Status -->
    <div class="ps-status-grid" aria-label="Systemstatus">
      <div class="ps-status-tile">
        <span class="ps-status-tile-label">E-Mail-Versand</span>
        <span class="ps-status-tile-value">
          <?php echo $smtp_ok ? 'SMTP' : 'WordPress Standard'; ?>
        </span>
        <span class="ps-status-tile-detail">
          <?php echo $smtp_ok ? esc_html($smtp_host . ':' . $smtp_port) : 'Kein SMTP-Server konfiguriert'; ?>
        </span>
      </div>
      <div class="ps-status-tile">
        <span class="ps-status-tile-label">KI-Chat-Assistent</span>
        <span class="ps-status-tile-value">
          <?php echo $chat_enabled ? 'Aktiv' : 'Inaktiv'; ?>
        </span>
        <span class="ps-status-tile-detail">
          <?php echo $chat_enabled ? 'Im Booking-Widget integriert' : 'Deaktiviert für Besucher'; ?>
        </span>
      </div>
      <div class="ps-status-tile">
        <span class="ps-status-tile-label">Cloudflare Worker</span>
        <span class="ps-status-tile-value">
          <?php echo $chat_worker_ok ? 'Konfiguriert' : 'Nicht konfiguriert'; ?>
        </span>
        <span class="ps-status-tile-detail">
          <?php echo $chat_worker_ok ? 'Empfohlener Backend-Pfad' : 'Worker URL fehlt'; ?>
        </span>
      </div>
      <div class="ps-status-tile">
        <span class="ps-status-tile-label">OpenAI Fallback</span>
        <span class="ps-status-tile-value">
          <?php echo $chat_openai_ok ? 'Verfügbar' : 'Nicht konfiguriert'; ?>
        </span>
        <span class="ps-status-tile-detail">
          <?php echo $chat_openai_ok ? esc_html($chat_model) : 'API Key fehlt'; ?>
        </span>
      </div>
    </div>

    <div class="ps-layout">

      <div class="ps-main">

        <!-- 2. E-Mail & Benachrichtigungen -->
        <section class="ps-card" aria-labelledby="ps-section-email">
          <div class="ps-card-head">
            <span class="ps-card-num">2</span>
            <div class="ps-card-icon ps-card-icon--purple">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2 id="ps-section-email">E-Mail &amp; Benachrichtigungen</h2>
              <p>Postfächer für Buchungsanfragen und interne Benachrichtigungen</p>
            </div>
          </div>
          <div class="ps-card-body">
            <div class="ps-field">
              <label class="ps-label" for="paxdesign_booking_notification_email">
                Haupt-Postfach <span class="ps-required">*</span>
              </label>
              <div class="ps-input-wrap ps-has-icon">
                <span class="ps-input-icon">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                <input type="email" id="paxdesign_booking_notification_email"
                       name="paxdesign_booking_notification_email"
                       value="<?php echo esc_attr($notif_email); ?>"
                       placeholder="info@paxdesign.at">
              </div>
              <span class="ps-hint">Erhält alle Buchungsanfragen. Das gebuchte Team-Mitglied wird automatisch in CC gesetzt.</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_notify_emails">Live-Chat Alarm-E-Mails</label>
              <textarea id="paxdesign_live_notify_emails"
                        name="paxdesign_live_notify_emails"
                        class="ps-input"
                        rows="3"
                        placeholder="info@paxdesign.at&#10;al.kahalaf.ahmad@gmail.com"><?php echo esc_textarea($live_notify_emails); ?></textarea>
              <span class="ps-hint">Eine Adresse pro Zeile. Beide Standard-Adressen sind vorkonfiguriert und erhalten jede Live-Agent-Anfrage.</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_whatsapp_phone">WhatsApp-Nummer (Live-Alarm)</label>
              <input type="text" id="paxdesign_live_whatsapp_phone"
                     name="paxdesign_live_whatsapp_phone"
                     class="ps-input"
                     value="<?php echo esc_attr($live_whatsapp_phone); ?>"
                     placeholder="4368120543638">
              <span class="ps-hint">Vorkonfiguriert für +43 681 20543638 (CallMeBot aktiv).</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_whatsapp_callmebot_key">CallMeBot WhatsApp API-Key</label>
              <input type="password" id="paxdesign_live_whatsapp_callmebot_key"
                     name="paxdesign_live_whatsapp_callmebot_key"
                     class="ps-input"
                     value="<?php echo esc_attr($live_whatsapp_key); ?>"
                     placeholder="3515631"
                     autocomplete="new-password">
              <span class="ps-hint">Vorkonfiguriert und aktiv. Überschreibbar via Konstante <code>PAXDESIGN_WHATSAPP_CALLMEBOT_KEY</code> in wp-config.php.</span>
            </div>

            <div class="ps-divider"></div>
            <h3 class="ps-subheading">iOS Live Chat — Apple Push (APNs)</h3>
            <p class="ps-hint ps-hint--block">Erforderlich für sofortige Push-Benachrichtigungen an die native iPhone App (LiveContainer). Auth Key (.p8) aus dem Apple Developer Portal.</p>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_apns_key_id">APNs Key ID</label>
              <input type="text" id="paxdesign_apns_key_id" name="paxdesign_apns_key_id"
                     class="ps-input" value="<?php echo esc_attr($apns_key_id); ?>" placeholder="AB12CD34EF">
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_apns_team_id">Apple Team ID</label>
              <input type="text" id="paxdesign_apns_team_id" name="paxdesign_apns_team_id"
                     class="ps-input" value="<?php echo esc_attr($apns_team_id); ?>" placeholder="XXXXXXXXXX">
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_apns_bundle_id">Bundle ID</label>
              <input type="text" id="paxdesign_apns_bundle_id" name="paxdesign_apns_bundle_id"
                     class="ps-input" value="<?php echo esc_attr($apns_bundle_id); ?>" placeholder="at.paxdesign.livechat">
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_apns_key_p8">APNs Auth Key (.p8 Inhalt)</label>
              <textarea id="paxdesign_apns_key_p8" name="paxdesign_apns_key_p8"
                        class="ps-input" rows="6"
                        placeholder="-----BEGIN PRIVATE KEY-----"><?php echo esc_textarea($apns_key_p8); ?></textarea>
            </div>

            <div class="ps-divider"></div>
            <h3 class="ps-subheading">Live-Chat Agent Profil</h3>
            <p class="ps-hint ps-hint--block">Profilbild, Name und Rolle erscheinen im Live Chat für Kunden und im Admin-Panel. Klick auf das Profilbild öffnet die Kurzinfo.</p>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_chat_agent_name">Agent Name</label>
              <input type="text" id="paxdesign_live_chat_agent_name" name="paxdesign_live_chat_agent_name"
                     class="ps-input" value="<?php echo esc_attr($live_agent_name); ?>"
                     placeholder="Ahmad Alkhalaf">
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_chat_agent_avatar">Profilbild URL</label>
              <input type="url" id="paxdesign_live_chat_agent_avatar" name="paxdesign_live_chat_agent_avatar"
                     class="ps-input" value="<?php echo esc_attr($live_agent_avatar); ?>"
                     placeholder="https://paxdesign.at/wp-content/uploads/...">
              <span class="ps-hint">Direktlink zum Bild (JPG, PNG, WebP). Wird im Chat-Header und bei Agent-Nachrichten angezeigt.</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_chat_agent_role">Rolle (Untertitel)</label>
              <input type="text" id="paxdesign_live_chat_agent_role" name="paxdesign_live_chat_agent_role"
                     class="ps-input" value="<?php echo esc_attr($live_agent_role); ?>"
                     placeholder="Development Manager">
              <span class="ps-hint">Kleiner Text unter „Live Chat“, z.&nbsp;B. Development Manager.</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_chat_agent_tagline">Profil-Zeile</label>
              <input type="text" id="paxdesign_live_chat_agent_tagline" name="paxdesign_live_chat_agent_tagline"
                     class="ps-input" value="<?php echo esc_attr($live_agent_tagline); ?>"
                     placeholder="Owner &amp; Founder · PAXdesign">
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_live_chat_agent_bio">Profil-Text (Popup)</label>
              <textarea id="paxdesign_live_chat_agent_bio" name="paxdesign_live_chat_agent_bio" class="ps-input" rows="3"
                        placeholder="Kurzbeschreibung für das Profil-Popup …"><?php echo esc_textarea($live_agent_bio); ?></textarea>
            </div>

            <div class="ps-divider"><span>Website Quick Links</span></div>
            <h3 class="ps-subheading">Schnell-Links für Live-Chat</h3>
            <p class="ps-hint ps-hint--block">Vordefinierte Website-Seiten, die Mitarbeiter per „+“-Button an Kunden senden können. Reihenfolge per Drag &amp; Drop ändern.</p>
            <input type="hidden" name="paxdesign_chat_quick_links" id="paxdesign_chat_quick_links"
                   value="<?php echo esc_attr(wp_json_encode($chat_quick_links)); ?>">
            <div class="ps-quick-links-toolbar">
              <button type="button" class="ps-btn ps-btn-secondary" id="paxdesignQuickLinksAdd">Link hinzufügen</button>
            </div>
            <div class="ps-quick-links-table-wrap">
              <table class="ps-quick-links-table" id="paxdesignQuickLinksTable" aria-label="Website Quick Links">
                <thead>
                  <tr>
                    <th scope="col" class="ps-quick-links-col-drag" aria-label="Reihenfolge"></th>
                    <th scope="col">Icon</th>
                    <th scope="col">Bezeichnung</th>
                    <th scope="col">URL</th>
                    <th scope="col" class="ps-quick-links-col-actions" aria-label="Aktionen"></th>
                  </tr>
                </thead>
                <tbody id="paxdesignQuickLinksBody">
                  <?php foreach ($chat_quick_links as $link) : ?>
                    <tr data-link-id="<?php echo esc_attr($link['id']); ?>">
                      <td class="ps-quick-links-col-drag"><span class="ps-quick-links-drag" aria-hidden="true">⋮⋮</span></td>
                      <td><input type="text" class="ps-input ps-input--compact ps-quick-link-icon" value="<?php echo esc_attr($link['icon']); ?>" maxlength="8" aria-label="Icon"></td>
                      <td><input type="text" class="ps-input ps-quick-link-label" value="<?php echo esc_attr($link['label']); ?>" required aria-label="Bezeichnung"></td>
                      <td><input type="url" class="ps-input ps-quick-link-url" value="<?php echo esc_attr($link['url']); ?>" required aria-label="URL"></td>
                      <td class="ps-quick-links-col-actions"><button type="button" class="ps-btn ps-btn-ghost ps-quick-link-remove" aria-label="Link entfernen">Entfernen</button></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if (!$smtp_ok) : ?>
            <div class="ps-alert ps-alert--warn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              E-Mails werden derzeit über den Standard-WordPress-Mailer versendet. Für zuverlässige Zustellung SMTP konfigurieren (Abschnitt 5).
            </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- 3. Team-Mitglieder -->
        <section class="ps-card" aria-labelledby="ps-section-team">
          <div class="ps-card-head">
            <span class="ps-card-num">3</span>
            <div class="ps-card-icon ps-card-icon--slate">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2 id="ps-section-team">Team-Mitglieder</h2>
              <p>E-Mail-Adressen für CC-Benachrichtigungen bei Buchungen</p>
            </div>
          </div>
          <div class="ps-card-body">
            <div class="ps-member-list">
              <div class="ps-member-row">
                <div class="ps-member-ava">
                  <img src="https://paxdesign.at/wp-content/uploads/2025/12/38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif" alt="Ahmad Alkhalaf">
                </div>
                <div class="ps-member-meta">
                  <strong>Ahmad Alkhalaf</strong>
                  <span>Gründer &amp; Geschäftsführer – PAXDesign</span>
                </div>
                <div class="ps-member-email">
                  <input type="email" name="paxdesign_booking_email_ahmad"
                         value="<?php echo esc_attr($email_ahmad); ?>" placeholder="ahmad@paxdesign.at"
                         aria-label="E-Mail Ahmad Alkhalaf">
                </div>
              </div>
            </div>
            <span class="ps-hint" style="margin-top:12px;display:block;">Verfügbarkeit und Reihenfolge im Bereich <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking-team')); ?>">Team Management</a> verwalten.</span>
          </div>
        </section>

        <!-- 4. Marke & Kontaktinformationen -->
        <section class="ps-card" aria-labelledby="ps-section-brand">
          <div class="ps-card-head">
            <span class="ps-card-num">4</span>
            <div class="ps-card-icon ps-card-icon--purple">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l3 7h7l-5.5 4.2 2 7.3L12 17l-6.5 3.5 2-7.3L2 9h7z"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2 id="ps-section-brand">Marke &amp; Kontaktinformationen</h2>
              <p>Logo, Links und Kontaktdaten für HTML-Buchungs-E-Mails</p>
            </div>
          </div>
          <div class="ps-card-body">
            <div class="ps-field">
              <label class="ps-label" for="paxdesign_booking_logo_url">Logo-URL</label>
              <div class="ps-input-wrap">
                <input type="url" id="paxdesign_booking_logo_url" name="paxdesign_booking_logo_url"
                       value="<?php echo esc_attr($logo_url); ?>" placeholder="Automatisch vom Website-Logo">
              </div>
              <span class="ps-hint">Leer lassen = automatisch Theme-Logo / Site-Icon. Aktuell erkannt: <?php echo esc_html($resolved_logo); ?></span>
            </div>

            <div class="ps-grid-2">
              <div class="ps-field">
                <label class="ps-label" for="paxdesign_booking_services_url">Leistungsseite</label>
                <div class="ps-input-wrap">
                  <input type="url" id="paxdesign_booking_services_url" name="paxdesign_booking_services_url"
                         value="<?php echo esc_attr($services_url); ?>" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                </div>
              </div>
              <div class="ps-field">
                <label class="ps-label" for="paxdesign_booking_contact_url">Kontaktseite</label>
                <div class="ps-input-wrap">
                  <input type="url" id="paxdesign_booking_contact_url" name="paxdesign_booking_contact_url"
                         value="<?php echo esc_attr($contact_url); ?>" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                </div>
              </div>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_booking_phone">Telefon</label>
              <div class="ps-input-wrap">
                <input type="text" id="paxdesign_booking_phone" name="paxdesign_booking_phone"
                       value="<?php echo esc_attr($phone); ?>" placeholder="+43 681 20543638">
              </div>
            </div>

            <div class="ps-divider"><span>Social Media (optional)</span></div>

            <div class="ps-grid-2">
              <div class="ps-field">
                <label class="ps-label" for="paxdesign_booking_social_instagram">Instagram</label>
                <div class="ps-input-wrap">
                  <input type="url" id="paxdesign_booking_social_instagram" name="paxdesign_booking_social_instagram"
                         value="<?php echo esc_attr($social_instagram); ?>" placeholder="https://instagram.com/...">
                </div>
              </div>
              <div class="ps-field">
                <label class="ps-label" for="paxdesign_booking_social_linkedin">LinkedIn</label>
                <div class="ps-input-wrap">
                  <input type="url" id="paxdesign_booking_social_linkedin" name="paxdesign_booking_social_linkedin"
                         value="<?php echo esc_attr($social_linkedin); ?>" placeholder="https://linkedin.com/...">
                </div>
              </div>
            </div>
            <div class="ps-field">
              <label class="ps-label" for="paxdesign_booking_social_facebook">Facebook</label>
              <div class="ps-input-wrap">
                <input type="url" id="paxdesign_booking_social_facebook" name="paxdesign_booking_social_facebook"
                       value="<?php echo esc_attr($social_facebook); ?>" placeholder="https://facebook.com/...">
              </div>
            </div>
          </div>
        </section>

        <!-- 5. SMTP -->
        <section class="ps-card" aria-labelledby="ps-section-smtp">
          <div class="ps-card-head">
            <span class="ps-card-num">5</span>
            <div class="ps-card-icon ps-card-icon--green">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2 id="ps-section-smtp">SMTP-Konfiguration</h2>
              <p>Zuverlässige E-Mail-Zustellung über eigenen Mailserver</p>
            </div>
            <div class="ps-card-head-actions">
              <label class="ps-toggle ps-toggle-lg" for="paxdesign_smtp_enabled">
                <input type="checkbox" id="paxdesign_smtp_enabled" name="paxdesign_smtp_enabled"
                       value="1" <?php checked($smtp_enabled); ?>>
                <span class="ps-toggle-track"></span>
              </label>
              <span id="paxdesignSmtpToggleLabel" class="ps-toggle-label<?php echo $smtp_enabled ? ' on' : ''; ?>">
                <?php echo $smtp_enabled ? 'Aktiv' : 'Inaktiv'; ?>
              </span>
            </div>
          </div>

          <div class="ps-card-body" id="paxdesignSmtpFields"<?php echo $smtp_enabled ? '' : ' style="display:none"'; ?>>

            <?php if ($smtp_ok) : ?>
            <div class="ps-alert ps-alert--ok">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
              SMTP konfiguriert &mdash; <?php echo esc_html($smtp_host . ':' . $smtp_port); ?>
            </div>
            <?php else : ?>
            <div class="ps-alert ps-alert--warn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Noch nicht konfiguriert &mdash; E-Mails werden über den Standard-WordPress-Mailer gesendet
            </div>
            <?php endif; ?>

            <div class="ps-row">
              <div class="ps-field ps-grow">
                <label class="ps-label" for="paxdesign_smtp_host">SMTP-Host</label>
                <div class="ps-input-wrap ps-has-icon">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                  <input type="text" id="paxdesign_smtp_host" name="paxdesign_smtp_host"
                         value="<?php echo esc_attr($smtp_host); ?>" placeholder="smtp.gmail.com">
                </div>
                <span class="ps-hint">z.&nbsp;B. smtp.gmail.com &middot; smtp.office365.com</span>
              </div>
              <div class="ps-field ps-narrow">
                <label class="ps-label" for="paxdesign_smtp_port">Port</label>
                <div class="ps-input-wrap">
                  <input type="number" id="paxdesign_smtp_port" name="paxdesign_smtp_port"
                         value="<?php echo esc_attr($smtp_port); ?>" placeholder="587" min="1" max="65535">
                </div>
                <span class="ps-hint">587 TLS &middot; 465 SSL</span>
              </div>
            </div>

            <div class="ps-row">
              <div class="ps-field ps-half">
                <label class="ps-label" for="paxdesign_smtp_user">Benutzername / E-Mail</label>
                <div class="ps-input-wrap ps-has-icon">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                  <input type="text" id="paxdesign_smtp_user" name="paxdesign_smtp_user"
                         value="<?php echo esc_attr($smtp_user); ?>" placeholder="info@paxdesign.at" autocomplete="off">
                </div>
              </div>
              <div class="ps-field ps-half">
                <label class="ps-label" for="paxdesign_smtp_pass">Passwort / App-Passwort</label>
                <div class="ps-input-wrap ps-has-icon ps-pw-wrap">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                  <input type="password" id="paxdesign_smtp_pass" name="paxdesign_smtp_pass"
                         value="<?php echo esc_attr($smtp_pass); ?>" placeholder="••••••••••••" autocomplete="new-password">
                  <button type="button" class="ps-eye-btn paxdesign-toggle-pass" aria-label="Passwort anzeigen">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <span class="ps-hint">Bei Gmail: App-Passwort verwenden (2FA erforderlich)</span>
              </div>
            </div>

            <div class="ps-row">
              <div class="ps-field ps-narrow">
                <label class="ps-label" for="paxdesign_smtp_encryption">Verschlüsselung</label>
                <div class="ps-select-wrap">
                  <select id="paxdesign_smtp_encryption" name="paxdesign_smtp_encryption">
                    <option value="tls" <?php selected($smtp_enc, 'tls'); ?>>TLS (empfohlen)</option>
                    <option value="ssl" <?php selected($smtp_enc, 'ssl'); ?>>SSL</option>
                    <option value="none" <?php selected($smtp_enc, 'none'); ?>>Keine</option>
                  </select>
                  <span class="ps-select-arrow"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg></span>
                </div>
              </div>
              <div class="ps-field ps-half">
                <label class="ps-label" for="paxdesign_smtp_from_email">Absender-E-Mail</label>
                <div class="ps-input-wrap ps-has-icon">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                  <input type="email" id="paxdesign_smtp_from_email" name="paxdesign_smtp_from_email"
                         value="<?php echo esc_attr($smtp_from_email); ?>" placeholder="info@paxdesign.at">
                </div>
              </div>
              <div class="ps-field ps-half">
                <label class="ps-label" for="paxdesign_smtp_from_name">Absender-Name</label>
                <div class="ps-input-wrap ps-has-icon">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                  <input type="text" id="paxdesign_smtp_from_name" name="paxdesign_smtp_from_name"
                         value="<?php echo esc_attr($smtp_from_name); ?>" placeholder="PAXdesign">
                </div>
              </div>
            </div>

            <div class="ps-chips">
              <span class="ps-chips-label">Schnellauswahl:</span>
              <button type="button" class="ps-chip paxdesign-chip" data-host="smtp.gmail.com" data-port="587" data-enc="tls">Gmail</button>
              <button type="button" class="ps-chip paxdesign-chip" data-host="smtp.office365.com" data-port="587" data-enc="tls">Office 365</button>
              <button type="button" class="ps-chip paxdesign-chip" data-host="smtp.mail.yahoo.com" data-port="587" data-enc="tls">Yahoo</button>
              <button type="button" class="ps-chip paxdesign-chip" data-host="smtp.ionos.de" data-port="587" data-enc="tls">IONOS</button>
              <button type="button" class="ps-chip paxdesign-chip" data-host="mail.paxdesign.at" data-port="587" data-enc="tls">PAXdesign</button>
            </div>

            <div class="ps-test-bar">
              <div class="ps-test-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                SMTP-Verbindung testen
              </div>
              <div class="ps-test-controls">
                <input type="email" id="paxdesignTestEmailTo"
                       placeholder="<?php echo esc_attr($notif_email); ?>"
                       value="<?php echo esc_attr($notif_email); ?>"
                       aria-label="Test-E-Mail Empfänger">
                <button type="button" class="ps-btn ps-btn-secondary" id="paxdesignSendTestEmail">Test senden</button>
                <span class="ps-test-result" id="paxdesignTestResult"></span>
              </div>
            </div>
          </div>
        </section>

        <!-- 6. KI-Chat-Assistent -->
        <section class="ps-card ps-card--highlight" aria-labelledby="ps-section-chat">
          <div class="ps-card-head">
            <span class="ps-card-num">6</span>
            <div class="ps-card-icon ps-card-icon--blue">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2 id="ps-section-chat">KI-Chat-Assistent</h2>
              <p>Cloudflare Worker, OpenAI Fallback und Widget-Integration</p>
            </div>
            <div class="ps-card-head-actions">
              <label class="ps-toggle ps-toggle-lg" for="paxdesign_chat_enabled">
                <input type="checkbox" id="paxdesign_chat_enabled" name="paxdesign_chat_enabled"
                       value="1" <?php checked($chat_enabled); ?>>
                <span class="ps-toggle-track"></span>
              </label>
              <span id="paxdesignChatToggleLabel" class="ps-toggle-label<?php echo $chat_enabled ? ' on' : ''; ?>">
                <?php echo $chat_enabled ? 'Aktiv' : 'Inaktiv'; ?>
              </span>
            </div>
          </div>

          <div class="ps-card-body" id="paxdesignChatFields"<?php echo $chat_enabled ? '' : ' style="display:none"'; ?>>

            <?php if ($chat_configured) : ?>
            <div class="ps-alert ps-alert--ok">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
              Backend konfiguriert
              <?php if ($chat_worker_ok) : ?>
                &mdash; Cloudflare Worker aktiv
              <?php else : ?>
                &mdash; OpenAI Fallback aktiv
              <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="ps-alert ps-alert--warn">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Noch nicht konfiguriert &mdash; Bitte Worker URL oder OpenAI API Key hinterlegen
            </div>
            <?php endif; ?>

            <div class="ps-info-block">
              <strong>Integration</strong>
              <p>Der KI-Assistent ist im bestehenden Uhr-Widget integriert. Besucher wählen zwischen direktem Kontakt mit Ahmad Alkhalaf und dem KI-Chat — ohne separaten Button.</p>
            </div>

            <div class="ps-subsection">
              <p class="ps-subsection-title">Cloudflare Worker</p>
              <p class="ps-subsection-desc">Empfohlener Backend-Pfad — Anfragen werden serverseitig über WordPress an den Worker weitergeleitet.</p>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_chat_worker_url">Worker URL</label>
              <div class="ps-input-wrap ps-has-icon">
                <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
                <input type="url" id="paxdesign_chat_worker_url" name="paxdesign_chat_worker_url"
                       value="<?php echo esc_attr($chat_worker_url); ?>"
                       placeholder="https://paxdesign-chat.your-subdomain.workers.dev">
              </div>
              <?php if ($chat_worker_ok) : ?>
                <span class="ps-hint"><span class="ps-badge ps-badge--success" style="margin-right:6px;">Konfiguriert</span> Worker erreichbar unter der angegebenen URL.</span>
              <?php else : ?>
                <span class="ps-hint"><span class="ps-badge ps-badge--neutral" style="margin-right:6px;">Nicht konfiguriert</span> URL des deployten Cloudflare Workers eintragen.</span>
              <?php endif; ?>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_chat_worker_secret">Shared Secret</label>
              <div class="ps-input-wrap ps-has-icon ps-pw-wrap">
                <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                <input type="password" id="paxdesign_chat_worker_secret" name="paxdesign_chat_worker_secret"
                       value="<?php echo esc_attr($chat_worker_secret); ?>" placeholder="CHAT_SHARED_SECRET" autocomplete="new-password">
                <button type="button" class="ps-eye-btn paxdesign-toggle-pass" aria-label="Shared Secret anzeigen">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <span class="ps-hint">Muss mit dem Wrangler-Secret <code>CHAT_SHARED_SECRET</code> übereinstimmen. Wird maskiert gespeichert.</span>
            </div>

            <div class="ps-divider"><span>OpenAI Fallback</span></div>

            <div class="ps-row">
              <div class="ps-field ps-grow">
                <label class="ps-label" for="paxdesign_chat_openai_key">OpenAI API Key</label>
                <div class="ps-input-wrap ps-has-icon ps-pw-wrap">
                  <span class="ps-input-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                  <input type="password" id="paxdesign_chat_openai_key" name="paxdesign_chat_openai_key"
                         value="<?php echo esc_attr($chat_openai_key); ?>" placeholder="sk-..." autocomplete="new-password">
                  <button type="button" class="ps-eye-btn paxdesign-toggle-pass" aria-label="API Key anzeigen">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <?php if ($chat_openai_ok) : ?>
                  <span class="ps-hint"><span class="ps-badge ps-badge--success" style="margin-right:6px;">Konfiguriert</span> Wird nur serverseitig verwendet — nie im Frontend.</span>
                <?php else : ?>
                  <span class="ps-hint"><span class="ps-badge ps-badge--neutral" style="margin-right:6px;">Nicht konfiguriert</span> Aktiv wenn kein Worker konfiguriert ist.</span>
                <?php endif; ?>
              </div>
              <div class="ps-field ps-narrow">
                <label class="ps-label" for="paxdesign_chat_model">Modell</label>
                <div class="ps-input-wrap">
                  <input type="text" id="paxdesign_chat_model" name="paxdesign_chat_model"
                         value="<?php echo esc_attr($chat_model); ?>" placeholder="gpt-4o">
                </div>
                <span class="ps-hint">Empfohlen: gpt-4o. gpt-5 wird unterstützt (höheres Token-Budget). Bei Fehler: automatischer Fallback.</span>
              </div>
            </div>

            <div class="ps-divider"><span>Beratung &amp; Verhalten</span></div>

            <div class="ps-info-block">
              <strong>Sales-Assistent</strong>
              <p>Der System-Prompt wird dynamisch aus den Leistungen der PAXDesign-Website und diesen Einstellungen erzeugt. Preise werden standardmäßig nicht automatisch ausgegeben.</p>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_chat_greeting">Begrüßungstext</label>
              <textarea id="paxdesign_chat_greeting" name="paxdesign_chat_greeting" rows="2"
                        placeholder="Hallo! Ich bin der PAXDesign KI-Assistent…"><?php echo esc_textarea($chat_greeting); ?></textarea>
              <span class="ps-hint">Erste Nachricht im Chat-Widget. Leer = Standardtext.</span>
            </div>

            <div class="ps-field">
              <label class="ps-label" for="paxdesign_chat_response_style">Antwort-Stil (optional)</label>
              <textarea id="paxdesign_chat_response_style" name="paxdesign_chat_response_style" rows="3"
                        placeholder="Kurz, professionell, maximal 3–5 Punkte…"><?php echo esc_textarea($chat_response_style); ?></textarea>
              <span class="ps-hint">Zusätzliche Stil-Vorgaben für den System-Prompt. Leer = optimierte Sales-Defaults.</span>
            </div>

            <div class="ps-row">
              <div class="ps-field ps-grow">
                <label class="ps-label" for="paxdesign_chat_primary_services">Primäre Leistungen</label>
                <textarea id="paxdesign_chat_primary_services" name="paxdesign_chat_primary_services" rows="2"
                          placeholder="website, aichatbot, crm, ecommerce"><?php echo esc_textarea($chat_primary); ?></textarea>
                <span class="ps-hint">Service-Keys oder Namen, kommagetrennt — werden im Prompt priorisiert.</span>
              </div>
              <div class="ps-field ps-narrow">
                <label class="ps-label" for="paxdesign_chat_cta_text">CTA-Text</label>
                <input type="text" id="paxdesign_chat_cta_text" name="paxdesign_chat_cta_text"
                       value="<?php echo esc_attr($chat_cta_text); ?>"
                       placeholder="Kostenlose Erstberatung buchen">
              </div>
            </div>

            <div class="ps-row">
              <div class="ps-field ps-grow">
                <label class="ps-label" for="paxdesign_chat_phone">Telefon (Chat)</label>
                <input type="text" id="paxdesign_chat_phone" name="paxdesign_chat_phone"
                       value="<?php echo esc_attr($chat_phone); ?>"
                       placeholder="+43 681 20543638">
                <span class="ps-hint">Leer = Telefon aus den Buchungs-Einstellungen.</span>
              </div>
              <div class="ps-field ps-grow">
                <label class="ps-label" for="paxdesign_chat_email">E-Mail (Chat)</label>
                <input type="email" id="paxdesign_chat_email" name="paxdesign_chat_email"
                       value="<?php echo esc_attr($chat_email); ?>"
                       placeholder="info@paxdesign.at">
              </div>
            </div>

            <div class="ps-row">
              <div class="ps-field">
                <label class="ps-toggle" for="paxdesign_chat_show_prices">
                  <input type="checkbox" id="paxdesign_chat_show_prices" name="paxdesign_chat_show_prices"
                         value="1" <?php checked($chat_show_prices); ?>>
                  <span class="ps-toggle-track"></span>
                </label>
                <span class="ps-toggle-label<?php echo $chat_show_prices ? ' on' : ''; ?>">Preise anzeigen</span>
                <span class="ps-hint">Nur wenn aktiv und Hinweise unten ausgefüllt — sonst keine Preislisten.</span>
              </div>
              <div class="ps-field">
                <label class="ps-toggle" for="paxdesign_chat_auto_booking">
                  <input type="checkbox" id="paxdesign_chat_auto_booking" name="paxdesign_chat_auto_booking"
                         value="1" <?php checked($chat_auto_booking); ?>>
                  <span class="ps-toggle-track"></span>
                </label>
                <span class="ps-toggle-label<?php echo $chat_auto_booking ? ' on' : ''; ?>">Booking automatisch anbieten</span>
                <span class="ps-hint">Termin-Marker öffnet die Buchung im gleichen Widget.</span>
              </div>
            </div>

            <div class="ps-field" id="paxdesignChatPriceHintsWrap"<?php echo $chat_show_prices ? '' : ' style="display:none"'; ?>>
              <label class="ps-label" for="paxdesign_chat_price_hints">Preishinweise (optional)</label>
              <textarea id="paxdesign_chat_price_hints" name="paxdesign_chat_price_hints" rows="3"
                        placeholder="Nur bei expliziter Nachfrage nennen, z.B. Orientierungswerte…"><?php echo esc_textarea($chat_price_hints); ?></textarea>
            </div>

            <?php if ($chat_last_model || $chat_last_error || $chat_last_test) : ?>
            <div class="ps-info-block">
              <strong>Letzter Verbindungsstatus</strong>
              <?php if ($chat_last_model) : ?>
                <p>Aktives Modell bei letzter erfolgreicher Antwort: <code><?php echo esc_html($chat_last_model); ?></code></p>
              <?php endif; ?>
              <?php if ($chat_last_error) : ?>
                <p style="color:#996800;">Letzter Fehler: <?php echo esc_html($chat_last_error); ?></p>
              <?php endif; ?>
              <?php if ($chat_last_test) : ?>
                <p>Letzter Test: <?php echo esc_html(date_i18n('d.m.Y H:i', $chat_last_test)); ?></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="ps-test-bar">
              <div class="ps-test-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                OpenAI Verbindung testen
              </div>
              <div class="ps-test-controls">
                <button type="button" class="ps-btn ps-btn-secondary" id="paxdesignTestOpenAI">Verbindung testen</button>
                <span class="ps-test-result" id="paxdesignOpenAITestResult"></span>
              </div>
            </div>

            <!-- 7. Sicherheit -->
            <div class="ps-divider"><span>Sicherheit</span></div>

            <div class="ps-info-block">
              <strong>Schutzmaßnahmen</strong>
              <ul class="ps-info-list">
                <li>Serverseitige Verarbeitung mit Nonce, Honeypot &amp; Rate Limiting</li>
                <li>Streaming-Antworten · Shared Secret · Eingabevalidierung</li>
                <li>Keine API-Keys oder Secrets im Frontend oder in Logs</li>
                <li>Passwort- und Key-Felder werden maskiert gespeichert und angezeigt</li>
              </ul>
            </div>
          </div>
        </section>

      </div><!-- /ps-main -->

      <aside class="ps-sidebar">

        <!-- Save -->
        <div class="ps-card">
          <div class="ps-card-body">
            <span class="ps-save-hint">Änderungen werden nach dem Speichern sofort auf der Website wirksam.</span>
            <?php submit_button('Einstellungen speichern', 'primary', 'submit', false, array('class' => 'ps-btn ps-btn-primary button button-primary')); ?>
          </div>
        </div>

        <!-- 8. E-Mail-Ablauf -->
        <div class="ps-card">
          <div class="ps-card-head">
            <div class="ps-card-icon ps-card-icon--blue">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="ps-card-head-text">
              <h2>E-Mail-Ablauf</h2>
            </div>
          </div>
          <div class="ps-card-body">
            <ul class="ps-flow-list">
              <li>
                <span class="ps-flow-dot ps-dot-purple"></span>
                <div><strong>Buchungseingang</strong><p>Admin-Postfach erhält die Anfrage. Team-Mitglied wird in CC gesetzt.</p></div>
              </li>
              <li>
                <span class="ps-flow-dot ps-dot-green"></span>
                <div><strong>Kundenbestätigung</strong><p>Automatische Bestätigungs-E-Mail an den Kunden.</p></div>
              </li>
              <li>
                <span class="ps-flow-dot ps-dot-amber"></span>
                <div><strong>Absender</strong><p>Alle E-Mails werden über die konfigurierte SMTP-Adresse gesendet.</p></div>
              </li>
            </ul>
          </div>
        </div>

        <!-- 9. Schnellzugriff -->
        <div class="ps-card">
          <div class="ps-card-body">
            <span class="ps-links-title">Schnellzugriff</span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking')); ?>" class="ps-quick-link">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Buchungsübersicht
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking-team')); ?>" class="ps-quick-link">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Team Management
            </a>
          </div>
        </div>

      </aside><!-- /ps-sidebar -->

    </div><!-- /ps-layout -->
  </form>
</div><!-- /pax-settings -->
