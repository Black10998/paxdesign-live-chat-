<?php
/**
 * Booking Widget Template
 * Scoped UI — v3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$team_members = PAXdesign_Booking::get_instance()->get_team_members();
?>

<div id="paxdesign-booking-root" class="paxdesign-booking paxdesign-booking-wrapper paxdesign-booking-root" aria-live="polite">

  <!-- Floating Apple-style Support Message launcher -->
  <div
    class="paxdesign-booking-button"
    role="button"
    tabindex="0"
    aria-label="<?php echo esc_attr__('Support Message öffnen', 'paxdesign-booking'); ?>"
  >
    <span class="paxdesign-booking-launcher-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <path
          d="M7.5 19.5 4 22V7.5A3.5 3.5 0 0 1 7.5 4h9A3.5 3.5 0 0 1 20 7.5v8a3.5 3.5 0 0 1-3.5 3.5H7.5Z"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linejoin="round"
        />
        <path
          d="M8.25 10.25h7.5M8.25 13.5h4.75"
          stroke="currentColor"
          stroke-width="1.7"
          stroke-linecap="round"
        />
      </svg>
    </span>
    <span class="paxdesign-booking-launcher-label">Support Message</span>
  </div>

  <!-- Booking Panel -->
  <div class="paxdesign-booking-widget" aria-hidden="true">
    <div class="paxdesign-booking-frame">
      <div class="paxdesign-booking-frame-inner paxdesign-booking-container">

        <div class="paxdesign-booking-header">
          <div class="paxdesign-booking-header-content">
            <?php if (PAXdesign_Chat::get_instance()->is_enabled()) :
              $live_agent = PAXdesign_Chat_Live::get_agent_public_config();
            ?>
            <button type="button" class="paxdesign-booking-header-agent" id="paxdesignChatAgentProfile" aria-label="<?php echo esc_attr__('Agent-Profil anzeigen', 'paxdesign-booking'); ?>">
              <?php if (!empty($live_agent['avatar'])) : ?>
                <img class="paxdesign-booking-header-agent-avatar" src="<?php echo esc_url($live_agent['avatar']); ?>" alt="" width="36" height="36" loading="lazy">
              <?php endif; ?>
            </button>
            <div class="paxdesign-booking-header-text">
              <h3 class="paxdesign-booking-header-title" id="paxdesignWidgetTitle">Live Chat</h3>
              <p class="paxdesign-booking-header-subtitle paxdesign-booking-header-agent-role" id="paxdesignWidgetSubtitle"><?php echo esc_html($live_agent['role']); ?></p>
            </div>
            <?php else : ?>
            <h3 class="paxdesign-booking-header-title" id="paxdesignWidgetTitle">Termin buchen</h3>
            <p class="paxdesign-booking-header-subtitle" id="paxdesignWidgetSubtitle">PAXdesign Team</p>
            <?php endif; ?>
          </div>
          <div class="paxdesign-booking-header-actions">
            <?php if (PAXdesign_Chat::get_instance()->is_enabled()) : ?>
            <button type="button" class="paxdesign-booking-chat-notify" aria-label="Benachrichtigungston ein/aus" aria-pressed="true" title="Benachrichtigungston an">
              <span class="paxdesign-bell-icon paxdesign-bell-icon--on" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              </span>
              <span class="paxdesign-bell-icon paxdesign-bell-icon--off" aria-hidden="true" hidden>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="4" y1="4" x2="20" y2="20" stroke="#e74c3c" stroke-width="2"/></svg>
              </span>
            </button>
            <?php endif; ?>
            <button type="button" class="paxdesign-booking-close" aria-label="<?php echo esc_attr__('Close chat', 'paxdesign-booking'); ?>">
              <span class="paxdesign-booking-close-label"><?php echo esc_html__('Close', 'paxdesign-booking'); ?></span>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
        </div>

        <?php if (PAXdesign_Chat::get_instance()->is_enabled()) : ?>
        <div class="paxdesign-booking-mode-switch" role="tablist" aria-label="Live Chat oder Termin buchen">
          <button type="button" class="paxdesign-booking-mode-btn paxdesign-is-active" data-mode="chat" role="tab" aria-selected="true" aria-controls="paxdesignChatPanel">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Live Chat
          </button>
          <button type="button" class="paxdesign-booking-mode-btn" data-mode="booking" role="tab" aria-selected="false" aria-controls="paxdesignBookingPanel">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Termin buchen
          </button>
        </div>

        <?php if (get_option('paxdesign_customer_require_login_for_chat', '1') === '1') : ?>
        <div class="paxdesign-booking-chat-auth-gate" id="paxdesignChatAuthGate" hidden>
          <button type="button" class="paxdesign-booking-chat-auth-gate-close" id="paxdesignChatAuthClose" aria-label="<?php echo esc_attr__('Close', 'paxdesign-booking'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
          <div class="paxdesign-booking-chat-auth-gate-inner" role="region" aria-labelledby="paxdesignChatAuthGateTitle">
            <div class="paxdesign-booking-chat-auth-gate-intro">
              <h3 class="paxdesign-booking-chat-auth-gate-title" id="paxdesignChatAuthGateTitle"><?php echo esc_html__('Continue to Live Chat', 'paxdesign-booking'); ?></h3>
              <p class="paxdesign-booking-chat-auth-gate-sub" id="paxdesignChatAuthGateSubtitle"><?php echo esc_html__('Sign in or create a free account to message our team.', 'paxdesign-booking'); ?></p>
              <p class="paxdesign-booking-chat-auth-gate-verify" id="paxdesignChatAuthGateVerify" hidden><?php echo esc_html__('Verify your email to start chatting.', 'paxdesign-booking'); ?></p>
            </div>
            <div class="paxdesign-booking-chat-auth-actions" id="paxdesignChatAuthActions">
              <button type="button" class="paxdesign-booking-chat-auth-gate-btn" id="paxdesignChatAuthSignIn" data-auth-view="login"><?php echo esc_html__('Sign In', 'paxdesign-booking'); ?></button>
              <button type="button" class="paxdesign-booking-chat-auth-gate-btn paxdesign-booking-chat-auth-gate-btn--primary" id="paxdesignChatAuthRegister" data-auth-view="register"><?php echo esc_html__('Create Account', 'paxdesign-booking'); ?></button>
            </div>
            <div class="paxdesign-booking-chat-auth-inline" id="paxdesignChatAuthInline" aria-live="polite"></div>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Booking Mode -->
        <div class="paxdesign-booking-mode-panel" id="paxdesignBookingPanel" data-mode="booking" role="tabpanel" aria-hidden="true">

        <div class="paxdesign-booking-body">

          <div class="paxdesign-booking-steps" aria-hidden="true">
            <div class="paxdesign-booking-step-dot paxdesign-is-active"></div>
            <div class="paxdesign-booking-step-dot"></div>
            <div class="paxdesign-booking-step-dot"></div>
            <div class="paxdesign-booking-step-dot"></div>
          </div>

          <!-- Step 1: Team Selection -->
          <div class="paxdesign-booking-content paxdesign-is-active" data-step="1">
            <h3 class="paxdesign-booking-step-title">Wählen Sie Ihren Ansprechpartner</h3>
            <div class="paxdesign-booking-team-grid">
              <?php foreach ($team_members as $key => $member) :
                if (isset($member['enabled']) && $member['enabled'] === false) continue;

                $availability = isset($member['availability']) ? $member['availability'] : 'available';
                $is_selectable      = ($availability === 'available');
                $availability_class = 'paxdesign-availability-' . esc_attr($availability);

                $availability_labels = array(
                  'available'   => 'Verfügbar',
                  'busy'        => 'Beschäftigt',
                  'vacation'    => 'Im Urlaub',
                  'unavailable' => 'Nicht verfügbar',
                );
                $availability_label = isset($availability_labels[$availability]) ? $availability_labels[$availability] : '';
              ?>
              <div class="paxdesign-booking-team-card <?php echo $availability_class; ?><?php echo !$is_selectable ? ' paxdesign-not-selectable' : ''; ?><?php echo !empty($member['is_founder']) ? ' paxdesign-booking-team-card--founder' : ''; ?>"
                   data-member="<?php echo esc_attr($key); ?>"
                   data-has-services="<?php echo (!empty($member['has_services'])) ? 'true' : 'false'; ?>"
                   data-availability="<?php echo esc_attr($availability); ?>"
                   <?php echo !$is_selectable ? 'data-disabled="true"' : ''; ?>>
                <div class="paxdesign-booking-team-avatar">
                  <img src="<?php echo esc_url($member['image']); ?>" alt="<?php echo esc_attr($member['name']); ?>" width="120" height="120" loading="lazy" data-skip-lazy="1">
                  <?php if (!$is_selectable) : ?>
                  <span class="paxdesign-availability-badge paxdesign-badge-<?php echo esc_attr($availability); ?>">
                    <?php echo esc_html($availability_label); ?>
                  </span>
                  <?php endif; ?>
                </div>
                <div class="paxdesign-booking-team-info">
                  <?php if (!empty($member['is_founder'])) : ?>
                  <span class="paxdesign-booking-founder-badge">Gründer &amp; Inhaber</span>
                  <?php endif; ?>
                  <h3 class="paxdesign-booking-team-name"><?php echo esc_html($member['name']); ?></h3>
                  <p class="paxdesign-booking-team-role"><?php echo esc_html($member['role']); ?></p>
                  <?php if (!empty($member['role_en'])) : ?>
                  <p class="paxdesign-booking-team-role-en"><?php echo esc_html($member['role_en']); ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Step 1.5: Service Selection -->
          <div class="paxdesign-booking-content" data-step="1.5">
            <h3 class="paxdesign-booking-step-title">Wählen Sie Ihren gewünschten Service</h3>
            <div class="paxdesign-booking-services-grid" id="paxdesignServicesGrid"></div>
          </div>

          <!-- Step 2: Date & Time -->
          <div class="paxdesign-booking-content" data-step="2">
            <h3 class="paxdesign-booking-step-title">Datum und Uhrzeit wählen</h3>
            <div class="paxdesign-booking-selected-service paxdesign-booking-is-hidden" id="paxdesignSelectedServiceBanner" role="status" aria-live="polite">
              <div class="paxdesign-booking-selected-service-head">
                <span class="paxdesign-booking-selected-service-label">Ausgewählter Service:</span>
                <strong class="paxdesign-booking-selected-service-name" id="paxdesignSelectedServiceName"></strong>
              </div>
              <p class="paxdesign-booking-selected-service-description paxdesign-booking-is-hidden" id="paxdesignSelectedServiceDescription"></p>
              <div class="paxdesign-booking-selected-service-details paxdesign-booking-is-hidden" id="paxdesignSelectedServiceDetailsWrap">
                <span class="paxdesign-booking-selected-service-details-label">Details:</span>
                <ul class="paxdesign-booking-selected-service-features" id="paxdesignSelectedServiceFeatures"></ul>
              </div>
              <span class="paxdesign-booking-selected-service-category paxdesign-booking-is-hidden" id="paxdesignSelectedServiceCategory"></span>
            </div>
            <div class="paxdesign-booking-calendar-container">

              <div class="paxdesign-booking-calendar">
                <div class="paxdesign-booking-calendar-header">
                  <button type="button" class="paxdesign-booking-calendar-nav paxdesign-nav-prev" aria-label="Vorheriger Monat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <polyline points="15 18 9 12 15 6"/>
                    </svg>
                  </button>
                  <span class="paxdesign-booking-calendar-title" id="paxdesignCalendarTitle"></span>
                  <button type="button" class="paxdesign-booking-calendar-nav paxdesign-nav-next" aria-label="Nächster Monat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <polyline points="9 18 15 12 9 6"/>
                    </svg>
                  </button>
                </div>

                <div class="paxdesign-booking-calendar-weekdays">
                  <span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span>
                </div>

                <div class="paxdesign-booking-calendar-days" id="paxdesignCalendarDays"></div>
              </div>

              <div class="paxdesign-booking-timeslots">
                <h3 class="paxdesign-booking-timeslots-title">Verfügbare Zeiten</h3>
                <p class="paxdesign-booking-timeslots-date" id="paxdesignSelectedDateDisplay">Bitte wählen Sie ein Datum</p>
                <div class="paxdesign-booking-timeslots-grid" id="paxdesignTimeslotsGrid"></div>
              </div>

            </div>

            <div class="paxdesign-booking-actions">
              <button type="button" class="paxdesign-booking-btn paxdesign-booking-btn-back">Zurück</button>
              <button type="button" class="paxdesign-booking-btn paxdesign-booking-btn-next" disabled id="paxdesignNextToDetailsBtn">Weiter</button>
            </div>
          </div>

          <!-- Step 3: Details -->
          <div class="paxdesign-booking-content" data-step="3">
            <h3 class="paxdesign-booking-step-title">Ihre Kontaktdaten</h3>

            <div class="paxdesign-booking-summary">
              <div class="paxdesign-booking-summary-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                  <span class="paxdesign-booking-summary-label">Ansprechpartner</span>
                  <div class="paxdesign-booking-summary-value" id="paxdesignSummaryMember"></div>
                </div>
              </div>

              <div class="paxdesign-booking-summary-item paxdesign-booking-is-hidden" id="paxdesignSummaryServiceItem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                  <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
                <div class="paxdesign-booking-summary-service">
                  <span class="paxdesign-booking-summary-label">Service</span>
                  <div class="paxdesign-booking-summary-value" id="paxdesignSummaryService"></div>
                </div>
              </div>

              <div class="paxdesign-booking-summary-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="16" y1="2" x2="16" y2="6"/>
                  <line x1="8" y1="2" x2="8" y2="6"/>
                  <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <div>
                  <span class="paxdesign-booking-summary-label">Datum & Uhrzeit</span>
                  <span class="paxdesign-booking-summary-value" id="paxdesignSummaryDateTime"></span>
                </div>
              </div>
            </div>

            <form class="paxdesign-booking-form" id="paxdesignBookingDetailsForm" novalidate>
              <div class="paxdesign-booking-form-group">
                <label for="paxdesignBookingName">Ihr Name *</label>
                <input type="text" id="paxdesignBookingName" required placeholder="Max Mustermann" autocomplete="name">
              </div>

              <div class="paxdesign-booking-form-group">
                <label for="paxdesignBookingEmail">E-Mail-Adresse *</label>
                <input type="email" id="paxdesignBookingEmail" required placeholder="max@beispiel.com" autocomplete="email">
              </div>

              <div class="paxdesign-booking-form-group">
                <label for="paxdesignBookingPhone">Telefonnummer</label>
                <input type="tel" id="paxdesignBookingPhone" placeholder="+43 123 456789" autocomplete="tel">
              </div>

              <div class="paxdesign-booking-form-group">
                <label for="paxdesignBookingPurpose">Zweck des Termins *</label>
                <select id="paxdesignBookingPurpose" required>
                  <option value="">Bitte wählen...</option>
                  <option value="beratung">Beratungsgespräch</option>
                  <option value="projekt">Projektbesprechung</option>
                  <option value="support">Technischer Support</option>
                  <option value="demo">Produkt-Demo</option>
                  <option value="angebot">Angebotserstellung</option>
                  <option value="sonstiges">Sonstiges</option>
                </select>
              </div>

              <div class="paxdesign-booking-form-group">
                <label for="paxdesignBookingMessage">Nachricht (optional)</label>
                <textarea id="paxdesignBookingMessage" rows="4" placeholder="Teilen Sie uns mit, worum es in dem Termin gehen soll..."></textarea>
              </div>

              <div class="paxdesign-booking-checkbox">
                <input type="checkbox" id="paxdesignBookingPrivacy" required>
                <label for="paxdesignBookingPrivacy">
                  Ich akzeptiere die <a href="<?php echo esc_url(home_url('/datenschutz')); ?>" target="_blank" rel="noopener noreferrer">Datenschutzerklärung</a>
                  und stimme der Verarbeitung meiner Daten zu. *
                </label>
              </div>
            </form>

            <div class="paxdesign-booking-actions">
              <button type="button" class="paxdesign-booking-btn paxdesign-booking-btn-back">Zurück</button>
              <button type="button" class="paxdesign-booking-btn paxdesign-booking-btn-submit">Termin buchen</button>
            </div>
          </div>

          <!-- Success -->
          <div class="paxdesign-booking-success" id="paxdesignBookingSuccess">
            <div class="paxdesign-booking-success-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>
            <h2 class="paxdesign-booking-success-title">Terminanfrage erfolgreich gesendet!</h2>
            <p class="paxdesign-booking-success-text">Wir haben Ihre Anfrage erhalten und werden uns in Kürze bei Ihnen melden. Eine Bestätigung wurde an Ihre E-Mail-Adresse gesendet.</p>
            <div class="paxdesign-booking-success-details" id="paxdesignSuccessDetails"></div>
            <button type="button" class="paxdesign-booking-btn paxdesign-booking-btn-submit paxdesign-booking-close">Schließen</button>
          </div>

          <div class="paxdesign-booking-tags" aria-hidden="true">
            <span class="paxdesign-tag-step" data-step-tag="1">Team</span>
            <span class="paxdesign-tag-step" data-step-tag="2">Termin</span>
            <span class="paxdesign-tag-step" data-step-tag="3">Details</span>
          </div>

        </div>
        </div><!-- /paxdesign-booking-mode-panel booking -->

        <?php if (PAXdesign_Chat::get_instance()->is_enabled()) : ?>
        <!-- AI Chat Mode -->
        <div class="paxdesign-booking-mode-panel paxdesign-is-active" id="paxdesignChatPanel" data-mode="chat" role="tabpanel" aria-hidden="false">

          <div class="paxdesign-chat-shell-loader" aria-live="polite" aria-busy="false" hidden>
            <div class="paxdesign-chat-shell-loader-inner">
              <span class="paxdesign-chat-shell-loader-spinner" aria-hidden="true"></span>
              <span class="paxdesign-chat-shell-loader-label"><?php echo esc_html__('Chat wird geladen…', 'paxdesign-booking'); ?></span>
            </div>
          </div>

          <div class="paxdesign-booking-chat-readiness" id="paxdesignChatReadiness" hidden aria-live="polite" aria-busy="true">
            <div class="paxdesign-booking-chat-readiness-panel">
              <div class="paxdesign-booking-chat-readiness-loading" id="paxdesignChatReadinessLoading">
                <div class="paxdesign-chat-loader" aria-hidden="true">
                  <div class="bar1"></div>
                  <div class="bar2"></div>
                  <div class="bar3"></div>
                  <div class="bar4"></div>
                  <div class="bar5"></div>
                  <div class="bar6"></div>
                  <div class="bar7"></div>
                  <div class="bar8"></div>
                  <div class="bar9"></div>
                  <div class="bar10"></div>
                  <div class="bar11"></div>
                  <div class="bar12"></div>
                </div>
                <p class="paxdesign-booking-chat-readiness-text" id="paxdesignChatReadinessText"><?php echo esc_html__('Connecting to chat…', 'paxdesign-booking'); ?></p>
              </div>
              <div class="paxdesign-booking-chat-readiness-error" id="paxdesignChatReadinessError" hidden>
                <p class="paxdesign-booking-chat-readiness-error-text" id="paxdesignChatReadinessErrorText"></p>
                <div class="paxdesign-booking-chat-readiness-actions">
                  <button type="button" class="paxdesign-booking-chat-readiness-btn" id="paxdesignChatReadinessRetry"><?php echo esc_html__('Retry', 'paxdesign-booking'); ?></button>
                  <button type="button" class="paxdesign-booking-chat-readiness-btn paxdesign-booking-chat-readiness-btn--ghost" id="paxdesignChatReadinessClose"><?php echo esc_html__('Close', 'paxdesign-booking'); ?></button>
                </div>
              </div>
            </div>
          </div>

          <div class="paxdesign-booking-body paxdesign-booking-chat-body">
            <div class="paxdesign-booking-chat-messages paxdesign-chat-unpinned" role="log" aria-relevant="additions">
              <div class="paxdesign-booking-chat-entry" id="paxdesignChatEntry" hidden aria-hidden="true">
                <p class="paxdesign-booking-chat-entry-kicker">PAXdesign Support</p>
                <p class="paxdesign-booking-chat-entry-title"><?php echo esc_html__('Möchten Sie mit einem Live-Agent chatten?', 'paxdesign-booking'); ?></p>
                <p class="paxdesign-booking-chat-entry-sub"><?php echo esc_html__('Wählen Sie, wie wir Ihnen helfen — Live Chat oder KI-Assistent.', 'paxdesign-booking'); ?></p>
                <div class="paxdesign-booking-chat-entry-actions">
                  <button type="button" class="paxdesign-booking-chat-entry-btn paxdesign-booking-chat-entry-btn--live" data-entry="live">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/></svg>
                    Live Chat
                  </button>
                  <button type="button" class="paxdesign-booking-chat-entry-btn paxdesign-booking-chat-entry-btn--ai" data-entry="ai">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2a4 4 0 0 1 4 4v1h1a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V10a3 3 0 0 1 3-3h1V6a4 4 0 0 1 4-4z"/></svg>
                    KI-Assistent
                  </button>
                </div>
              </div>
              <div class="paxdesign-booking-chat-welcome" hidden>
                <p class="paxdesign-booking-step-title">Live Chat</p>
                <p class="paxdesign-booking-chat-welcome-text"><?php echo esc_html(PAXdesign_Chat::get_instance()->get_greeting()); ?></p>
              </div>
              <div class="paxdesign-booking-chat-thread" aria-live="polite"></div>
            </div>
          </div>

          <div class="paxdesign-booking-chat-quick-actions" aria-label="Schnellaktionen" hidden></div>

            <div class="paxdesign-booking-chat-input-area">
            <div class="paxdesign-booking-chat-closed-bar" hidden>
              <p class="paxdesign-booking-chat-closed-text">Dieses Gespräch wurde beendet.</p>
              <div class="paxdesign-booking-chat-rating" id="paxdesignChatRating" hidden>
                <p class="paxdesign-booking-chat-rating-label"><?php echo esc_html__('Wie war der Chat?', 'paxdesign-booking'); ?></p>
                <div class="paxdesign-booking-chat-rating-actions" role="group" aria-label="<?php echo esc_attr__('Bewertung', 'paxdesign-booking'); ?>">
                  <button type="button" class="paxdesign-booking-chat-action paxdesign-booking-chat-action--like" data-feedback="like" aria-label="<?php echo esc_attr__('Gefällt mir', 'paxdesign-booking'); ?>" aria-pressed="false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                  </button>
                  <button type="button" class="paxdesign-booking-chat-action paxdesign-booking-chat-action--dislike" data-feedback="dislike" aria-label="<?php echo esc_attr__('Gefällt mir nicht', 'paxdesign-booking'); ?>" aria-pressed="false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.33 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>
                  </button>
                </div>
                <p class="paxdesign-booking-chat-rating-thanks" id="paxdesignChatRatingThanks" hidden><?php echo esc_html__('Danke für Ihre Bewertung!', 'paxdesign-booking'); ?></p>
              </div>
              <button type="button" class="paxdesign-booking-chat-new-session">Neues Gespräch starten</button>
            </div>
            <div class="paxdesign-booking-chat-reply-bar" hidden>
              <div class="paxdesign-booking-chat-reply-inner">
                <span class="paxdesign-booking-chat-reply-label">Antwort auf</span>
                <span class="paxdesign-booking-chat-reply-preview"></span>
                <button type="button" class="paxdesign-booking-chat-reply-clear" aria-label="Antwort abbrechen">×</button>
              </div>
            </div>
            <form class="paxdesign-booking-chat-form" autocomplete="off">
              <div class="paxdesign-booking-chat-composer-row">
                <div class="paxdesign-booking-chat-composer">
                  <div class="paxdesign-booking-chat-plus-wrap">
                    <button type="button" class="paxdesign-booking-chat-plus" aria-label="Schnellaktionen" aria-expanded="false">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                      <span class="paxdesign-booking-chat-plus-tooltip">Schnellaktionen</span>
                    </button>
                  </div>
                  <button type="button" class="paxdesign-booking-chat-media" aria-label="<?php echo esc_attr__('Foto senden', 'paxdesign-booking'); ?>" title="<?php echo esc_attr__('Foto senden', 'paxdesign-booking'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  </button>
                  <button type="button" class="paxdesign-booking-chat-file" aria-label="<?php echo esc_attr__('Datei senden', 'paxdesign-booking'); ?>" title="<?php echo esc_attr__('Datei senden', 'paxdesign-booking'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  </button>
                  <textarea
                    class="paxdesign-booking-chat-input"
                    placeholder="Message..."
                    rows="1"
                    maxlength="2000"
                    aria-label="Nachricht eingeben"
                  ></textarea>
                  <button type="button" class="paxdesign-booking-chat-voice" aria-label="<?php echo esc_attr__('Spracheingabe', 'paxdesign-booking'); ?>" aria-pressed="false" title="<?php echo esc_attr__('Spracheingabe', 'paxdesign-booking'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
                  </button>
                </div>
                <button type="button" class="paxdesign-booking-chat-send paxdesign-is-disabled" aria-label="Senden" aria-disabled="true">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                </button>
              </div>
              <input type="text" name="website" class="paxdesign-booking-chat-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
            </form>
          </div>

        </div><!-- /paxdesign-booking-mode-panel chat -->
        <?php endif; ?>

      </div>
    </div>
  </div>

  <?php if (PAXdesign_Chat::get_instance()->is_enabled()) :
    $live_agent = PAXdesign_Chat_Live::get_agent_public_config();
  ?>
  <div class="paxdesign-agent-profile" id="paxdesignAgentProfileModal" hidden role="dialog" aria-modal="true" aria-labelledby="paxdesignAgentProfileName">
    <div class="paxdesign-agent-profile__backdrop" data-profile-close></div>
    <div class="paxdesign-agent-profile__card">
      <button type="button" class="paxdesign-agent-profile__close" data-profile-close aria-label="<?php echo esc_attr__('Schließen', 'paxdesign-booking'); ?>">×</button>
      <?php if (!empty($live_agent['avatar'])) : ?>
        <img class="paxdesign-agent-profile__avatar" src="<?php echo esc_url($live_agent['avatar']); ?>" alt="" width="72" height="72" loading="lazy">
      <?php endif; ?>
      <h4 class="paxdesign-agent-profile__name" id="paxdesignAgentProfileName"><?php echo esc_html($live_agent['name']); ?></h4>
      <p class="paxdesign-agent-profile__role"><?php echo esc_html($live_agent['role']); ?></p>
      <p class="paxdesign-agent-profile__tagline"><?php echo esc_html($live_agent['tagline']); ?></p>
      <p class="paxdesign-agent-profile__bio"><?php echo esc_html($live_agent['bio']); ?></p>
    </div>
  </div>
  <?php endif; ?>

</div>
