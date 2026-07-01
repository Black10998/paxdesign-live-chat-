/**
 * PAXdesign AI Chat — Sales & Booking Assistant
 * Version: 3.38.0
 */
(function () {
  'use strict';

  var config = window.paxdesignChat;
  if (!config || !config.enabled) return;

  var root = document.getElementById('paxdesign-booking-root');
  if (!root) return;

  var messagesEl   = root.querySelector('.paxdesign-booking-chat-messages');
  var threadEl     = root.querySelector('.paxdesign-booking-chat-thread');
  var quickActions = root.querySelector('.paxdesign-booking-chat-quick-actions');
  var form         = root.querySelector('.paxdesign-booking-chat-form');
  var input        = root.querySelector('.paxdesign-booking-chat-input');
  var sendBtn      = root.querySelector('.paxdesign-booking-chat-send');
  var plusBtn      = root.querySelector('.paxdesign-booking-chat-plus');
  var notifyBtn    = root.querySelector('.paxdesign-booking-chat-notify');
  var historyBtn   = root.querySelector('#paxdesignChatHistoryBtn');
  var historyPanel = root.querySelector('#paxdesignChatHistoryPanel');
  var historyList  = root.querySelector('#paxdesignChatHistoryList');
  var historyDetail = root.querySelector('#paxdesignChatHistoryDetail');
  var historyMessages = root.querySelector('#paxdesignChatHistoryMessages');
  var historyBackBtn = root.querySelector('#paxdesignChatHistoryBack');
  var historyTitle = root.querySelector('#paxdesignChatHistoryTitle');
  var honeypot     = root.querySelector('.paxdesign-booking-chat-honeypot');
  var closedBar    = root.querySelector('.paxdesign-booking-chat-closed-bar');
  var newSessionBtn = root.querySelector('.paxdesign-booking-chat-new-session');
  var replyBar      = root.querySelector('.paxdesign-booking-chat-reply-bar');
  var replyPreview  = root.querySelector('.paxdesign-booking-chat-reply-preview');
  var replyClearBtn = root.querySelector('.paxdesign-booking-chat-reply-clear');

  var entryEl          = root.querySelector('#paxdesignChatEntry');
  var welcomeEl        = root.querySelector('.paxdesign-booking-chat-welcome');

  var messages           = [];
  var isStreaming        = false;
  var abortCtrl          = null;
  var initialized        = false;
  var consultationLogged = false;
  var streamRaf          = 0;
  var pendingBubble      = null;
  var pendingText        = '';
  var pendingMessageEl   = null;
  var statusInterval     = null;
  var typingEl           = null;
  var audioCtx           = null;
  var audioUnlocked      = false;
  var soundEnabled       = true;
  var chatHandler        = 'ai';
  var adminName          = '';
  var pollSeq            = 0;
  var pollTimer          = null;
  var localMsgId         = 0;
  var domMsgIds          = {};
  var liveAgentPhase     = 0;
  var streamingMsgId     = 0;
  var adminTypingEl      = null;
  var userTypingTimer    = null;
  var userTypingActive   = false;
  var typingSoundActive  = false;
  var typingSoundLoopTimer = null;
  var typingSoundAudio   = null;
  var TYPING_SOUND_VOLUME = 0.32;
  var TYPING_SOUND_GAP_MS = 70;
  var messageReactions   = {};
  var chatMessageMap     = {};
  var replyToId          = 0;

  var customerName       = '';
  var pendingLiveTopic   = '';
  var awaitingName       = false;
  var awaitingLiveConfirm = false;
  var liveNameConfirmed  = false;
  var sessionRating      = 0;
  var ratingSubmitted    = false;
  var prevChatHandler    = '';
  var historyView        = 'list';
  var historyOpen        = false;
  var historyCache       = null;
  var historyCacheAt     = 0;
  var historyFetchPromise = null;
  var HISTORY_CACHE_MS   = 30000;
  var mp3AudioCache      = {};

  var SOUND_URLS = (config && config.sounds) ? config.sounds : {
    typing: 'https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3',
    openClose: 'https://paxdesign.at/wp-content/uploads/2026/06/u_8e8ungop1x-intro_cinematic-270840.mp3',
  };

  var namePromptEl       = root.querySelector('#paxdesignChatNamePrompt');
  var nameInputEl        = root.querySelector('#paxdesignChatNameInput');
  var nameSubmitBtn      = root.querySelector('#paxdesignChatNameSubmit');
  var liveConfirmEl      = root.querySelector('#paxdesignChatLiveConfirm');
  var liveConfirmYesBtn  = root.querySelector('#paxdesignChatLiveConfirmYes');
  var liveConfirmNoBtn   = root.querySelector('#paxdesignChatLiveConfirmNo');
  var endWrapEl          = root.querySelector('#paxdesignChatEndWrap');
  var endBtnEl           = root.querySelector('#paxdesignChatEndBtn');
  var ratingEl           = root.querySelector('#paxdesignChatRating');
  var ratingThanksEl     = root.querySelector('#paxdesignChatRatingThanks');

  if (!messagesEl || !threadEl || !form || !input) return;

  var entryChoice      = '';
  var sessionRestored  = false;

  var PAX_FEEDBACK_KEYS = {
    like: 'Gefällt mir',
    dislike: 'Gefällt mir nicht',
  };

  var COPY_ICON = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><rect x="4" y="4" width="11" height="11" rx="2"/></svg>';

  var ACTION_ICONS = {
    copy: COPY_ICON,
    copied: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
    share: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>',
    retry: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>',
    reply: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>',
    like: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
    dislike: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.33 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>',
  };

  var RATING_LIKE = 5;
  var RATING_DISLIKE = 1;

  var OPEN_CLOSE_SOUND_KEY = 'paxdesign-chat-openclose-sound';
  var agentJoinSoundPlayed = false;
  var chatEndSoundPlayed   = false;
  var SESSION_KEY       = 'paxdesign-chat-session';
  var SESSION_META_KEY  = 'paxdesign-chat-session-meta';
  var ENTRY_CHOICE_KEY  = 'paxdesign-chat-entry-choice';
  var SNAPSHOT_PREFIX   = 'paxdesign-chat-snapshot-';
  var SOUND_KEY         = 'paxdesign-chat-sound';
  var LIVE_AGENT_KEY    = 'paxdesign-chat-live-agent-phase';
  var CUSTOMER_NAME_KEY = 'paxdesign-chat-customer-name';
  var DEVICE_TOKEN_KEY  = 'paxdesign-chat-device-token';
  var HISTORY_INDEX_KEY = 'paxdesign-chat-history-index';
  var ARCHIVED_IDS_KEY  = 'paxdesign-chat-archived-ids';
  var BOOKING_MARKER_RE = /\[\[BOOKING:([^\]]+)\]\]/gi;
  var USER_BOOKING_RE   = /(?:termin\s*(?:buch|vereinbar|machen|wunsch)?|beratung\s*buchen|kontakt\s*aufnehmen|ruf(?:en)?\s*(?:mich\s*)?an|(?:ein\s*)?angebot|möchte\s*(?:einen?\s*)?termin|ja[\s,]*(?:bitte|gerne|ok)?\s*(?:termin|buchen)?)/i;
  var LIVE_AGENT_RE     = /(?:mit\s+(?:einem\s+)?(?:mitarbeiter|menschen|echten|support|agent|berater)|live\s*(?:agent|chat|support)|(?:kann|möchte|will)\s+ich\s+(?:mit\s+)?(?:einem\s+)?(?:menschen|mitarbeiter|support|agent|berater)|(?:kann|darf)\s+ich\s+mit|sprechen\s+(?:sie\s+)?mit|echter?\s+mensch|menschlichen?\s+support|echte\s+person)/i;
  var STATUS_MESSAGES   = [
    'KI analysiert Ihre Anfrage …',
    'Antwort wird erstellt …',
    'Einen kleinen Moment bitte …',
  ];
  var LIVE_QUALIFY_TEXT   = 'Gerne. Damit ich Sie richtig weiterleiten kann: Worum geht es kurz — Website, AI Chatbot, Booking, Support oder ein anderes Thema?';
  var POLL_INTERVAL_MS    = 600;

  function init() {
    if (initialized) return;
    initialized = true;
    loadOpenCloseSoundFlags();
    loadCustomerName();
    migrateSessionStorage();
    loadLiveAgentPhase();
    loadEntryChoice();
    applyGreeting();
    initQuickActions();
    initEntryChooser();
    initNamePrompt();
    initLiveConfirm();
    initAgentProfile();
    initCustomerClose();
    initRatingUi();
    initSoundToggle();
    initHistoryUi();
    initPlusToggle();
    bindAudioUnlock();
    bindVisibilityResume();
    restoreCustomerSession().then(function () {
      if (!sessionRestored) updateEntryUi();
      logConsultationStarted();
      startLivePolling();
      updateInputState();
    });
  }

  function migrateSessionStorage() {
    try {
      var legacy = sessionStorage.getItem(SESSION_KEY);
      if (legacy && !localStorage.getItem(SESSION_KEY)) localStorage.setItem(SESSION_KEY, legacy);
      var legacyPhase = sessionStorage.getItem(LIVE_AGENT_KEY);
      if (legacyPhase && !localStorage.getItem(LIVE_AGENT_KEY)) localStorage.setItem(LIVE_AGENT_KEY, legacyPhase);
    } catch (e) {}
  }

  function snapshotKey(sessionId) {
    return SNAPSHOT_PREFIX + sessionId;
  }

  function saveSessionSnapshot() {
    var sid = getSessionId();
    try {
      localStorage.setItem(SESSION_KEY, sid);
      localStorage.setItem(SESSION_META_KEY, JSON.stringify({ sessionId: sid, updatedAt: Date.now() }));
      localStorage.setItem(snapshotKey(sid), JSON.stringify({
        messages: messages,
        chatHandler: chatHandler,
        liveAgentPhase: liveAgentPhase,
        entryChoice: entryChoice,
        customerName: customerName,
        sessionRating: sessionRating,
        pollSeq: pollSeq,
        updatedAt: Date.now(),
      }));
      if (entryChoice) localStorage.setItem(ENTRY_CHOICE_KEY + '-' + sid, entryChoice);
    } catch (e) {}
  }

  function loadSessionSnapshot(sessionId) {
    try {
      var raw = localStorage.getItem(snapshotKey(sessionId));
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function loadEntryChoice() {
    try {
      entryChoice = localStorage.getItem(ENTRY_CHOICE_KEY + '-' + getSessionId()) || '';
    } catch (e) {
      entryChoice = '';
    }
  }

  function setEntryChoice(choice) {
    entryChoice = choice || '';
    try {
      if (entryChoice) localStorage.setItem(ENTRY_CHOICE_KEY + '-' + getSessionId(), entryChoice);
    } catch (e) {}
    updateEntryUi();
    saveSessionSnapshot();
  }

  function updateEntryUi() {
    var hasMessages = messages.length > 0 || root.classList.contains('paxdesign-has-chat-messages');
    if (threadEl && threadEl.children.length > 0) {
      hasMessages = true;
    }
    var showEntry = !hasMessages && !entryChoice && chatHandler !== 'closed';
    if (entryEl) entryEl.hidden = !showEntry;
    if (welcomeEl) welcomeEl.hidden = showEntry || hasMessages || entryChoice !== 'ai';
    root.classList.toggle('paxdesign-chat-entry-active', showEntry);
    if (form) form.classList.toggle('paxdesign-is-organizer-mode', showEntry);
    if (quickActions) quickActions.classList.toggle('paxdesign-is-organizer-hidden', showEntry);
  }

  function onWidgetOpen() {
    if (chatHandler === 'closed' && isSessionArchived(getSessionId())) {
      beginFreshSessionSilently();
    }
    updateEntryUi();
    notifyLayout();
    if (entryEl && !entryEl.hidden) {
      try {
        entryEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } catch (e) {}
    }
  }

  function onWidgetClose() {
    notifyLayout();
  }

  function loadOpenCloseSoundFlags() {
    agentJoinSoundPlayed = false;
    chatEndSoundPlayed = false;
    try {
      var raw = sessionStorage.getItem(OPEN_CLOSE_SOUND_KEY + '-' + getSessionId());
      if (!raw) return;
      var data = JSON.parse(raw);
      agentJoinSoundPlayed = !!data.agentJoin;
      chatEndSoundPlayed = !!data.chatEnd;
    } catch (e) {}
  }

  function saveOpenCloseSoundFlags() {
    try {
      sessionStorage.setItem(OPEN_CLOSE_SOUND_KEY + '-' + getSessionId(), JSON.stringify({
        agentJoin: agentJoinSoundPlayed,
        chatEnd: chatEndSoundPlayed,
      }));
    } catch (e) {}
  }

  function resetOpenCloseSoundFlags() {
    agentJoinSoundPlayed = false;
    chatEndSoundPlayed = false;
    try {
      sessionStorage.removeItem(OPEN_CLOSE_SOUND_KEY + '-' + getSessionId());
    } catch (e) {}
  }

  function playAgentJoinedSoundOnce() {
    if (agentJoinSoundPlayed || !soundEnabled) return;
    agentJoinSoundPlayed = true;
    saveOpenCloseSoundFlags();
    playMp3Sound('openClose', { volume: 0.42 });
  }

  function playChatEndedSoundOnce() {
    if (chatEndSoundPlayed || !soundEnabled) return;
    chatEndSoundPlayed = true;
    saveOpenCloseSoundFlags();
    playMp3Sound('openClose', { volume: 0.42 });
  }

  function loadCustomerName() {
    try {
      customerName = localStorage.getItem(CUSTOMER_NAME_KEY + '-' + getSessionId()) || '';
    } catch (e) {
      customerName = '';
    }
  }

  function saveCustomerName() {
    try {
      if (customerName) {
        localStorage.setItem(CUSTOMER_NAME_KEY + '-' + getSessionId(), customerName);
      }
    } catch (e) {}
    saveSessionSnapshot();
  }


  function playMp3Sound(kind, options) {
    options = options || {};
    if (!soundEnabled && !options.force) return;
    unlockAudio();
    var url = SOUND_URLS[kind];
    if (!url) return;
    try {
      if (!mp3AudioCache[kind]) {
        mp3AudioCache[kind] = new Audio(url);
        mp3AudioCache[kind].preload = 'auto';
      }
      var audio = mp3AudioCache[kind].cloneNode();
      audio.volume = typeof options.volume === 'number' ? options.volume : 0.45;
      audio.play().catch(function () {});
    } catch (e) {}
  }


  function showLiveConfirm() {
    awaitingLiveConfirm = true;
    awaitingName = false;
    if (liveConfirmEl) liveConfirmEl.hidden = false;
    if (namePromptEl) namePromptEl.hidden = true;
    if (form) form.hidden = true;
    if (endWrapEl) endWrapEl.hidden = true;
    notifyLayout();
  }

  function hideLiveConfirm() {
    awaitingLiveConfirm = false;
    if (liveConfirmEl) liveConfirmEl.hidden = true;
    updateInputState();
    notifyLayout();
  }

  function showNamePrompt() {
    awaitingName = true;
    awaitingLiveConfirm = false;
    if (liveConfirmEl) liveConfirmEl.hidden = true;
    if (namePromptEl) namePromptEl.hidden = false;
    if (form) form.hidden = true;
    if (endWrapEl) endWrapEl.hidden = true;
    if (nameInputEl) {
      nameInputEl.value = '';
      nameInputEl.removeAttribute('readonly');
      setTimeout(function () { nameInputEl.focus(); }, 80);
    }
    notifyLayout();
  }

  function hideNamePrompt() {
    awaitingName = false;
    if (namePromptEl) namePromptEl.hidden = true;
    updateInputState();
    notifyLayout();
  }

  function queueLiveAgentRequest(topic) {
    pendingLiveTopic = topic || inferServiceFromConversation();
    showNamePrompt();
    return Promise.resolve();
  }

  function submitCustomerName() {
    var name = nameInputEl ? (nameInputEl.value || '').trim() : '';
    if (name.length < 2) {
      showError('Bitte geben Sie Ihren Namen ein (mindestens 2 Zeichen).');
      return;
    }
    customerName = name;
    liveNameConfirmed = true;
    saveCustomerName();
    hideNamePrompt();
    var topic = pendingLiveTopic || inferServiceFromConversation();
    isStreaming = true;
    updateSendButton();
    requestLiveAgent(topic, customerName)
      .catch(function (err) { showError(err.message || 'Weiterleitung fehlgeschlagen.'); })
      .finally(function () {
        isStreaming = false;
        updateSendButton();
        saveSessionSnapshot();
      });
  }

  function initNamePrompt() {
    if (nameSubmitBtn) {
      nameSubmitBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitCustomerName();
      });
    }
    if (nameInputEl) {
      nameInputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          submitCustomerName();
        }
      });
    }
  }

  function initLiveConfirm() {
    if (liveConfirmYesBtn) {
      liveConfirmYesBtn.addEventListener('click', function (e) {
        e.preventDefault();
        hideLiveConfirm();
        showNamePrompt();
      });
    }
    if (liveConfirmNoBtn) {
      liveConfirmNoBtn.addEventListener('click', function (e) {
        e.preventDefault();
        hideLiveConfirm();
        setEntryChoice('ai');
        liveAgentPhase = 0;
        saveLiveAgentPhase();
        if (config.greeting) appendLocalAssistant(config.greeting);
        saveSessionSnapshot();
      });
    }
  }

  function canCustomerEndChat() {
    if (chatHandler === 'closed') return false;
    return isHumanMode() || liveAgentPhase >= 1 || entryChoice === 'live' ||
      root.classList.contains('paxdesign-has-chat-messages');
  }

  function updateEndButtonUi() {
    if (!endWrapEl) return;
    var show = canCustomerEndChat() && !awaitingName && !awaitingLiveConfirm && chatHandler !== 'closed';
    endWrapEl.hidden = !show;
  }

  function customerCloseConversation() {
    if (!window.confirm('Möchten Sie dieses Gespräch wirklich beenden?')) return;
    if (!config.ajaxUrl) return;
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_customer_close');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          showError(json && json.data && json.data.message ? json.data.message : 'Beenden fehlgeschlagen.');
          return;
        }
        if (json.data && json.data.message) {
          domMsgIds[json.data.message.id] = true;
          seenMsgId(json.data.message.id);
          renderMessageDom(json.data.message.role, json.data.message.content, json.data.message.id, { skipPush: true });
        }
        applyHandlerState('closed', '');
        showRatingUi();
        archiveClosedSession();
        saveSessionSnapshot();
      })
      .catch(function () { showError('Verbindungsfehler beim Beenden.'); });
  }

  function initCustomerClose() {
    if (endBtnEl) {
      endBtnEl.addEventListener('click', function (e) {
        e.preventDefault();
        customerCloseConversation();
      });
    }
  }

  function showRatingUi() {
    if (!ratingEl || ratingSubmitted || sessionRating > 0) return;
    ratingEl.hidden = false;
    if (ratingThanksEl) ratingThanksEl.hidden = true;
    restoreRatingUi();
  }

  function lockRatingUi(feedback) {
    if (!ratingEl) return;
    ratingEl.querySelectorAll('[data-feedback]').forEach(function (btn) {
      var selected = btn.getAttribute('data-feedback') === feedback;
      btn.classList.toggle('paxdesign-is-active', selected);
      btn.disabled = true;
      btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }

  function restoreRatingUi() {
    if (sessionRating === RATING_LIKE) {
      ratingSubmitted = true;
      lockRatingUi('like');
      if (ratingThanksEl) ratingThanksEl.hidden = false;
    } else if (sessionRating === RATING_DISLIKE) {
      ratingSubmitted = true;
      lockRatingUi('dislike');
      if (ratingThanksEl) ratingThanksEl.hidden = false;
    }
  }

  function submitFeedback(feedback) {
    if (!config.ajaxUrl || ratingSubmitted || sessionRating > 0) return;
    if (feedback !== 'like' && feedback !== 'dislike') return;
    ratingSubmitted = true;
    sessionRating = feedback === 'like' ? RATING_LIKE : RATING_DISLIKE;
    lockRatingUi(feedback);
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_rating');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('feedback', feedback);
    formData.append('rating', String(sessionRating));
    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).catch(function () {});
    if (ratingThanksEl) ratingThanksEl.hidden = false;
    saveSessionSnapshot();
  }

  function initRatingUi() {
    if (!ratingEl) return;
    ratingEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-feedback]');
      if (!btn || ratingSubmitted || sessionRating > 0) return;
      e.preventDefault();
      submitFeedback(btn.getAttribute('data-feedback'));
    });
  }

  function initEntryChooser() {
    if (!entryEl) return;
    entryEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-entry]');
      if (!btn) return;
      e.preventDefault();
      var choice = btn.getAttribute('data-entry');
      setEntryChoice(choice);
      if (choice === 'live') {
        appendLocalAssistant('Gerne — ich verbinde Sie mit unserem Live Chat.');
        liveAgentPhase = 1;
        saveLiveAgentPhase();
        showLiveConfirm();
      } else if (choice === 'ai' && config.greeting) {
        appendLocalAssistant(config.greeting);
      }
      saveSessionSnapshot();
    });
  }

  function restoreCustomerSession() {
    var sid = getSessionId();
    if (isSessionArchived(sid)) {
      beginFreshSessionSilently();
      return Promise.resolve();
    }
    var snap = loadSessionSnapshot(sid);
    if (snap && Array.isArray(snap.messages) && snap.messages.length) {
      if (snap.chatHandler === 'closed') {
        archiveClosedSession(sid, snap);
        beginFreshSessionSilently();
        return Promise.resolve();
      }
      applyRestoredSnapshot(snap);
      sessionRestored = true;
    }
    return fetchSessionFromServer(true).then(function (restored) {
      if (chatHandler === 'closed') {
        archiveClosedSession();
        beginFreshSessionSilently();
        return;
      }
      if (restored) sessionRestored = true;
      updateEntryUi();
    });
  }

  function applyRestoredSnapshot(snap) {
    if (snap.chatHandler) applyHandlerState(snap.chatHandler, adminName);
    if (typeof snap.liveAgentPhase === 'number') {
      liveAgentPhase = snap.liveAgentPhase;
      saveLiveAgentPhase();
    }
    if (snap.entryChoice) entryChoice = snap.entryChoice;
    if (snap.customerName) customerName = snap.customerName;
    if (typeof snap.sessionRating === 'number') {
      sessionRating = snap.sessionRating;
      if (sessionRating > 0) restoreRatingUi();
    }
    if (typeof snap.pollSeq === 'number') pollSeq = snap.pollSeq;
    (snap.messages || []).forEach(function (msg) {
      if (!msg || !msg.id || domMsgIds[msg.id]) return;
      domMsgIds[msg.id] = true;
      seenMsgId(msg.id);
      renderMessageDom(msg.role, msg.content, msg.id, {
        reaction: msg.reaction || '',
        reply_to: msg.reply_to || 0,
        image_url: msg.image_url || '',
        ts: msg.ts || 0,
        skipPush: true,
      });
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin' || msg.role === 'system') {
        messages.push({ role: msg.role, content: msg.content, id: msg.id });
      }
    });
    updateEntryUi();
  }

  function syncSessionMetaFromPoll(data) {
    if (!data) return;
    if (data.customer_name) {
      customerName = data.customer_name;
      saveCustomerName();
    }
    if (typeof data.session_rating === 'number') {
      sessionRating = data.session_rating;
      if (sessionRating > 0) {
        ratingSubmitted = true;
        restoreRatingUi();
        if (ratingEl) ratingEl.hidden = false;
      }
    }
  }

  function fetchSessionFromServer(full) {
    if (!config.ajaxUrl) return Promise.resolve(false);
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_poll');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('since', '0');
    if (full) formData.append('full', '1');
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data) return false;
        var data = json.data;
        applyHandlerState(data.handler || 'ai', data.admin_name || '');
        syncSessionMetaFromPoll(data);
        if (Array.isArray(data.messages) && data.messages.length) applyRestoredMessages(data.messages);
        if (data.reactions) applyReactionStates(data.reactions);
        if (typeof data.seq === 'number') pollSeq = Math.max(pollSeq, data.seq);
        saveSessionSnapshot();
        return !!(data.messages && data.messages.length);
      })
      .catch(function () { return false; });
  }

  function applyRestoredMessages(incoming) {
    incoming.sort(function (a, b) { return (a.id || 0) - (b.id || 0); });
    incoming.forEach(function (msg) {
      if (!msg || !msg.id || domMsgIds[msg.id]) return;
      if (msg.role === 'system' && msg.content === 'Chat-Session gestartet.') return;
      domMsgIds[msg.id] = true;
      seenMsgId(msg.id);
      if (msg.reaction) messageReactions[msg.id] = msg.reaction;
      indexChatMessage(msg);
      renderMessageDom(msg.role, msg.content, msg.id, {
        reaction: msg.reaction || '',
        reply_to: msg.reply_to || 0,
        image_url: msg.image_url || '',
        ts: msg.ts || 0,
        skipPush: true,
      });
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin') {
        if (!messages.some(function (m) { return m.id === msg.id; })) {
          messages.push({ role: msg.role, content: msg.content, id: msg.id });
        }
      } else if (msg.role === 'system') {
        messages.push({ role: 'system', content: msg.content, id: msg.id });
      }
    });
    updateEntryUi();
  }

  function getCustomerAgentLabel() {
    var agent = getLiveAgent();
    return (agent && agent.name) ? agent.name : 'Live Chat';
  }

  function initAgentProfile() {
    var profileBtn = root.querySelector('#paxdesignChatAgentProfile');
    var modal = document.getElementById('paxdesignAgentProfileModal');
    var subtitle = document.getElementById('paxdesignWidgetSubtitle');
    var agent = getLiveAgent();
    if (subtitle && agent && agent.role) {
      subtitle.textContent = agent.role;
    }
    if (!modal) return;
    function openProfile() {
      modal.hidden = false;
      document.body.classList.add('paxdesign-agent-profile-open');
    }
    function closeProfile() {
      modal.hidden = true;
      document.body.classList.remove('paxdesign-agent-profile-open');
    }
    if (profileBtn) {
      profileBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openProfile();
      });
    }
    modal.querySelectorAll('[data-profile-close]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        closeProfile();
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeProfile();
    });
  }

  function formatMsgTime(ts) {
    var d = ts ? new Date(ts * 1000) : new Date();
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' });
  }

  function loadLiveAgentPhase() {
    try {
      liveAgentPhase = parseInt(localStorage.getItem(LIVE_AGENT_KEY) || sessionStorage.getItem(LIVE_AGENT_KEY) || '0', 10) || 0;
    } catch (e) {
      liveAgentPhase = 0;
    }
  }

  function saveLiveAgentPhase() {
    try {
      localStorage.setItem(LIVE_AGENT_KEY, String(liveAgentPhase));
      sessionStorage.setItem(LIVE_AGENT_KEY, String(liveAgentPhase));
    } catch (e) {}
    saveSessionSnapshot();
  }

  function resetLiveAgentPhase() {
    liveAgentPhase = 0;
    saveLiveAgentPhase();
  }

  function nextLocalId() {
    localMsgId += 1;
    pollSeq = Math.max(pollSeq, localMsgId);
    return localMsgId;
  }

  function getSessionId() {
    try {
      var id = localStorage.getItem(SESSION_KEY) || sessionStorage.getItem(SESSION_KEY);
      if (!id) {
        id = 'pax_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 11);
        localStorage.setItem(SESSION_KEY, id);
        sessionStorage.setItem(SESSION_KEY, id);
      }
      return id;
    } catch (e) {
      return 'pax_' + Date.now().toString(36);
    }
  }

  function createNewSessionId() {
    return 'pax_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 11);
  }

  function randomHex(bytes) {
    var out = '';
    var i;
    if (window.crypto && window.crypto.getRandomValues) {
      var arr = new Uint8Array(bytes);
      window.crypto.getRandomValues(arr);
      for (i = 0; i < arr.length; i++) {
        out += arr[i].toString(16).padStart(2, '0');
      }
      return out;
    }
    for (i = 0; i < bytes; i++) {
      out += Math.floor(Math.random() * 256).toString(16).padStart(2, '0');
    }
    return out;
  }

  function getDeviceToken() {
    try {
      var token = localStorage.getItem(DEVICE_TOKEN_KEY);
      if (!token) {
        token = 'paxdev_' + randomHex(24);
        localStorage.setItem(DEVICE_TOKEN_KEY, token);
      }
      return token;
    } catch (e) {
      return 'paxdev_' + randomHex(16);
    }
  }

  function stampChatRequest(formData) {
    if (formData) formData.append('device_token', getDeviceToken());
    return formData;
  }

  function loadHistoryIndex() {
    try {
      var raw = localStorage.getItem(HISTORY_INDEX_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function saveHistoryIndex(items) {
    try {
      localStorage.setItem(HISTORY_INDEX_KEY, JSON.stringify(items.slice(0, 50)));
    } catch (e) {}
  }

  function loadArchivedIds() {
    try {
      var raw = localStorage.getItem(ARCHIVED_IDS_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function saveArchivedIds(ids) {
    try {
      localStorage.setItem(ARCHIVED_IDS_KEY, JSON.stringify(ids.slice(0, 100)));
    } catch (e) {}
  }

  function isSessionArchived(sessionId) {
    if (!sessionId) return false;
    return loadArchivedIds().indexOf(sessionId) !== -1;
  }

  function getLastMessagePreview(sourceMessages) {
    var list = sourceMessages || messages;
    for (var i = list.length - 1; i >= 0; i--) {
      var msg = list[i];
      if (!msg) continue;
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin') {
        var text = String(msg.content || '').trim();
        if (text) return text.length > 120 ? text.slice(0, 120) + '…' : text;
        if (msg.image_url) return 'Bild';
      }
    }
    return '';
  }

  function archiveClosedSession(sessionId, snap) {
    var sid = sessionId || getSessionId();
    if (!sid || isSessionArchived(sid)) return;

    var archivedIds = loadArchivedIds();
    archivedIds.unshift(sid);
    saveArchivedIds(archivedIds.filter(function (id, idx, arr) {
      return arr.indexOf(id) === idx;
    }).slice(0, 100));

    var preview = getLastMessagePreview(snap && snap.messages ? snap.messages : null);
    var meta = {
      sessionId: sid,
      archivedAt: Date.now(),
      preview: preview,
      customerName: (snap && snap.customerName) || customerName || '',
      messageCount: (snap && snap.messages) ? snap.messages.length : messages.length,
      sessionRating: (snap && typeof snap.sessionRating === 'number') ? snap.sessionRating : sessionRating,
    };

    var index = loadHistoryIndex().filter(function (item) {
      return item && item.sessionId !== sid;
    });
    index.unshift(meta);
    saveHistoryIndex(index.slice(0, 50));
    historyCache = null;
    historyCacheAt = 0;
  }

  function formatHistoryDate(value) {
    if (!value) return '';
    var date = typeof value === 'number' ? new Date(value) : new Date(String(value).replace(' ', 'T'));
    if (isNaN(date.getTime())) return '';
    try {
      return date.toLocaleString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return date.toLocaleString();
    }
  }

  function feedbackLabelFromRating(rating) {
    var r = parseInt(rating, 10);
    if (r === RATING_LIKE) return '<span class="paxdesign-booking-chat-history-feedback paxdesign-booking-chat-history-feedback--like" title="Gefällt mir">' + ACTION_ICONS.like + '</span>';
    if (r === RATING_DISLIKE) return '<span class="paxdesign-booking-chat-history-feedback paxdesign-booking-chat-history-feedback--dislike" title="Gefällt mir nicht">' + ACTION_ICONS.dislike + '</span>';
    return '';
  }

  function renderHistoryListItems(items) {
    if (!historyList) return;
    if (!items.length) {
      historyList.innerHTML = '<p class="paxdesign-booking-chat-history-empty">Noch keine abgeschlossenen Gespräche in Ihrem Verlauf.</p>';
      return;
    }
    var frag = document.createDocumentFragment();
    items.forEach(function (item) {
      var rating = item.session_rating || item.sessionRating || 0;
      var label = item.customer_name || item.customerName || 'Gespräch';
      var when = formatHistoryDate(item.updated_at || item.archivedAt);
      var preview = item.preview || 'Chat-Verlauf';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'paxdesign-booking-chat-history-item paxdesign-booking-chat-history-item--archived';
      btn.setAttribute('data-history-id', item.session_id || item.sessionId || '');
      btn.setAttribute('role', 'listitem');
      btn.innerHTML =
        '<div class="paxdesign-booking-chat-history-item-meta"><span>' + escapeHtml(label) + '</span><span>' +
        escapeHtml(when) + '</span></div>' +
        '<div class="paxdesign-booking-chat-history-item-preview">' + escapeHtml(preview) + '</div>' +
        feedbackLabelFromRating(rating);
      frag.appendChild(btn);
    });
    historyList.innerHTML = '';
    historyList.appendChild(frag);
  }

  function fetchHistoryList(force) {
    var now = Date.now();
    if (!force && historyCache && (now - historyCacheAt) < HISTORY_CACHE_MS) {
      renderHistoryListItems(historyCache);
      return Promise.resolve(historyCache);
    }
    if (!force && historyFetchPromise) {
      return historyFetchPromise;
    }

    var localItems = loadHistoryIndex();
    if (localItems.length) {
      renderHistoryListItems(localItems);
    }

    if (!config.ajaxUrl) {
      historyCache = localItems;
      historyCacheAt = now;
      return Promise.resolve(localItems);
    }

    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_customer_history_list');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);

    historyFetchPromise = fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        var remote = (json && json.success && json.data && Array.isArray(json.data.sessions)) ? json.data.sessions : [];
        var merged = mergeHistoryItems(localItems, remote);
        saveHistoryIndex(merged);
        historyCache = merged;
        historyCacheAt = Date.now();
        renderHistoryListItems(merged);
        return merged;
      })
      .catch(function () {
        historyCache = localItems;
        historyCacheAt = Date.now();
        renderHistoryListItems(localItems);
        return localItems;
      })
      .finally(function () {
        historyFetchPromise = null;
      });

    return historyFetchPromise;
  }

  function mergeHistoryItems(localItems, remoteItems) {
    var map = {};
    (localItems || []).forEach(function (item) {
      if (!item || !item.sessionId) return;
      map[item.sessionId] = item;
    });
    (remoteItems || []).forEach(function (item) {
      if (!item || !item.session_id) return;
      var existing = map[item.session_id];
      var preview = item.preview || '';
      if (existing && existing.preview && !preview) {
        preview = existing.preview;
      }
      map[item.session_id] = {
        sessionId: item.session_id,
        session_id: item.session_id,
        customerName: item.customer_name || (existing && existing.customerName) || '',
        customer_name: item.customer_name || (existing && existing.customer_name) || '',
        updated_at: item.updated_at || (existing && existing.updated_at),
        archivedAt: item.updated_at || (existing && existing.archivedAt),
        preview: preview,
        messageCount: item.message_count || (existing && existing.messageCount) || 0,
        sessionRating: item.session_rating || (existing && existing.sessionRating) || 0,
        session_rating: item.session_rating || (existing && existing.session_rating) || 0,
      };
    });
    return Object.keys(map).map(function (key) { return map[key]; }).sort(function (a, b) {
      var ta = new Date(a.updated_at || a.archivedAt || 0).getTime();
      var tb = new Date(b.updated_at || b.archivedAt || 0).getTime();
      return tb - ta;
    });
  }

  function renderHistoryMessages(list) {
    if (!historyMessages) return;
    var frag = document.createDocumentFragment();
    (list || []).forEach(function (msg) {
      if (!msg || !msg.content) {
        if (!msg || !msg.image_url) return;
      }
      if (msg.role === 'system' && msg.content === 'Chat-Session gestartet.') return;
      var role = msg.role || 'assistant';
      var wrap = document.createElement('div');
      wrap.className = 'paxdesign-booking-chat-history-msg paxdesign-booking-chat-history-msg--' + role;
      var bubble = document.createElement('div');
      bubble.className = 'paxdesign-booking-chat-history-msg-bubble';
      bubble.textContent = String(msg.content || '').trim();
      if (!bubble.textContent && msg.image_url) bubble.textContent = 'Bild';
      wrap.appendChild(bubble);
      frag.appendChild(wrap);
    });
    historyMessages.innerHTML = '';
    historyMessages.appendChild(frag);
    historyMessages.scrollTop = historyMessages.scrollHeight;
  }

  function openHistoryPanel() {
    if (!historyPanel) return;
    historyOpen = true;
    historyView = 'list';
    historyPanel.hidden = false;
    if (historyDetail) historyDetail.hidden = true;
    if (historyList) historyList.hidden = false;
    if (historyTitle) historyTitle.textContent = 'Chat-Verlauf';
    root.classList.add('paxdesign-chat-history-open');
    if (historyBtn) historyBtn.classList.add('paxdesign-is-active');
    fetchHistoryList();
    notifyLayout();
  }

  function closeHistoryPanel() {
    if (!historyPanel) return;
    historyOpen = false;
    historyView = 'list';
    historyPanel.hidden = true;
    if (historyDetail) historyDetail.hidden = true;
    if (historyList) historyList.hidden = false;
    if (historyTitle) historyTitle.textContent = 'Chat-Verlauf';
    root.classList.remove('paxdesign-chat-history-open');
    if (historyBtn) historyBtn.classList.remove('paxdesign-is-active');
    notifyLayout();
  }

  function openHistorySession(sessionId) {
    if (!sessionId || !historyDetail || !historyList) return;
    historyView = 'detail';
    historyList.hidden = true;
    historyDetail.hidden = false;
    if (historyTitle) historyTitle.textContent = 'Gespräch';
    historyMessages.innerHTML = '<p class="paxdesign-booking-chat-history-empty">Lade Verlauf …</p>';

    var snap = loadSessionSnapshot(sessionId);
    if (snap && Array.isArray(snap.messages) && snap.messages.length) {
      renderHistoryMessages(snap.messages);
    }

    if (!config.ajaxUrl) return;
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_customer_history_session');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', sessionId);
    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data || !Array.isArray(json.data.messages)) {
          if (!snap || !snap.messages || !snap.messages.length) {
            historyMessages.innerHTML = '<p class="paxdesign-booking-chat-history-empty">Dieser Verlauf ist nicht verfügbar.</p>';
          }
          return;
        }
        renderHistoryMessages(json.data.messages);
      })
      .catch(function () {
        if (!snap || !snap.messages || !snap.messages.length) {
          historyMessages.innerHTML = '<p class="paxdesign-booking-chat-history-empty">Verlauf konnte nicht geladen werden.</p>';
        }
      });
  }

  function initHistoryUi() {
    if (historyBtn) {
      historyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        unlockAudio();
        if (historyOpen) closeHistoryPanel();
        else openHistoryPanel();
      });
    }
    if (historyBackBtn) {
      historyBackBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (historyView === 'detail') {
          historyView = 'list';
          if (historyDetail) historyDetail.hidden = true;
          if (historyList) historyList.hidden = false;
          if (historyTitle) historyTitle.textContent = 'Chat-Verlauf';
          return;
        }
        closeHistoryPanel();
      });
    }
    if (historyList) {
      historyList.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-history-id]');
        if (!btn) return;
        e.preventDefault();
        openHistorySession(btn.getAttribute('data-history-id'));
      });
    }
  }

  function isHumanMode() {
    return chatHandler === 'admin' || chatHandler === 'live_request';
  }

  function applyGreeting() {
    if (!config.greeting) return;
    var welcome = messagesEl.querySelector('.paxdesign-booking-chat-welcome-text');
    if (welcome) welcome.textContent = config.greeting;
  }

  function initSoundToggle() {
    try {
      soundEnabled = localStorage.getItem(SOUND_KEY) !== 'off';
    } catch (e) {
      soundEnabled = true;
    }
    updateSoundToggleUi();
    if (!notifyBtn) return;
    notifyBtn.addEventListener('click', function (e) {
      e.preventDefault();
      unlockAudio();
      soundEnabled = !soundEnabled;
      try {
        localStorage.setItem(SOUND_KEY, soundEnabled ? 'on' : 'off');
      } catch (err) {}
      updateSoundToggleUi();
      if (soundEnabled) {
        playNotificationSound(true);
      } else {
        stopTypingSound();
      }
    });
  }

  function updateSoundToggleUi() {
    if (!notifyBtn) return;
    notifyBtn.classList.toggle('paxdesign-is-muted', !soundEnabled);
    notifyBtn.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    notifyBtn.title = soundEnabled ? 'Benachrichtigungston aus' : 'Benachrichtigungston an';
  }

  function bindAudioUnlock() {
    var unlock = function () { unlockAudio(); };
    root.addEventListener('click', unlock, { once: false });
    root.addEventListener('touchstart', unlock, { once: false, passive: true });
    input.addEventListener('focus', unlock, { once: false });
  }

  function bindVisibilityResume() {
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) unlockAudio();
    });
    window.addEventListener('pageshow', unlockAudio);
  }

  function unlockAudio() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      audioCtx = audioCtx || new Ctx();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      if (!audioUnlocked) {
        var buffer = audioCtx.createBuffer(1, 1, 22050);
        var source = audioCtx.createBufferSource();
        source.buffer = buffer;
        source.connect(audioCtx.destination);
        source.start(0);
        audioUnlocked = true;
      }
    } catch (e) {}
  }

  function runWithAudioContext(fn) {
    unlockAudio();
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') {
      audioCtx.resume().then(function () { fn(audioCtx); }).catch(function () {});
      return;
    }
    fn(audioCtx);
  }

  function shouldPlayMessageNotification() {
    if (document.hidden) return true;
    if (root.classList.contains('paxdesign-widget-open')) return false;
    if (root.classList.contains('paxdesign-chat-entry-active')) return false;
    return false;
  }

  function playMessengerPop(preview) {
    if (!soundEnabled && !preview) return;
    if (!preview && !shouldPlayMessageNotification()) return;
    runWithAudioContext(function (ctx) {
      try {
        var t = ctx.currentTime;
        var master = ctx.createGain();
        master.gain.setValueAtTime(preview ? 0.12 : 0.22, t);
        master.connect(ctx.destination);
        [[880, 0, 0.08], [1174.66, 0.06, 0.1]].forEach(function (tone) {
          var osc = ctx.createOscillator();
          var gain = ctx.createGain();
          var start = t + tone[1];
          osc.type = 'sine';
          osc.frequency.setValueAtTime(tone[0], start);
          gain.gain.setValueAtTime(0.0001, start);
          gain.gain.exponentialRampToValueAtTime(0.9, start + 0.008);
          gain.gain.exponentialRampToValueAtTime(0.0001, start + tone[2]);
          osc.connect(gain);
          gain.connect(master);
          osc.start(start);
          osc.stop(start + tone[2] + 0.02);
        });
      } catch (e) {}
    });
  }

  function playNotificationSound(preview) {
    playMessengerPop(preview);
  }

  function ensureTypingSoundAudio() {
    if (!SOUND_URLS.typing) return null;
    if (!typingSoundAudio) {
      typingSoundAudio = new Audio(SOUND_URLS.typing);
      typingSoundAudio.preload = 'auto';
    }
    return typingSoundAudio;
  }

  function scheduleTypingSoundLoop() {
    if (!typingSoundActive || !soundEnabled) return;
    typingSoundLoopTimer = null;
    var audio = ensureTypingSoundAudio();
    if (!audio) return;
    unlockAudio();
    audio.onended = null;
    audio.pause();
    audio.currentTime = 0;
    audio.volume = TYPING_SOUND_VOLUME;
    audio.onended = function () {
      audio.onended = null;
      if (!typingSoundActive || !soundEnabled) return;
      typingSoundLoopTimer = window.setTimeout(scheduleTypingSoundLoop, TYPING_SOUND_GAP_MS);
    };
    var playPromise = audio.play();
    if (playPromise && typeof playPromise.catch === 'function') {
      playPromise.catch(function () {
        if (typingSoundActive && soundEnabled) {
          typingSoundLoopTimer = window.setTimeout(scheduleTypingSoundLoop, 420);
        }
      });
    }
  }

  function syncTypingSound(shouldPlay) {
    if (shouldPlay && soundEnabled) {
      if (typingSoundActive) return;
      typingSoundActive = true;
      scheduleTypingSoundLoop();
      return;
    }
    typingSoundActive = false;
    if (typingSoundLoopTimer) {
      clearTimeout(typingSoundLoopTimer);
      typingSoundLoopTimer = null;
    }
    if (typingSoundAudio) {
      typingSoundAudio.onended = null;
      typingSoundAudio.pause();
      typingSoundAudio.currentTime = 0;
    }
  }

  function startTypingSound() {
    syncTypingSound(true);
  }

  function stopTypingSound() {
    syncTypingSound(false);
  }

  function stopAdminTypingFeedback() {
    stopTypingSound();
    removeAdminTypingIndicator();
  }

  function startLivePolling() {
    if (pollTimer) return;
    pollUpdates();
    pollTimer = window.setInterval(pollUpdates, POLL_INTERVAL_MS);
  }

  function applyHandlerState(handler, name) {
    if (!handler || handler === chatHandler) {
      if (name) adminName = name;
      updateHandlerUi();
      updateEndButtonUi();
      return;
    }
    var previousHandler = chatHandler;
    var transitioningToAdmin = handler === 'admin' && previousHandler !== 'admin';
    var transitioningToClosed = handler === 'closed' && previousHandler !== 'closed';
    if (handler === 'admin') {
      abortStream();
      removeTyping();
    }
    if (handler === 'ai') {
      resetLiveAgentPhase();
    }
    if (handler === 'closed') {
      abortStream();
      removeTyping();
      if (transitioningToClosed) {
        playChatEndedSoundOnce();
        showRatingUi();
        archiveClosedSession();
      }
    }
    prevChatHandler = chatHandler;
    chatHandler = handler;
    if (name) adminName = name;
    updateHandlerUi();
    updateInputState();
    updateEndButtonUi();
  }

  function updateHandlerUi() {
    root.classList.toggle('paxdesign-chat-admin-active', chatHandler === 'admin');
    root.classList.toggle('paxdesign-chat-live-request', chatHandler === 'live_request');
    root.classList.toggle('paxdesign-chat-closed', chatHandler === 'closed');
  }

  function updateInputState() {
    var closed = chatHandler === 'closed';
    var showForm = !closed && !awaitingName && !awaitingLiveConfirm;
    input.disabled = closed || awaitingName || awaitingLiveConfirm;
    if (sendBtn) {
      sendBtn.classList.toggle('paxdesign-is-disabled', closed || awaitingName || awaitingLiveConfirm || (!input.value.trim() && !isStreaming));
      sendBtn.setAttribute('aria-disabled', (closed || awaitingName || awaitingLiveConfirm) ? 'true' : sendBtn.getAttribute('aria-disabled'));
    }
    if (closedBar) {
      closedBar.hidden = !closed;
    }
    if (form) {
      form.hidden = !showForm;
    }
    if (namePromptEl) {
      namePromptEl.hidden = !awaitingName;
    }
    if (liveConfirmEl) {
      liveConfirmEl.hidden = !awaitingLiveConfirm;
    }
    updateEndButtonUi();
    if (closed) {
      input.placeholder = 'Chat geschlossen';
    } else if (awaitingName || awaitingLiveConfirm) {
      input.placeholder = '';
    } else if (isHumanMode()) {
      input.placeholder = 'Nachricht an Live Chat …';
    } else {
      input.placeholder = 'Nachricht schreiben …';
    }
  }

  function resetSessionState() {
    abortStream();
    removeTyping();
    isStreaming = false;
    streamingMsgId = 0;
    pendingMessageEl = null;
    pendingBubble = null;
    pendingText = '';
    consultationLogged = false;

    messages = [];
    domMsgIds = {};
    pollSeq = 0;
    localMsgId = 0;
    chatHandler = 'ai';
    adminName = '';
    liveAgentPhase = 0;
    entryChoice = '';
    sessionRestored = false;
    customerName = '';
    pendingLiveTopic = '';
    awaitingName = false;
    awaitingLiveConfirm = false;
    liveNameConfirmed = false;
    sessionRating = 0;
    ratingSubmitted = false;
    prevChatHandler = '';
    replyToId = 0;
    chatMessageMap = {};
    clearClientReply();
    resetOpenCloseSoundFlags();

    threadEl.innerHTML = '';
    stopAdminTypingFeedback();
    if (ratingEl) ratingEl.hidden = true;
    if (ratingThanksEl) ratingThanksEl.hidden = true;
    root.classList.remove(
      'paxdesign-has-chat-messages',
      'paxdesign-chat-closed',
      'paxdesign-chat-admin-active',
      'paxdesign-chat-live-request'
    );

    updateHandlerUi();
    updateInputState();
    updateEntryUi();
  }

  function beginFreshSessionSilently() {
    closeHistoryPanel();
    var newId = createNewSessionId();
    try {
      localStorage.setItem(SESSION_KEY, newId);
      sessionStorage.setItem(SESSION_KEY, newId);
      sessionStorage.removeItem(LIVE_AGENT_KEY);
      localStorage.removeItem(LIVE_AGENT_KEY);
      localStorage.removeItem(ENTRY_CHOICE_KEY + '-' + newId);
    } catch (e) {}

    resetSessionState();
    logConsultationStarted();
    startLivePolling();
    notifyLayout();
    saveSessionSnapshot();
  }

  function startNewConversation() {
    if (!window.confirm('Neues Gespräch starten? Ihr bisheriger Chat bleibt gespeichert — es beginnt eine neue Session.')) {
      return;
    }
    archiveClosedSession();
    beginFreshSessionSilently();
  }

  function pollUpdates() {
    if (!config.ajaxUrl) return;
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_poll');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('since', String(pollSeq));

    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data) return;
        var data = json.data;
        applyHandlerState(data.handler || 'ai', data.admin_name || '');
        syncSessionMetaFromPoll(data);

        var incomingAdmin = false;
        var hadNewMessages = false;
        if (Array.isArray(data.messages) && data.messages.length) {
          data.messages.sort(function (a, b) {
            return (a.id || 0) - (b.id || 0);
          });
          hadNewMessages = data.messages.some(function (m) {
            return m && m.id && !domMsgIds[m.id];
          });
          incomingAdmin = data.messages.some(function (m) {
            return m && m.role === 'admin' && m.id && !domMsgIds[m.id];
          });
          applyIncomingMessages(data.messages);
        }

        if (incomingAdmin) {
          stopAdminTypingFeedback();
        } else if (data.admin_typing && chatHandler === 'admin') {
          if (!adminTypingEl) showAdminTypingIndicator();
          syncTypingSound(true);
        } else {
          stopAdminTypingFeedback();
        }

        if (hadNewMessages || incomingAdmin) {
          ensureAdminMessageChrome();
        }

        if (data.reactions && typeof data.reactions === 'object') {
          applyReactionStates(data.reactions);
        }
        if (typeof data.seq === 'number') {
          pollSeq = Math.max(pollSeq, data.seq);
        }
      })
      .catch(function () {});
  }

  function morphAdminTypingToMessage(msg) {
    if (!adminTypingEl) return false;
    var msgEl = adminTypingEl;
    adminTypingEl = null;
    stopTypingSound();

    msgEl.classList.remove('paxdesign-booking-chat-message--typing');
    msgEl.removeAttribute('data-admin-typing');
    msgEl.setAttribute('data-msg-id', String(msg.id));

    var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
    if (!bubble) return false;

    if (msg.reply_to) {
      var quoteEl = renderQuoteBlock(msg.reply_to);
      if (quoteEl) msgEl.insertBefore(quoteEl, bubble);
    }

    bubble.classList.remove('paxdesign-booking-chat-typing-bubble');
    bubble.innerHTML = buildBubbleInnerHtml('admin', msg.content, { image_url: msg.image_url || '' });
    if (!msgEl.querySelector('.paxdesign-booking-chat-message-footer')) {
      attachMessageChrome(msgEl, bubble, 'admin', msg.content, msg.id, msg.reaction || '');
    } else {
      ensureAdminReplyButton(msgEl, msg.id);
    }
    scrollToBottom();
    return true;
  }

  function applyIncomingMessages(incoming) {
    var played = false;
    incoming.forEach(function (msg) {
      if (!msg || !msg.id) return;
      if (domMsgIds[msg.id]) return;
      if (msg.role === 'system' && msg.content === 'Der Kunde hat das Gespräch beendet.') {
        var existingClose = threadEl.querySelector('.paxdesign-booking-chat-message--system');
        if (existingClose) {
          var bubbles = threadEl.querySelectorAll('.paxdesign-booking-chat-message--system .paxdesign-booking-chat-message-bubble');
          for (var ci = 0; ci < bubbles.length; ci++) {
            if (bubbles[ci].textContent.trim() === msg.content) {
              domMsgIds[msg.id] = true;
              seenMsgId(msg.id);
              return;
            }
          }
        }
      }
      if (msg.role === 'user') return;
      if (msg.role === 'assistant' && isStreaming) return;
      if (msg.role === 'assistant' && streamingMsgId && msg.id === streamingMsgId) return;

      domMsgIds[msg.id] = true;
      seenMsgId(msg.id);
      if (msg.reaction) messageReactions[msg.id] = msg.reaction;
      indexChatMessage(msg);

      if (msg.role === 'admin') {
        stopTypingSound();
        if (!morphAdminTypingToMessage(msg)) {
          removeAdminTypingIndicator();
          renderMessageDom(msg.role, msg.content, msg.id, {
            reaction: msg.reaction || '',
            reply_to: msg.reply_to || 0,
            image_url: msg.image_url || ''
          });
        }
        if (!played) {
          playMessengerPop(false);
          played = true;
        }
      } else {
        renderMessageDom(msg.role, msg.content, msg.id, { reaction: msg.reaction || '', reply_to: msg.reply_to || 0, image_url: msg.image_url || '' });
      }

      if (msg.role === 'assistant' || msg.role === 'admin') {
        messages.push({ role: msg.role, content: msg.content, id: msg.id });
      } else if (msg.role === 'system') {
        messages.push({ role: 'system', content: msg.content, id: msg.id });
      }
    });
    if (played) syncChatLog();
  }

  function seenMsgId(id) {
    pollSeq = Math.max(pollSeq, id);
  }

  function getLiveAgent() {
    return (config && config.liveAgent) ? config.liveAgent : { name: 'Ahmad Alkhalaf', avatar: '' };
  }

  function copyPlainText(text) {
    text = (text || '').trim();
    if (!text) return Promise.reject();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        resolve();
      } catch (e) {
        reject(e);
      }
    });
  }

  function flashActionBtn(btn, label) {
    if (!btn) return;
    btn.classList.add('paxdesign-is-active');
    var prev = btn.getAttribute('aria-label');
    if (label) btn.setAttribute('aria-label', label);
    setTimeout(function () {
      btn.classList.remove('paxdesign-is-active');
      if (prev) btn.setAttribute('aria-label', prev);
    }, 1400);
  }

  function flashCopyBtn(btn) {
    if (!btn) return;
    var original = btn.getAttribute('data-icon-html') || ACTION_ICONS.copy;
    if (!btn.getAttribute('data-icon-html')) {
      btn.setAttribute('data-icon-html', btn.innerHTML || ACTION_ICONS.copy);
      original = btn.getAttribute('data-icon-html');
    }
    btn.innerHTML = ACTION_ICONS.copied;
    btn.classList.add('paxdesign-is-copied');
    btn.setAttribute('aria-label', 'Kopiert');
    window.setTimeout(function () {
      btn.innerHTML = original;
      btn.classList.remove('paxdesign-is-copied');
      btn.setAttribute('aria-label', 'Nachricht kopieren');
    }, 1600);
  }

  function sharePlainText(text, btn) {
    text = (text || '').trim();
    if (!text) return Promise.resolve();
    if (navigator.share) {
      return navigator.share({ text: text }).catch(function () {
        return copyPlainText(text).then(function () { flashActionBtn(btn, 'Kopiert'); });
      });
    }
    return copyPlainText(text).then(function () { flashActionBtn(btn, 'Kopiert'); });
  }

  function createActionButton(classSuffix, label, iconHtml, onClick) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'paxdesign-booking-chat-action paxdesign-booking-chat-action--' + classSuffix;
    btn.setAttribute('aria-label', label);
    btn.setAttribute('data-icon-html', iconHtml);
    btn.innerHTML = iconHtml;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      onClick(btn);
    });
    return btn;
  }

  function getMessagePlainText(bubble, plainText) {
    if (plainText) return String(plainText).trim();
    return bubble ? String(bubble.textContent || '').trim() : '';
  }

  function markMessageFailed(msgId, text) {
    var msgEl = threadEl.querySelector('[data-msg-id="' + msgId + '"]');
    if (!msgEl) return;
    msgEl.classList.add('paxdesign-booking-chat-message--failed');
    msgEl.setAttribute('data-failed', '1');
    msgEl.setAttribute('data-failed-text', text || '');
    refreshMessageActions(msgEl);
  }

  function clearMessageFailed(msgEl) {
    if (!msgEl) return;
    msgEl.classList.remove('paxdesign-booking-chat-message--failed');
    msgEl.removeAttribute('data-failed');
    msgEl.removeAttribute('data-failed-text');
    refreshMessageActions(msgEl);
  }

  function retryFailedMessage(msgEl, msgId, text, role) {
    text = (text || msgEl.getAttribute('data-failed-text') || '').trim();
    if (!text) return;
    clearMessageFailed(msgEl);
    if (isHumanMode()) {
      isStreaming = true;
      updateSendButton();
      sendHumanModeMessage(text)
        .then(function () { syncChatLog(); })
        .catch(function (err) {
          markMessageFailed(msgId, text);
          showError(err.message || 'Verbindungsfehler.');
        })
        .finally(function () {
          isStreaming = false;
          updateSendButton();
        });
      return;
    }
    sendMessage(text);
  }

  function refreshMessageActions(msgEl) {
    if (!msgEl) return;
    var msgId = parseInt(msgEl.getAttribute('data-msg-id'), 10) || 0;
    var roleClass = Array.prototype.find.call(msgEl.classList, function (c) {
      return c.indexOf('paxdesign-booking-chat-message--') === 0 && c !== 'paxdesign-booking-chat-message--failed' && c !== 'paxdesign-booking-chat-message--typing';
    });
    var role = roleClass ? roleClass.replace('paxdesign-booking-chat-message--', '') : 'assistant';
    if (role === 'system' || role === 'typing') return;
    var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
    var msg = chatMessageMap[msgId] || { content: bubble ? bubble.textContent : '' };
    var footer = msgEl.querySelector('.paxdesign-booking-chat-message-footer');
    if (footer) footer.remove();
    attachMessageChrome(msgEl, bubble, role, msg.content || '', msgId, messageReactions[msgId] || '', {
      failed: msgEl.getAttribute('data-failed') === '1',
    });
  }

  function normalizeMessageReaction(reaction) {
    if (reaction === 'like' || reaction === 'dislike') return reaction;
    if (reaction === 'pax-love') return 'like';
    if (reaction === 'pax-top' || reaction === 'pax-thanks' || reaction === 'pax-clear') return 'dislike';
    return '';
  }

  function sendReaction(messageId, reactionKey) {
    if (!config.ajaxUrl || !messageId) return;
    if (reactionKey !== 'like' && reactionKey !== 'dislike') return;
    var current = normalizeMessageReaction(messageReactions[messageId] || '');
    var next = current === reactionKey ? '' : reactionKey;
    var prev = current;
    if (next) messageReactions[messageId] = next;
    else delete messageReactions[messageId];
    updateReactionUi(messageId, next);

    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_reaction');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('message_id', String(messageId));
    formData.append('reaction', next);
    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          if (prev) messageReactions[messageId] = prev;
          else delete messageReactions[messageId];
          updateReactionUi(messageId, prev);
          return;
        }
        if (next) messageReactions[messageId] = next;
        else delete messageReactions[messageId];
        updateReactionUi(messageId, next);
      })
      .catch(function () {
        if (prev) messageReactions[messageId] = prev;
        else delete messageReactions[messageId];
        updateReactionUi(messageId, prev);
      });
  }

  function updateReactionUi(messageId, reactionKey) {
    var msgEl = threadEl.querySelector('[data-msg-id="' + messageId + '"]');
    if (!msgEl) return;
    var actions = msgEl.querySelector('.paxdesign-booking-chat-message-actions');
    if (!actions) return;
    var normalized = normalizeMessageReaction(reactionKey);
    actions.querySelectorAll('.paxdesign-booking-chat-action--like, .paxdesign-booking-chat-action--dislike').forEach(function (btn) {
      var key = btn.classList.contains('paxdesign-booking-chat-action--like') ? 'like' : 'dislike';
      var active = key === normalized;
      btn.classList.toggle('paxdesign-is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.title = active
        ? (PAX_FEEDBACK_KEYS[key] + ' — erneut tippen zum Entfernen')
        : PAX_FEEDBACK_KEYS[key];
    });
  }

  function createReplyButton(msgId) {
    return createActionButton('reply', 'Auf diese Nachricht antworten', ACTION_ICONS.reply, function () {
      setClientReply(msgId);
    });
  }

  function ensureAdminReplyButton(msgEl, msgId) {
    if (!msgEl || !msgId) return;
    var actions = msgEl.querySelector('.paxdesign-booking-chat-message-actions');
    if (!actions || actions.querySelector('.paxdesign-booking-chat-action--reply')) return;
    if (chatHandler === 'closed') return;
    actions.appendChild(createReplyButton(msgId));
  }

  function ensureAdminMessageChrome() {
    threadEl.querySelectorAll('.paxdesign-booking-chat-message--admin[data-msg-id]').forEach(function (msgEl) {
      var msgId = parseInt(msgEl.getAttribute('data-msg-id'), 10);
      if (!msgId) return;
      var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
      if (!bubble) return;
      var oldReact = msgEl.querySelector('.paxdesign-booking-chat-pax-reactions');
      if (oldReact) oldReact.remove();
      var msg = chatMessageMap[msgId] || { id: msgId, role: 'admin', content: bubble.textContent || '' };
      if (!msgEl.querySelector('.paxdesign-booking-chat-message-footer')) {
        attachMessageChrome(msgEl, bubble, 'admin', msg.content || bubble.textContent || '', msgId, messageReactions[msgId] || '');
        return;
      }
      var actions = msgEl.querySelector('.paxdesign-booking-chat-message-actions');
      if (actions && chatHandler !== 'closed' && !actions.querySelector('.paxdesign-booking-chat-action--like')) {
        actions.appendChild(createActionButton('like', PAX_FEEDBACK_KEYS.like, ACTION_ICONS.like, function () {
          sendReaction(msgId, 'like');
        }));
        actions.appendChild(createActionButton('dislike', PAX_FEEDBACK_KEYS.dislike, ACTION_ICONS.dislike, function () {
          sendReaction(msgId, 'dislike');
        }));
        updateReactionUi(msgId, messageReactions[msgId] || '');
      }
      ensureAdminReplyButton(msgEl, msgId);
    });
  }

  function applyReactionStates(reactions) {
    Object.keys(reactions).forEach(function (id) {
      var mid = parseInt(id, 10);
      if (!mid || !reactions[id]) return;
      var normalized = normalizeMessageReaction(reactions[id]);
      if (!normalized) return;
      messageReactions[mid] = normalized;
      updateReactionUi(mid, normalized);
    });
  }

  function indexChatMessage(msg) {
    if (msg && msg.id) chatMessageMap[msg.id] = msg;
  }

  function clearClientReply() {
    replyToId = 0;
    if (replyBar) replyBar.hidden = true;
    if (replyPreview) replyPreview.textContent = '';
  }

  function setClientReply(msgId) {
    var msg = chatMessageMap[msgId];
    if (!msg || !replyBar) return;
    replyToId = msgId;
    if (replyPreview) replyPreview.textContent = String(msg.content || '').slice(0, 90);
    replyBar.hidden = false;
    input.focus();
  }

  function renderQuoteBlock(replyTo) {
    var src = chatMessageMap[replyTo];
    if (!src) return null;
    var q = document.createElement('div');
    q.className = 'paxdesign-booking-chat-quote';
    var agent = getLiveAgent();
    var author = src.role === 'admin' ? getCustomerAgentLabel() : (src.role === 'user' ? 'Sie' : 'KI');
    q.innerHTML = '<span class="paxdesign-booking-chat-quote-author">' + escapeHtml(author) + '</span>' +
      escapeHtml(String(src.content || '').slice(0, 120));
    return q;
  }

  function attachMessageChrome(msgEl, bubble, role, plainText, msgId, reactionKey, opts) {
    opts = opts || {};
    if (role === 'system') return;

    var footer = document.createElement('div');
    footer.className = 'paxdesign-booking-chat-message-footer';

    var actions = document.createElement('div');
    actions.className = 'paxdesign-booking-chat-message-actions';

    var text = getMessagePlainText(bubble, plainText);
    var failed = !!opts.failed || msgEl.getAttribute('data-failed') === '1';

    actions.appendChild(createActionButton('copy', 'Nachricht kopieren', ACTION_ICONS.copy, function (btn) {
      copyPlainText(text).then(function () { flashCopyBtn(btn); }).catch(function () {});
    }));

    if (text) {
      actions.appendChild(createActionButton('share', 'Nachricht teilen', ACTION_ICONS.share, function (btn) {
        sharePlainText(text, btn);
      }));
    }

    if ((role === 'admin' || role === 'assistant') && msgId && chatHandler !== 'closed') {
      actions.appendChild(createReplyButton(msgId));
    }

    if (role === 'admin' && msgId && chatHandler !== 'closed') {
      actions.appendChild(createActionButton('like', PAX_FEEDBACK_KEYS.like, ACTION_ICONS.like, function () {
        sendReaction(msgId, 'like');
      }));
      actions.appendChild(createActionButton('dislike', PAX_FEEDBACK_KEYS.dislike, ACTION_ICONS.dislike, function () {
        sendReaction(msgId, 'dislike');
      }));
    }

    if (failed && text) {
      actions.appendChild(createActionButton('retry', 'Erneut senden', ACTION_ICONS.retry, function () {
        retryFailedMessage(msgEl, msgId, text, role);
      }));
    }

    footer.appendChild(actions);
    msgEl.appendChild(footer);

    if (reactionKey) {
      var normalizedReaction = normalizeMessageReaction(reactionKey);
      if (normalizedReaction) {
        messageReactions[msgId] = normalizedReaction;
        updateReactionUi(msgId, normalizedReaction);
      }
    }
  }

  function showAdminTypingIndicator() {
    if (adminTypingEl) return;
    adminTypingEl = document.createElement('div');
    adminTypingEl.className = 'paxdesign-booking-chat-message paxdesign-booking-chat-message--admin paxdesign-booking-chat-message--typing';
    adminTypingEl.setAttribute('data-admin-typing', '1');
    renderAdminMessageHeader(adminTypingEl);
    var bubble = document.createElement('div');
    bubble.className = 'paxdesign-booking-chat-message-bubble paxdesign-booking-chat-typing-bubble';
    bubble.innerHTML =
      '<span class="paxdesign-booking-chat-typing-dot"></span>' +
      '<span class="paxdesign-booking-chat-typing-dot"></span>' +
      '<span class="paxdesign-booking-chat-typing-dot"></span>';
    adminTypingEl.appendChild(bubble);
    threadEl.appendChild(adminTypingEl);
    scrollToBottom();
  }

  function removeAdminTypingIndicator() {
    stopTypingSound();
    if (adminTypingEl && adminTypingEl.parentNode) adminTypingEl.remove();
    adminTypingEl = null;
    var el = threadEl.querySelector('[data-admin-typing]');
    if (el) el.remove();
  }

  function pingUserTyping(stop) {
    if (!config.ajaxUrl || !isHumanMode() || chatHandler === 'closed') return;
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_user_typing');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    if (stop) formData.append('stop', '1');
    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' }).catch(function () {});
  }

  function clearUserTypingState() {
    if (userTypingTimer) {
      clearTimeout(userTypingTimer);
      userTypingTimer = null;
    }
    if (userTypingActive) {
      userTypingActive = false;
      pingUserTyping(true);
    }
  }

  function scheduleUserTypingPing() {
    if (!isHumanMode() || chatHandler === 'closed') return;
    pingUserTyping(false);
    userTypingActive = true;
    if (userTypingTimer) clearTimeout(userTypingTimer);
    userTypingTimer = setTimeout(function () {
      userTypingTimer = null;
      userTypingActive = false;
    }, 1800);
  }

  function renderAdminMessageHeader(msgEl) {
    var agent = getLiveAgent();
    var head = document.createElement('div');
    head.className = 'paxdesign-booking-chat-agent-head paxdesign-booking-chat-agent-head--live';
    if (agent.avatar) {
      var img = document.createElement('img');
      img.className = 'paxdesign-booking-chat-agent-avatar paxdesign-booking-chat-agent-avatar--clickable';
      img.src = agent.avatar;
      img.alt = getCustomerAgentLabel();
      img.width = 24;
      img.height = 24;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var modal = document.getElementById('paxdesignAgentProfileModal');
        if (modal) {
          modal.hidden = false;
          document.body.classList.add('paxdesign-agent-profile-open');
        }
      });
      head.appendChild(img);
    }
    var nameWrap = document.createElement('span');
    nameWrap.className = 'paxdesign-booking-chat-agent-ident';
    var name = document.createElement('span');
    name.className = 'paxdesign-booking-chat-agent-name';
    name.textContent = getCustomerAgentLabel();
    nameWrap.appendChild(name);
    if (agent.role) {
      var role = document.createElement('span');
      role.className = 'paxdesign-booking-chat-agent-role';
      role.textContent = agent.role;
      nameWrap.appendChild(role);
    }
    head.appendChild(nameWrap);
    var time = document.createElement('span');
    time.className = 'paxdesign-booking-chat-agent-time';
    time.textContent = formatMsgTime(Math.floor(Date.now() / 1000));
    head.appendChild(time);
    msgEl.insertBefore(head, msgEl.firstChild);
  }

  function buildBubbleInnerHtml(role, content, opts) {
    opts = opts || {};
    var html = '';
    if (opts.image_url) {
      html += '<div class="paxdesign-booking-chat-message-image"><img src="' + escapeHtml(opts.image_url) + '" alt="Foto" loading="lazy" decoding="async"></div>';
    }
    if (role === 'assistant' || role === 'admin') {
      if (content) html += formatMarkdown(content);
    } else if (role !== 'system' && content) {
      html += escapeHtml(content);
    }
    return html;
  }

  function renderMessageDom(role, content, msgId, opts) {
    opts = opts || {};
    root.classList.add('paxdesign-has-chat-messages');
    var msg = document.createElement('div');
    msg.className = 'paxdesign-booking-chat-message paxdesign-booking-chat-message--' + role;
    if (msgId) msg.setAttribute('data-msg-id', String(msgId));

    if (role === 'admin') {
      renderAdminMessageHeader(msg);
    }

    if (opts.reply_to) {
      var quoteEl = renderQuoteBlock(opts.reply_to);
      if (quoteEl) msg.appendChild(quoteEl);
    }

    var bubble = document.createElement('div');
    bubble.className = 'paxdesign-booking-chat-message-bubble';
    if (role === 'system') {
      bubble.textContent = content;
    } else {
      bubble.innerHTML = buildBubbleInnerHtml(role, content, opts);
    }

    msg.appendChild(bubble);
    if (role === 'user' || role === 'assistant') {
      var meta = document.createElement('div');
      meta.className = 'paxdesign-booking-chat-message-meta';
      meta.textContent = formatMsgTime(opts.ts || Math.floor(Date.now() / 1000));
      msg.appendChild(meta);
    }
    if (String(content || '').trim() || opts.image_url) {
      attachMessageChrome(msg, bubble, role, content, msgId, opts.reaction || '');
    }
    if (msgId) {
      indexChatMessage({
        id: msgId,
        role: role,
        content: content,
        reply_to: opts.reply_to || 0,
        image_url: opts.image_url || ''
      });
    }
    threadEl.appendChild(msg);
    scrollToBottom();
    if (!opts.skipPush && msgId) saveSessionSnapshot();
    return { bubble: bubble, messageEl: msg };
  }

  function initPlusToggle() {
    if (!plusBtn || !quickActions) return;
    plusBtn.addEventListener('click', function (e) {
      e.preventDefault();
      unlockAudio();
      var open = root.classList.toggle('paxdesign-chat-quick-open');
      quickActions.classList.toggle('paxdesign-is-open', open);
      plusBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      plusBtn.classList.toggle('paxdesign-is-active', open);
      notifyLayout();
    });
  }

  function notifyLayout() {
    if (window.PAXdesignBookingMobile && typeof window.PAXdesignBookingMobile.adjustLayout === 'function') {
      window.PAXdesignBookingMobile.adjustLayout();
    }
  }

  function abortStream() {
    if (abortCtrl) {
      abortCtrl.abort();
      abortCtrl = null;
    }
    isStreaming = false;
    streamingMsgId = 0;
    updateSendButton();
    removeTyping();
  }

  function initQuickActions() {
    if (!config.quickActions || !quickActions) return;
    quickActions.innerHTML = '';
    config.quickActions.forEach(function (action) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'paxdesign-booking-chat-quick-btn';
      btn.setAttribute('aria-label', action.label);
      btn.setAttribute('data-message', action.message);
      if (action.intent) btn.setAttribute('data-intent', action.intent);
      btn.textContent = action.label;
      quickActions.appendChild(btn);
    });
    if (!quickActions.dataset.bound) {
      quickActions.dataset.bound = '1';
      quickActions.addEventListener('click', function (e) {
        var btn = e.target.closest('.paxdesign-booking-chat-quick-btn');
        if (!btn || isStreaming) return;
        e.preventDefault();
        root.classList.remove('paxdesign-chat-quick-open');
        if (plusBtn) {
          plusBtn.classList.remove('paxdesign-is-active');
          plusBtn.setAttribute('aria-expanded', 'false');
        }
        quickActions.classList.remove('paxdesign-is-open');
        sendMessage(btn.getAttribute('data-message') || '', {
          intent: btn.getAttribute('data-intent') || '',
        });
      });
    }
  }

  function updateSendButton() {
    if (!sendBtn) return;
    var hasText = input.value.trim().length > 0;
    var inactive = chatHandler === 'closed' || !hasText || isStreaming;
    sendBtn.classList.toggle('paxdesign-is-disabled', inactive);
    sendBtn.setAttribute('aria-disabled', inactive ? 'true' : 'false');
    var composer = root.querySelector('.paxdesign-booking-chat-composer');
    if (composer) composer.classList.toggle('paxdesign-has-text', hasText);
  }

  function autoResizeInput() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 72) + 'px';
  }

  function scrollToBottom() {
    requestAnimationFrame(function () {
      if (!messagesEl) return;
      messagesEl.scrollTop = messagesEl.scrollHeight;
      requestAnimationFrame(function () {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      });
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function stripBookingMarkers(text) {
    return (text || '').replace(BOOKING_MARKER_RE, '').replace(/\s+$/, '');
  }

  function parseBookingMarker(text) {
    var match = BOOKING_MARKER_RE.exec(text || '');
    BOOKING_MARKER_RE.lastIndex = 0;
    return match ? match[1].trim() : '';
  }

  function isUserBookingIntent(text) {
    return USER_BOOKING_RE.test(text || '');
  }

  function isLiveAgentIntent(text) {
    return LIVE_AGENT_RE.test(text || '');
  }

  function buildChatSummary() {
    var parts = [];
    messages.slice(-6).forEach(function (msg) {
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin') {
        var line = stripBookingMarkers(msg.content);
        if (line) {
          var label = msg.role === 'user' ? 'Kunde' : (msg.role === 'admin' ? getCustomerAgentLabel() : 'Assistent');
          parts.push(label + ': ' + line);
        }
      }
    });
    return parts.join('\n');
  }

  function inferServiceFromConversation() {
    var blob = messages.map(function (m) { return m.content; }).join(' ').toLowerCase();
    var checks = [
      { keys: ['website', 'webseite', 'homepage'], service: 'Website' },
      { keys: ['web app', 'webapp', 'webanwendung'], service: 'Web App' },
      { keys: ['chatbot', 'ki-assistent', 'ai chatbot'], service: 'AI Chatbot' },
      { keys: ['automatisierung', 'ai automation'], service: 'AI Automation' },
      { keys: ['crm'], service: 'CRM System' },
      { keys: ['shop', 'e-commerce', 'ecommerce'], service: 'E-Commerce Shop' },
      { keys: ['booking', 'terminbuchung', 'buchungssystem'], service: 'Appointment Booking System' },
      { keys: ['support'], service: 'Support' },
      { keys: ['app', 'android', 'ios', 'mobile'], service: 'iOS + Android' },
      { keys: ['performance', 'geschwindigkeit', 'pagespeed', 'langsam'], service: 'Website Speed Optimization' },
      { keys: ['sicherheit', 'security', 'dsgvo', 'gdpr'], service: 'IT-Sicherheit' },
    ];
    for (var i = 0; i < checks.length; i++) {
      for (var j = 0; j < checks[i].keys.length; j++) {
        if (blob.indexOf(checks[i].keys[j]) !== -1) return checks[i].service;
      }
    }
    return '';
  }

  function syncChatLog(extra) {
    extra = extra || {};
    if (!config.ajaxUrl || messages.length === 0) return Promise.resolve();
    var syncMessages = messages.filter(function (m) {
      return m.role === 'user' || m.role === 'assistant';
    });
    if (!syncMessages.length) return Promise.resolve();
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_log');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('messages', JSON.stringify(syncMessages));
    formData.append('detected_service', extra.service || inferServiceFromConversation());
    formData.append('booking_triggered', extra.booking ? '1' : '0');
    formData.append('consultation_started', consultationLogged ? '1' : '0');
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        saveSessionSnapshot();
        return json;
      })
      .catch(function () { return null; });
  }

  function syncChatLogAsync(extra) {
    return Promise.resolve(syncChatLog(extra));
  }

  function logConsultationStarted() {
    if (consultationLogged) return;
    consultationLogged = true;
    syncChatLog({ consultation: true });
  }

  function triggerBookingHandoff(serviceName, note) {
    if (!config.autoBooking) return;
    if (!window.PAXdesignBooking || typeof window.PAXdesignBooking.openFromChat !== 'function') return;
    syncChatLog({ service: serviceName, booking: true });
    window.PAXdesignBooking.openFromChat({
      service: serviceName || inferServiceFromConversation() || 'Beratung',
      message: note || buildChatSummary(),
    });
  }

  function appendBookingCta(msgEl, serviceName) {
    if (!msgEl || !config.autoBooking) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'paxdesign-booking-chat-action-btn';
    btn.textContent = config.ctaText || 'Termin buchen';
    btn.addEventListener('click', function () {
      triggerBookingHandoff(serviceName, buildChatSummary());
    });
    msgEl.appendChild(btn);
    scrollToBottom();
  }

  function formatMarkdown(text) {
    var html = escapeHtml(stripBookingMarkers(text));
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    html = html.replace(/^[-•] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, function (match) {
      return '<ul>' + match + '</ul>';
    });
    html = html.replace(/\n\n/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');
    return '<p>' + html + '</p>';
  }

  function flushStreamBubble() {
    if (!pendingBubble) return;
    pendingBubble.innerHTML = formatMarkdown(pendingText);
    scrollToBottom();
  }

  function scheduleStreamUpdate(bubble, fullText) {
    pendingBubble = bubble;
    pendingText = fullText;
    if (streamRaf) return;
    streamRaf = requestAnimationFrame(function () {
      streamRaf = 0;
      flushStreamBubble();
    });
  }

  function appendLocalAssistant(content) {
    var id = nextLocalId();
    domMsgIds[id] = true;
    seenMsgId(id);
    var rendered = renderMessageDom('assistant', content, id);
    messages.push({ role: 'assistant', content: content, id: id });
    syncChatLog();
    playNotificationSound(false);
    return rendered;
  }

  function appendUserMessage(text, opts) {
    opts = opts || {};
    var userId = nextLocalId();
    domMsgIds[userId] = true;
    seenMsgId(userId);
    renderMessageDom('user', text, userId);
    messages.push({ role: 'user', content: text, id: userId });
    if (!opts.skipSync) syncChatLog();
    return userId;
  }

  function finalizeAssistantMessage(fullText, meta) {
    meta = meta || {};
    var cleanText = stripBookingMarkers(fullText);
    var serviceName = parseBookingMarker(fullText);

    if (cleanText) {
      var id = streamingMsgId || nextLocalId();
      domMsgIds[id] = true;
      seenMsgId(id);
      if (pendingMessageEl) {
        pendingMessageEl.setAttribute('data-msg-id', String(id));
        attachMessageChrome(pendingMessageEl, pendingBubble, 'assistant', cleanText, id, '');
      }
      messages.push({ role: 'assistant', content: cleanText, id: id });
    }

    streamingMsgId = 0;
    var ctaService = serviceName || (meta.userBookingIntent ? inferServiceFromConversation() : '');
    if (ctaService && meta.messageEl) appendBookingCta(meta.messageEl, ctaService);
    syncChatLog({ service: serviceName || inferServiceFromConversation(), booking: !!serviceName });
    playNotificationSound(false);

    if (config.autoBooking && serviceName) {
      setTimeout(function () {
        triggerBookingHandoff(serviceName, buildChatSummary());
      }, 700);
    }
  }

  function showTyping() {
    removeTyping();
    typingEl = document.createElement('div');
    typingEl.className = 'paxdesign-booking-chat-status';
    typingEl.setAttribute('data-typing', '1');
    typingEl.innerHTML =
      '<div class="paxdesign-booking-chat-status-inner">' +
        '<span class="paxdesign-booking-chat-typing-dot"></span>' +
        '<span class="paxdesign-booking-chat-typing-dot"></span>' +
        '<span class="paxdesign-booking-chat-typing-dot"></span>' +
        '<span class="paxdesign-booking-chat-status-text">' + STATUS_MESSAGES[0] + '</span>' +
      '</div>';
    threadEl.appendChild(typingEl);
    scrollToBottom();
    var idx = 0;
    statusInterval = window.setInterval(function () {
      idx = (idx + 1) % STATUS_MESSAGES.length;
      var label = typingEl.querySelector('.paxdesign-booking-chat-status-text');
      if (label) label.textContent = STATUS_MESSAGES[idx];
    }, 2800);
    return typingEl;
  }

  function removeTyping() {
    if (statusInterval) {
      clearInterval(statusInterval);
      statusInterval = null;
    }
    if (typingEl && typingEl.parentNode) typingEl.remove();
    typingEl = null;
    var el = threadEl.querySelector('[data-typing]');
    if (el) el.remove();
  }

  function showError(text) {
    removeTyping();
    var el = document.createElement('div');
    el.className = 'paxdesign-booking-chat-error';
    el.textContent = text;
    threadEl.appendChild(el);
    scrollToBottom();
    setTimeout(function () { el.remove(); }, 5000);
  }

  function sendHumanModeMessage(text) {
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_user_send');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('message', text);
    if (replyToId) formData.append('reply_to', String(replyToId));
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : 'Nachricht konnte nicht gesendet werden.');
        }
        clearClientReply();
        if (json.data && json.data.message && json.data.message.id) {
          domMsgIds[json.data.message.id] = true;
          seenMsgId(json.data.message.id);
          indexChatMessage(json.data.message);
        }
      });
  }

  function requestLiveAgent(topic, name) {
    name = (name || '').trim();
    if (name.length < 2) {
      showNamePrompt();
      return Promise.reject(new Error('Bitte geben Sie Ihren Namen ein (mindestens 2 Zeichen).'));
    }
    var syncMessages = messages.filter(function (m) {
      return m.role === 'user' || m.role === 'assistant';
    });
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_request');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('topic', topic || inferServiceFromConversation());
    formData.append('customer_name', name);
    formData.append('messages', JSON.stringify(syncMessages));
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : 'Weiterleitung fehlgeschlagen.');
        }
        applyHandlerState('live_request', '');
        liveAgentPhase = 2;
        saveLiveAgentPhase();
        if (json.data && Array.isArray(json.data.messages)) {
          json.data.messages.forEach(function (msg) {
            if (!msg || !msg.id || domMsgIds[msg.id]) return;
            domMsgIds[msg.id] = true;
            seenMsgId(msg.id);
            renderMessageDom(msg.role, msg.content, msg.id);
            messages.push({ role: msg.role, content: msg.content, id: msg.id });
          });
        }
        playNotificationSound(false);
        syncChatLog({ service: topic || inferServiceFromConversation() });
      });
  }

  function handleLiveAgentFlow(text) {
    if (chatHandler !== 'ai' || liveAgentPhase >= 2 || chatHandler === 'live_request') return false;

    if (liveAgentPhase === 0 && entryChoice === 'live') {
      appendUserMessage(text);
      pendingLiveTopic = text || inferServiceFromConversation();
      liveAgentPhase = 1;
      saveLiveAgentPhase();
      showLiveConfirm();
      return true;
    }

    if (liveAgentPhase === 0 && isLiveAgentIntent(text)) {
      appendUserMessage(text);
      pendingLiveTopic = text || inferServiceFromConversation();
      liveAgentPhase = 1;
      saveLiveAgentPhase();
      showLiveConfirm();
      return true;
    }

    if (liveAgentPhase === 1 && !liveNameConfirmed && chatHandler === 'ai') {
      pendingLiveTopic = text || pendingLiveTopic || inferServiceFromConversation();
      showLiveConfirm();
      return true;
    }

    return false;
  }

  function sendMessage(text, opts) {
    opts = opts || {};
    text = (text || input.value).trim();
    if (!text || isStreaming) return;
    if (awaitingName || awaitingLiveConfirm) return;
    if (chatHandler === 'closed') {
      showError('Dieser Chat wurde geschlossen.');
      return;
    }

    clearUserTypingState();
    unlockAudio();
    var userBookingIntent = opts.intent === 'booking' || isUserBookingIntent(text);

    if (opts.intent === 'live') {
      setEntryChoice('live');
      text = text || 'Ich möchte mit einem Live-Agent sprechen.';
    }

    input.value = '';
    autoResizeInput();
    updateSendButton();

    if (handleLiveAgentFlow(text)) return;

    if (isHumanMode()) {
      var userMsgId = appendUserMessage(text, { skipSync: true });
      isStreaming = true;
      updateSendButton();
      sendHumanModeMessage(text)
        .then(function () {
          clearMessageFailed(threadEl.querySelector('[data-msg-id="' + userMsgId + '"]'));
          syncChatLog();
        })
        .catch(function (err) {
          markMessageFailed(userMsgId, text);
          showError(err.message || 'Verbindungsfehler.');
        })
        .finally(function () {
          isStreaming = false;
          updateSendButton();
        });
      return;
    }

    appendUserMessage(text);

    isStreaming = true;
    updateSendButton();
    showTyping();

    var formData = new FormData();
    formData.append('action', 'paxdesign_chat');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('messages', JSON.stringify(messages.filter(function (m) {
      return m.role === 'user' || m.role === 'assistant';
    })));
    formData.append('website', honeypot ? honeypot.value : '');
    abortCtrl = new AbortController();

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      signal: abortCtrl.signal,
      credentials: 'same-origin',
    })
      .then(function (response) {
        if (!response.ok) {
          return response.json().then(function (data) {
            throw new Error(data.data && data.data.message ? data.data.message : 'Fehler bei der Anfrage.');
          }).catch(function () {
            throw new Error('Fehler bei der Anfrage.');
          });
        }

        var bubble = null;
        var messageEl = null;
        var fullText = '';
        var streamError = false;
        var gotFirstChunk = false;

        var contentType = response.headers.get('content-type') || '';
        if (contentType.indexOf('text/event-stream') === -1) {
          return response.text().then(function (body) {
            var message = 'Fehler bei der Anfrage.';
            try {
              var json = JSON.parse(body);
              if (json.data && json.data.message) message = json.data.message;
            } catch (e) {
              if (body && body.indexOf('-1') === 0) message = 'Sitzung abgelaufen. Bitte laden Sie die Seite neu.';
            }
            throw new Error(message);
          });
        }

        if (!response.body || !response.body.getReader) {
          throw new Error('Streaming nicht verfügbar.');
        }

        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';

        function ensureAssistantBubble() {
          if (bubble) return;
          removeTyping();
          streamingMsgId = nextLocalId();
          domMsgIds[streamingMsgId] = true;
          seenMsgId(streamingMsgId);
          var rendered = renderMessageDom('assistant', '', streamingMsgId);
          bubble = rendered.bubble;
          messageEl = rendered.messageEl;
          pendingMessageEl = messageEl;
        }

        function processLine(line) {
          line = line.trim();
          if (!line || line === 'data: [DONE]') return;
          if (line.indexOf('data: ') !== 0) return;
          try {
            var data = JSON.parse(line.slice(6));
            if (data.type === 'text' && data.text) {
              if (!gotFirstChunk) {
                gotFirstChunk = true;
                ensureAssistantBubble();
                fullText += data.text;
                pendingBubble = bubble;
                pendingText = fullText;
                flushStreamBubble();
              } else {
                fullText += data.text;
                scheduleStreamUpdate(bubble, fullText);
              }
            } else if (data.error || data.type === 'error') {
              streamError = true;
              removeTyping();
              showError(data.message || data.error || 'Ein Fehler ist aufgetreten.');
            } else if (data.type === 'text-delta' && data.delta) {
              if (!gotFirstChunk) {
                gotFirstChunk = true;
                ensureAssistantBubble();
                fullText += data.delta;
                pendingBubble = bubble;
                pendingText = fullText;
                flushStreamBubble();
              } else {
                fullText += data.delta;
                scheduleStreamUpdate(bubble, fullText);
              }
            }
          } catch (e) {}
        }

        function pump() {
          return reader.read().then(function (result) {
            if (result.done) {
              flushStreamBubble();
              if (fullText) {
                finalizeAssistantMessage(fullText, {
                  messageEl: messageEl,
                  userBookingIntent: userBookingIntent,
                });
              } else if (!streamError) {
                removeTyping();
                if (!gotFirstChunk) {
                  showError('Keine Antwort erhalten. Bitte versuchen Sie es erneut.');
                } else if (messageEl && messageEl.parentElement && !bubble.textContent.trim()) {
                  messageEl.parentElement.remove();
                  if (streamingMsgId) delete domMsgIds[streamingMsgId];
                  streamingMsgId = 0;
                }
              }
              isStreaming = false;
              pendingMessageEl = null;
              updateSendButton();
              return;
            }
            buffer += decoder.decode(result.value, { stream: true });
            var lines = buffer.split('\n');
            buffer = lines.pop();
            lines.forEach(processLine);
            return pump();
          });
        }

        return pump();
      })
      .catch(function (err) {
        if (err.name === 'AbortError') return;
        removeTyping();
        showError(err.message || 'Verbindungsfehler. Bitte versuchen Sie es erneut.');
        isStreaming = false;
        streamingMsgId = 0;
        pendingMessageEl = null;
        updateSendButton();
      });
  }

  function handleSend(e) {
    if (e) e.preventDefault();
    if (sendBtn && sendBtn.classList.contains('paxdesign-is-disabled')) return;
    sendMessage();
  }

  if (replyClearBtn) {
    replyClearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      clearClientReply();
    });
  }

  form.addEventListener('submit', handleSend);
  if (sendBtn) sendBtn.addEventListener('click', handleSend);
  if (newSessionBtn) {
    newSessionBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (chatHandler === 'closed' && isSessionArchived(getSessionId())) {
        beginFreshSessionSilently();
      } else {
        startNewConversation();
      }
    });
  }
  input.addEventListener('input', function () {
    autoResizeInput();
    updateSendButton();
    scheduleUserTypingPing();
  });
  input.addEventListener('focus', function () {
    setTimeout(notifyLayout, 50);
    setTimeout(notifyLayout, 300);
  });
  input.addEventListener('blur', function () {
    clearUserTypingState();
    setTimeout(notifyLayout, 100);
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend(e);
    }
  });

  window.PAXdesignChat = {
    init: init,
    onOpen: onWidgetOpen,
    onClose: onWidgetClose,
    abort: abortStream,
    sendMessage: sendMessage,
  };

})();
