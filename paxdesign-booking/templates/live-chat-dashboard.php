<?php
/**
 * Shared Live Chat dashboard markup (admin page + shortcode).
 *
 * @var bool   $is_shortcode Whether rendered via front-end shortcode.
 * @var string $context      'admin' or 'shortcode'.
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_shortcode  = !empty($is_shortcode);
$context       = $is_shortcode ? 'shortcode' : 'admin';
$wrapper_class = 'pax-live-dashboard pax-live-dashboard--' . esc_attr($context);
$agent_name    = PAXdesign_Chat_Live::get_agent_display_name();
$agent_avatar  = PAXdesign_Chat_Live::get_agent_avatar_url();
$agent_role    = PAXdesign_Chat_Live::get_agent_role();
$agent_tagline = PAXdesign_Chat_Live::get_agent_tagline();
$agent_bio     = PAXdesign_Chat_Live::get_agent_bio();
$settings_url  = admin_url('admin.php?page=paxdesign-booking-settings');
$team_url      = admin_url('admin.php?page=paxdesign-live-chat-team');
$tour_done     = (bool) get_user_meta(get_current_user_id(), 'pax_live_dashboard_tour_completed', true);
?>

<div class="<?php echo esc_attr($wrapper_class); ?>" id="paxLiveChatDashboard" data-context="<?php echo esc_attr($context); ?>">
  <header class="pax-live-dashboard__header">
    <div class="pax-live-dashboard__brand">
      <button type="button" class="pax-live-dashboard__agent-profile-btn" id="paxLiveAgentProfileBtn" aria-label="Agent-Profil anzeigen">
      <?php if ($agent_avatar) : ?>
        <img class="pax-live-dashboard__agent-logo" src="<?php echo esc_url($agent_avatar); ?>" alt="" width="40" height="40" loading="lazy">
      <?php else : ?>
        <div class="pax-live-dashboard__logo" aria-hidden="true">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
      <?php endif; ?>
      </button>
      <div>
        <h1 class="pax-live-dashboard__title">Live Chat</h1>
        <p class="pax-live-dashboard__subtitle pax-live-dashboard__agent-role"><?php echo esc_html($agent_role); ?></p>
        <p class="pax-live-dashboard__subtitle pax-live-dashboard__subtitle-meta"><?php echo $is_shortcode ? 'Support-Center' : 'Admin'; ?> · <?php echo esc_html(PAXdesign_Chat_Live::get_agent_display_name()); ?></p>
      </div>
    </div>
    <div class="pax-live-dashboard__stats">
      <span class="pax-live-stat pax-live-stat--live"><span id="paxLiveChatLiveCount">0</span> Live-Anfragen</span>
      <span class="pax-live-stat pax-live-stat--active"><span id="paxLiveChatCount">0</span> Chats</span>
    </div>
    <div class="pax-live-dashboard__header-actions">
      <button type="button" class="pax-live-btn pax-live-btn--ghost" id="paxLiveLanguageToggle" aria-label="Sprache wechseln">DE</button>
      <button type="button" class="pax-live-btn pax-live-btn--ghost" id="paxLiveRestartTour">Tour neu starten</button>
    </div>
  </header>

  <section class="pax-live-dashboard__activity-panel" id="paxLiveActivityPanel">
    <article class="pax-live-activity-card">
      <span class="pax-live-activity-card__label">Wartend</span>
      <strong class="pax-live-activity-card__value" id="paxLiveActivityWaiting">0</strong>
      <span class="pax-live-activity-card__meta">Kunden aktuell in Warteschlange</span>
    </article>
    <article class="pax-live-activity-card">
      <span class="pax-live-activity-card__label">Offen</span>
      <strong class="pax-live-activity-card__value" id="paxLiveActivityOpen">0</strong>
      <span class="pax-live-activity-card__meta">Aktive Konversationen heute</span>
    </article>
    <article class="pax-live-activity-card">
      <span class="pax-live-activity-card__label">Sofortkritisch</span>
      <strong class="pax-live-activity-card__value" id="paxLiveActivityUrgent">0</strong>
      <span class="pax-live-activity-card__meta">Live-/neue Requests</span>
    </article>
    <article class="pax-live-activity-card pax-live-activity-card--actions">
      <span class="pax-live-activity-card__label">Admin Schnellzugriff</span>
      <div class="pax-live-activity-actions">
        <a class="pax-live-activity-actions__link" href="<?php echo esc_url($settings_url); ?>">Profil & Settings</a>
        <a class="pax-live-activity-actions__link" href="<?php echo esc_url($settings_url); ?>#security">Geräte-Management</a>
        <a class="pax-live-activity-actions__link" href="<?php echo esc_url($team_url); ?>">Admin/Staff Tools</a>
      </div>
    </article>
  </section>

  <div class="pax-live-dashboard__grid">
    <aside class="pax-live-dashboard__sidebar">
      <div class="pax-live-dashboard__sidebar-top">
        <div class="pax-live-dashboard__sidebar-title">
          <strong>Chat-Liste</strong>
          <span class="pax-live-dashboard__hint">Links wählen · Rechts antworten</span>
        </div>
        <div class="pax-live-dashboard__sidebar-search">
          <input type="search" id="paxLiveChatSearch" class="pax-live-dashboard__search" placeholder="Chats suchen …" autocomplete="off">
          <button type="button" class="pax-live-btn pax-live-btn--ghost pax-live-btn--icon" id="paxLiveChatRefresh" aria-label="Aktualisieren">↻</button>
        </div>
      </div>
      <div id="paxLiveChatList" class="pax-live-dashboard__list">
        <p class="pax-live-dashboard__empty">Lade Chats …</p>
      </div>
    </aside>

    <main class="pax-live-dashboard__main">
      <div id="paxLiveChatPlaceholder" class="pax-live-dashboard__placeholder">
        <div class="pax-live-dashboard__placeholder-icon" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <p><strong>Kein Chat ausgewählt</strong><br>Wählen Sie links einen Chat. Kundennachrichten erscheinen links im Verlauf — Ihre Antworten rechts.</p>
      </div>

      <div id="paxLiveChatActive" class="pax-live-dashboard__conversation" hidden>
        <div class="pax-live-dashboard__conversation-head">
          <button type="button" class="pax-live-dashboard__back" id="paxLiveChatBack" aria-label="Zurück zur Chatliste">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <div class="pax-live-dashboard__conversation-ident">
            <div class="pax-live-dashboard__chat-title-row">
              <strong id="paxLiveChatSessionLabel" class="pax-live-dashboard__session">Kunde</strong>
              <span id="paxLiveChatSessionMeta" class="pax-live-dashboard__session-id"></span>
            </div>
            <div class="pax-live-dashboard__chat-sub-row">
              <span id="paxLiveChatService" class="pax-live-dashboard__meta-line" hidden></span>
              <span id="paxLiveChatUpdated" class="pax-live-dashboard__meta-time"></span>
              <span id="paxLiveChatSessionRating" class="pax-live-dashboard__session-rating" hidden></span>
            </div>
          </div>
          <span id="paxLiveChatHandlerBadge" class="pax-live-badge pax-live-badge--ai">KI aktiv</span>
        </div>

        <div id="paxLiveChatMessages" class="pax-live-dashboard__messages" aria-live="polite"></div>

        <div class="pax-live-dashboard__chat-footer">
          <div class="pax-live-dashboard__actions">
            <button type="button" class="pax-live-btn pax-live-btn--primary" id="paxLiveChatTakeover">Übernehmen</button>
            <button type="button" class="pax-live-btn pax-live-btn--ghost" id="paxLiveChatRelease" hidden>An KI zurückgeben</button>
            <button type="button" class="pax-live-btn pax-live-btn--primary" id="paxLiveChatReopen" hidden>Chat wieder öffnen</button>
            <button type="button" class="pax-live-btn pax-live-btn--danger" id="paxLiveChatClose">Chat schließen</button>
          </div>

          <div class="pax-live-dashboard__assist" id="paxLiveChatAssist" hidden>
            <div class="pax-live-dashboard__quick-replies" id="paxLiveChatQuickReplies" aria-label="Schnellantworten"></div>
            <div class="pax-live-dashboard__ai-suggest" id="paxLiveChatAiSuggest" aria-label="KI-Vorschläge" hidden></div>
          </div>

          <div class="pax-live-dashboard__compose" id="paxLiveChatCompose">
            <p id="paxLiveChatComposeHint" class="pax-live-dashboard__compose-hint" hidden></p>
            <div id="paxLiveChatReplyBar" class="pax-live-dashboard__reply-bar" hidden>
              <div class="pax-live-dashboard__reply-bar-inner">
                <span class="pax-live-dashboard__reply-label">Antwort auf</span>
                <span id="paxLiveChatReplyPreview" class="pax-live-dashboard__reply-preview"></span>
                <button type="button" class="pax-live-dashboard__reply-clear" id="paxLiveChatReplyClear" aria-label="Antwort abbrechen">×</button>
              </div>
            </div>
            <div class="pax-live-dashboard__compose-row">
              <label class="pax-live-dashboard__attach" id="paxLiveChatAttachLabel" title="Foto senden (nur Admin)">
                <input type="file" id="paxLiveChatAttach" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </label>
              <textarea id="paxLiveChatInput" rows="1" placeholder="Antwort an den Kunden …"></textarea>
              <button type="button" class="pax-live-btn pax-live-btn--primary" id="paxLiveChatSend">Senden</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
window.paxLiveChatAdmin = <?php echo wp_json_encode(array_merge(
    PAXdesign_Chat_Live::get_agent_public_config(),
    array(
        'adminName'    => PAXdesign_Chat_Live::get_agent_display_name(),
        'quickReplies' => PAXdesign_Chat_Live::get_admin_quick_replies(),
        'tourCompleted' => $tour_done,
    )
)); ?>;
</script>

<div class="pax-live-agent-profile" id="paxLiveAgentProfileModal" hidden role="dialog" aria-modal="true">
  <div class="pax-live-agent-profile__backdrop" data-live-profile-close></div>
  <div class="pax-live-agent-profile__card">
    <button type="button" class="pax-live-agent-profile__close" data-live-profile-close aria-label="Schließen">×</button>
    <?php if ($agent_avatar) : ?>
      <img class="pax-live-agent-profile__avatar" src="<?php echo esc_url($agent_avatar); ?>" alt="" width="72" height="72" loading="lazy">
    <?php endif; ?>
    <h4 class="pax-live-agent-profile__name"><?php echo esc_html($agent_name); ?></h4>
    <p class="pax-live-agent-profile__role"><?php echo esc_html($agent_role); ?></p>
    <p class="pax-live-agent-profile__tagline"><?php echo esc_html($agent_tagline); ?></p>
    <p class="pax-live-agent-profile__bio"><?php echo esc_html($agent_bio); ?></p>
  </div>
</div>
