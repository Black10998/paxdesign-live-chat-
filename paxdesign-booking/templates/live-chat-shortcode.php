<?php
/**
 * Front-end admin console — [paxdesign_live_chat] shortcode only.
 *
 * @var string $agent_name
 * @var string $agent_avatar
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="pax-live-console pax-live-app" id="paxLiveChatDashboard" data-context="shortcode">
  <header class="pax-live-console__bar pax-live-app__header" role="banner" aria-label="Live Chat">
    <div class="pax-live-console__brand pax-live-app__brand">
      <button type="button" class="pax-live-console__profile-btn" id="paxLiveAgentProfileBtn" aria-label="Agent-Profil anzeigen">
      <?php if ($agent_avatar) : ?>
        <img class="pax-live-console__logo pax-live-app__avatar" src="<?php echo esc_url($agent_avatar); ?>" alt="" width="44" height="44" loading="lazy">
      <?php else : ?>
        <div class="pax-live-console__logo pax-live-console__logo--fallback pax-live-app__avatar" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
      <?php endif; ?>
      </button>
      <div class="pax-live-console__brand-text">
        <h1 class="pax-live-console__title">Live Chat</h1>
        <p class="pax-live-console__subtitle pax-live-console__agent-role"><?php echo esc_html(PAXdesign_Chat_Live::get_agent_role()); ?></p>
        <p class="pax-live-console__subtitle pax-live-console__subtitle-meta">
          <span class="pax-live-app__status-dot" aria-hidden="true"></span>
          <?php echo esc_html($agent_name); ?> · Online
        </p>
      </div>
    </div>
    <div class="pax-live-console__stats pax-live-app__stats">
      <span class="pax-live-console__stat pax-live-console__stat--live" title="Live-Anfragen">
        <span class="pax-live-app__stat-icon" aria-hidden="true">●</span>
        <span id="paxLiveChatLiveCount">0</span>
      </span>
      <span class="pax-live-console__stat" title="Aktive Chats">
        <span id="paxLiveChatCount">0</span> Chats
      </span>
    </div>
  </header>

  <div class="pax-live-console__workspace pax-live-app__body">
    <aside class="pax-live-console__list-pane pax-live-app__sidebar" aria-label="Unterhaltungen">
      <div class="pax-live-console__list-head pax-live-app__sidebar-head">
        <div class="pax-live-console__list-title">
          <strong>Nachrichten</strong>
        </div>
        <div class="pax-live-console__list-search pax-live-app__search-row">
          <input type="search" id="paxLiveChatSearch" class="pax-live-console__search pax-live-app__search" placeholder="Suchen …" autocomplete="off">
          <button type="button" class="pax-live-console__btn pax-live-console__btn--icon pax-live-app__icon-btn" id="paxLiveChatRefresh" aria-label="Aktualisieren">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
          </button>
        </div>
      </div>
      <div id="paxLiveChatList" class="pax-live-console__list-scroll pax-live-app__chat-list">
        <p class="pax-live-console__empty">Lade Chats …</p>
      </div>
    </aside>

    <section class="pax-live-console__chat-pane pax-live-app__conversation" aria-label="Chat">
      <div id="paxLiveChatPlaceholder" class="pax-live-console__chat-empty pax-live-app__empty">
        <div class="pax-live-console__chat-empty-icon pax-live-app__empty-icon" aria-hidden="true">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <p class="pax-live-console__chat-empty-title">PAX Live Chat</p>
        <p class="pax-live-console__chat-empty-text">Wählen Sie eine Unterhaltung links, um zu antworten. Live-Anfragen erscheinen oben mit roter Markierung.</p>
      </div>

      <div id="paxLiveChatActive" class="pax-live-console__chat-active pax-live-app__thread" hidden>
        <div class="pax-live-console__chat-head pax-live-app__thread-head" role="banner">
          <button type="button" class="pax-live-console__back pax-live-app__back" id="paxLiveChatBack" aria-label="Zurück">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <div class="pax-live-console__chat-ident pax-live-app__thread-meta">
            <div class="pax-live-console__chat-title-row">
              <strong id="paxLiveChatSessionLabel" class="pax-live-console__session">Kunde</strong>
              <span id="paxLiveChatSessionMeta" class="pax-live-console__session-id"></span>
            </div>
            <div class="pax-live-console__chat-sub-row">
              <span id="paxLiveChatService" class="pax-live-console__meta-line pax-live-app__thread-topic" hidden></span>
              <span id="paxLiveChatUpdated" class="pax-live-console__meta-time"></span>
              <span id="paxLiveChatSessionRating" class="pax-live-console__session-rating" hidden></span>
            </div>
          </div>
          <span id="paxLiveChatHandlerBadge" class="pax-live-badge pax-live-badge--ai pax-live-app__handler-chip">KI aktiv</span>
        </div>

        <div id="paxLiveChatMessages" class="pax-live-console__chat-scroll pax-live-app__messages" aria-live="polite"></div>

        <div class="pax-live-console__chat-foot pax-live-app__composer-wrap">
          <div class="pax-live-console__toolbar pax-live-app__actions">
            <button type="button" class="pax-live-console__btn pax-live-console__btn--primary pax-live-app__action" id="paxLiveChatTakeover">Übernehmen</button>
            <button type="button" class="pax-live-console__btn pax-live-console__btn--ghost pax-live-app__action" id="paxLiveChatRelease" hidden>KI</button>
            <button type="button" class="pax-live-console__btn pax-live-console__btn--primary pax-live-app__action" id="paxLiveChatReopen" hidden>Öffnen</button>
            <button type="button" class="pax-live-console__btn pax-live-console__btn--danger pax-live-app__action" id="paxLiveChatClose">Schließen</button>
          </div>

          <div class="pax-live-console__assist pax-live-app__assist" id="paxLiveChatAssist" hidden>
            <div class="pax-live-console__quick-replies" id="paxLiveChatQuickReplies" aria-label="Schnellantworten"></div>
            <div class="pax-live-console__ai-suggest" id="paxLiveChatAiSuggest" aria-label="KI-Vorschläge" hidden></div>
          </div>

          <div class="pax-live-console__compose pax-live-app__composer" id="paxLiveChatCompose">
            <p id="paxLiveChatComposeHint" class="pax-live-console__compose-hint" hidden></p>
            <div id="paxLiveChatReplyBar" class="pax-live-console__reply-bar" hidden>
              <div class="pax-live-console__reply-inner">
                <span class="pax-live-console__reply-label">Antwort auf</span>
                <span id="paxLiveChatReplyPreview" class="pax-live-console__reply-preview"></span>
                <button type="button" class="pax-live-console__reply-clear" id="paxLiveChatReplyClear" aria-label="Antwort abbrechen">×</button>
              </div>
            </div>
            <div class="pax-live-console__compose-row pax-live-app__compose-row">
              <button type="button" class="pax-live-dashboard__quick-links pax-live-console__quick-links pax-live-app__quick-links" id="paxLiveChatQuickLinks" title="Website-Links senden" aria-label="Website-Links senden" hidden>+</button>
              <label class="pax-live-console__attach pax-live-app__attach" id="paxLiveChatAttachLabel" title="Foto senden">
                <input type="file" id="paxLiveChatAttach" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </label>
              <textarea id="paxLiveChatInput" rows="1" placeholder="Nachricht schreiben …"></textarea>
              <button type="button" class="pax-live-console__btn pax-live-console__btn--primary pax-live-console__btn--send pax-live-app__send" id="paxLiveChatSend" aria-label="Senden">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
window.paxLiveChatAdmin = <?php echo wp_json_encode(array_merge(
    PAXdesign_Chat_Live::get_agent_public_config(),
    array(
        'adminName'    => PAXdesign_Chat_Live::get_agent_display_name(),
        'quickReplies' => PAXdesign_Chat_Live::get_admin_quick_replies(),
        'quickLinks'   => PAXdesign_Chat_Quick_Links::get_links(),
    )
)); ?>;
</script>

<div class="pax-live-quick-links-modal" id="paxLiveChatQuickLinksModal" hidden role="dialog" aria-modal="true" aria-labelledby="paxLiveQuickLinksTitle">
  <div class="pax-live-quick-links-modal__backdrop" data-quick-links-close></div>
  <div class="pax-live-quick-links-modal__card">
    <button type="button" class="pax-live-quick-links-modal__close" data-quick-links-close aria-label="Schließen">×</button>
    <h4 id="paxLiveQuickLinksTitle" class="pax-live-quick-links-modal__title">Website-Links</h4>
    <p class="pax-live-quick-links-modal__hint">Wählen Sie eine Seite — der Kunde erhält einen klickbaren Button.</p>
    <div class="pax-live-quick-links-modal__list" id="paxLiveChatQuickLinksList"></div>
  </div>
</div>

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
