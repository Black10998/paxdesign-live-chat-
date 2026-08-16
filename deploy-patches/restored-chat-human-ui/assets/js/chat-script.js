/**
 * PAXdesign AI Chat — Sales & Booking Assistant
 * Version: 3.174.128
 */
(function () {
  'use strict';

  var path = window.location.pathname || '';
  if (path.indexOf('cybercrime-support') !== -1) {
    window.PAXdesignPageContext = window.PAXdesignPageContext || {};
    if (!window.PAXdesignPageContext.intent) {
      window.PAXdesignPageContext.intent = 'cybercrime-support';
    }
  }
})();

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
  var voiceBtn     = root.querySelector('.paxdesign-booking-chat-voice');
  var plusBtn      = root.querySelector('.paxdesign-booking-chat-composer .paxdesign-booking-chat-plus');
  var notifyBtn    = root.querySelector('.paxdesign-booking-chat-notify');
  var honeypot     = root.querySelector('.paxdesign-booking-chat-honeypot');
  var closedBar    = root.querySelector('.paxdesign-booking-chat-closed-bar');
  var newSessionBtn = root.querySelector('.paxdesign-booking-chat-new-session');
  var replyBar      = root.querySelector('.paxdesign-booking-chat-reply-bar');
  var replyPreview  = root.querySelector('.paxdesign-booking-chat-reply-preview');
  var replyClearBtn = root.querySelector('.paxdesign-booking-chat-reply-clear');

  var entryEl          = root.querySelector('#paxdesignChatEntry');
  var welcomeEl        = root.querySelector('.paxdesign-booking-chat-welcome');

  var messages           = [];
  var lastUserSyncPromise = Promise.resolve();
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
  var assignedAgent      = null;
  var pollSeq            = 0;
  var appliedMessageSeq  = 0;
  var unifiedSyncInFlight = null;
  var unifiedSyncQueued  = false;
  var unifiedSyncTimer   = 0;
  var pollTimer          = null;
  var composerWantsKeyboard = false;
  var sendInFlight            = false;
  var voiceRecognition      = null;
  var voiceListening        = false;
  var voiceMicStream        = null;
  var voiceAudioCtx         = null;
  var voiceAnalyser         = null;
  var voiceWaveRaf          = 0;
  var voiceRecordingEl      = null;
  var voiceWavePathEl       = null;
  var voiceBaseText         = '';
  var voiceMicPermission    = 'unknown';
  var voiceMicSessionReady  = false;
  var voiceStartInFlight    = false;
  var voicePendingMicRetry  = false;
  var voiceInputMaxHeight   = 120;
  var HISTORY_INITIAL    = 10;
  var HISTORY_BATCH      = 10;
  var oldestLoadedSeq    = 0;
  var hasOlderMessages   = false;
  var loadingOlderHistory = false;
  var historyScrollBound = false;
  var historyLoadedAt = 0;
  var HISTORY_REUSE_MS = 8000;
  var localMsgId         = 0;
  var domMsgIds          = {};
  var domClientMsgIds    = {};
  var cachedSessionId    = null;
  var customerEndedChat  = false;
  var deletedMessageIds  = {};
  var deletingInProgress = {};
  var MESSAGE_TRANSFORM_MS = 200;
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
  var authGateEl         = null;
  var authGateVerifyEl   = null;
  var authGateBound      = false;

  var customerName       = '';
  var pendingLiveTopic   = '';
  var liveNameConfirmed  = false;
  var sessionRating      = 0;
  var ratingSubmitted    = false;
  var prevChatHandler    = '';
  var mp3AudioCache      = {};

  var SOUND_URLS = (config && config.sounds) ? config.sounds : {
    typing: 'https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3',
    openClose: 'https://paxdesign.at/wp-content/uploads/2026/06/u_8e8ungop1x-intro_cinematic-270840.mp3',
    incoming: ''
  };

  var endWrapEl          = root.querySelector('#paxdesignChatEndWrap');
  var endBtnEl           = root.querySelector('#paxdesignChatEndBtn');
  var ratingEl           = root.querySelector('#paxdesignChatRating');
  var ratingThanksEl     = root.querySelector('#paxdesignChatRatingThanks');

  if (!messagesEl || !threadEl || !form || !input) return;

  var entryChoice      = '';
  var sessionRestored  = false;
  var bootPromise      = null;
  var sessionLoading   = false;

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
  var cachedDeviceToken = null;
  var CONSULTATION_KEY  = 'paxdesign-chat-consultation';
  var ARCHIVED_IDS_KEY  = 'paxdesign-chat-archived-ids';
  var lastAuthUserId    = null;
  var BOOKING_MARKER_RE = /\[\[BOOKING:([^\]]+)\]\]/gi;
  var USER_BOOKING_RE   = /(?:termin\s*(?:buch|vereinbar|machen|wunsch)?|beratung\s*buchen|kontakt\s*aufnehmen|ruf(?:en)?\s*(?:mich\s*)?an|(?:ein\s*)?angebot|möchte\s*(?:einen?\s*)?termin|ja[\s,]*(?:bitte|gerne|ok)?\s*(?:termin|buchen)?)/i;
  var LIVE_AGENT_RE     = /(?:mit\s+(?:einem\s+)?(?:mitarbeiter|menschen|echten|support|agent|berater)|live\s*(?:agent|chat|support)|(?:kann|möchte|will)\s+ich\s+(?:mit\s+)?(?:einem\s+)?(?:menschen|mitarbeiter|support|agent|berater)|(?:kann|darf)\s+ich\s+mit|sprechen\s+(?:sie\s+)?mit|echter?\s+mensch|menschlichen?\s+support|echte\s+person)/i;
  var STATUS_MESSAGES   = [
    'KI analysiert Ihre Anfrage …',
    'Antwort wird erstellt …',
    'Einen kleinen Moment bitte …',
  ];
  var LIVE_QUALIFY_TEXT   = 'Gerne. Damit ich Sie richtig weiterleiten kann: Worum geht es kurz — Website, AI Chatbot, Booking, Support oder ein anderes Thema?';
  var POLL_INTERVAL_MS    = 1200;
  var POLL_INTERVAL_OPEN_MS = 450;
  var POLL_INTERVAL_HUMAN_MS = 800;
  var POLL_INTERVAL_BACKGROUND_MS = 2000;
  var POLL_INTERVAL_SSE_MS = 10000;
  var STREAM_RECONNECT_BASE_MS = 1500;
  var STREAM_RECONNECT_MAX_MS = 30000;
  var EDGE_BLOCK_PAUSE_MS = 300000;
  var customerStreamReconnectDelay = STREAM_RECONNECT_BASE_MS;
  var edgeBlockUntil = 0;
  var widgetOpen          = false;
  var stickToBottom       = true;
  var scrollRaf           = 0;
  var pollInFlight        = null;
  var sessionFetchInFlight = null;
  var pageVisible         = !document.hidden;
  var streamSource        = null;
  var streamEventSince    = 0;
  var streamRestartTimer  = null;
  var READINESS_TIMEOUT_MS = 45000;
  var READINESS_STREAM_WAIT_MS = 3000;
  var READINESS_STREAM_BACKGROUND_MS = 12000;
  var READINESS_AUTO_RETRY_MAX = 2;
  var READINESS_AUTO_RETRY_DELAY_MS = 2500;
  var READINESS_STEPS = {
    authentication: 'authentication',
    session: 'session',
    history: 'history',
    liveAgent: 'live_agent',
    sync: 'sync',
    sse: 'sse',
    finalSync: 'final_sync',
    timeout: 'timeout',
  };
  var chatPanelEl = null;
  var readinessEl = null;
  var readinessLoadingEl = null;
  var readinessErrorEl = null;
  var readinessTextEl = null;
  var readinessErrorTextEl = null;
  var readinessRetryBtn = null;
  var readinessCloseBtn = null;
  var readinessState = 'idle';
  var readinessGeneration = 0;
  var readinessPromise = null;
  var readinessAutoRetryTimer = null;
  var readinessUiBound = false;
  var lastReadinessPollAt = 0;
  var silentRefreshPromise = null;
  var silentRefreshGeneration = 0;
  var streamCacheBustNext = false;

  function customerStreamUrl(cacheBust) {
    var parts = [
      'action=paxdesign_chat_stream',
      'nonce=' + encodeURIComponent(config.nonce),
      'session_id=' + encodeURIComponent(getSessionId()),
      'since=' + encodeURIComponent(String(streamEventSince)),
    ];
    if (cacheBust) {
      parts.push('_=' + encodeURIComponent(String(Date.now())));
    }
    return config.ajaxUrl + '?' + parts.join('&');
  }

  function stopCustomerStream() {
    if (streamRestartTimer) {
      clearTimeout(streamRestartTimer);
      streamRestartTimer = null;
    }
    if (streamSource) {
      streamSource.close();
      streamSource = null;
    }
  }

  function scheduleCustomerStreamRestart(delayMs) {
    if (isEdgeBlocked()) return;
    if (streamRestartTimer) clearTimeout(streamRestartTimer);
    var delay = typeof delayMs === 'number' ? delayMs : customerStreamReconnectDelay;
    streamRestartTimer = window.setTimeout(function () {
      streamRestartTimer = null;
      startCustomerStream();
      customerStreamReconnectDelay = Math.min(customerStreamReconnectDelay * 2, STREAM_RECONNECT_MAX_MS);
    }, delay);
  }

  function customerStreamConnected() {
    return streamSource && streamSource.readyState === EventSource.OPEN;
  }

  function isEdgeBlocked() {
    return Date.now() < edgeBlockUntil;
  }

  function isEdgeForbiddenResponse(res, bodyText) {
    if (!res || res.status !== 403) return false;
    var body = bodyText || '';
    return body.indexOf('Access to this resource on the server is denied') !== -1;
  }

  function markEdgeBlocked() {
    if (isEdgeBlocked()) return;
    edgeBlockUntil = Date.now() + EDGE_BLOCK_PAUSE_MS;
    stopCustomerStream();
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    window.setTimeout(function () {
      edgeBlockUntil = 0;
      customerStreamReconnectDelay = STREAM_RECONNECT_BASE_MS;
      if (pageVisible && canUseChat()) {
        pollUpdates();
        scheduleLivePolling();
        if (widgetOpen || isPersistentAccountChat()) startCustomerStream();
      }
    }, EDGE_BLOCK_PAUSE_MS);
  }

  function handleCustomerStreamPayload(data) {
    if (!data || !data.type) return;
    if (typeof data.id === 'number') {
      streamEventSince = Math.max(streamEventSince, data.id);
    }
    var payload = data.payload || {};
    if (data.type === 'message' && Array.isArray(payload.message ? [payload.message] : payload.messages)) {
      scheduleUnifiedSync('sse-message');
    }
    if (data.type === 'link_scan_updated' && payload.message) {
      if (!isMessagePermanentlyDeleted(payload.message.id)) {
        updateCustomerLinkScanMessage(payload.message);
      }
    }
    if (data.type === 'message_deleted' && payload.message_id) {
      transformCustomerMessageInPlace(
        payload.message_id,
        payload.tombstone || 'This message was deleted by an employee.',
        { warn: !!payload.warn || (payload.tombstone || '').indexOf('unsafe link') !== -1 }
      );
    }
    if (data.type === 'typing') {
      if (payload.active && payload.who === 'admin' && chatHandler === 'admin') {
        if (!adminTypingEl) showAdminTypingIndicator();
        syncTypingSound(true);
      } else if (payload.active && payload.who === 'assistant' && chatHandler === 'ai' && !isStreaming) {
        if (!typingEl) showTyping();
      } else if (payload.who === 'admin') {
        stopAdminTypingFeedback();
      } else if (payload.who === 'assistant' && !isStreaming) {
        removeTyping();
      }
    }
    if (data.type === 'handler' && payload.handler) {
      if (payload.message) {
        scheduleUnifiedSync('sse-handler-message');
      }
      onRealtimeHandlerChange(payload.handler, payload.admin_name || '');
    }
    if ((data.type === 'typing' || data.type === 'handler') && !customerStreamConnected()) {
      pollUpdates();
    }
  }

  function onRealtimeHandlerChange(handler, name) {
    applyHandlerState(handler, name || '');
    if (!customerStreamConnected()) {
      pollUpdates();
    }
    scheduleLivePolling();
    scheduleCustomerStreamRestart(customerStreamReconnectDelay);
  }

  function startCustomerStream() {
    if (!pageVisible || typeof EventSource === 'undefined') return;
    if (!canUseChat()) return;
    if (!getSessionId()) return;
    if (!widgetOpen && !isPersistentAccountChat()) return;
    if (!isPersistentAccountChat() && chatHandler === 'closed') return;
    if (isEdgeBlocked()) return;
    stopCustomerStream();
    var bustCache = streamCacheBustNext;
    streamCacheBustNext = false;
    try {
      streamSource = new EventSource(customerStreamUrl(bustCache));
      streamSource.onopen = function () {
        customerStreamReconnectDelay = STREAM_RECONNECT_BASE_MS;
        scheduleLivePolling();
      };
      streamSource.addEventListener('chat', function (event) {
        try {
          handleCustomerStreamPayload(JSON.parse(event.data));
        } catch (e) {}
      });
      streamSource.addEventListener('ping', function (event) {
        try {
          var ping = JSON.parse(event.data);
          if (ping && typeof ping.since === 'number') {
            streamEventSince = Math.max(streamEventSince, ping.since);
          }
        } catch (e) {}
      });
      streamSource.onerror = function () {
        if (streamSource) {
          streamSource.close();
          streamSource = null;
        }
        scheduleCustomerStreamRestart();
      };
    } catch (e) {
      scheduleCustomerStreamRestart(STREAM_RECONNECT_BASE_MS);
    }
  }

  function localizedReadiness(key, fallback) {
    var localized = localizedI18n(key);
    if (localized) return localized;
    return fallback || '';
  }

  function isReadinessDebugEnabled() {
    return !!(config && config.readinessDebug);
  }

  function logReadiness(step, status, detail) {
    if (!isReadinessDebugEnabled()) return;
    var prefix = '[PAX Chat Readiness]';
    if (detail !== undefined && detail !== null) {
      console.log(prefix, step + ':', status, detail);
    } else {
      console.log(prefix, step + ':', status);
    }
  }

  function readinessFailure(step, code, messageKey, detail) {
    var err = { step: step, code: code, messageKey: messageKey };
    if (detail) err.detail = detail;
    logReadiness(step, 'FAILED', err);
    return err;
  }

  function readinessReject(step, code, messageKey, detail) {
    return Promise.reject(readinessFailure(step, code, messageKey, detail));
  }

  function classifyChatAjaxFailure(step, json, res, fallbackCode) {
    var err = { step: step, code: fallbackCode || 'backend', messageKey: 'readinessNetworkFailed' };
    if (json && json.data && json.data.code === 'login_required') {
      err.code = 'auth';
      err.messageKey = 'readinessAuthFailed';
    }
    if (res && res.status === 403 && json && json.data && json.data.message === 'Invalid nonce') {
      err.code = 'network';
      err.messageKey = 'readinessNetworkFailed';
      err.detail = 'invalid_chat_nonce';
    }
    if (json && json.data && json.data.message) {
      err.detail = String(json.data.message);
    } else if (json && json.message) {
      err.detail = String(json.message);
    }
    if (res && typeof res.status === 'number') {
      err.httpStatus = res.status;
    }
    logReadiness(step, 'FAILED', err);
    return err;
  }

  function logReadinessStart(options) {
    logReadiness('start', 'BEGIN', {
      reuseSession: !!(options && options.reuseSession),
      sessionId: getSessionId() || '',
      handler: chatHandler,
      loggedIn: isLoggedIn(),
      persistent: isPersistentAccountChat(),
    });
  }

  function initChatReadinessUi() {
    chatPanelEl = root.querySelector('#paxdesignChatPanel');
    readinessEl = root.querySelector('#paxdesignChatReadiness');
    readinessLoadingEl = root.querySelector('#paxdesignChatReadinessLoading');
    readinessErrorEl = root.querySelector('#paxdesignChatReadinessError');
    readinessTextEl = root.querySelector('#paxdesignChatReadinessText');
    readinessErrorTextEl = root.querySelector('#paxdesignChatReadinessErrorText');
    readinessRetryBtn = root.querySelector('#paxdesignChatReadinessRetry');
    readinessCloseBtn = root.querySelector('#paxdesignChatReadinessClose');
    if (readinessRetryBtn) {
      readinessRetryBtn.textContent = localizedReadiness('readinessRetry', 'Retry');
    }
    if (readinessCloseBtn) {
      readinessCloseBtn.textContent = localizedReadiness('readinessClose', 'Close');
    }
    if (readinessUiBound || !readinessEl) return;
    readinessUiBound = true;
    if (readinessRetryBtn) {
      readinessRetryBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (readinessAutoRetryTimer) {
          clearTimeout(readinessAutoRetryTimer);
          readinessAutoRetryTimer = null;
        }
        beginChatReadiness({ reuseSession: true, attempt: 0, blockUi: true });
      });
    }
    if (readinessCloseBtn) {
      readinessCloseBtn.addEventListener('click', function (e) {
        e.preventDefault();
        cancelChatReadiness();
        hideReadinessOverlay();
        if (window.PAXdesignBooking && typeof window.PAXdesignBooking.close === 'function') {
          window.PAXdesignBooking.close();
        }
      });
    }
  }

  function setReadinessPhase(key, fallback) {
    if (readinessTextEl) {
      readinessTextEl.textContent = localizedReadiness(key, fallback);
    }
  }

  function showReadinessOverlay() {
    initChatReadinessUi();
    if (!readinessEl || !chatPanelEl) return;
    if (readinessAutoRetryTimer) {
      clearTimeout(readinessAutoRetryTimer);
      readinessAutoRetryTimer = null;
    }
    readinessEl.hidden = false;
    readinessEl.setAttribute('aria-busy', 'true');
    if (readinessLoadingEl) readinessLoadingEl.hidden = false;
    if (readinessErrorEl) readinessErrorEl.hidden = true;
    chatPanelEl.classList.add('paxdesign-chat-readiness-active');
    readinessState = 'loading';
    setReadinessPhase('readinessConnecting', 'Connecting to chat…');
  }

  function showReadinessError(message) {
    initChatReadinessUi();
    if (!readinessEl) return;
    readinessState = 'error';
    readinessEl.setAttribute('aria-busy', 'false');
    if (readinessLoadingEl) readinessLoadingEl.hidden = true;
    if (readinessErrorEl) readinessErrorEl.hidden = false;
    if (readinessErrorTextEl) {
      readinessErrorTextEl.textContent = message || localizedReadiness('readinessGenericFailed', 'Chat could not be loaded.');
    }
  }

  function hideReadinessOverlay() {
    initChatReadinessUi();
    if (!readinessEl || !chatPanelEl) return;
    readinessEl.hidden = true;
    readinessEl.setAttribute('aria-busy', 'false');
    readinessEl.classList.remove('paxdesign-auth-transition');
    chatPanelEl.classList.remove('paxdesign-chat-readiness-active');
    readinessState = 'ready';
  }

  function showAuthTransitionOverlay() {
    initChatReadinessUi();
    if (!readinessEl) return;
    if (readinessAutoRetryTimer) {
      clearTimeout(readinessAutoRetryTimer);
      readinessAutoRetryTimer = null;
    }
    readinessEl.classList.add('paxdesign-auth-transition');
    readinessEl.hidden = false;
    readinessEl.setAttribute('aria-busy', 'true');
    if (readinessLoadingEl) readinessLoadingEl.hidden = false;
    if (readinessErrorEl) readinessErrorEl.hidden = true;
    readinessState = 'loading';
    setReadinessPhase('readinessAuthenticating', 'Preparing your conversation…');
  }

  function cancelChatReadiness() {
    readinessGeneration += 1;
    readinessPromise = null;
    if (readinessAutoRetryTimer) {
      clearTimeout(readinessAutoRetryTimer);
      readinessAutoRetryTimer = null;
    }
  }

  function readinessErrorMessage(err) {
    if (!err) {
      return localizedReadiness('readinessGenericFailed', 'Chat could not be loaded.');
    }
    if (isReadinessDebugEnabled() && err.step) {
      console.warn('[PAX Chat Readiness] User-facing error for step "' + err.step + '" (code: ' + (err.code || 'unknown') + ')', err.detail || err);
    }
    if (err.message && typeof err.message === 'string' && err.message.indexOf('readiness') !== 0) {
      return err.message;
    }
    if (err.messageKey) {
      return localizedReadiness(err.messageKey, localizedReadiness('readinessGenericFailed', 'Chat could not be loaded.'));
    }
    switch (err.code) {
      case 'auth':
        return localizedReadiness('readinessAuthFailed', 'Please sign in to use chat.');
      case 'session':
        return localizedReadiness('readinessSessionFailed', 'Could not start your chat session.');
      case 'network':
      case 'backend':
        return localizedReadiness('readinessNetworkFailed', 'Could not reach the server.');
      case 'sse':
        return localizedReadiness('readinessStreamFailed', 'Real-time connection could not be established.');
      case 'ai':
        return localizedReadiness('readinessAiFailed', 'The AI assistant is currently unavailable.');
      case 'live':
        return localizedReadiness('readinessLiveFailed', 'Could not confirm your live agent request.');
      default:
        return localizedReadiness('readinessGenericFailed', 'Chat could not be loaded.');
    }
  }

  function shouldAutoRetryReadiness(err) {
    if (!err || err.silent || err.code === 'auth') return false;
    return true;
  }

  function scheduleReadinessAutoRetry(attempt) {
    if (readinessAutoRetryTimer) clearTimeout(readinessAutoRetryTimer);
    readinessAutoRetryTimer = window.setTimeout(function () {
      readinessAutoRetryTimer = null;
      if (readinessState !== 'error' || !widgetOpen) return;
      beginChatReadiness({ reuseSession: true, attempt: attempt, blockUi: true });
    }, READINESS_AUTO_RETRY_DELAY_MS);
  }

  function abortIfReadinessStale(generation) {
    if (generation !== readinessGeneration) {
      var aborted = { code: 'aborted', silent: true };
      throw aborted;
    }
  }

  function withReadinessTimeout(promise, generation) {
    return new Promise(function (resolve, reject) {
      var timer = window.setTimeout(function () {
        reject(readinessFailure(READINESS_STEPS.timeout, 'network', 'readinessNetworkFailed', {
          reason: 'overall_timeout',
          timeoutMs: READINESS_TIMEOUT_MS,
        }));
      }, READINESS_TIMEOUT_MS);
      promise.then(function (value) {
        window.clearTimeout(timer);
        resolve(value);
      }).catch(function (err) {
        window.clearTimeout(timer);
        if (generation !== readinessGeneration) {
          reject({ code: 'aborted', silent: true });
          return;
        }
        reject(err);
      });
    });
  }

  function waitForCustomerStreamOpen(timeoutMs, generation) {
    return new Promise(function (resolve, reject) {
      var startedAt = Date.now();
      startCustomerStream();
      var tick = function () {
        if (generation !== readinessGeneration) {
          reject({ code: 'aborted', silent: true });
          return;
        }
        if (customerStreamConnected()) {
          logReadiness(READINESS_STEPS.sse, 'OK', { readyState: streamSource.readyState });
          resolve(true);
          return;
        }
        if (Date.now() - startedAt >= timeoutMs) {
          reject(readinessFailure(READINESS_STEPS.sse, 'sse', 'readinessStreamFailed', {
            reason: 'stream_open_timeout',
            timeoutMs: timeoutMs,
            readyState: streamSource ? streamSource.readyState : null,
          }));
          return;
        }
        window.setTimeout(tick, 100);
      };
      tick();
    });
  }

  function verifyLiveAgentStateFromServer(expectedHandlers) {
    expectedHandlers = expectedHandlers || ['live_request', 'admin'];
    if (expectedHandlers.indexOf(chatHandler) === -1) {
      return readinessReject(READINESS_STEPS.liveAgent, 'live', 'readinessLiveFailed', {
        reason: 'unexpected_handler',
        handler: chatHandler,
      });
    }
    if (chatHandler === 'admin' && !adminName && !(assignedAgent && assignedAgent.name)) {
      return readinessReject(READINESS_STEPS.liveAgent, 'live', 'readinessLiveFailed', {
        reason: 'missing_admin_identity',
        handler: chatHandler,
      });
    }
    logReadiness(READINESS_STEPS.liveAgent, 'OK', { handler: chatHandler });
    return Promise.resolve(true);
  }

  function releaseChatShellLoader() {
    if (window.PAXdesignBooking && typeof window.PAXdesignBooking.hideShellLoader === 'function') {
      window.PAXdesignBooking.hideShellLoader();
    }
  }

  function finalizeChatReadinessUi() {
    hideReadinessOverlay();
    releaseChatShellLoader();
    updateEntryUi();
    updateInputState();
    updateEndButtonUi();
    scheduleLivePolling();
    if (widgetOpen || isPersistentAccountChat()) {
      startCustomerStream();
    }
    notifyLayout();
    stickToBottom = true;
    scrollToBottom(true);
  }

  function queueBackgroundRealtimeSync(generation) {
    window.setTimeout(function () {
      if (generation !== readinessGeneration) {
        return;
      }
      waitForCustomerStreamOpen(READINESS_STREAM_BACKGROUND_MS, generation)
        .catch(function (err) {
          if (err && err.silent) {
            return null;
          }
          logReadiness(READINESS_STEPS.sse, 'DEFERRED', {
            reason: err && err.code ? err.code : 'stream_background_failed',
          });
          return null;
        })
        .then(function () {
          if (generation !== readinessGeneration) {
            return null;
          }
          logReadiness(READINESS_STEPS.sse, 'OK', { background: true });
          return pollUpdatesOnce(false);
        })
        .then(function () {
          if (generation !== readinessGeneration) {
            return;
          }
          logReadiness(READINESS_STEPS.finalSync, 'OK', { background: true });
        });
    }, 0);
  }

  function pollIsFresh(ms) {
    return lastReadinessPollAt > 0 && (Date.now() - lastReadinessPollAt) < (ms || 800);
  }

  function concealThreadUntilPinned() {
    if (messagesEl) messagesEl.classList.add('paxdesign-chat-unpinned');
  }

  function pinToLatestMessage() {
    if (!messagesEl) return;
    stickToBottom = true;
    if (scrollRaf) {
      cancelAnimationFrame(scrollRaf);
      scrollRaf = 0;
    }
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function revealPinnedThread() {
    pinToLatestMessage();
    if (messagesEl) messagesEl.classList.remove('paxdesign-chat-unpinned');
  }

  function whenBootstrapped() {
    return bootPromise || Promise.resolve();
  }

  function paintCachedThreadIfNeeded() {
    if (threadEl && threadEl.children.length > 0) return;
    if (messages.length > 0) {
      stickToBottom = true;
      scrollToBottom(true);
      return;
    }
  }

  function isNearBottom(threshold) {
    if (!messagesEl) return true;
    return (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) <= (threshold || 96);
  }

  function updateStickToBottomFromScroll() {
    if (loadingOlderHistory) return;
    stickToBottom = isNearBottom(96);
  }

  function shouldPreserveHistoryDom() {
    if (loadingOlderHistory) return true;
    if (!stickToBottom) return true;
    if (threadEl && threadEl.children.length > 0) return true;
    if (messages.length > 0) return true;
    return false;
  }

  function shouldSkipHistoryFetch(options) {
    options = options || {};
    if (options.forceHistory || options.forceOpen) {
      return false;
    }
    if (options.skipHistory) {
      return true;
    }
    if (sessionFetchInFlight) {
      return true;
    }
    if (threadEl && threadEl.children.length > 0 && pollIsFresh(HISTORY_REUSE_MS) && options.background) {
      return true;
    }
    if (!options.reuseSession && !options.background) {
      return false;
    }
    if (!historyLoadedAt || oldestLoadedSeq <= 0) {
      return false;
    }
    return (Date.now() - historyLoadedAt) < HISTORY_REUSE_MS;
  }

  function runChatReadinessChecks(options) {
    options = options || {};
    var generation = readinessGeneration;
    var skipSessionBootstrap = !!options.reuseSession && !!getSessionId() && sessionRestored;
    logReadinessStart(options);

    return withReadinessTimeout(Promise.resolve()
      .then(function () {
        abortIfReadinessStale(generation);
        setReadinessPhase('readinessAuthenticating', 'Verifying your sign-in…');
        if (!canUseChat()) {
          return readinessReject(READINESS_STEPS.authentication, 'auth', 'readinessAuthFailed', {
            reason: 'login_or_verification_required',
            loggedIn: isLoggedIn(),
            verified: isVerifiedAccount(),
          });
        }
        if (!config || config.enabled === false) {
          return readinessReject(READINESS_STEPS.authentication, 'ai', 'readinessAiFailed', {
            reason: 'chat_disabled',
          });
        }
        logReadiness(READINESS_STEPS.authentication, 'OK');
      })
      .then(function () {
        abortIfReadinessStale(generation);
        setReadinessPhase('readinessSession', 'Preparing your chat session…');
        if (skipSessionBootstrap) {
          logReadiness(READINESS_STEPS.session, 'OK', { reused: true, sessionId: getSessionId() });
          return getSessionId();
        }
        if (isPersistentAccountChat()) {
          return refreshAuthenticatedChatSession({ skipHistory: true }).then(function () {
            abortIfReadinessStale(generation);
            return reopenAuthenticatedSessionIfNeeded();
          }).then(function () {
            abortIfReadinessStale(generation);
            return getSessionId();
          });
        }
        return restoreCustomerSession().then(function () {
          abortIfReadinessStale(generation);
          return getSessionId();
        });
      })
      .then(function (sessionId) {
        abortIfReadinessStale(generation);
        if (!sessionId) {
          return readinessReject(READINESS_STEPS.session, 'session', 'readinessSessionFailed', {
            reason: 'missing_session_id',
          });
        }
        logReadiness(READINESS_STEPS.session, 'OK', { sessionId: sessionId });
        setReadinessPhase('readinessHistory', 'Loading conversation history…');
        if (shouldSkipHistoryFetch(options)) {
          logReadiness(READINESS_STEPS.history, 'OK', { reused: true, skipped: true });
          return true;
        }
        return fetchSessionFromServer(true, !options.background);
      })
      .then(function () {
        abortIfReadinessStale(generation);
        logReadiness(READINESS_STEPS.history, 'OK');
        if (chatHandler === 'closed' && isPersistentAccountChat()) {
          return reopenAuthenticatedSessionIfNeeded().then(function () {
            abortIfReadinessStale(generation);
            return fetchSessionFromServer(true, !options.background);
          });
        }
        return true;
      })
      .then(function () {
        abortIfReadinessStale(generation);
        if (chatHandler === 'live_request' || chatHandler === 'admin') {
          return verifyLiveAgentStateFromServer(['live_request', 'admin']).catch(function (err) {
            if (options.background) {
              logReadiness(READINESS_STEPS.liveAgent, 'DEFERRED', err);
              return true;
            }
            return Promise.reject(err);
          });
        }
        logReadiness(READINESS_STEPS.liveAgent, 'OK', { skipped: true, handler: chatHandler });
        return true;
      })
      .then(function () {
        abortIfReadinessStale(generation);
        if (options.background) {
          logReadiness(READINESS_STEPS.sync, 'OK', { skipped: true, background: true });
          queueBackgroundRealtimeSync(generation);
          return true;
        }
        if (pollIsFresh(1200)) {
          logReadiness(READINESS_STEPS.sync, 'OK', { skipped: true, reused: true });
          queueBackgroundRealtimeSync(generation);
          return true;
        }
        setReadinessPhase('readinessSyncing', 'Synchronizing chat status…');
        return pollUpdatesOnce(!!options.blockUi);
      })
      .then(function () {
        abortIfReadinessStale(generation);
        logReadiness(READINESS_STEPS.sync, 'OK');
        logReadiness('complete', 'SUCCESS');
        queueBackgroundRealtimeSync(generation);
      }), generation);
  }

  function beginChatReadiness(options) {
    options = options || {};
    if (!canUseChat()) return Promise.resolve(false);
    initChatReadinessUi();
    if (readinessState === 'loading' && readinessPromise && !options.force) {
      return readinessPromise;
    }
    cancelChatReadiness();
    var generation = readinessGeneration;
    var attempt = typeof options.attempt === 'number' ? options.attempt : 0;
    if (options.blockUi) {
      showReadinessOverlay();
    } else {
      hideReadinessOverlay();
    }
    readinessPromise = runChatReadinessChecks(options)
      .then(function () {
        if (generation !== readinessGeneration) return false;
        finalizeChatReadinessUi();
        readinessPromise = null;
        return true;
      })
      .catch(function (err) {
        if (err && err.silent) return false;
        if (generation !== readinessGeneration) return false;
        if (options.background && err && err.code !== 'auth') {
          hideReadinessOverlay();
          readinessPromise = null;
          scheduleLivePolling();
          return false;
        }
        if (err && err.code === 'auth') {
          showAuthGate();
          hideReadinessOverlay();
          readinessPromise = null;
          return false;
        }
        showReadinessError(readinessErrorMessage(err));
        readinessPromise = null;
        if (attempt < READINESS_AUTO_RETRY_MAX && shouldAutoRetryReadiness(err)) {
          scheduleReadinessAutoRetry(attempt + 1);
        }
        return false;
      });
    return readinessPromise;
  }

  function isLoginGateEnabled() {
    return !!(config && config.requireLogin);
  }

  function getAuthUserId() {
    if (window.PDXAuth && typeof window.PDXAuth.getUser === 'function') {
      var u = window.PDXAuth.getUser();
      if (u && u.id) return parseInt(u.id, 10) || 0;
    }
    var auth = config && config.auth ? config.auth : {};
    return parseInt(auth.id, 10) || 0;
  }

  function getSessionStorageKey() {
    var uid = getAuthUserId();
    return uid > 0 ? SESSION_KEY + '-' + uid : SESSION_KEY;
  }

  function clearAllChatStorage() {
    clearUserSessionCache([0, getAuthUserId()], { allKnownSessions: true });
  }

  function storageKeysForUser(userId) {
    var uid = parseInt(userId, 10) || 0;
    var keys = [];
    if (uid > 0) {
      keys.push(getSessionStorageKeyForUser(uid));
    } else {
      keys.push(SESSION_KEY);
    }
    return keys;
  }

  function getSessionStorageKeyForUser(userId) {
    var uid = parseInt(userId, 10) || 0;
    return uid > 0 ? SESSION_KEY + '-' + uid : SESSION_KEY;
  }

  function clearUserSessionCache(userIds, options) {
    options = options || {};
    var ids = Array.isArray(userIds) ? userIds : [userIds];
    var sessionIds = options.sessionIds || [];
    if (options.sessionId) sessionIds.push(options.sessionId);
    var seenUsers = {};
    var seenSessions = {};
    try {
      ids.forEach(function (userId) {
        var uid = parseInt(userId, 10) || 0;
        if (seenUsers[uid]) return;
        seenUsers[uid] = true;
        storageKeysForUser(uid).forEach(function (key) {
          localStorage.removeItem(key);
        });
        if (uid > 0) {
          localStorage.removeItem(SESSION_META_KEY + '-' + uid);
        } else {
          localStorage.removeItem(SESSION_META_KEY);
        }
      });
      sessionIds.forEach(function (sessionId) {
        sessionId = String(sessionId || '').trim();
        if (!sessionId || seenSessions[sessionId]) return;
        seenSessions[sessionId] = true;
        localStorage.removeItem(SNAPSHOT_PREFIX + sessionId);
        localStorage.removeItem(ENTRY_CHOICE_KEY + '-' + sessionId);
        localStorage.removeItem(CONSULTATION_KEY + '-' + sessionId);
        localStorage.removeItem(CUSTOMER_NAME_KEY + '-' + sessionId);
        sessionStorage.removeItem(OPEN_CLOSE_SOUND_KEY + '-' + sessionId);
      });
      if (options.allKnownSessions) {
        var removeKeys = [];
        var i;
        for (i = 0; i < localStorage.length; i++) {
          removeKeys.push(localStorage.key(i));
        }
        removeKeys.forEach(function (key) {
          if (!key) return;
          if (key.indexOf(SNAPSHOT_PREFIX) === 0
            || key.indexOf(ENTRY_CHOICE_KEY + '-') === 0
            || key.indexOf(CONSULTATION_KEY + '-') === 0
            || key.indexOf(CUSTOMER_NAME_KEY + '-') === 0
            || key.indexOf(SESSION_KEY) === 0
            || key.indexOf(SESSION_META_KEY) === 0) {
            localStorage.removeItem(key);
          }
        });
      }
      sessionStorage.removeItem(SESSION_KEY);
      sessionStorage.removeItem(LIVE_AGENT_KEY);
      var sessionKeys = [];
      for (i = 0; i < sessionStorage.length; i++) {
        sessionKeys.push(sessionStorage.key(i));
      }
      sessionKeys.forEach(function (key) {
        if (!key) return;
        if (key.indexOf(OPEN_CLOSE_SOUND_KEY + '-') === 0) {
          sessionStorage.removeItem(key);
        }
      });
    } catch (e) {}
    cachedSessionId = null;
  }

  function refreshChatAjaxNonce() {
    if (!config || !config.ajaxUrl) return Promise.resolve(false);
    return fetch(config.ajaxUrl + '?action=paxdesign_chat_nonce&_=' + Date.now(), {
      credentials: 'same-origin',
    }).then(function (res) {
      return safeJson(res);
    }).then(function (json) {
      if (json && json.success && json.data && json.data.nonce) {
        config.nonce = json.data.nonce;
        return true;
      }
      return false;
    }).catch(function () {
      return false;
    });
  }

  function refreshChatAuthNonce() {
    if (window.PDXAuth && typeof window.PDXAuth.getUser === 'function' && config) {
      config.auth = window.PDXAuth.getUser();
    }
    var refreshRest = Promise.resolve(true);
    if (window.PDXAuth && typeof window.PDXAuth.refreshSessionNonce === 'function') {
      refreshRest = window.PDXAuth.refreshSessionNonce().catch(function () { return false; });
    }
    return Promise.all([refreshChatAjaxNonce(), refreshRest]).then(function () {
      return true;
    });
  }

  function inferAuthSessionChangeReason(eventDetail, previousUserId, nextUserId) {
    if (eventDetail && eventDetail.reason) return String(eventDetail.reason);
    var wasLoggedIn = previousUserId !== null && previousUserId > 0;
    if (wasLoggedIn && nextUserId <= 0) return 'logout';
    if (!wasLoggedIn && nextUserId > 0) return 'login';
    if (wasLoggedIn && nextUserId > 0 && previousUserId !== nextUserId) return 'user_switch';
    if (wasLoggedIn && nextUserId > 0) return 'session_update';
    return 'session_update';
  }

  function stopChatRealtimeTransport() {
    stopCustomerStream();
    abortStream();
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function resetChatConfigSessionMeta() {
    if (!config) return;
    config.chatSessionId = '';
    config.chatSessionHasMessages = false;
    config.chatMessageCount = 0;
  }

  function silentRefreshChatSession(reason, options) {
    options = options || {};
    if (silentRefreshPromise && !options.force) return silentRefreshPromise;

    var generation = ++silentRefreshGeneration;
    var previousUserId = typeof options.previousUserId === 'number' ? options.previousUserId : lastAuthUserId;
    var nextUserId = getAuthUserId();
    var oldSessionId = cachedSessionId || getSessionId() || (config && config.chatSessionId) || '';
    logReadiness('silent_refresh', 'BEGIN', {
      reason: reason || 'session_update',
      previousUserId: previousUserId,
      nextUserId: nextUserId,
      oldSessionId: oldSessionId,
    });

    silentRefreshPromise = refreshChatAuthNonce().then(function () {
      if (generation !== silentRefreshGeneration) return false;
      if (oldSessionId) sendCustomerDisconnectBeacon();
      stopChatRealtimeTransport();
      clearUserSessionCache([previousUserId, nextUserId], {
        sessionIds: [oldSessionId],
        allKnownSessions: reason === 'logout',
      });
      resetChatConfigSessionMeta();
      cachedSessionId = null;
      streamEventSince = 0;
      streamCacheBustNext = true;
      sessionRestored = false;
      if (!options.preserveUi) {
        resetSessionState();
      }
      lastAuthUserId = nextUserId;
      updateAuthGateUi();

      if (!canUseChat()) {
        if (reason === 'logout' || nextUserId <= 0) showAuthGate();
        return false;
      }

      hideAuthGate();
      purgeGuestChatStorage();
      root.classList.toggle('paxdesign-chat-direct-mode', isPersistentAccountChat());
      if (isPersistentAccountChat()) {
        if (entryEl) entryEl.hidden = true;
        if (quickActions) quickActions.hidden = true;
      }

      var boot;
      if (isPersistentAccountChat()) {
        boot = refreshAuthenticatedChatSession({ skipHistory: true }).then(function () {
          return reopenAuthenticatedSessionIfNeeded();
        });
      } else {
        boot = restoreCustomerSession();
      }

      return boot.then(function () {
        if (generation !== silentRefreshGeneration) return false;
        return fetchSessionFromServer(true, false).then(function () {
          if (generation !== silentRefreshGeneration) return false;
          return pollUpdatesOnce(false).then(function () {
            if (generation !== silentRefreshGeneration) return false;
            inferEntryChoiceFromHandler();
            updateEntryUi();
            updateInputState();
            updateEndButtonUi();
            scheduleLivePolling();
            if (widgetOpen || isPersistentAccountChat()) startCustomerStream();
            notifyLayout();
            logReadiness('silent_refresh', 'OK', {
              reason: reason || 'session_update',
              sessionId: getSessionId(),
              handler: chatHandler,
            });
            return true;
          });
        });
      });
    }).catch(function (err) {
      logReadiness('silent_refresh', 'FAILED', {
        reason: reason || 'session_update',
        error: err && err.message ? err.message : err,
      });
      return false;
    }).finally(function () {
      silentRefreshPromise = null;
    });

    return silentRefreshPromise;
  }

  function invalidateAndRefreshChatSession(reason, options) {
    options = options || {};
    options.force = true;
    return silentRefreshChatSession(reason, options);
  }

  function transitionAfterLogin(options) {
    options = options || {};
    if (silentRefreshPromise && !options.force) return silentRefreshPromise;

    var generation = ++silentRefreshGeneration;
    var previousUserId = typeof options.previousUserId === 'number' ? options.previousUserId : lastAuthUserId;
    var oldSessionId = cachedSessionId || getSessionId() || (config && config.chatSessionId) || '';

    hideAuthGate();
    clearInlineAuthForm();
    root.classList.add('paxdesign-chat-direct-mode');
    if (entryEl) entryEl.hidden = true;
    if (quickActions) quickActions.hidden = true;
    showAuthTransitionOverlay();
    notifyLayout();

    silentRefreshPromise = refreshChatAuthNonce().then(function () {
      if (generation !== silentRefreshGeneration) return false;
      if (oldSessionId) sendCustomerDisconnectBeacon();
      stopChatRealtimeTransport();
      clearUserSessionCache([previousUserId, getAuthUserId()], {
        sessionIds: [oldSessionId],
        allKnownSessions: false,
      });
      resetChatConfigSessionMeta();
      cachedSessionId = null;
      streamEventSince = 0;
      streamCacheBustNext = true;
      lastAuthUserId = getAuthUserId();
      purgeGuestChatStorage();

      return refreshAuthenticatedChatSession({ skipHistory: true }).then(function () {
        return reopenAuthenticatedSessionIfNeeded();
      }).then(function () {
        if (generation !== silentRefreshGeneration) return false;
        return fetchSessionFromServer(true, false);
      }).then(function () {
        if (generation !== silentRefreshGeneration) return false;
        return pollUpdatesOnce(false);
      }).then(function () {
        if (generation !== silentRefreshGeneration) return false;
        hideReadinessOverlay();
        inferEntryChoiceFromHandler();
        updateEntryUi();
        updateInputState();
        updateEndButtonUi();
        scheduleLivePolling();
        if (widgetOpen || isPersistentAccountChat()) startCustomerStream();
        stickToBottom = true;
        scrollToBottom(true);
        notifyLayout();
        if (widgetOpen && input && !input.disabled) {
          try { input.focus({ preventScroll: true }); } catch (focusErr) {}
        }
        logReadiness('login_transition', 'OK', { sessionId: getSessionId() });
        return true;
      });
    }).catch(function (err) {
      hideReadinessOverlay();
      logReadiness('login_transition', 'FAILED', {
        error: err && err.message ? err.message : err,
      });
      return false;
    }).finally(function () {
      silentRefreshPromise = null;
    });

    return silentRefreshPromise;
  }

  function resetChatForAuthChange(clearUi) {
    stopCustomerStream();
    abortStream();
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    cachedSessionId = null;
    if (config) {
      config.chatSessionId = '';
      config.chatSessionHasMessages = false;
      config.chatMessageCount = 0;
    }
    if (clearUi) resetSessionState();
  }

  function sendCustomerDisconnectBeacon() {
    var sid = cachedSessionId;
    if (!sid && isPersistentAccountChat()) {
      try {
        sid = localStorage.getItem(getSessionStorageKey()) || sessionStorage.getItem(SESSION_KEY) || '';
      } catch (e) {
        sid = '';
      }
    }
    if (!sid || !config || !config.ajaxUrl || !config.nonce) return;
    try {
      var body = new URLSearchParams();
      body.append('action', 'paxdesign_chat_disconnect');
      body.append('nonce', config.nonce);
      body.append('session_id', sid);
      if (navigator.sendBeacon) {
        navigator.sendBeacon(config.ajaxUrl, body);
        return;
      }
      fetch(config.ajaxUrl, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        keepalive: true,
      }).catch(function () {});
    } catch (e) {}
  }

  function handleAuthSessionChange(event) {
    var detail = event && event.detail ? event.detail : {};
    var previousUserId = lastAuthUserId;
    var nextUserId = getAuthUserId();
    var reason = inferAuthSessionChangeReason(detail, previousUserId, nextUserId);
    var wasLoggedIn = previousUserId !== null && previousUserId > 0;
    var loggedOut = wasLoggedIn && nextUserId <= 0;

    if (loggedOut || reason === 'logout') {
      cancelChatReadiness();
      invalidateAndRefreshChatSession('logout', {
        previousUserId: previousUserId,
        force: true,
      });
      return;
    }

    if (!canUseChat() && wasLoggedIn) {
      cancelChatReadiness();
      invalidateAndRefreshChatSession('logout', {
        previousUserId: previousUserId,
        force: true,
      });
      return;
    }

    if (reason === 'session_update') {
      lastAuthUserId = nextUserId;
      updateAuthGateUi();
      if (canUseChat()) hideAuthGate();
      return;
    }

    if ((reason === 'login' || reason === 'verification') && widgetOpen && canUseChat()) {
      cancelChatReadiness();
      transitionAfterLogin({
        previousUserId: previousUserId,
        force: true,
      });
      return;
    }

    var shouldRefresh = reason === 'login'
      || reason === 'logout'
      || reason === 'user_switch'
      || reason === 'new_conversation'
      || reason === 'verification'
      || (wasLoggedIn !== (nextUserId > 0))
      || (wasLoggedIn && nextUserId > 0 && previousUserId !== nextUserId);

    if (shouldRefresh) {
      cancelChatReadiness();
      invalidateAndRefreshChatSession(reason, {
        previousUserId: previousUserId,
        force: true,
      });
      return;
    }

    lastAuthUserId = nextUserId;
    updateAuthGateUi();
  }

  function isLoggedIn() {
    if (window.PDXAuth && typeof window.PDXAuth.isLoggedIn === 'function') {
      return window.PDXAuth.isLoggedIn();
    }
    return !!(config && config.auth && config.auth.logged_in);
  }

  function isVerifiedAccount() {
    if (window.PDXAuth && typeof window.PDXAuth.isVerified === 'function') {
      return window.PDXAuth.isVerified();
    }
    var auth = config && config.auth ? config.auth : {};
    return !!auth.verified || !!auth.is_admin;
  }

  function canUseChat() {
    if (!isLoginGateEnabled()) return true;
    return isLoggedIn() && isVerifiedAccount();
  }

  function initAuthGate() {
    authGateEl = root.querySelector('#paxdesignChatAuthGate');
    authGateVerifyEl = root.querySelector('#paxdesignChatAuthGateVerify');
    if (config && config.authGate) {
      var titleEl = root.querySelector('#paxdesignChatAuthGateTitle');
      var subEl = root.querySelector('#paxdesignChatAuthGateSubtitle');
      if (titleEl && config.authGate.title) titleEl.textContent = config.authGate.title;
      if (subEl && config.authGate.subtitle) subEl.textContent = config.authGate.subtitle;
      if (authGateVerifyEl && config.authGate.verifyHint) authGateVerifyEl.textContent = config.authGate.verifyHint;
    }
    if (authGateBound) return;
    authGateBound = true;
    var authActionsEl = root.querySelector('#paxdesignChatAuthActions');
    if (authActionsEl) {
      authActionsEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-auth-view]');
        if (!btn) return;
        e.preventDefault();
        mountInlineAuthForm(btn.getAttribute('data-auth-view'));
      });
    }
    if (!window.__paxChatAuthSessionBound) {
      window.__paxChatAuthSessionBound = true;
      window.addEventListener('pdx-session-updated', function (event) {
        if (config && window.PDXAuth && typeof window.PDXAuth.getUser === 'function') {
          config.auth = window.PDXAuth.getUser();
        }
        refreshChatAjaxNonce();
        handleAuthSessionChange(event);
      });
      window.addEventListener('pdx-chat-session-invalidate', function (event) {
        var detail = event && event.detail ? event.detail : {};
        cancelChatReadiness();
        invalidateAndRefreshChatSession(detail.reason || 'new_conversation', {
          previousUserId: lastAuthUserId,
          force: true,
        });
      });
    }
    updateAuthGateUi();
  }

  function openAuthOverlay(view) {
    var inlineEl = root.querySelector('#paxdesignChatAuthInline');
    if (inlineEl && window.PDXAuth && typeof window.PDXAuth.mountInlineAuth === 'function') {
      showAuthGate();
      window.PDXAuth.mountInlineAuth(inlineEl, view === 'register' ? 'register' : 'login', {
        compact: true,
        context: 'chat',
      });
      return;
    }
    if (window.PDXAuth && typeof window.PDXAuth.openLogin === 'function') {
      window.PDXAuth.openLogin(view === 'register' ? 'register' : 'login');
      return;
    }
    showLoginRequiredNotice();
  }

  function mountInlineAuthForm(view) {
    var inlineEl = root.querySelector('#paxdesignChatAuthInline');
    if (!inlineEl || !window.PDXAuth || typeof window.PDXAuth.mountInlineAuth !== 'function') return;
    if (inlineEl.dataset.mounted === '1' && inlineEl.dataset.authView === (view || 'login')) return;
    clearInlineAuthForm();
    window.PDXAuth.mountInlineAuth(inlineEl, view || 'login', {
      compact: true,
      context: 'chat',
    });
    inlineEl.dataset.mounted = '1';
    inlineEl.dataset.authView = view || 'login';
    updateChatAuthActionState(view || 'login');
  }

  function updateChatAuthActionState(activeView) {
    var actionsEl = root.querySelector('#paxdesignChatAuthActions');
    if (!actionsEl) return;
    actionsEl.querySelectorAll('[data-auth-view]').forEach(function (btn) {
      var isActive = btn.getAttribute('data-auth-view') === activeView;
      btn.classList.toggle('paxdesign-is-active', isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function clearInlineAuthForm() {
    var inlineEl = root.querySelector('#paxdesignChatAuthInline');
    if (inlineEl && window.PDXAuth && typeof window.PDXAuth.unmountInlineAuth === 'function') {
      window.PDXAuth.unmountInlineAuth(inlineEl);
    }
    if (inlineEl) inlineEl.dataset.mounted = '';
    if (inlineEl) inlineEl.dataset.authView = '';
  }

  function purgeGuestChatStorage() {
    if (!isLoginGateEnabled()) return;
    try {
      var stored = localStorage.getItem(SESSION_KEY) || sessionStorage.getItem(SESSION_KEY) || '';
      if (stored && stored.indexOf('pax_u') !== 0) {
        localStorage.removeItem(SESSION_KEY);
        sessionStorage.removeItem(SESSION_KEY);
        cachedSessionId = '';
      }
    } catch (e) {}
  }

  function updateAuthGateUi() {
    if (!authGateEl) return;
    var needsVerify = isLoginGateEnabled() && isLoggedIn() && !isVerifiedAccount();
    if (authGateVerifyEl) authGateVerifyEl.hidden = !needsVerify;
    if (needsVerify) {
      showAuthGate();
      return;
    }
    if (canUseChat()) {
      hideAuthGate();
    } else if (widgetOpen) {
      showAuthGate();
    }
  }

  function showAuthGate() {
    initAuthGate();
    if (!isLoginGateEnabled()) return;
    if (isLoggedIn() && isVerifiedAccount()) {
      hideAuthGate();
      return;
    }
    if (authGateEl) authGateEl.hidden = false;
    root.classList.add('paxdesign-chat-auth-locked');
    stopCustomerStream();
    abortStream();
    if (!isLoggedIn()) {
      mountInlineAuthForm('login');
    }
    if (window.PAXdesignBookingMobile && typeof window.PAXdesignBookingMobile.adjustLayout === 'function') {
      window.PAXdesignBookingMobile.adjustLayout(false);
    }
  }

  function hideAuthGate() {
    if (authGateEl) authGateEl.hidden = true;
    root.classList.remove('paxdesign-chat-auth-locked');
    clearInlineAuthForm();
    notifyLayout();
  }

  function adoptSessionId(sessionId, opts) {
    opts = opts || {};
    sessionId = String(sessionId || '').trim();
    if (!sessionId || sessionId === cachedSessionId) return;
    var preserveUi = !!opts.preserveUi;
    cachedSessionId = sessionId;
    if (!preserveUi) {
      messages = [];
      domMsgIds = {};
      domClientMsgIds = {};
      pollSeq = 0;
      appliedMessageSeq = 0;
      localMsgId = 0;
      entryChoice = '';
      sessionRestored = false;
      sessionLoading = false;
      if (threadEl) threadEl.innerHTML = '';
      root.classList.remove('paxdesign-has-chat-messages');
    } else {
      sessionLoading = true;
    }
    try {
      localStorage.setItem(getSessionStorageKey(), sessionId);
      sessionStorage.setItem(SESSION_KEY, sessionId);
    } catch (e) {}
    if (opts.fromServer) {
      scheduleCustomerStreamRestart(customerStreamReconnectDelay);
    }
    loadEntryChoice();
    loadCustomerName();
    if (!preserveUi) updateEntryUi();
  }

  function hasExistingConversation() {
    if (messages.length > 0) return true;
    if (root.classList.contains('paxdesign-has-chat-messages')) return true;
    if (threadEl && threadEl.children.length > 0) return true;
    if (chatHandler === 'admin' || chatHandler === 'live_request') return true;
    if (entryChoice) return true;
    if (config && config.chatSessionHasMessages) return true;
    if (config && typeof config.chatMessageCount === 'number' && config.chatMessageCount > 0) return true;
    return false;
  }

  function inferEntryChoiceFromHandler() {
    if (entryChoice) return;
    if (chatHandler === 'admin' || chatHandler === 'live_request') {
      entryChoice = 'live';
      return;
    }
    if (messages.length > 0 || (threadEl && threadEl.children.length > 0)) {
      entryChoice = 'ai';
    }
  }

  function refreshAuthenticatedChatSession(options) {
    options = options || {};
    if (!isLoggedIn()) return Promise.resolve();
    if (window.PDXAuth && typeof window.PDXAuth.customerApiFetch === 'function') {
      return window.PDXAuth.customerApiFetch('GET', '/customer/chat/session').then(function (data) {
        if (data && data.session_id) {
          if (data.has_messages || (data.message_count && data.message_count > 0)) {
            sessionRestored = true;
            if (config) {
              config.chatSessionHasMessages = true;
              config.chatMessageCount = data.message_count || 1;
            }
          }
          adoptSessionId(data.session_id, { fromServer: true, preserveUi: false });
          if (!options.skipHistory) {
            return fetchSessionFromServer(true, false);
          }
          return data.session_id;
        }
        return null;
      }).catch(function () {
        if (config && config.chatSessionId) {
          adoptSessionId(config.chatSessionId, { fromServer: true, preserveUi: false });
          if (!options.skipHistory) {
            return fetchSessionFromServer(true, false);
          }
        }
        return null;
      });
    }
    if (config && config.chatSessionId) {
      adoptSessionId(config.chatSessionId, { fromServer: true, preserveUi: false });
      if (!options.skipHistory) {
        return fetchSessionFromServer(true, false);
      }
    }
    return Promise.resolve();
  }

  function isPersistentAccountChat() {
    return isLoggedIn() && isVerifiedAccount();
  }

  function reopenAuthenticatedSessionIfNeeded() {
    if (!isPersistentAccountChat()) return Promise.resolve();
    if (window.PDXAuth && typeof window.PDXAuth.customerApiFetch === 'function') {
      return window.PDXAuth.customerApiFetch('POST', '/customer/chat/session', {
        session_id: getSessionId(),
        new_conversation: false,
      }).then(function (data) {
        if (data && data.session_id) {
          adoptSessionId(data.session_id, { fromServer: true, preserveUi: false });
          if (data.handler) applyHandlerState(data.handler, adminName);
        }
      }).catch(function () {
        return refreshAuthenticatedChatSession();
      });
    }
    return Promise.resolve();
  }

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
    initAgentProfile();
    initCustomerClose();
    initRatingUi();
    initSoundToggle();
    initPlusToggle();
    initComposerAttachments();
    initVoiceInput();
    bindComposerFocusGuards();
    if (isPersistentAccountChat()) {
      root.classList.add('paxdesign-chat-direct-mode');
      if (entryEl) entryEl.hidden = true;
      if (quickActions) quickActions.hidden = true;
    }
    initAuthGate();
    initHistoryScroll();
    lastAuthUserId = getAuthUserId();
    purgeGuestChatStorage();
    bindAudioUnlock();
    bindVisibilityResume();
    bindChatLifecycle();
    if (isPersistentAccountChat()) {
      bootPromise = Promise.resolve();
      if (config && config.chatSessionId) {
        adoptSessionId(config.chatSessionId, { fromServer: true, preserveUi: true });
      }
      updateEntryUi();
      updateInputState();
    } else {
      bootPromise = restoreCustomerSession();
      bootPromise.then(function () {
        if (!sessionRestored) updateEntryUi();
        updateInputState();
      });
    }
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
    if (isPersistentAccountChat()) return;
    try {
      localStorage.setItem(getSessionStorageKey(), sid);
      localStorage.setItem(SESSION_META_KEY, JSON.stringify({ sessionId: sid, updatedAt: Date.now() }));
      localStorage.setItem(snapshotKey(sid), JSON.stringify({
        messages: messages,
        chatHandler: chatHandler,
        liveAgentPhase: liveAgentPhase,
        entryChoice: entryChoice,
        customerName: customerName,
        sessionRating: sessionRating,
        pollSeq: pollSeq,
        customerEndedChat: customerEndedChat,
        consultationLogged: consultationLogged,
        updatedAt: Date.now(),
      }));
      if (entryChoice) localStorage.setItem(ENTRY_CHOICE_KEY + '-' + sid, entryChoice);
      if (consultationLogged) {
        localStorage.setItem(CONSULTATION_KEY + '-' + sid, '1');
      }
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
    inferEntryChoiceFromHandler();
    if (isPersistentAccountChat()) {
      if (!entryChoice && chatHandler !== 'live_request' && chatHandler !== 'admin') {
        entryChoice = 'ai';
      }
      if (entryEl) entryEl.hidden = true;
      if (welcomeEl) welcomeEl.hidden = true;
      if (quickActions) quickActions.hidden = true;
      root.classList.add('paxdesign-chat-direct-mode');
      root.classList.remove('paxdesign-chat-entry-active');
      if (form) form.classList.remove('paxdesign-is-organizer-mode');
      return;
    }
    var hasMessages = messages.length > 0 || root.classList.contains('paxdesign-has-chat-messages');
    if (threadEl && threadEl.children.length > 0) {
      hasMessages = true;
    }
    var showEntry = false;
    if (!canUseChat()) {
      showEntry = false;
    } else {
      showEntry = !hasMessages && !entryChoice && chatHandler !== 'closed';
    }
    if (entryEl) entryEl.hidden = !showEntry;
    if (welcomeEl) welcomeEl.hidden = showEntry || hasMessages || entryChoice !== 'ai';
    root.classList.toggle('paxdesign-chat-entry-active', showEntry);
    if (form) form.classList.toggle('paxdesign-is-organizer-mode', showEntry);
    if (quickActions) quickActions.classList.toggle('paxdesign-is-organizer-hidden', showEntry);
  }

  function onWidgetOpen() {
    widgetOpen = true;
    initAuthGate();
    whenBootstrapped().then(function () {
      if (!canUseChat()) {
        releaseChatShellLoader();
        showAuthGate();
        notifyLayout();
        return;
      }
      hideAuthGate();
      hideReadinessOverlay();
      stickToBottom = true;
      updateInputState();
      updateEntryUi();
      notifyLayout();
      beginChatReadiness({
        reuseSession: false,
        forceHistory: true,
        forceOpen: true,
        background: false,
        blockUi: false,
      }).then(function (ready) {
        if (!ready) {
          releaseChatShellLoader();
          return;
        }
        pinToLatestMessage();
        revealPinnedThread();
        if (chatHandler === 'closed' && isSessionArchived(getSessionId()) && !isPersistentAccountChat()) {
          fetchSessionFromServer(true).then(function () {
            var hasHistory = messages.length > 0 || (config && config.chatMessageCount > 0);
            if (!hasHistory) {
              beginFreshSessionSilently();
            } else {
              pinToLatestMessage();
            }
          });
        }
      });
    });
  }

  function onWidgetClose() {
    widgetOpen = false;
    composerWantsKeyboard = false;
    stopVoiceInput(true);
    hideReadinessOverlay();
    notifyLayout();
    window.setTimeout(function () {
      cancelChatReadiness();
      stopCustomerStream();
      sendCustomerDisconnectBeacon();
      scheduleLivePolling();
    }, 0);
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

  function resolvedCustomerName() {
    if (customerName && customerName.trim().length >= 2) return customerName.trim();
    if (config && config.auth && config.auth.display_name) {
      var authName = String(config.auth.display_name || '').trim();
      if (authName.length >= 2) return authName;
    }
    if (window.PDXAuth && typeof window.PDXAuth.getUser === 'function') {
      var user = window.PDXAuth.getUser();
      if (user && user.display_name) {
        var displayName = String(user.display_name).trim();
        if (displayName.length >= 2) return displayName;
      }
    }
    return '';
  }

  function anonymousGuestName() {
    var resolved = resolvedCustomerName();
    if (resolved && resolved.length >= 2) return resolved;
    var sid = getSessionId();
    if (sid && sid.length >= 4) return 'Guest ' + sid.slice(-4);
    return 'Guest';
  }

  function safeJson(res) {
    var contentType = (res.headers.get('content-type') || '').toLowerCase();
    if (contentType.indexOf('application/json') === -1) {
      return res.text().then(function (body) {
        var msg = 'Serverfehler. Bitte kurz warten und erneut versuchen.';
        if (body && (body.indexOf('kritischen Fehler') !== -1 || body.indexOf('<!DOCTYPE') !== -1 || body.indexOf('<html') !== -1)) {
          msg = 'Ein Serverfehler ist aufgetreten. Bitte Plugin aktualisieren oder den Administrator kontaktieren.';
        }
        throw new Error(msg);
      });
    }
    return res.json().catch(function () {
      throw new Error('Ungültige Server-Antwort.');
    });
  }

  function beginLiveAgentRequest(topic) {
    var name = anonymousGuestName();
    customerName = name;
    liveNameConfirmed = true;
    saveCustomerName();
    pendingLiveTopic = topic || inferServiceFromConversation();
    return requestLiveAgent(pendingLiveTopic, name);
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


  function queueLiveAgentRequest(topic) {
    pendingLiveTopic = topic || inferServiceFromConversation();
    return beginLiveAgentRequest(pendingLiveTopic);
  }

  function canCustomerEndChat() {
    return false;
  }

  function updateEndButtonUi() {
    if (endWrapEl) endWrapEl.hidden = true;
    if (endBtnEl) endBtnEl.hidden = true;
  }

  function customerCloseConversation() {
    return;
  }

  function initCustomerClose() {
    updateEndButtonUi();
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
        liveAgentPhase = 1;
        saveLiveAgentPhase();
        beginLiveAgentRequest(inferServiceFromConversation()).catch(function (err) {
          showError(err && err.message ? err.message : localizedReadiness('readinessLiveFailed', 'Could not confirm your live agent request.'));
        });
      } else if (choice === 'ai' && config.greeting) {
        appendLocalAssistant(config.greeting);
      }
      saveSessionSnapshot();
    });
  }

  function restoreCustomerSession() {
    var sid = getSessionId();
    consultationLogged = loadConsultationLogged(sid);
    if (isPersistentAccountChat()) {
      if (config && config.chatSessionHasMessages) {
        sessionRestored = true;
      }
      if (config && config.chatSessionId) {
        adoptSessionId(config.chatSessionId, { fromServer: true, preserveUi: false });
      }
      return fetchSessionFromServer(true).then(function (restored) {
        if (chatHandler === 'closed') {
          return reopenAuthenticatedSessionIfNeeded().then(function () {
            return fetchSessionFromServer(true);
          });
        }
        sessionLoading = false;
        if (restored) sessionRestored = true;
        inferEntryChoiceFromHandler();
        updateEntryUi();
      });
    }
    if (isSessionArchived(sid)) {
      beginFreshSessionSilently();
      return Promise.resolve();
    }
    var snap = loadSessionSnapshot(sid);
    if (snap && snap.chatHandler === 'closed' && snap.customerEndedChat) {
      archiveClosedSession(sid, snap);
      beginFreshSessionSilently();
      return Promise.resolve();
    }
    if (snap && Array.isArray(snap.messages) && snap.messages.length) {
      applyRestoredSnapshot(snap);
      sessionRestored = true;
    }
    return fetchSessionFromServer(true).then(function (restored) {
      if (chatHandler === 'closed' && customerEndedChat) {
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
    if (snap.customerEndedChat) customerEndedChat = true;
    if (snap.consultationLogged) consultationLogged = true;
    syncLocalMessageCursor(snap.messages || [], snap.pollSeq);
    var snapMessages = snap.messages || [];
    snapMessages.forEach(function (msg) {
      if (isDuplicateMessage(msg)) return;
      rememberMessageIdentity(msg);
      renderMessageDom(msg.role, messageOriginalContent(msg) || messageText(msg.content), msg.id, messageRenderOpts(msg, { skipPush: true, skipScroll: true }));
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin' || msg.role === 'system') {
        messages.push({
          role: msg.role,
          content: msg.content,
          id: msg.id,
          client_msg_id: msg.client_msg_id || ''
        });
      }
    });
    updateEntryUi();
    stickToBottom = true;
    scrollToBottom(true);
  }

  function syncSessionMetaFromPoll(data) {
    if (!data) return;
    if (data.assigned_agent && data.assigned_agent.name) {
      assignedAgent = data.assigned_agent;
    } else if (data.admin_name) {
      assignedAgent = {
        name: data.admin_name,
        avatar: (data.assigned_agent && data.assigned_agent.avatar) ? data.assigned_agent.avatar : '',
        role: (data.assigned_agent && data.assigned_agent.role) ? data.assigned_agent.role : ''
      };
    }
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

  function clearHistoryDomState() {
    threadEl.innerHTML = '';
    messages = [];
    domMsgIds = {};
    domClientMsgIds = {};
    pollSeq = 0;
    appliedMessageSeq = 0;
    localMsgId = 0;
    oldestLoadedSeq = 0;
    hasOlderMessages = false;
    loadingOlderHistory = false;
    root.classList.remove('paxdesign-has-chat-messages');
  }

  function syncHistoryPaginationFromPoll(data) {
    if (!data) return;
    if (typeof data.has_older === 'boolean') {
      hasOlderMessages = data.has_older;
    }
    if (typeof data.oldest_seq === 'number' && data.oldest_seq > 0) {
      oldestLoadedSeq = data.oldest_seq;
      return;
    }
    if (Array.isArray(data.messages) && data.messages.length) {
      data.messages.forEach(function (msg) {
        var id = msg && msg.id ? parseInt(msg.id, 10) : 0;
        if (id > 0 && (oldestLoadedSeq === 0 || id < oldestLoadedSeq)) {
          oldestLoadedSeq = id;
        }
      });
    }
  }

  function initHistoryScroll() {
    if (historyScrollBound || !messagesEl) return;
    historyScrollBound = true;
    messagesEl.addEventListener('scroll', function () {
      if (!loadingOlderHistory) {
        updateStickToBottomFromScroll();
      }
      if (!hasOlderMessages || loadingOlderHistory) return;
      if (messagesEl.scrollTop <= 80) {
        fetchOlderMessages();
      }
    }, { passive: true });
    var relayout = function () {
      if (stickToBottom) scrollToBottom(true);
    };
    window.addEventListener('resize', relayout);
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', relayout);
    }
  }

  function fetchOlderMessages() {
    if (loadingOlderHistory || !hasOlderMessages || !oldestLoadedSeq || !config.ajaxUrl || !canUseChat()) {
      return Promise.resolve(false);
    }
    if (isEdgeBlocked()) return Promise.resolve(false);
    loadingOlderHistory = true;
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_poll');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('since', '0');
    formData.append('before', String(oldestLoadedSeq));
    formData.append('history_limit', String(HISTORY_BATCH));
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return safeJson(res).then(function (json) { return { res: res, json: json }; }); })
      .then(function (result) {
        var json = result.json;
        if (handleAuthGateResponse(json)) return false;
        if (!json || !json.success || !json.data) return false;
        var data = json.data;
        syncHistoryPaginationFromPoll(data);
        if (Array.isArray(data.messages) && data.messages.length) {
          applyOlderMessages(data.messages);
        } else {
          hasOlderMessages = false;
        }
        if (data.reactions) applyReactionStates(data.reactions);
        return true;
      })
      .catch(function () { return false; })
      .finally(function () {
        loadingOlderHistory = false;
      });
  }

  function applyOlderMessages(incoming) {
    incoming.sort(function (a, b) { return (a.id || 0) - (b.id || 0); });
    var prevScrollHeight = messagesEl ? messagesEl.scrollHeight : 0;
    incoming.forEach(function (msg) {
      if (isDuplicateMessage(msg)) return;
      rememberMessageIdentity(msg);
      if (msg.reaction) messageReactions[msg.id] = msg.reaction;
      indexChatMessage(msg);
      renderMessageDom(
        msg.role,
        messageOriginalContent(msg) || messageText(msg.content),
        msg.id,
        messageRenderOpts(msg, { skipPush: true, prepend: true, skipScroll: true })
      );
      var entry = {
        role: msg.role,
        content: msg.content,
        id: msg.id,
        client_msg_id: msg.client_msg_id || ''
      };
      if (!messages.some(function (m) { return m.id === msg.id; })) {
        messages.unshift(entry);
      }
    });
    syncLocalMessageCursor(messages);
    if (messagesEl) {
      messagesEl.scrollTop = messagesEl.scrollHeight - prevScrollHeight;
      stickToBottom = isNearBottom();
    }
  }

  function fetchSessionFromServer(full, strict) {
    var step = READINESS_STEPS.history;
    if (!config.ajaxUrl) {
      if (strict) return readinessReject(step, 'network', 'readinessNetworkFailed', { reason: 'missing_ajax_url' });
      return Promise.resolve(false);
    }
    if (full && sessionFetchInFlight) {
      return sessionFetchInFlight;
    }
    var hasPaintedThread = (threadEl && threadEl.children.length > 0) || messages.length > 0;
    if (full && !hasPaintedThread) {
      clearHistoryDomState();
    }
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_poll');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('since', '0');
    if (full) {
      formData.append('full', '1');
      formData.append('history_limit', String(HISTORY_INITIAL));
    }
    var request = fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return safeJson(res).then(function (json) { return { res: res, json: json }; }); })
      .then(function (result) {
        var json = result.json;
        if (handleAuthGateResponse(json)) {
          if (strict) return readinessReject(step, 'auth', 'readinessAuthFailed', { reason: 'login_required' });
          return false;
        }
        if (!json || !json.success || !json.data) {
          if (strict) return Promise.reject(classifyChatAjaxFailure(step, json, result.res, 'backend'));
          return false;
        }
        var data = json.data;
        if (data.auth_user_id && getAuthUserId() > 0 && data.auth_user_id !== getAuthUserId()) {
          clearAllChatStorage();
          resetChatForAuthChange(true);
          refreshAuthenticatedChatSession();
          if (strict) return readinessReject(step, 'auth', 'readinessAuthFailed', { reason: 'auth_user_mismatch' });
          return false;
        }
        if (data.session_id) {
          if (config) config.chatSessionId = data.session_id;
          if (data.session_id !== cachedSessionId) {
            adoptSessionId(data.session_id, { fromServer: true, preserveUi: !full });
          }
        }
        applyHandlerState(data.handler || 'ai', data.admin_name || '');
        syncSessionMetaFromPoll(data);
        if (Array.isArray(data.messages) && data.messages.length) applyRestoredMessages(data.messages);
        syncHistoryPaginationFromPoll(data);
        if (data.reactions) applyReactionStates(data.reactions);
        if (typeof data.seq === 'number') {
          pollSeq = Math.max(pollSeq, data.seq);
          syncLocalMessageCursor(messages, data.seq);
        }
        if (typeof data.message_count === 'number' && data.message_count > 0) {
          sessionRestored = true;
          if (config) {
            config.chatSessionHasMessages = true;
            config.chatMessageCount = data.message_count;
          }
        }
        sessionLoading = false;
        inferEntryChoiceFromHandler();
        saveSessionSnapshot();
        lastReadinessPollAt = Date.now();
        if (full) {
          historyLoadedAt = Date.now();
        }
        if (widgetOpen || stickToBottom) {
          stickToBottom = true;
          scrollToBottom(true);
        }
        return true;
      })
      .catch(function (err) {
        if (strict) {
          if (err && err.step) return Promise.reject(err);
          return Promise.reject(readinessFailure(step, 'network', 'readinessNetworkFailed', {
            reason: 'fetch_failed',
            message: err && err.message ? String(err.message) : 'unknown',
          }));
        }
        return false;
      });
    if (full) {
      sessionFetchInFlight = request.finally(function () {
        if (sessionFetchInFlight === request) sessionFetchInFlight = null;
      });
      return sessionFetchInFlight;
    }
    return request;
  }

  function applyRestoredMessages(incoming) {
    incoming.sort(function (a, b) { return (a.id || 0) - (b.id || 0); });
    incoming.forEach(function (msg) {
      if (isDuplicateMessage(msg)) return;
      rememberMessageIdentity(msg);
      if (msg.reaction) messageReactions[msg.id] = msg.reaction;
      indexChatMessage(msg);
      renderMessageDom(msg.role, messageOriginalContent(msg) || messageText(msg.content), msg.id, messageRenderOpts(msg, { skipPush: true, skipScroll: true }));
      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin') {
        if (!messages.some(function (m) { return m.id === msg.id; })) {
          messages.push({
            role: msg.role,
            content: msg.content,
            id: msg.id,
            client_msg_id: msg.client_msg_id || ''
          });
        }
      } else if (msg.role === 'system') {
        messages.push({
          role: 'system',
          content: msg.content,
          id: msg.id,
          client_msg_id: msg.client_msg_id || ''
        });
      }
    });
    syncLocalMessageCursor(messages);
    updateEntryUi();
    if (widgetOpen || stickToBottom) {
      stickToBottom = true;
      scrollToBottom(true);
    }
  }

  function getLiveAgent() {
    return (config && config.liveAgent) ? config.liveAgent : { name: 'Ahmad Alkhalaf', avatar: '' };
  }

  function getAssignedAgent() {
    if (assignedAgent && assignedAgent.name) return assignedAgent;
    if (adminName) {
      return { name: adminName, avatar: '', role: '' };
    }
    return getLiveAgent();
  }

  function getCustomerAgentLabel() {
    var agent = getAssignedAgent();
    return (agent && agent.name) ? agent.name : 'Live Chat';
  }

  function initAgentProfile() {
    var profileBtn = root.querySelector('#paxdesignChatAgentProfile');
    var modal = document.getElementById('paxdesignAgentProfileModal');
    var subtitle = document.getElementById('paxdesignWidgetSubtitle');
    var agent = getAssignedAgent();
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

  function highestAppliedSeq() {
    var maxId = 0;
    (messages || []).forEach(function (msg) {
      if (msg && typeof msg.id === 'number' && msg.id > maxId) {
        maxId = msg.id;
      }
    });
    if (threadEl) {
      threadEl.querySelectorAll('[data-msg-id]').forEach(function (el) {
        var id = parseInt(el.getAttribute('data-msg-id'), 10);
        if (!isNaN(id) && id > maxId) {
          maxId = id;
        }
      });
    }
    return maxId;
  }

  function refreshAppliedSeq() {
    appliedMessageSeq = Math.max(appliedMessageSeq, highestAppliedSeq(), localMsgId);
    pollSeq = Math.max(pollSeq, appliedMessageSeq);
  }

  function getIncrementalSince() {
    return Math.max(0, appliedMessageSeq);
  }

  function scheduleUnifiedSync(reason) {
    if (!canUseChat() || !getSessionId()) return;
    if (unifiedSyncInFlight) {
      unifiedSyncQueued = true;
      return;
    }
    if (unifiedSyncTimer) {
      clearTimeout(unifiedSyncTimer);
      unifiedSyncTimer = 0;
    }
    var delay = reason && reason.indexOf('sse') === 0 ? 0 : 16;
    unifiedSyncTimer = window.setTimeout(function () {
      unifiedSyncTimer = 0;
      runUnifiedSync(false);
    }, delay);
  }

  function runUnifiedSync(strict) {
    if (unifiedSyncInFlight) {
      unifiedSyncQueued = true;
      return unifiedSyncInFlight;
    }
    if (sessionFetchInFlight && !strict) {
      unifiedSyncQueued = true;
      return Promise.resolve(null);
    }
    unifiedSyncInFlight = pollUpdatesOnce(!!strict).then(function (data) {
      if (unifiedSyncQueued) {
        unifiedSyncQueued = false;
        window.setTimeout(function () { runUnifiedSync(false); }, 0);
      }
      return data;
    }).finally(function () {
      unifiedSyncInFlight = null;
    });
    return unifiedSyncInFlight;
  }

  function syncLocalMessageCursor(msgs, serverSeq) {
    var maxId = 0;
    (msgs || []).forEach(function (msg) {
      if (msg && typeof msg.id === 'number' && msg.id > maxId) {
        maxId = msg.id;
      }
    });
    if (typeof serverSeq === 'number' && serverSeq > maxId) {
      maxId = serverSeq;
    }
    if (maxId > localMsgId) {
      localMsgId = maxId;
    }
    if (maxId > appliedMessageSeq) {
      appliedMessageSeq = maxId;
    }
    if (typeof serverSeq === 'number') {
      pollSeq = Math.max(pollSeq, appliedMessageSeq);
    }
  }

  function nextLocalId() {
    localMsgId += 1;
    appliedMessageSeq = Math.max(appliedMessageSeq, localMsgId);
    pollSeq = Math.max(pollSeq, appliedMessageSeq);
    return localMsgId;
  }

  function newClientMessageId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'web-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }

  function detectChatLanguage() {
    var htmlLang = (document.documentElement.lang || '').toLowerCase();
    if (htmlLang.indexOf('ar') === 0) { return 'ar'; }
    if (htmlLang.indexOf('en') === 0) { return 'en'; }
    var nav = (navigator.language || navigator.userLanguage || 'de').toLowerCase();
    if (nav.indexOf('ar') === 0) { return 'ar'; }
    if (nav.indexOf('en') === 0) { return 'en'; }
    return 'de';
  }

  function localizedI18n(key) {
    if (!config || !config.i18n || !config.i18n[key]) { return ''; }
    var bucket = config.i18n[key];
    var lang = detectChatLanguage();
    if (bucket[lang]) { return String(bucket[lang]); }
    if (bucket.de) { return String(bucket.de); }
    return '';
  }

  function staffReturnedToAiNoticeText() {
    var localized = localizedI18n('staffReturnedToAi');
    if (localized) { return localized; }
    if (config && config.i18n && config.i18n.staffReturnedToAi) {
      return String(config.i18n.staffReturnedToAi);
    }
    return 'Das Gespräch wurde an den KI-Assistenten zurückgegeben.';
  }

  function staffTakeoverNoticeText() {
    var localized = localizedI18n('staffTakeover');
    if (localized) { return localized; }
    if (config && config.i18n && config.i18n.staffTakeover) {
      return String(config.i18n.staffTakeover);
    }
    return 'Ein Mitarbeiter hat den Live-Chat übernommen.';
  }

  function injectStaffTakeoverNotice() {
    var notice = staffTakeoverNoticeText();
    var dedupKey = 'sys:staff_takeover';
    if (domClientMsgIds[dedupKey]) return;
    var tempId = nextLocalId();
    renderMessageDom('system', notice, tempId, { skipPush: true });
    rememberMessageIdentity({ id: tempId, role: 'system', content: notice, client_msg_id: dedupKey });
    messages.push({ role: 'system', content: notice, id: tempId, client_msg_id: dedupKey });
  }

  function injectStaffReturnedToAiNotice() {
    var notice = staffReturnedToAiNoticeText();
    var dedupKey = 'sys:staff_returned_to_ai';
    if (domClientMsgIds[dedupKey]) return;
    var tempId = nextLocalId();
    renderMessageDom('system', notice, tempId, { skipPush: true });
    rememberMessageIdentity({ id: tempId, role: 'system', content: notice, client_msg_id: dedupKey });
    messages.push({ role: 'system', content: notice, id: tempId, client_msg_id: dedupKey });
  }

  function systemMessageDedupKey(content) {
    var known = {
      'Chat-Session gestartet.': 'sys:session_started',
      'Dieser Chat wurde geschlossen. Sie können jederzeit ein neues Gespräch starten.': 'sys:chat_closed',
      'This chat has been closed. You can start a new conversation anytime.': 'sys:chat_closed',
      'تم إغلاق هذه الدردشة. يمكنك بدء محادثة جديدة في أي وقت.': 'sys:chat_closed',
      'Der Kunde hat das Gespräch beendet.': 'sys:customer_closed',
      'Der KI-Assistent ist wieder für Sie da. Schreiben Sie jederzeit weiter.': 'sys:customer_released_to_ai',
      'The KI Assistant is back for you. Feel free to keep messaging anytime.': 'sys:customer_released_to_ai',
      'Der KI-Assistent übernimmt den Chat wieder.': 'sys:ai_reclaimed',
      'The conversation has been returned to the KI Assistant.': 'sys:staff_returned_to_ai',
      'Das Gespräch wurde an den KI-Assistenten zurückgegeben.': 'sys:staff_returned_to_ai',
      'تم إرجاع المحادثة إلى مساعد KI.': 'sys:staff_returned_to_ai',
      'Ein Mitarbeiter hat den Live-Chat übernommen.': 'sys:staff_takeover',
      'A team member has taken over the live chat.': 'sys:staff_takeover',
      'قام أحد موظفينا بتولي الدردشة المباشرة.': 'sys:staff_takeover',
      'Ein PAXDesign-Mitarbeiter wurde informiert. Bitte bleiben Sie kurz im Chat.': 'sys:live_agent_notified',
      'Danke. Ich leite Sie jetzt an einen PAXDesign-Mitarbeiter weiter.': 'sys:live_transfer_thanks'
    };
    if (known[content]) return known[content];
    if (content && content.indexOf('Der Chat wurde wieder geöffnet.') === 0) {
      return 'sys:chat_reopened:' + content;
    }
    if (content && content.indexOf(' ist dem Chat beigetreten.') === content.length - 24) {
      return 'sys:admin_joined:' + content;
    }
    return content ? ('sys:content:' + content) : '';
  }

  function messageDedupKey(msg) {
    if (!msg) return '';
    if (msg.client_msg_id) return String(msg.client_msg_id);
    if (msg.role === 'system') return systemMessageDedupKey(msg.content || '');
    return '';
  }

  function isDuplicateMessage(msg) {
    if (!msg) return true;
    if (msg.id && domMsgIds[msg.id]) return true;
    var dedupKey = messageDedupKey(msg);
    if (dedupKey && domClientMsgIds[dedupKey]) return true;
    if (msg.role === 'system' && msg.content === 'Chat-Session gestartet.') return true;
    return false;
  }

  function rememberMessageIdentity(msg) {
    if (!msg || !msg.id) return;
    domMsgIds[msg.id] = true;
    seenMsgId(msg.id);
    var dedupKey = messageDedupKey(msg);
    if (dedupKey) domClientMsgIds[dedupKey] = msg.id;
  }

  function upgradeMessageServerId(localId, serverMsg) {
    if (!serverMsg || !serverMsg.id || localId === serverMsg.id) return;
    var dedupKey = messageDedupKey(serverMsg);
    delete domMsgIds[localId];
    domMsgIds[serverMsg.id] = true;
    seenMsgId(serverMsg.id);
    if (dedupKey) domClientMsgIds[dedupKey] = serverMsg.id;
    var msgEl = threadEl && threadEl.querySelector('[data-msg-id="' + localId + '"]');
    if (msgEl) {
      msgEl.setAttribute('data-msg-id', String(serverMsg.id));
    }
    messages.forEach(function (m) {
      if (m && m.id === localId) {
        m.id = serverMsg.id;
        if (serverMsg.client_msg_id) m.client_msg_id = serverMsg.client_msg_id;
      }
    });
    if (chatMessageMap[localId]) {
      chatMessageMap[serverMsg.id] = chatMessageMap[localId];
      chatMessageMap[localId].id = serverMsg.id;
      delete chatMessageMap[localId];
    }
    updateCustomerLinkScanMessage(serverMsg);
  }

  function reconcileSyncedUserMessage(msg) {
    var dedupKey = messageDedupKey(msg);
    if (!dedupKey || !domClientMsgIds[dedupKey]) return false;
    var localId = domClientMsgIds[dedupKey];
    if (localId && msg.id && localId !== msg.id) {
      upgradeMessageServerId(localId, msg);
    }
    return true;
  }

  function keepComposerFocus() {
    if (!input || input.disabled) return;
    window.setTimeout(function () {
      try {
        input.focus({ preventScroll: true });
      } catch (e) {
        input.focus();
      }
    }, 0);
  }

  function maybeRestoreComposerFocus() {
    if (composerWantsKeyboard && widgetOpen && input && !input.disabled) {
      keepComposerFocus();
    }
  }

  function preventComposerBlur(e) {
    e.preventDefault();
  }

  function bindComposerFocusGuards() {
    if (root.dataset.composerFocusBound === '1') return;
    root.dataset.composerFocusBound = '1';
    var focusGuardSelector =
      '.paxdesign-booking-chat-plus, .paxdesign-booking-chat-media, .paxdesign-booking-chat-file, .paxdesign-booking-chat-voice, .paxdesign-booking-chat-attach-item, .paxdesign-booking-chat-quick-btn';
    root.addEventListener('mousedown', function (e) {
      var control = e.target.closest(focusGuardSelector);
      if (control && root.contains(control)) {
        preventComposerBlur(e);
      }
    }, true);
    root.addEventListener('touchstart', function (e) {
      var control = e.target.closest(focusGuardSelector);
      if (control && root.contains(control)) {
        preventComposerBlur(e);
      }
    }, { capture: true, passive: false });
  }

  function speechRecognitionSupported() {
    return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
  }

  function speechLangForChat() {
    var lang = detectChatLanguage();
    if (lang === 'ar') return 'ar-SA';
    if (lang === 'en') return 'en-US';
    return 'de-DE';
  }

  function refreshVoiceInputMaxHeight() {
    voiceInputMaxHeight = window.matchMedia('(max-width: 768px)').matches ? 160 : 120;
  }

  function stopVoiceAnalyser() {
    if (voiceWaveRaf) {
      cancelAnimationFrame(voiceWaveRaf);
      voiceWaveRaf = 0;
    }
    voiceAnalyser = null;
  }

  function disposeVoiceAudioContext() {
    stopVoiceAnalyser();
    if (voiceAudioCtx) {
      try { voiceAudioCtx.close(); } catch (e) {}
      voiceAudioCtx = null;
    }
  }

  function releaseVoiceMicStream() {
    if (!voiceMicStream) return;
    voiceMicStream.getTracks().forEach(function (track) {
      try { track.stop(); } catch (e) {}
    });
    voiceMicStream = null;
  }

  function ensureVoiceRecordingUi() {
    if (voiceRecordingEl) return;
    var composer = root.querySelector('.paxdesign-booking-chat-composer');
    if (!composer || !input) return;
    voiceRecordingEl = document.createElement('div');
    voiceRecordingEl.className = 'paxdesign-booking-chat-voice-recording';
    voiceRecordingEl.hidden = true;
    voiceRecordingEl.setAttribute('aria-hidden', 'true');
    voiceRecordingEl.innerHTML =
      '<span class="paxdesign-booking-chat-voice-recording-dot" aria-hidden="true"></span>' +
      '<div class="paxdesign-booking-chat-voice-wave" aria-hidden="true">' +
        '<svg class="paxdesign-booking-chat-voice-wave-svg" viewBox="0 0 240 28" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' +
          '<path class="paxdesign-booking-chat-voice-wave-path" d="M0,14 L240,14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="6 5"/>' +
        '</svg>' +
      '</div>' +
      '<span class="paxdesign-booking-chat-voice-recording-label">Aufnahme</span>';
    composer.insertBefore(voiceRecordingEl, input);
    voiceWavePathEl = voiceRecordingEl.querySelector('.paxdesign-booking-chat-voice-wave-path');
  }

  function setVoiceRecordingUi(active) {
    ensureVoiceRecordingUi();
    if (voiceRecordingEl) {
      voiceRecordingEl.hidden = !active;
      voiceRecordingEl.setAttribute('aria-hidden', active ? 'false' : 'true');
    }
    if (input) input.classList.toggle('paxdesign-is-voice-hidden', active);
    root.classList.toggle('paxdesign-voice-recording', active);
  }

  function tickVoiceWaveform() {
    if (!voiceListening || !voiceAnalyser || !voiceWavePathEl) return;
    var buffer = new Uint8Array(voiceAnalyser.fftSize);
    voiceAnalyser.getByteTimeDomainData(buffer);
    var sum = 0;
    for (var i = 0; i < buffer.length; i++) {
      var sample = (buffer[i] - 128) / 128;
      sum += sample * sample;
    }
    var level = Math.min(1, Math.sqrt(sum / buffer.length) * 5);
    var width = 240;
    var mid = 14;
    var amp = 2 + level * 11;
    var phase = Date.now() / 110;
    var parts = [];
    for (var x = 0; x <= width; x += 4) {
      var y = mid + Math.sin((x / width) * Math.PI * 10 + phase) * amp;
      parts.push((x === 0 ? 'M' : 'L') + x + ',' + y.toFixed(1));
    }
    voiceWavePathEl.setAttribute('d', parts.join(' '));
    voiceWaveRaf = requestAnimationFrame(tickVoiceWaveform);
  }

  function startVoiceWaveformFallback() {
    stopVoiceAnalyser();
    if (!voiceWavePathEl) return;
    var phase = 0;
    function tick() {
      if (!voiceListening || !voiceWavePathEl) return;
      phase += 0.22;
      var level = 0.28 + (Math.sin(phase) * 0.5 + 0.5) * 0.35;
      var width = 240;
      var mid = 14;
      var amp = 2 + level * 11;
      var parts = [];
      for (var x = 0; x <= width; x += 4) {
        var y = mid + Math.sin((x / width) * Math.PI * 10 + phase) * amp;
        parts.push((x === 0 ? 'M' : 'L') + x + ',' + y.toFixed(1));
      }
      voiceWavePathEl.setAttribute('d', parts.join(' '));
      voiceWaveRaf = requestAnimationFrame(tick);
    }
    tick();
  }

  function startVoiceAnalyser(stream) {
    stopVoiceAnalyser();
    if (!stream) {
      startVoiceWaveformFallback();
      return;
    }
    try {
      if (!voiceAudioCtx) {
        voiceAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      var begin = function () {
        if (!voiceListening || !voiceAudioCtx) return;
        var source = voiceAudioCtx.createMediaStreamSource(stream);
        voiceAnalyser = voiceAudioCtx.createAnalyser();
        voiceAnalyser.fftSize = 512;
        voiceAnalyser.smoothingTimeConstant = 0.65;
        source.connect(voiceAnalyser);
        tickVoiceWaveform();
      };
      if (voiceAudioCtx.state === 'suspended') {
        voiceAudioCtx.resume().then(begin).catch(function () {
          startVoiceWaveformFallback();
        });
      } else {
        begin();
      }
    } catch (e) {
      startVoiceWaveformFallback();
    }
  }

  function stopVoiceInput(releaseMic) {
    voiceListening = false;
    voiceStartInFlight = false;
    voicePendingMicRetry = false;
    root.classList.remove('paxdesign-voice-active');
    setVoiceRecordingUi(false);
    stopVoiceAnalyser();
    if (voiceRecognition) {
      voiceRecognition.onend = null;
      voiceRecognition.onerror = null;
      voiceRecognition.onresult = null;
      voiceRecognition.onstart = null;
      try { voiceRecognition.stop(); } catch (e) {}
      voiceRecognition = null;
    }
    if (releaseMic) {
      releaseVoiceMicStream();
    }
    if (voiceBtn) {
      voiceBtn.setAttribute('aria-pressed', 'false');
      voiceBtn.classList.remove('paxdesign-is-active', 'paxdesign-is-pending');
    }
    voiceBaseText = input ? input.value : '';
    autoResizeInput();
    updateSendButton();
    maybeRestoreComposerFocus();
  }

  function syncVoiceMicPermission(state) {
    if (state === 'granted') {
      voiceMicPermission = 'granted';
      return;
    }
    if (state === 'denied') {
      voiceMicPermission = 'denied';
      return;
    }
    if (voiceMicPermission !== 'granted') {
      voiceMicPermission = 'prompt';
    }
  }

  function refreshVoiceMicPermissionHint() {
    if (!navigator.permissions || typeof navigator.permissions.query !== 'function') {
      return Promise.resolve(voiceMicPermission);
    }
    return navigator.permissions.query({ name: 'microphone' }).then(function (status) {
      syncVoiceMicPermission(status.state);
      return status.state;
    }).catch(function () {
      return voiceMicPermission;
    });
  }

  function initVoiceMicPermissionWatch() {
    if (!navigator.permissions || typeof navigator.permissions.query !== 'function') return;
    navigator.permissions.query({ name: 'microphone' }).then(function (status) {
      syncVoiceMicPermission(status.state);
      status.onchange = function () {
        syncVoiceMicPermission(status.state);
        if (status.state === 'granted') {
          voiceMicSessionReady = false;
        }
      };
    }).catch(function () {});
  }

  function bindVoiceMicTrackLifecycle(track) {
    if (!track || track.__paxVoiceBound) return;
    track.__paxVoiceBound = true;
    track.addEventListener('ended', function () {
      voiceMicPermission = 'prompt';
      voiceMicStream = null;
      initVoiceMicPermissionWatch();
    });
  }

  function markVoiceMicPermissionGranted(stream) {
    voiceMicPermission = 'granted';
    voiceMicSessionReady = true;
    if (stream && stream.getAudioTracks) {
      stream.getAudioTracks().forEach(bindVoiceMicTrackLifecycle);
    }
  }

  function primeVoiceAudioContext() {
    try {
      if (!voiceAudioCtx) {
        voiceAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (voiceAudioCtx && voiceAudioCtx.state === 'suspended') {
        voiceAudioCtx.resume().catch(function () {});
      }
    } catch (e) {}
  }

  function hasLiveVoiceMicStream() {
    if (!voiceMicStream) return false;
    var tracks = voiceMicStream.getAudioTracks();
    return tracks.length > 0 && tracks[0].readyState === 'live';
  }

  function acquireMicrophoneStream(options) {
    options = options || {};
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
      return Promise.reject({ code: 'unsupported' });
    }
    if (!options.forceNew && hasLiveVoiceMicStream()) {
      return Promise.resolve(voiceMicStream);
    }
    if (options.forceNew || !hasLiveVoiceMicStream()) {
      releaseVoiceMicStream();
    }
    return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      voiceMicStream = stream;
      markVoiceMicPermissionGranted(stream);
      return stream;
    }).catch(function (err) {
      var name = err && (err.name || err.code || '');
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'not-allowed') {
        voiceMicPermission = 'denied';
      }
      throw err;
    });
  }

  function requestMicrophoneFromUserGesture(options) {
    options = options || {};
    var forceNew = !!options.forceNew || !hasLiveVoiceMicStream();
    return acquireMicrophoneStream({ forceNew: forceNew });
  }

  function startVoiceWaveformFromHeldStream() {
    if (!voiceListening) return;
    if (hasLiveVoiceMicStream()) {
      startVoiceAnalyser(voiceMicStream);
      return;
    }
    startVoiceWaveformFallback();
  }

  function microphoneDeniedRecoveryMessage() {
    return 'Mikrofon-Zugriff verweigert. Klicken Sie in Chrome oder Edge auf das Schlosssymbol in der Adressleiste, erlauben Sie das Mikrofon für paxdesign.at und drücken Sie das Mikrofon erneut.';
  }

  function speechRecognitionBlockedMessage() {
    return 'Mikrofon ist erlaubt, aber die Spracherkennung wurde vom Browser blockiert. Bitte schließen Sie andere Apps, die das Mikrofon verwenden, und drücken Sie das Mikrofon erneut.';
  }

  function microphoneAccessErrorMessage(err) {
    if (!err) return 'Mikrofon-Zugriff nicht verfügbar.';
    if (err.code === 'unsupported') return 'Mikrofon wird in diesem Browser nicht unterstützt.';
    var name = err.name || err.code || '';
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'not-allowed') {
      return microphoneDeniedRecoveryMessage();
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'Kein Mikrofon gefunden.';
    }
    return 'Mikrofon-Zugriff nicht verfügbar.';
  }

  function beginSpeechRecognition(options) {
    options = options || {};
    if (voiceRecognition) {
      voiceRecognition.onend = null;
      voiceRecognition.onerror = null;
      voiceRecognition.onresult = null;
      voiceRecognition.onstart = null;
      try { voiceRecognition.stop(); } catch (e) {}
      voiceRecognition = null;
    }
    if (!speechRecognitionSupported()) {
      if (voiceBtn) voiceBtn.classList.remove('paxdesign-is-pending');
      showError('Spracheingabe wird in diesem Browser nicht unterstützt.');
      return;
    }
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    voiceRecognition = new SpeechRecognition();
    voiceRecognition.continuous = true;
    voiceRecognition.interimResults = true;
    voiceRecognition.lang = speechLangForChat();
    voiceBaseText = input.value || '';
    if (voiceBaseText && !/\s$/.test(voiceBaseText)) {
      voiceBaseText += ' ';
    }
    voiceRecognition.onresult = function (event) {
      var finalText = '';
      var interimText = '';
      for (var i = event.resultIndex; i < event.results.length; i++) {
        var piece = event.results[i][0].transcript;
        if (event.results[i].isFinal) finalText += piece;
        else interimText += piece;
      }
      input.value = voiceBaseText + finalText + interimText;
      autoResizeInput();
      updateSendButton();
    };
    voiceRecognition.onstart = function () {
      voiceListening = true;
      voicePendingMicRetry = false;
      voiceMicPermission = 'granted';
      voiceMicSessionReady = true;
      root.classList.add('paxdesign-voice-active');
      setVoiceRecordingUi(true);
      if (voiceBtn) {
        voiceBtn.setAttribute('aria-pressed', 'true');
        voiceBtn.classList.remove('paxdesign-is-pending');
        voiceBtn.classList.add('paxdesign-is-active');
      }
      startVoiceWaveformFromHeldStream();
    };
    voiceRecognition.onend = function () {
      if (!voiceListening) return;
      try {
        voiceRecognition.start();
      } catch (e) {
        stopVoiceInput(false);
      }
    };
    voiceRecognition.onerror = function (event) {
      if (event.error === 'not-allowed') {
        if (options.syncGesture && !options.afterMicGrant && !hasLiveVoiceMicStream()) {
          voicePendingMicRetry = true;
          return;
        }
        if (hasLiveVoiceMicStream() && !options.retriedNotAllowed) {
          options.retriedNotAllowed = true;
          stopVoiceAnalyser();
          window.setTimeout(function () {
            if (!voiceStartInFlight && !voiceListening && !voicePendingMicRetry) return;
            beginSpeechRecognition({ retried: true, retriedNotAllowed: true, afterMicGrant: options.afterMicGrant });
          }, 140);
          return;
        }
        if (hasLiveVoiceMicStream() || options.afterMicGrant) {
          showError(speechRecognitionBlockedMessage());
        } else {
          showError(microphoneDeniedRecoveryMessage());
        }
        stopVoiceInput(false);
        return;
      }
      if (!options.retried && event.error === 'audio-capture') {
        options.retried = true;
        stopVoiceAnalyser();
        window.setTimeout(function () {
          if (!voiceListening) return;
          beginSpeechRecognition({ retried: true });
        }, 120);
        return;
      }
      if (event.error !== 'aborted' && event.error !== 'no-speech') {
        showError('Spracheingabe fehlgeschlagen.');
      }
      stopVoiceInput(false);
    };
    try {
      voiceRecognition.start();
      composerWantsKeyboard = true;
      keepComposerFocus();
    } catch (err) {
      if (voiceBtn) voiceBtn.classList.remove('paxdesign-is-pending');
      stopVoiceInput(false);
      showError('Spracheingabe konnte nicht gestartet werden.');
    }
  }

  function startVoiceInput() {
    if (!input || input.disabled || chatHandler === 'closed' || isStreaming) return;
    if (voiceListening) {
      stopVoiceInput(false);
      return;
    }
    if (voiceStartInFlight) return;
    voiceStartInFlight = true;
    voicePendingMicRetry = false;

    composerWantsKeyboard = true;
    keepComposerFocus();
    primeVoiceAudioContext();
    if (voiceBtn) voiceBtn.classList.add('paxdesign-is-pending');
    refreshVoiceMicPermissionHint();

    var micPromise = requestMicrophoneFromUserGesture({
      forceNew: !hasLiveVoiceMicStream()
    });

    beginSpeechRecognition({ syncGesture: true });

    micPromise.then(function () {
      voiceStartInFlight = false;
      if (voiceListening) {
        startVoiceWaveformFromHeldStream();
        return;
      }
      if (voicePendingMicRetry) {
        voicePendingMicRetry = false;
        beginSpeechRecognition({ afterMicGrant: true });
        return;
      }
      if (voiceBtn) voiceBtn.classList.remove('paxdesign-is-pending');
    }).catch(function (err) {
      voiceStartInFlight = false;
      voicePendingMicRetry = false;
      if (voiceBtn) voiceBtn.classList.remove('paxdesign-is-pending');
      if (!voiceListening) {
        showError(microphoneAccessErrorMessage(err));
        maybeRestoreComposerFocus();
      }
    });
  }

  function toggleVoiceInput(e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    primeVoiceAudioContext();
    if (voiceListening) stopVoiceInput(false);
    else startVoiceInput();
  }

  function initVoiceInput() {
    refreshVoiceInputMaxHeight();
    initVoiceMicPermissionWatch();
    window.addEventListener('resize', refreshVoiceInputMaxHeight);
    voiceBtn = root.querySelector('.paxdesign-booking-chat-voice');
    if (!voiceBtn) return;
    voiceBtn.hidden = false;
    var voicePointerHandled = false;
    voiceBtn.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && typeof e.button === 'number' && e.button !== 0) return;
      voicePointerHandled = true;
      window.setTimeout(function () { voicePointerHandled = false; }, 450);
      toggleVoiceInput(e);
    });
    voiceBtn.addEventListener('click', function (e) {
      if (typeof e.button === 'number' && e.button !== 0) return;
      if (voicePointerHandled) {
        e.preventDefault();
        return;
      }
      toggleVoiceInput(e);
    });
  }

  function openComposerAttachmentPicker(kind) {
    closeComposerAttachMenu();
    if (!isHumanMode()) {
      showError(attachMenuLabel('humanOnly'));
      maybeRestoreComposerFocus();
      return;
    }
    if (!canUseChat()) {
      showAuthGate();
      maybeRestoreComposerFocus();
      return;
    }
    composerWantsKeyboard = true;
    ensureHumanAttachInputs();
    var inputEl = document.getElementById(kind === 'image' ? 'paxdesignChatHumanImageAttach' : 'paxdesignChatHumanFileAttach');
    window.setTimeout(function () {
      triggerHiddenFileInput(inputEl);
    }, 0);
  }

  function initComposerAttachments() {
    if (root.dataset.composerAttachBound === '1') return;
    root.dataset.composerAttachBound = '1';
    ensureHumanAttachInputs();
    var mediaBtn = root.querySelector('.paxdesign-booking-chat-media');
    var fileBtn = root.querySelector('.paxdesign-booking-chat-file');
    function bindPickerButton(btn, kind) {
      if (!btn) return;
      btn.hidden = false;
      btn.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        openComposerAttachmentPicker(kind);
      });
    }
    bindPickerButton(mediaBtn, 'image');
    bindPickerButton(fileBtn, 'file');
  }

  function loadConsultationLogged(sessionId) {
    try {
      return localStorage.getItem(CONSULTATION_KEY + '-' + sessionId) === '1';
    } catch (e) {
      return false;
    }
  }

  function getSessionId() {
    if (cachedSessionId) return cachedSessionId;
    if (isLoginGateEnabled() && !canUseChat()) {
      return '';
    }
    if (isPersistentAccountChat()) {
      if (config && config.chatSessionId) {
        cachedSessionId = config.chatSessionId;
        try {
          localStorage.setItem(getSessionStorageKey(), config.chatSessionId);
          sessionStorage.setItem(SESSION_KEY, config.chatSessionId);
        } catch (e) {}
        return cachedSessionId;
      }
      try {
        var stored = localStorage.getItem(getSessionStorageKey()) || sessionStorage.getItem(SESSION_KEY);
        if (stored) {
          cachedSessionId = stored;
          return stored;
        }
      } catch (e) {}
      return config && config.chatSessionId ? config.chatSessionId : '';
    }
    if (isLoginGateEnabled()) {
      return config && config.chatSessionId ? config.chatSessionId : '';
    }
    try {
      var id = localStorage.getItem(SESSION_KEY) || sessionStorage.getItem(SESSION_KEY);
      if (!id) {
        id = createNewSessionId();
        localStorage.setItem(SESSION_KEY, id);
        sessionStorage.setItem(SESSION_KEY, id);
      }
      cachedSessionId = id;
      return id;
    } catch (e) {
      if (!cachedSessionId) {
        cachedSessionId = createNewSessionId();
      }
      return cachedSessionId;
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
    if (cachedDeviceToken) return cachedDeviceToken;
    try {
      var token = localStorage.getItem(DEVICE_TOKEN_KEY) || sessionStorage.getItem(DEVICE_TOKEN_KEY);
      if (!token) {
        token = 'paxdev_' + randomHex(24);
        localStorage.setItem(DEVICE_TOKEN_KEY, token);
        sessionStorage.setItem(DEVICE_TOKEN_KEY, token);
      }
      cachedDeviceToken = token;
      return token;
    } catch (e) {
      if (!cachedDeviceToken) {
        cachedDeviceToken = 'paxdev_' + randomHex(24);
      }
      return cachedDeviceToken;
    }
  }

  function stampChatRequest(formData) {
    if (formData) {
      formData.append('device_token', getDeviceToken());
      appendPageContextFields(formData);
    }
    return formData;
  }

  function appendPageContextFields(formData) {
    var ctx = window.PAXdesignPageContext || {};
    if (!ctx.intent) {
      var path = window.location.pathname || '';
      if (path.indexOf('cybercrime-support') !== -1) {
        ctx.intent = 'cybercrime-support';
      }
    }
    if (ctx.intent) {
      formData.append('page_context', ctx.intent);
    }
    if (ctx.language) {
      formData.append('page_language', ctx.language);
    }
    if (ctx.referenceId) {
      formData.append('page_reference', ctx.referenceId);
    }
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

  function archiveClosedSession(sessionId, snap) {
    var sid = sessionId || getSessionId();
    if (!sid || isSessionArchived(sid)) return;

    var archivedIds = loadArchivedIds();
    archivedIds.unshift(sid);
    saveArchivedIds(archivedIds.filter(function (id, idx, arr) {
      return arr.indexOf(id) === idx;
    }).slice(0, 100));
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
        showChatStatusNotice('Benachrichtigungen aktiviert');
      } else {
        stopTypingSound();
        showChatStatusNotice('Benachrichtigungen deaktiviert');
      }
    });
  }

  function showChatStatusNotice(text) {
    if (!messagesEl) return;
    var note = document.createElement('div');
    note.className = 'paxdesign-booking-chat-status-notice';
    note.setAttribute('role', 'status');
    note.textContent = text;
    messagesEl.appendChild(note);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    setTimeout(function () {
      if (note.parentNode) note.parentNode.removeChild(note);
    }, 3200);
  }

  function updateSoundToggleUi() {
    if (!notifyBtn) return;
    var onIcon = notifyBtn.querySelector('.paxdesign-bell-icon--on');
    var offIcon = notifyBtn.querySelector('.paxdesign-bell-icon--off');
    if (onIcon) onIcon.hidden = !soundEnabled;
    if (offIcon) offIcon.hidden = soundEnabled;
    notifyBtn.classList.toggle('paxdesign-is-muted', !soundEnabled);
    notifyBtn.classList.toggle('paxdesign-is-active', soundEnabled);
    notifyBtn.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
    notifyBtn.title = soundEnabled ? 'Benachrichtigungston aus' : 'Benachrichtigungston an';
  }

  function bindAudioUnlock() {
    var unlock = function () { unlockAudio(); };
    root.addEventListener('click', unlock, { once: false });
    root.addEventListener('touchstart', unlock, { once: false, passive: true });
    input.addEventListener('focus', unlock, { once: false });
  }

  function bindChatLifecycle() {
    window.addEventListener('pagehide', sendCustomerDisconnectBeacon);
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

  function playIncomingAdminSound() {
    if (!soundEnabled) return;
    if (SOUND_URLS.incoming) {
      playMp3Sound('incoming', { volume: document.hidden ? 0.5 : 0.28, force: false });
      return;
    }
    runWithAudioContext(function (ctx) {
      try {
        var t = ctx.currentTime;
        var master = ctx.createGain();
        master.gain.setValueAtTime(document.hidden ? 0.34 : 0.18, t);
        master.connect(ctx.destination);
        [[660, 0, 0.07], [880, 0.05, 0.09]].forEach(function (tone) {
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

  function tryCustomerBrowserNotification(title, body) {
    if (!document.hidden) return;
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'granted') {
      if (Notification.permission !== 'denied') {
        Notification.requestPermission();
      }
      return;
    }
    try {
      var n = new Notification(title || 'PAXDesign Live Chat', {
        body: body || 'Neue Nachricht vom Support-Team.',
        tag: 'pax-customer-admin-msg',
        silent: false
      });
      n.onclick = function () {
        window.focus();
        n.close();
      };
    } catch (e) {}
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
    scheduleLivePolling();
  }

  function effectivePollIntervalMs() {
    if (customerStreamConnected()) return POLL_INTERVAL_SSE_MS;
    if (!pageVisible) return POLL_INTERVAL_BACKGROUND_MS;
    if (widgetOpen) {
      return (chatHandler === 'admin' || chatHandler === 'live_request')
        ? POLL_INTERVAL_HUMAN_MS
        : POLL_INTERVAL_OPEN_MS;
    }
    return POLL_INTERVAL_BACKGROUND_MS;
  }

  function scheduleLivePolling() {
    if (pollTimer) clearInterval(pollTimer);
    if (!pageVisible || !canUseChat() || isEdgeBlocked()) {
      pollTimer = null;
      return;
    }
    if (!pollInFlight && !sessionFetchInFlight && !pollIsFresh(400)) {
      pollUpdates();
    }
    pollTimer = window.setInterval(pollUpdates, effectivePollIntervalMs());
  }

  document.addEventListener('visibilitychange', function () {
    pageVisible = !document.hidden;
    if (pageVisible && canUseChat()) {
      pollUpdates();
      scheduleLivePolling();
      if (widgetOpen || isPersistentAccountChat()) startCustomerStream();
    } else if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
      stopCustomerStream();
    }
  });

  function applyHandlerState(handler, name) {
    if (!handler) return;
    if (handler === chatHandler) {
      if (name && name !== adminName) {
        adminName = name;
        updateHandlerUi();
      }
      updateEndButtonUi();
      return;
    }
    var previousHandler = chatHandler;
    var transitioningToAdmin = handler === 'admin' && previousHandler !== 'admin';
    var transitioningToClosed = handler === 'closed' && previousHandler !== 'closed';
    var returningToAi = handler === 'ai' && (previousHandler === 'admin' || previousHandler === 'live_request');
    if (handler === 'admin') {
      abortStream();
      removeTyping();
      if (transitioningToAdmin) {
        playAgentJoinedSoundOnce();
        injectStaffTakeoverNotice();
      }
      if (previousHandler === 'live_request') {
        liveAgentPhase = 2;
        saveLiveAgentPhase();
      }
    }
    if (handler === 'ai') {
      resetLiveAgentPhase();
      if (returningToAi) {
        stopAdminTypingFeedback();
        injectStaffReturnedToAiNotice();
      }
    }
    if (handler === 'closed') {
      abortStream();
      removeTyping();
      if (isPersistentAccountChat()) {
        prevChatHandler = chatHandler;
        chatHandler = handler;
        reopenAuthenticatedSessionIfNeeded().then(function () {
          fetchSessionFromServer(true);
        });
        return;
      }
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
    scheduleLivePolling();
    scheduleCustomerStreamRestart(customerStreamReconnectDelay);
  }

  function updateHandlerUi() {
    root.classList.toggle('paxdesign-chat-admin-active', chatHandler === 'admin');
    root.classList.toggle('paxdesign-chat-live-request', chatHandler === 'live_request');
    root.classList.toggle('paxdesign-chat-closed', chatHandler === 'closed');
    updateHumanStaffStatus();
    updateComposerPlusUi();
  }

  function updateInputState() {
    var closed = chatHandler === 'closed' && !isPersistentAccountChat();
    var showForm = !closed;
    input.disabled = closed;
    if (closedBar) {
      closedBar.hidden = !closed;
    }
    if (form) {
      form.hidden = !showForm;
    }
    updateEndButtonUi();
    updateSendButton();
    if (closed) {
      input.placeholder = 'Chat geschlossen';
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
    domClientMsgIds = {};
    pollSeq = 0;
    appliedMessageSeq = 0;
    oldestLoadedSeq = 0;
    hasOlderMessages = false;
    loadingOlderHistory = false;
    localMsgId = 0;
    chatHandler = 'ai';
    adminName = '';
    liveAgentPhase = 0;
    entryChoice = '';
    sessionRestored = false;
    customerName = '';
    customerEndedChat = false;
    pendingLiveTopic = '';
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
    var newId = createNewSessionId();
    cachedSessionId = newId;
    try {
      localStorage.setItem(SESSION_KEY, newId);
      sessionStorage.setItem(SESSION_KEY, newId);
      sessionStorage.removeItem(LIVE_AGENT_KEY);
      localStorage.removeItem(LIVE_AGENT_KEY);
      localStorage.removeItem(ENTRY_CHOICE_KEY + '-' + newId);
      localStorage.removeItem(CONSULTATION_KEY + '-' + newId);
    } catch (e) {}

    resetSessionState();
    logConsultationStarted();
    startLivePolling();
    notifyLayout();
    saveSessionSnapshot();
  }

  function startNewConversation() {
    if (isPersistentAccountChat()) {
      if (!window.PDXAuth || typeof window.PDXAuth.customerApiFetch !== 'function') {
        return;
      }
      if (!window.confirm('Neues Gespräch starten? Ihr bisheriger Chat bleibt gespeichert — es beginnt eine neue Session.')) {
        return;
      }
      window.PDXAuth.customerApiFetch('POST', '/customer/chat/session', { new_conversation: true }).then(function (data) {
        if (data && data.session_id && config) {
          config.chatSessionId = data.session_id;
          config.chatSessionHasMessages = false;
          config.chatMessageCount = 0;
        }
        if (data && data.session_id) {
          invalidateAndRefreshChatSession('new_conversation', {
            previousUserId: lastAuthUserId,
            force: true,
          });
        }
      });
      return;
    }
    if (!window.confirm('Neues Gespräch starten? Ihr bisheriger Chat bleibt gespeichert — es beginnt eine neue Session.')) {
      return;
    }
    archiveClosedSession();
    beginFreshSessionSilently();
  }

  function applyPollPayload(data) {
    if (!data) return false;
    if (data.auth_user_id && getAuthUserId() > 0 && data.auth_user_id !== getAuthUserId()) {
      clearAllChatStorage();
      resetChatForAuthChange(true);
      refreshAuthenticatedChatSession();
      return false;
    }
    if (data.session_id && data.session_id !== getSessionId()) {
      adoptSessionId(data.session_id, {
        fromServer: true,
        preserveUi: false,
      });
    }
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
    } else if (data.assistant_typing && chatHandler === 'ai' && !isStreaming) {
      if (!typingEl) showTyping();
    } else if (!data.assistant_typing && chatHandler === 'ai' && !isStreaming) {
      removeTyping();
    } else {
      stopAdminTypingFeedback();
    }

    if (hadNewMessages || incomingAdmin) {
      ensureAdminMessageChrome();
    }

    if (data.reactions && typeof data.reactions === 'object') {
      applyReactionStates(data.reactions);
    }
    if (data.sync && data.sync.resync_required && !loadingOlderHistory) {
      fetchSessionFromServer(true, false).then(function () {
        refreshAppliedSeq();
        if (stickToBottom) scrollToBottom(true);
      });
    }
    refreshAppliedSeq();
    if (typeof data.seq === 'number') {
      pollSeq = Math.max(pollSeq, appliedMessageSeq);
    }
    lastReadinessPollAt = Date.now();
    maybeRestoreComposerFocus();
    return true;
  }

  function pollUpdatesOnce(strict) {
    var step = strict ? READINESS_STEPS.sync : READINESS_STEPS.finalSync;
    if (!config.ajaxUrl || !canUseChat()) {
      if (strict) return readinessReject(step, 'network', 'readinessNetworkFailed', { reason: 'poll_unavailable' });
      return Promise.resolve(null);
    }
    if (isEdgeBlocked()) {
      if (strict) return readinessReject(step, 'network', 'readinessNetworkFailed', { reason: 'edge_blocked' });
      return Promise.resolve(null);
    }
    if (pollInFlight && !strict) {
      return pollInFlight;
    }
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_poll');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('since', String(getIncrementalSince()));

    var request = fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) {
        return res.text().then(function (text) {
          if (isEdgeForbiddenResponse(res, text)) {
            markEdgeBlocked();
            if (strict) return readinessReject(step, 'network', 'readinessNetworkFailed', { reason: 'edge_blocked' });
            return null;
          }
          var json = null;
          try { json = JSON.parse(text); } catch (e) { json = null; }
          return { res: res, json: json };
        });
      })
      .then(function (result) {
        if (!result) return null;
        var json = result.json;
        if (handleAuthGateResponse(json)) {
          if (strict) return readinessReject(step, 'auth', 'readinessAuthFailed', { reason: 'login_required' });
          return null;
        }
        if (!json || !json.success || !json.data) {
          if (strict) return Promise.reject(classifyChatAjaxFailure(step, json, result.res, 'backend'));
          return null;
        }
        applyPollPayload(json.data);
        return json.data;
      })
      .catch(function (err) {
        if (strict) {
          if (err && err.step) return Promise.reject(err);
          return Promise.reject(readinessFailure(step, 'network', 'readinessNetworkFailed', {
            reason: 'poll_fetch_failed',
            message: err && err.message ? String(err.message) : 'unknown',
          }));
        }
        return null;
      })
      .finally(function () {
        if (pollInFlight === request) pollInFlight = null;
      });
    pollInFlight = request;
    return request;
  }

  function pollUpdates() {
    if (!config.ajaxUrl) return;
    if (!canUseChat()) return;
    scheduleUnifiedSync('poll-timer');
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

  function maskCustomerLinkScanMessage(msg) {
    return msg;
  }

  var urlScanAnimTimers = {};
  var urlScanAnimOriginals = {};

  function scrambleUrlClient(url, frame) {
    var chars = '0123456789abcdef•·∙▪▫◦×÷+=@#$_-';
    var out = '';
    for (var i = 0; i < url.length; i++) {
      var seed = 0;
      var key = url + ':' + frame + ':' + i;
      for (var j = 0; j < key.length; j++) {
        seed = ((seed << 5) - seed + key.charCodeAt(j)) | 0;
      }
      out += chars.charAt(Math.abs(seed) % chars.length);
    }
    return out;
  }

  function messageOriginalContent(msg) {
    if (!msg) return '';
    if (msg.link_scan_original_content) return messageText(msg.link_scan_original_content);
    return messageText(msg.content || '');
  }

  function wrapUrlsForScanHtml(content) {
    content = messageText(content || '');
    if (!content) return '';
    var urls = extractUrlsFromText(content);
    if (!urls.length) return escapeHtml(content);
    var html = escapeHtml(content);
    urls.forEach(function (url) {
      var escaped = escapeHtml(url);
      var replacement = '<span class="paxdesign-booking-chat-url-scan" data-original-url="' + escaped + '">' + escaped + '</span>';
      html = html.split(escaped).join(replacement);
    });
    return html;
  }

  function extractUrlsFromText(text) {
    if (!text) return [];
    var pattern = /\bhttps?:\/\/[^\s<>"')\]]+/gi;
    var matches = String(text).match(pattern) || [];
    var urls = [];
    matches.forEach(function (raw) {
      var url = raw.replace(/[.,;:!?)]+$/, '');
      if (url && urls.indexOf(url) === -1) urls.push(url);
    });
    return urls;
  }

  function customerLinkScanLabel(status, serverLabel) {
    if (serverLabel) return serverLabel;
    var lang = (document.documentElement.lang || 'de').toLowerCase();
    var isEn = lang.indexOf('en') === 0;
    var isAr = lang.indexOf('ar') === 0;
    if (status === 'checking') {
      if (isAr) return 'جاري فحص الأمان …';
      return isEn ? 'Security scan in progress …' : 'Sicherheitsprüfung läuft …';
    }
    if (status === 'safe') {
      if (isAr) return 'رابط آمن';
      return isEn ? 'Safe link' : 'Sicherer Link';
    }
    if (status === 'suspicious') {
      if (isAr) return 'رابط مشبوه';
      return isEn ? 'Suspicious link' : 'Verdächtiger Link';
    }
    if (status === 'dangerous') {
      if (isAr) return 'رابط خطير';
      return isEn ? 'Dangerous link' : 'Gefährlicher Link';
    }
    if (status === 'failed' || status === 'timeout' || status === 'incomplete') {
      if (isAr) return 'الفحص غير مكتمل';
      return isEn ? 'Scan not completed.' : 'Scan nicht abgeschlossen.';
    }
    return '';
  }

  function startUrlScanAnimation(msgId, originalContent) {
    var key = String(msgId);
    if (!originalContent) return;
    urlScanAnimOriginals[key] = originalContent;
    stopUrlScanAnimation(msgId);
    var msgEl = threadEl.querySelector('[data-msg-id="' + key + '"]');
    if (!msgEl) return;
    var urlSpans = msgEl.querySelectorAll('.paxdesign-booking-chat-url-scan');
    if (!urlSpans.length) return;
    msgEl.classList.add('paxdesign-booking-chat-message--url-scanning');
    urlScanAnimTimers[key] = window.setInterval(function () {
      var frame = Date.now();
      urlSpans.forEach(function (span) {
        var original = span.getAttribute('data-original-url') || '';
        if (!original) return;
        span.textContent = scrambleUrlClient(original, frame + original.length);
      });
    }, 45);
  }

  function stopUrlScanAnimation(msgId, restoreOriginal) {
    var key = String(msgId);
    if (urlScanAnimTimers[key]) {
      clearInterval(urlScanAnimTimers[key]);
      delete urlScanAnimTimers[key];
    }
    var msgEl = threadEl.querySelector('[data-msg-id="' + key + '"]');
    if (msgEl) {
      msgEl.classList.remove('paxdesign-booking-chat-message--url-scanning');
      if (restoreOriginal !== false) {
        msgEl.querySelectorAll('.paxdesign-booking-chat-url-scan').forEach(function (span) {
          var original = span.getAttribute('data-original-url') || '';
          if (original) span.textContent = original;
        });
      }
    }
  }

  function updateCustomerLinkScanContent(msgId, serverMsg) {
    if (!msgId || !serverMsg) return;
    var msgEl = threadEl.querySelector('[data-msg-id="' + msgId + '"]');
    if (!msgEl) return;
    var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
    if (!bubble) return;
    var status = serverMsg.link_scan_status || '';
    var original = messageOriginalContent(serverMsg);
    var textEl = bubble.querySelector('.paxdesign-booking-chat-message-text');
    if (!textEl) {
      var badge = bubble.querySelector('.paxdesign-booking-chat-link-scan');
      textEl = document.createElement('span');
      textEl.className = 'paxdesign-booking-chat-message-text';
      textEl.innerHTML = wrapUrlsForScanHtml(original);
      if (badge) {
        bubble.insertBefore(textEl, badge);
      } else {
        bubble.insertBefore(textEl, bubble.firstChild);
      }
    }
    if (status === 'checking') {
      if (!urlScanAnimOriginals[String(msgId)]) {
        urlScanAnimOriginals[String(msgId)] = original;
      }
      if (!textEl.querySelector('.paxdesign-booking-chat-url-scan')) {
        textEl.innerHTML = wrapUrlsForScanHtml(original);
      }
      if (!urlScanAnimTimers[String(msgId)]) {
        startUrlScanAnimation(msgId, original);
      }
      return;
    }
    stopUrlScanAnimation(msgId, true);
    textEl.innerHTML = wrapUrlsForScanHtml(original);
  }

  function updateCustomerLinkScanMessage(serverMsg) {
    if (!serverMsg || !serverMsg.id) return;
    updateCustomerLinkScanContent(serverMsg.id, serverMsg);
    updateCustomerLinkScanBadge(serverMsg.id, serverMsg);
    refreshMessageScanState(serverMsg);
    scrollToBottom();
  }

  function messageRenderOpts(msg, extra) {
    extra = extra || {};
    msg = maskCustomerLinkScanMessage(msg);
    return Object.assign({
      reaction: msg.reaction || '',
      reply_to: msg.reply_to || 0,
      image_url: msg.image_url || '',
      file_url: msg.file_url || '',
      file_name: msg.file_name || '',
      attachment_type: msg.attachment_type || '',
      link_url: msg.link_url || '',
      link_label: msg.link_label || '',
      link_icon: msg.link_icon || '',
      link_scan_status: msg.link_scan_status || '',
      link_scan_label: msg.link_scan_label || '',
      link_scan_analysis: msg.link_scan_analysis || '',
      link_scan_original_content: msg.link_scan_original_content || '',
      sender_name: msg.sender_name || '',
      sender_avatar: msg.sender_avatar || '',
      sender_role: msg.sender_role || '',
      ts: msg.ts || 0,
    }, extra);
  }

  function applyIncomingMessages(incoming) {
    var played = false;
    incoming.forEach(function (msg) {
      if (!msg || !msg.id) return;
      msg = maskCustomerLinkScanMessage(msg);
      if (isMessagePermanentlyDeleted(msg.id)) return;
      if (msg.role === 'user' && reconcileSyncedUserMessage(msg)) return;
      if (isDuplicateMessage(msg)) return;
      if (msg.role === 'assistant' && isStreaming) return;
      if (msg.role === 'assistant' && streamingMsgId && msg.id === streamingMsgId) return;

      rememberMessageIdentity(msg);
      if (msg.reaction) messageReactions[msg.id] = msg.reaction;
      indexChatMessage(msg);

      if (msg.role === 'admin') {
        stopTypingSound();
        if (!morphAdminTypingToMessage(msg)) {
          removeAdminTypingIndicator();
          renderMessageDom(msg.role, msg.content, msg.id, messageRenderOpts(msg, {
            sender_name: msg.sender_name || '',
            sender_avatar: msg.sender_avatar || '',
            sender_role: msg.sender_role || '',
            skipScroll: true
          }));
        }
        if (!played) {
          playIncomingAdminSound();
          if (document.hidden) {
            tryCustomerBrowserNotification(
              msg.sender_name || getCustomerAgentLabel(),
              (msg.content || '').substring(0, 120)
            );
          }
          played = true;
        }
      } else {
        renderMessageDom(msg.role, messageText(msg.content), msg.id, messageRenderOpts(msg, { skipScroll: true }));
      }

      if (msg.role === 'user' || msg.role === 'assistant' || msg.role === 'admin') {
        messages.push({
          role: msg.role,
          content: messageText(msg.content),
          id: msg.id,
          client_msg_id: msg.client_msg_id || ''
        });
      } else if (msg.role === 'system') {
        messages.push({ role: 'system', content: messageText(msg.content), id: msg.id });
      }
    });
    if (played) syncChatLog();
    refreshAppliedSeq();
    if (stickToBottom) scrollToBottom();
  }

  function seenMsgId(id) {
    if (id > appliedMessageSeq) {
      appliedMessageSeq = id;
    }
    pollSeq = Math.max(pollSeq, appliedMessageSeq);
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
      var pending = messages.find(function (message) {
        return String(message.id) === String(msgId);
      });
      var clientMsgId = pending && pending.client_msg_id
        ? pending.client_msg_id
        : newClientMessageId();
      if (pending) pending.client_msg_id = clientMsgId;
      isStreaming = true;
      updateSendButton();
      sendHumanModeMessage(text, clientMsgId)
        .then(function (serverMessage) {
          if (pending && serverMessage && serverMessage.id) pending.id = serverMessage.id;
        })
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
      .then(function (res) { return safeJson(res); })
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
    scrollToBottom();
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

  function renderAdminMessageHeader(msgEl, opts) {
    opts = opts || {};
    var agent = opts.sender_name
      ? { name: opts.sender_name, avatar: opts.sender_avatar || '', role: opts.sender_role || '' }
      : getAssignedAgent();
    var head = document.createElement('div');
    head.className = 'paxdesign-booking-chat-agent-head paxdesign-booking-chat-agent-head--live';
    if (agent.avatar) {
      var img = document.createElement('img');
      img.className = 'paxdesign-booking-chat-agent-avatar paxdesign-booking-chat-agent-avatar--clickable';
      img.src = agent.avatar;
      img.alt = agent.name || getCustomerAgentLabel();
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
    } else if (agent.name) {
      var initials = document.createElement('span');
      initials.className = 'paxdesign-booking-chat-agent-avatar paxdesign-booking-chat-agent-avatar--initials';
      initials.textContent = initialsFromName(agent.name);
      head.appendChild(initials);
    }
    var nameWrap = document.createElement('span');
    nameWrap.className = 'paxdesign-booking-chat-agent-ident';
    var name = document.createElement('span');
    name.className = 'paxdesign-booking-chat-agent-name';
    name.textContent = agent.name || getCustomerAgentLabel();
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
    time.textContent = formatMsgTime(opts.ts || Math.floor(Date.now() / 1000));
    head.appendChild(time);
    msgEl.insertBefore(head, msgEl.firstChild);
  }

  function initialsFromName(name) {
    name = String(name || '').trim();
    if (!name) return '?';
    var parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    return name.slice(0, 2).toUpperCase();
  }

  function renderParticipantHeader(msgEl, agent, variant) {
    agent = agent || {};
    var name = agent.name || agent.sender_name || '';
    var role = agent.role || agent.sender_role || '';
    var avatar = agent.avatar || agent.sender_avatar || '';
    if (!name) return;
    var head = document.createElement('div');
    head.className = 'paxdesign-booking-chat-agent-head paxdesign-booking-chat-agent-head--' + (variant || 'assistant');
    if (avatar) {
      var img = document.createElement('img');
      img.className = 'paxdesign-booking-chat-agent-avatar';
      img.src = avatar;
      img.alt = name;
      img.width = 24;
      img.height = 24;
      img.loading = 'lazy';
      img.decoding = 'async';
      head.appendChild(img);
    } else {
      var initials = document.createElement('span');
      initials.className = 'paxdesign-booking-chat-agent-avatar paxdesign-booking-chat-agent-avatar--initials';
      initials.textContent = initialsFromName(name);
      head.appendChild(initials);
    }
    var nameWrap = document.createElement('span');
    nameWrap.className = 'paxdesign-booking-chat-agent-ident';
    var nameEl = document.createElement('span');
    nameEl.className = 'paxdesign-booking-chat-agent-name';
    nameEl.textContent = name;
    nameWrap.appendChild(nameEl);
    if (role) {
      var roleEl = document.createElement('span');
      roleEl.className = 'paxdesign-booking-chat-agent-role';
      roleEl.textContent = role;
      nameWrap.appendChild(roleEl);
    }
    head.appendChild(nameWrap);
    msgEl.insertBefore(head, msgEl.firstChild);
  }

  function buildLinkCardHtml(opts) {
    if (!opts || !opts.link_url) return '';
    var rawLabel = opts.link_label || opts.content || 'Link';
    var label = String(rawLabel).trim();
    if (label.toLowerCase().indexOf('view ') !== 0) {
      label = 'View ' + label;
    }
    var iconSvg = linkCardIconSvg(opts.link_icon || '', label);
    return '<a class="paxdesign-booking-chat-link-card paxdesign-booking-chat-link-card--compact" href="' + escapeHtml(opts.link_url) + '" target="_blank" rel="noopener noreferrer">' +
      '<span class="paxdesign-booking-chat-link-card__icon-svg" aria-hidden="true">' + iconSvg + '</span>' +
      '<span class="paxdesign-booking-chat-link-card__label">' + escapeHtml(label) + '</span>' +
      '<span class="paxdesign-booking-chat-link-card__arrow" aria-hidden="true">' +
        '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12L12 4"/><path d="M6 4h6v6"/></svg>' +
      '</span>' +
      '</a>';
  }

  function linkCardIconSvg(icon, label) {
    var key = String(icon || '').trim();
    if (key.indexOf('svg:') === 0) key = key.slice(4);
    else if (key.indexOf('sf:') === 0) key = 'link';
    else if (/^[a-z0-9_-]+$/i.test(key) && key.length <= 24) { /* keep */ }
    else {
      var lower = String(label || '').toLowerCase();
      if (lower.indexOf('service') !== -1) key = 'services';
      else if (lower.indexOf('project') !== -1) key = 'projects';
      else if (lower.indexOf('pric') !== -1) key = 'pricing';
      else if (lower.indexOf('contact') !== -1) key = 'contact';
      else if (lower.indexOf('about') !== -1) key = 'about';
      else if (lower.indexOf('faq') !== -1) key = 'faq';
      else if (lower.indexOf('portfolio') !== -1) key = 'portfolio';
      else key = 'link';
    }
    var paths = {
      link: '<path d="M8.5 3.5a4 4 0 0 1 5.7 5.7l-2.1 2.1a4 4 0 0 1-5.7-5.7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M7.5 12.5a4 4 0 0 1-5.7-5.7l2.1-2.1a4 4 0 0 1 5.7 5.7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      services: '<path d="M3 7h10M3 11h7M3 3h12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      projects: '<path d="M4 14l4-9 4 9H4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M6.5 10h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      pricing: '<circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 5.5v5M6.2 8h3.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      contact: '<path d="M3.5 4.5h9v7h-9z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 6.5l4 2.5 4-2.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
      about: '<circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 7.2v3.3M8 5.4h.01" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      faq: '<circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6.2 6.4a2 2 0 0 1 3.4 1.4c0 1.2-1.6 1.2-1.6 2.4M8 12.2h.01" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
      portfolio: '<rect x="3.5" y="4.5" width="9" height="7" rx="1.2" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="6.2" cy="7.2" r="1" fill="currentColor"/><path d="M5 11l2.2-2 1.8 1.5L11 8.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>'
    };
    return '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true">' + (paths[key] || paths.link) + '</svg>';
  }

  function isFinalScanStatus(status) {
    return status === 'safe' || status === 'suspicious' || status === 'dangerous'
      || status === 'failed' || status === 'timeout' || status === 'incomplete';
  }

  function refreshMessageScanState(msg) {
    if (!msg || !msg.id) return;
    var msgEl = threadEl.querySelector('[data-msg-id="' + msg.id + '"]');
    if (!msgEl) return;
    if (msg.link_scan_status === 'dangerous') {
      msgEl.classList.add('paxdesign-booking-chat-message--dangerous-link');
      var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
      if (bubble) {
        bubble.querySelectorAll('a').forEach(function (anchor) {
          anchor.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showDangerousLinkWarning();
          });
        });
      }
    }
  }

  function showDangerousLinkWarning() {
    var isEn = document.documentElement.lang && document.documentElement.lang.indexOf('en') === 0;
    showError(isEn
      ? 'This link was flagged as potentially dangerous and cannot be opened.'
      : 'Dieser Link wurde als potenziell gefährlich eingestuft und kann nicht geöffnet werden.');
  }

  function isMessagePermanentlyDeleted(messageId) {
    return !!deletedMessageIds[String(messageId)];
  }

  function markMessagePermanentlyDeleted(messageId) {
    deletedMessageIds[String(messageId)] = true;
  }

  function transformCustomerMessageInPlace(messageId, warningText, opts) {
    opts = opts || {};
    var id = String(messageId);
    if (deletingInProgress[id] || isMessagePermanentlyDeleted(messageId)) return;
    deletingInProgress[id] = true;
    markMessagePermanentlyDeleted(messageId);

    var msgEl = threadEl.querySelector('[data-msg-id="' + messageId + '"]');
    messages = messages.map(function (m) {
      if (String(m.id) !== id) return m;
      return Object.assign({}, m, {
        role: 'system',
        content: warningText,
        image_url: '',
        link_scan_status: '',
        _inPlaceWarning: true
      });
    });

    if (!msgEl) {
      saveSessionSnapshot();
      deletingInProgress[id] = false;
      return;
    }

    msgEl.classList.add('paxdesign-booking-chat-message--transforming');
    window.requestAnimationFrame(function () {
      window.setTimeout(function () {
        msgEl.classList.remove(
          'paxdesign-booking-chat-message--transforming',
          'paxdesign-booking-chat-message--deleting',
          'paxdesign-booking-chat-message--dangerous-link'
        );
        msgEl.classList.add('paxdesign-booking-chat-message--in-place-warning');
        if (opts.warn) {
          msgEl.classList.add('paxdesign-booking-chat-message--warned');
        }

        var quote = msgEl.querySelector('.paxdesign-booking-chat-quote');
        if (quote) quote.remove();
        var meta = msgEl.querySelector('.paxdesign-booking-chat-message-meta');
        if (meta) meta.remove();
        var footer = msgEl.querySelector('.paxdesign-booking-chat-message-footer');
        if (footer) footer.remove();
        var adminHead = msgEl.querySelector('.paxdesign-booking-chat-admin-head');
        if (adminHead) adminHead.remove();

        var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
        if (bubble) {
          bubble.innerHTML = '';
          bubble.textContent = warningText;
        }

        saveSessionSnapshot();
        deletingInProgress[id] = false;
      }, MESSAGE_TRANSFORM_MS);
    });
  }

  function animateCustomerMessageDeletion(messageId, tombstone) {
    transformCustomerMessageInPlace(messageId, tombstone, {});
  }

  function linkScanIconSvg(status) {
    if (status === 'safe') {
      return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 1.5l5.5 2.2v3.8c0 3.4-2.3 6.5-5.5 7.2-3.2-.7-5.5-3.8-5.5-7.2V3.7L8 1.5z" fill="currentColor" opacity="0.2"/><path d="M5.5 8.2l1.6 1.6 3.4-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if (status === 'suspicious') {
      return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 1.5l5.5 2.2v3.8c0 3.4-2.3 6.5-5.5 7.2-3.2-.7-5.5-3.8-5.5-7.2V3.7L8 1.5z" fill="currentColor" opacity="0.2"/><path d="M8 5.2v3.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="8" cy="11" r="0.8" fill="currentColor"/></svg>';
    }
    if (status === 'dangerous') {
      return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 1.5l5.5 2.2v3.8c0 3.4-2.3 6.5-5.5 7.2-3.2-.7-5.5-3.8-5.5-7.2V3.7L8 1.5z" fill="currentColor" opacity="0.2"/><path d="M6 6.2l4 4M10 6.2l-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }
    if (status === 'failed' || status === 'timeout' || status === 'incomplete') {
      return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><circle cx="8" cy="8" r="5.5" fill="currentColor" opacity="0.15"/><path d="M8 5.4v3.2M8 10.8h.01" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }
    return '<svg class="paxdesign-booking-chat-link-scan__icon-spin" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M8 1.5l5.5 2.2v3.8c0 3.4-2.3 6.5-5.5 7.2-3.2-.7-5.5-3.8-5.5-7.2V3.7L8 1.5z" fill="currentColor" opacity="0.2"/><path d="M8 4.5v2.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
  }

  function buildCustomerLinkScanHtml(opts) {
    if (!opts) return '';
    var status = opts.link_scan_status || '';
    var urls = extractUrlsFromText(opts.content || '');
    if (!status && !urls.length) return '';
    if (!status) status = 'checking';
    var label = customerLinkScanLabel(status, opts.link_scan_label || '');
    var html = '<span class="paxdesign-booking-chat-link-scan paxdesign-booking-chat-link-scan--' + escapeHtml(status) + '" data-scan-status="' + escapeHtml(status) + '" role="status">' +
      '<span class="paxdesign-booking-chat-link-scan__icon">' + linkScanIconSvg(status) + '</span>' +
      '<span class="paxdesign-booking-chat-link-scan__label">' + escapeHtml(label) + '</span>' +
      '</span>';
    if (opts.link_scan_analysis && isFinalScanStatus(status)) {
      html += '<span class="paxdesign-booking-chat-link-scan__analysis">' + escapeHtml(opts.link_scan_analysis) + '</span>';
    }
    return html;
  }

  function updateCustomerLinkScanBadge(msgId, serverMsg) {
    if (!msgId || isMessagePermanentlyDeleted(msgId)) return;
    var msgEl = threadEl.querySelector('[data-msg-id="' + msgId + '"]');
    if (!msgEl) return;
    var bubble = msgEl.querySelector('.paxdesign-booking-chat-message-bubble');
    if (!bubble) return;
    var status = serverMsg && serverMsg.link_scan_status ? serverMsg.link_scan_status : '';
    var content = messageOriginalContent(serverMsg) || (serverMsg && serverMsg.content ? serverMsg.content : '');
    var urls = extractUrlsFromText(content);
    if (!status && urls.length) status = 'checking';
    if (!status) return;
    msgEl.classList.remove('paxdesign-booking-chat-message--dangerous-link');
    if (status === 'dangerous') {
      msgEl.classList.add('paxdesign-booking-chat-message--dangerous-link');
    }
    var existing = bubble.querySelector('.paxdesign-booking-chat-link-scan');
    bubble.querySelectorAll('.paxdesign-booking-chat-link-scan__analysis').forEach(function (el) {
      el.remove();
    });
    var html = buildCustomerLinkScanHtml({
      link_scan_status: status,
      content: content,
      link_scan_label: serverMsg && serverMsg.link_scan_label ? serverMsg.link_scan_label : '',
      link_scan_analysis: serverMsg && serverMsg.link_scan_analysis ? serverMsg.link_scan_analysis : ''
    });
    if (existing) {
      existing.outerHTML = html;
    } else if (html) {
      bubble.insertAdjacentHTML('beforeend', html);
    }
    refreshMessageScanState({ id: msgId, link_scan_status: status, content: content });
  }

  function buildBubbleInnerHtml(role, content, opts) {
    opts = opts || {};
    content = messageText(content);
    var html = '';
    if (opts.image_url) {
      html += '<div class="paxdesign-booking-chat-message-image"><img src="' + escapeHtml(opts.image_url) + '" alt="Foto" loading="lazy" decoding="async"></div>';
    }
    if (opts.file_url || (opts.attachment_type === 'file' && opts.file_name)) {
      var fileHref = opts.file_url && opts.file_url !== '#' ? opts.file_url : '';
      var fileName = opts.file_name || content || 'Datei';
      html += '<a class="paxdesign-booking-chat-file-chip"' + (fileHref ? ' href="' + escapeHtml(fileHref) + '" target="_blank" rel="noopener"' : '') + '>' +
        '<span class="paxdesign-booking-chat-file-chip__icon" aria-hidden="true"></span>' +
        '<span class="paxdesign-booking-chat-file-chip__name">' + escapeHtml(fileName) + '</span>' +
      '</a>';
    }
    if (opts.attachment_type === 'link_card' || opts.link_url) {
      html += buildLinkCardHtml(opts);
    } else if (role === 'assistant' || role === 'admin') {
      if (content) html += formatMarkdown(content);
    } else if (role !== 'system' && content && opts.attachment_type !== 'file') {
      html += '<span class="paxdesign-booking-chat-message-text">' + wrapUrlsForScanHtml(content) + '</span>';
    }
    if (role === 'user') {
      html += buildCustomerLinkScanHtml(Object.assign({}, opts, { content: content }));
    }
    return html;
  }

  function renderMessageDom(role, content, msgId, opts) {
    opts = opts || {};
    if (role === 'user' && opts.link_scan_original_content) {
      content = messageText(opts.link_scan_original_content);
    } else {
      content = messageText(content);
    }
    root.classList.add('paxdesign-has-chat-messages');
    var msg = document.createElement('div');
    msg.className = 'paxdesign-booking-chat-message paxdesign-booking-chat-message--' + role;
    if (msgId) msg.setAttribute('data-msg-id', String(msgId));

    if (role === 'admin') {
      renderAdminMessageHeader(msg, opts);
    } else if (role === 'assistant') {
      var aiAgent = (config && config.aiAssistant) ? config.aiAssistant : getLiveAgent();
      renderParticipantHeader(msg, {
        name: opts.sender_name || aiAgent.name,
        avatar: opts.sender_avatar || aiAgent.avatar,
        role: opts.sender_role || aiAgent.role || 'AI Assistant'
      }, 'assistant');
    } else if (role === 'user') {
      var auth = (config && config.auth) ? config.auth : {};
      renderParticipantHeader(msg, {
        name: opts.sender_name || auth.display_name || customerName || 'You',
        avatar: opts.sender_avatar || '',
        role: opts.sender_role || 'Customer'
      }, 'customer');
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
    if (String(content || '').trim() || opts.image_url || opts.file_url) {
      attachMessageChrome(msg, bubble, role, content, msgId, opts.reaction || '');
    }
    if (msgId) {
      indexChatMessage({
        id: msgId,
        role: role,
        content: content,
        reply_to: opts.reply_to || 0,
        image_url: opts.image_url || '',
        file_url: opts.file_url || '',
        file_name: opts.file_name || '',
        attachment_type: opts.attachment_type || ''
      });
    }
    if (opts.prepend && threadEl.firstChild) {
      threadEl.insertBefore(msg, threadEl.firstChild);
    } else {
      threadEl.appendChild(msg);
    }
    if (!opts.skipScroll) {
      scrollToBottom();
    }
    if (role === 'user' && opts.link_scan_status) {
      refreshMessageScanState({ id: msgId, link_scan_status: opts.link_scan_status, content: content });
      if (opts.link_scan_status === 'checking') {
        startUrlScanAnimation(msgId, content);
      }
    }
    if (!opts.skipPush && msgId) saveSessionSnapshot();
    return { bubble: bubble, messageEl: msg };
  }

  function attachUiLang() {
    var lang = ((navigator.language || navigator.userLanguage || '') + '').toLowerCase();
    if (lang.indexOf('ar') === 0) return 'ar';
    if (lang.indexOf('en') === 0) return 'en';
    return 'de';
  }

  function attachMenuLabel(key) {
    var lang = attachUiLang();
    if (key === 'liveChat') {
      if (lang === 'ar') return 'دردشة مباشرة';
      if (lang === 'en') return 'Live Chat';
      return 'Live Chat';
    }
    if (key === 'bookAppointment') {
      if (lang === 'ar') return 'حجز موعد';
      if (lang === 'en') return 'Book appointment';
      return 'Termin buchen';
    }
    if (key === 'image') {
      if (lang === 'ar') return 'إرفاق صورة';
      if (lang === 'en') return 'Attach image';
      return 'Bild anhängen';
    }
    if (key === 'file') {
      if (lang === 'ar') return 'إرفاق ملف';
      if (lang === 'en') return 'Attach file';
      return 'Datei anhängen';
    }
    if (key === 'attach') {
      if (lang === 'ar') return 'إرفاق';
      if (lang === 'en') return 'Attach';
      return 'Anhängen';
    }
    if (key === 'tooLargeImage') {
      if (lang === 'ar') return 'الصورة أكبر من 5 ميغابايت.';
      if (lang === 'en') return 'Images must be 5 MB or smaller.';
      return 'Bilder dürfen höchstens 5 MB groß sein.';
    }
    if (key === 'tooLargeFile') {
      if (lang === 'ar') return 'الملف أكبر من 8 ميغابايت.';
      if (lang === 'en') return 'Files must be 8 MB or smaller.';
      return 'Dateien dürfen höchstens 8 MB groß sein.';
    }
    if (key === 'badType') {
      if (lang === 'ar') return 'نوع الملف غير مسموح.';
      if (lang === 'en') return 'This file type is not allowed.';
      return 'Dieser Dateityp ist nicht erlaubt.';
    }
    if (lang === 'ar') return 'المرفقات متاحة أثناء الدعم البشري.';
    if (lang === 'en') return 'Attachments are available during human support.';
    return 'Anhänge sind während des Live-Chats verfügbar.';
  }

  function updateHumanStaffStatus() {
    var inputArea = root.querySelector('.paxdesign-booking-chat-input-area');
    var statusEl = root.querySelector('#paxdesignChatSupportStatus');
    if (!statusEl && inputArea && form) {
      statusEl = document.createElement('p');
      statusEl.id = 'paxdesignChatSupportStatus';
      statusEl.className = 'paxdesign-booking-chat-support-status';
      statusEl.hidden = true;
      inputArea.insertBefore(statusEl, form);
    }
    if (!statusEl) return;
    if (chatHandler === 'admin') {
      var name = (assignedAgent && assignedAgent.name) || adminName || '';
      var lang = attachUiLang();
      if (lang === 'ar') statusEl.textContent = name ? ('تم اتصال الموظف: ' + name) : 'موظف حقيقي متصل';
      else if (lang === 'en') statusEl.textContent = name ? ('Staff connected: ' + name) : 'A team member has joined';
      else statusEl.textContent = name ? ('Mitarbeiter verbunden: ' + name) : 'Mitarbeiter verbunden';
      statusEl.hidden = false;
    } else {
      statusEl.textContent = '';
      statusEl.hidden = true;
    }
  }

  function triggerHiddenFileInput(inputEl) {
    if (!inputEl) return;
    try {
      if (typeof inputEl.showPicker === 'function') {
        inputEl.showPicker();
        return;
      }
    } catch (pickerErr) {}
    inputEl.click();
  }

  function ensureHumanAttachInputs() {
    if (document.getElementById('paxdesignChatHumanImageAttach')) return;
    var imageInput = document.createElement('input');
    imageInput.type = 'file';
    imageInput.id = 'paxdesignChatHumanImageAttach';
    imageInput.className = 'paxdesign-chat-human-attach-input';
    imageInput.accept = 'image/jpeg,image/png,image/webp,image/gif,image/*';
    imageInput.setAttribute('aria-hidden', 'true');
    imageInput.tabIndex = -1;
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.id = 'paxdesignChatHumanFileAttach';
    fileInput.className = 'paxdesign-chat-human-attach-input';
    fileInput.accept = '.pdf,.doc,.docx,.txt,.zip,.jpg,.jpeg,.png,.webp,.gif';
    fileInput.setAttribute('aria-hidden', 'true');
    fileInput.tabIndex = -1;
    document.body.appendChild(imageInput);
    document.body.appendChild(fileInput);
    imageInput.addEventListener('change', function () {
      var file = imageInput.files && imageInput.files[0];
      imageInput.value = '';
      if (file) uploadHumanAttachFile(file, 'image');
      maybeRestoreComposerFocus();
    });
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      fileInput.value = '';
      if (file) uploadHumanAttachFile(file, 'file');
      maybeRestoreComposerFocus();
    });
  }

  function ensureComposerAttachMenu() {
    if (root.querySelector('.paxdesign-booking-chat-attach-menu')) return;
    var menu = document.createElement('div');
    menu.className = 'paxdesign-booking-chat-attach-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'menu');
    menu.innerHTML =
      '<button type="button" class="paxdesign-booking-chat-attach-item" data-widget-mode="chat" role="menuitem">' +
        '<span class="paxdesign-booking-chat-attach-item-label">' + escapeHtml(attachMenuLabel('liveChat')) + '</span>' +
      '</button>' +
      '<button type="button" class="paxdesign-booking-chat-attach-item" data-widget-mode="booking" role="menuitem">' +
        '<span class="paxdesign-booking-chat-attach-item-label">' + escapeHtml(attachMenuLabel('bookAppointment')) + '</span>' +
      '</button>' +
      '<button type="button" class="paxdesign-booking-chat-attach-item paxdesign-booking-chat-attach-item--file" data-attach-kind="image" role="menuitem" hidden>' +
        '<span class="paxdesign-booking-chat-attach-item-label">' + escapeHtml(attachMenuLabel('image')) + '</span>' +
      '</button>' +
      '<button type="button" class="paxdesign-booking-chat-attach-item paxdesign-booking-chat-attach-item--file" data-attach-kind="file" role="menuitem" hidden>' +
        '<span class="paxdesign-booking-chat-attach-item-label">' + escapeHtml(attachMenuLabel('file')) + '</span>' +
      '</button>';
    menu.addEventListener('pointerdown', function (e) {
      var modeBtn = e.target.closest('[data-widget-mode]');
      if (modeBtn) {
        e.preventDefault();
        e.stopPropagation();
        var mode = modeBtn.getAttribute('data-widget-mode') || 'chat';
        closeComposerAttachMenu();
        if (window.PAXdesignBooking && typeof window.PAXdesignBooking.switchMode === 'function') {
          window.PAXdesignBooking.switchMode(mode);
        }
        if (mode === 'chat' && !canUseChat()) {
          showAuthGate();
        }
        notifyLayout();
        return;
      }
      var btn = e.target.closest('[data-attach-kind]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      var kind = btn.getAttribute('data-attach-kind') || 'file';
      closeComposerAttachMenu();
      if (!isHumanMode()) {
        showError(attachMenuLabel('humanOnly'));
        return;
      }
      if (!canUseChat()) {
        showAuthGate();
        return;
      }
      var inputEl = document.getElementById(kind === 'image' ? 'paxdesignChatHumanImageAttach' : 'paxdesignChatHumanFileAttach');
      window.setTimeout(function () {
        triggerHiddenFileInput(inputEl);
      }, 0);
    });
    var host = root.querySelector('.paxdesign-booking-frame-inner') || root.querySelector('.paxdesign-booking-chat-input-area') || root;
    host.appendChild(menu);
  }

  function syncPlusButtons(open) {
    var nodes = root.querySelectorAll('.paxdesign-booking-chat-plus');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].setAttribute('aria-expanded', open ? 'true' : 'false');
      nodes[i].classList.toggle('paxdesign-is-active', !!open);
    }
  }

  function toggleComposerAttachMenu(forceOpen) {
    var menu = root.querySelector('.paxdesign-booking-chat-attach-menu');
    if (!menu) return;
    var shouldOpen = forceOpen === true ? true : (forceOpen === false ? false : menu.hidden);
    menu.hidden = !shouldOpen;
    root.classList.toggle('paxdesign-chat-attach-open', shouldOpen);
    syncPlusButtons(shouldOpen);
    if (shouldOpen) maybeRestoreComposerFocus();
    if (shouldOpen && quickActions) {
      root.classList.remove('paxdesign-chat-quick-open');
      quickActions.classList.remove('paxdesign-is-open');
    }
  }

  function closeComposerAttachMenu() {
    var menu = root.querySelector('.paxdesign-booking-chat-attach-menu');
    if (!menu) return;
    menu.hidden = true;
    root.classList.remove('paxdesign-chat-attach-open');
    syncPlusButtons(false);
    maybeRestoreComposerFocus();
  }

  function updateComposerPlusUi() {
    var plusWraps = root.querySelectorAll('.paxdesign-booking-chat-plus-wrap, .paxdesign-booking-mode-plus-bar');
    for (var w = 0; w < plusWraps.length; w++) plusWraps[w].hidden = false;
    var humanConnected = chatHandler === 'admin';
    var humanQueue = isHumanMode();
    var nodes = root.querySelectorAll('.paxdesign-booking-chat-plus');
    var label = 'Menü';
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].classList.toggle('paxdesign-booking-chat-plus--human', humanQueue);
      nodes[i].classList.toggle('paxdesign-booking-chat-plus--connected', humanConnected);
      nodes[i].setAttribute('aria-label', label);
      var tip = nodes[i].querySelector('.paxdesign-booking-chat-plus-tooltip');
      if (tip) tip.textContent = label;
    }
    var menu = root.querySelector('.paxdesign-booking-chat-attach-menu');
    if (!menu) return;
    var attachItems = menu.querySelectorAll('[data-attach-kind]');
    for (var a = 0; a < attachItems.length; a++) {
      attachItems[a].hidden = !humanQueue;
    }
    var extras = menu.querySelectorAll('[data-quick-message]');
    for (var x = 0; x < extras.length; x++) {
      if (extras[x].parentNode) extras[x].parentNode.removeChild(extras[x]);
    }
    if (!humanQueue && config.quickActions && config.quickActions.length) {
      config.quickActions.forEach(function (action) {
        var extra = document.createElement('button');
        extra.type = 'button';
        extra.className = 'paxdesign-booking-chat-attach-item';
        extra.setAttribute('role', 'menuitem');
        extra.setAttribute('data-quick-message', action.message || '');
        if (action.intent) extra.setAttribute('data-quick-intent', action.intent);
        extra.innerHTML = '<span class="paxdesign-booking-chat-attach-item-label">' + escapeHtml(action.label || '') + '</span>';
        extra.addEventListener('pointerdown', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          closeComposerAttachMenu();
          if (!canUseChat()) {
            showAuthGate();
            return;
          }
          sendMessage(extra.getAttribute('data-quick-message') || '', {
            intent: extra.getAttribute('data-quick-intent') || ''
          });
        });
        menu.appendChild(extra);
      });
    }
  }

  function validateAttachFile(file, kind) {
    var name = ((file && file.name) || '').toLowerCase();
    var ext = name.split('.').pop() || '';
    var imageExts = { jpg: 1, jpeg: 1, png: 1, webp: 1, gif: 1 };
    var fileExts = { pdf: 1, doc: 1, docx: 1, txt: 1, zip: 1, jpg: 1, jpeg: 1, png: 1, webp: 1, gif: 1 };
    var isImage = kind === 'image' || (file && /^image\//.test(file.type));
    if (isImage) {
      if (!imageExts[ext] && !(file.type && /^image\/(jpeg|png|webp|gif)$/.test(file.type))) {
        return attachMenuLabel('badType');
      }
      if (file.size > 5 * 1024 * 1024) return attachMenuLabel('tooLargeImage');
    } else {
      if (!fileExts[ext]) return attachMenuLabel('badType');
      if (file.size > 8 * 1024 * 1024) return attachMenuLabel('tooLargeFile');
    }
    return '';
  }

  function uploadHumanAttachFile(file, kind) {
    if (!file || isStreaming) return;
    if (!isHumanMode()) {
      showError(attachMenuLabel('humanOnly'));
      return;
    }
    if (!canUseChat()) {
      showAuthGate();
      return;
    }
    var validationError = validateAttachFile(file, kind);
    if (validationError) {
      showError(validationError);
      return;
    }

    var isImage = kind === 'image' || /^image\//.test(file.type);
    var localUrl = '';
    try {
      if (isImage) localUrl = URL.createObjectURL(file);
    } catch (e) {}
    var clientMsgId = newClientMessageId();
    var localId = nextLocalId();
    var caption = file.name || (isImage ? 'image' : 'file');
    renderMessageDom('user', isImage ? '' : caption, localId, {
      image_url: localUrl,
      file_url: isImage ? '' : '#',
      file_name: caption,
      attachment_type: isImage ? 'image' : 'file',
      client_msg_id: clientMsgId,
      ts: Math.floor(Date.now() / 1000)
    });
    messages.push({
      role: 'user',
      content: caption,
      id: localId,
      client_msg_id: clientMsgId,
      image_url: localUrl,
      file_name: caption,
      attachment_type: isImage ? 'image' : 'file'
    });
    rememberMessageIdentity({ id: localId, role: 'user', content: caption, client_msg_id: clientMsgId });
    scrollToBottom();

    if (!config.ajaxUrl) {
      markHumanAttachFailed(localId, attachMenuLabel('humanOnly'));
      return;
    }

    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_user_attach');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('client_msg_id', clientMsgId);
    formData.append('kind', isImage ? 'image' : 'file');
    formData.append('file', file, file.name);

    fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return safeJson(res); })
      .then(function (json) {
        if (handleAuthGateResponse(json)) {
          throw new Error('login_required');
        }
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : 'Upload failed.');
        }
        var data = json.data || {};
        if (data.message && data.message.id) {
          var localEl = threadEl.querySelector('[data-msg-id="' + localId + '"]');
          if (localEl) localEl.setAttribute('data-msg-id', String(data.message.id));
          rememberMessageIdentity(data.message);
          indexChatMessage(data.message);
          pollSeq = Math.max(pollSeq, data.message.id);
        }
        pollUpdatesOnce(false);
        saveSessionSnapshot();
      })
      .catch(function (err) {
        markHumanAttachFailed(localId, err && err.message ? err.message : 'Upload failed.');
      });
  }

  function markHumanAttachFailed(localId, message) {
    var failed = threadEl.querySelector('[data-msg-id="' + localId + '"]');
    if (!failed) return;
    var bubble = failed.querySelector('.paxdesign-booking-chat-message-bubble');
    if (bubble) {
      bubble.insertAdjacentHTML('beforeend', '<span class="paxdesign-ccs-attach-error">' + escapeHtml(message) + '</span>');
    }
  }

  function initPlusToggle() {
    if (root.dataset.composerPlusBound === '1') return;
    root.dataset.composerPlusBound = '1';
    var plusWrap = root.querySelector('.paxdesign-booking-chat-plus-wrap');
    if (plusWrap) plusWrap.hidden = false;
    ensureHumanAttachInputs();
    ensureComposerAttachMenu();

    root.addEventListener('click', function (e) {
      var btn = e.target.closest('.paxdesign-booking-chat-plus');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      e.stopPropagation();
      unlockAudio();
      toggleComposerAttachMenu();
    });

    document.addEventListener('pointerdown', function (e) {
      if (!root.classList.contains('paxdesign-chat-attach-open')) return;
      var menu = root.querySelector('.paxdesign-booking-chat-attach-menu');
      if (!menu || menu.hidden) return;
      if (menu.contains(e.target) || (e.target.closest && e.target.closest('.paxdesign-booking-chat-plus'))) return;
      closeComposerAttachMenu();
    }, true);

    updateComposerPlusUi();
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
    if (!quickActions) return;
    quickActions.innerHTML = '';
    if (!config.quickActions || !config.quickActions.length) {
      quickActions.hidden = true;
      return;
    }
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
    var composerRow = root.querySelector('.paxdesign-booking-chat-composer-row');
    if (composer) composer.classList.toggle('paxdesign-has-text', hasText);
    if (composerRow) composerRow.classList.toggle('paxdesign-has-text', hasText);
  }

  function autoResizeInput() {
    refreshVoiceInputMaxHeight();
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, voiceInputMaxHeight) + 'px';
  }

  function scrollToBottom(force) {
    if (!messagesEl) return;
    if (!force && !stickToBottom) return;
    stickToBottom = true;
    pinToLatestMessage();
    if (force) revealPinnedThread();
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function messageText(value) {
    if (value == null) return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    if (typeof value === 'object') {
      if (typeof value.content === 'string') return value.content;
      if (typeof value.text === 'string') return value.text;
      if (typeof value.message === 'string') return value.message;
    }
    return '';
  }

  function apiErrorMessage(json, fallback) {
    fallback = fallback || 'Fehler bei der Anfrage.';
    if (!json || !json.data) return fallback;
    var data = json.data;
    if (typeof data.message === 'string') return data.message;
    if (typeof data.error === 'string') return data.error;
    return fallback;
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
    if (!config.ajaxUrl) return Promise.resolve();
    if (!canUseChat()) return Promise.resolve();
    var syncMessages = messages.filter(function (m) {
      return m.role === 'user' || m.role === 'assistant';
    });
    if (!syncMessages.length && !consultationLogged) return Promise.resolve();
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
      .then(function (res) { return safeJson(res); })
      .then(function (json) {
        if (handleAuthGateResponse(json)) return json;
        saveSessionSnapshot();
        return json;
      })
      .catch(function () { return null; });
  }

  function syncChatLogAsync(extra) {
    return Promise.resolve(syncChatLog(extra));
  }

  function logConsultationStarted() {
    if (consultationLogged || loadConsultationLogged(getSessionId())) {
      consultationLogged = true;
      return;
    }
    consultationLogged = true;
    try {
      localStorage.setItem(CONSULTATION_KEY + '-' + getSessionId(), '1');
    } catch (e) {}
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
    messages.push({ role: 'assistant', content: content, id: id, client_msg_id: newClientMessageId() });
    syncChatLog();
    playNotificationSound(false);
    return rendered;
  }

  function appendUserMessage(text, opts) {
    opts = opts || {};
    var userId = nextLocalId();
    var clientMsgId = opts.clientMsgId || newClientMessageId();
    domMsgIds[userId] = true;
    seenMsgId(userId);
    var urls = extractUrlsFromText(text);
    var renderOpts = {};
    if (urls.length) {
      renderOpts.link_scan_status = 'checking';
    }
    stickToBottom = true;
    renderMessageDom('user', text, userId, renderOpts);
    messages.push({ role: 'user', content: text, id: userId, client_msg_id: clientMsgId });
    rememberMessageIdentity({ id: userId, role: 'user', content: text, client_msg_id: clientMsgId });
    if (!opts.skipSync) {
      lastUserSyncPromise = syncChatLog();
    }
    return userId;
  }

  function finalizeAssistantMessage(fullText, meta) {
    meta = meta || {};
    fullText = messageText(fullText);
    var cleanText = stripBookingMarkers(fullText);
    var serviceName = parseBookingMarker(fullText);

    if (cleanText) {
      var id = meta.serverMessage && meta.serverMessage.id
        ? meta.serverMessage.id
        : (streamingMsgId || nextLocalId());
      if (streamingMsgId && id !== streamingMsgId) {
        delete domMsgIds[streamingMsgId];
        if (pendingMessageEl) pendingMessageEl.setAttribute('data-msg-id', String(id));
      }
      domMsgIds[id] = true;
      seenMsgId(id);
      if (pendingMessageEl) {
        pendingMessageEl.setAttribute('data-msg-id', String(id));
        attachMessageChrome(pendingMessageEl, pendingBubble, 'assistant', cleanText, id, '');
      }
      messages.push({
        role: 'assistant',
        content: cleanText,
        id: id,
        client_msg_id: meta.clientMsgId || newClientMessageId()
      });
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
    var message = messageText(text) || (typeof text === 'string' ? text : '') || 'Ein Fehler ist aufgetreten.';
    var el = document.createElement('div');
    el.className = 'paxdesign-booking-chat-error';
    el.textContent = message;
    threadEl.appendChild(el);
    scrollToBottom();
    setTimeout(function () { el.remove(); }, 5000);
  }

  function isLoginRequiredResponse(json) {
    if (!json || json.success) return false;
    var data = json.data || {};
    return data.code === 'login_required';
  }

  function showLoginRequiredNotice() {
    removeTyping();
    showAuthGate();
  }

  function handleAuthGateResponse(json) {
    if (!isLoginRequiredResponse(json)) return false;
    if (isLoggedIn() && isVerifiedAccount()) {
      hideAuthGate();
      return false;
    }
    showAuthGate();
    return true;
  }

  function handleAuthenticatedChatJsonSuccess(data) {
    removeTyping();
    isStreaming = false;
    updateSendButton();
    if (data && data.session_id) {
      adoptSessionId(data.session_id, { fromServer: true, preserveUi: false });
    }
    if (data && data.handler) {
      applyHandlerState(data.handler, data.admin_name || '');
    }
    if (data && data.assistant) {
      var assistantPayload = data.assistant;
      var assistantText = messageText(assistantPayload);
      var assistantId = assistantPayload.id || nextLocalId();
      if (assistantText && !isDuplicateMessage(assistantPayload)) {
        rememberMessageIdentity(assistantPayload);
        renderMessageDom('assistant', assistantText, assistantId, messageRenderOpts(assistantPayload, { skipPush: true }));
        messages.push({
          role: 'assistant',
          content: assistantText,
          id: assistantId,
          client_msg_id: assistantPayload.client_msg_id || newClientMessageId(),
        });
        playNotificationSound(false);
      }
    }
    syncChatLog();
    saveSessionSnapshot();
    maybeRestoreComposerFocus();
  }

  function sendHumanModeMessage(text, clientMsgId) {
    var formData = new FormData();
    formData.append('action', 'paxdesign_chat_live_user_send');
    formData.append('nonce', config.nonce);
    stampChatRequest(formData);
    formData.append('session_id', getSessionId());
    formData.append('message', text);
    formData.append('client_msg_id', clientMsgId);
    if (replyToId) formData.append('reply_to', String(replyToId));
    return fetch(config.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return safeJson(res); })
      .then(function (json) {
        if (handleAuthGateResponse(json)) {
          throw new Error('login_required');
        }
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : 'Nachricht konnte nicht gesendet werden.');
        }
        clearClientReply();
        if (json.data && json.data.message && json.data.message.id) {
          domMsgIds[json.data.message.id] = true;
          seenMsgId(json.data.message.id);
          indexChatMessage(json.data.message);
          pollSeq = Math.max(pollSeq, json.data.message.id);
        }
        pollUpdates();
        return json.data.message;
      });
  }

  function requestLiveAgent(topic, name) {
    name = (name || anonymousGuestName()).trim();
    if (name.length < 2) name = anonymousGuestName();
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
      .then(function (res) { return safeJson(res); })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error(json && json.data && json.data.message ? json.data.message : localizedReadiness('readinessLiveFailed', 'Could not confirm your live agent request.'));
        }
        if (json.data && Array.isArray(json.data.messages)) {
          json.data.messages.forEach(function (msg) {
            if (!msg || !msg.id || domMsgIds[msg.id]) return;
            domMsgIds[msg.id] = true;
            seenMsgId(msg.id);
            renderMessageDom(msg.role, msg.content, msg.id);
            messages.push({ role: msg.role, content: msg.content, id: msg.id });
          });
        }
        return pollUpdatesOnce(true).then(function (data) {
          if (!data || (data.handler !== 'live_request' && data.handler !== 'admin')) {
            return Promise.reject({ code: 'live', messageKey: 'readinessLiveFailed' });
          }
          applyHandlerState(data.handler, data.admin_name || '');
          liveAgentPhase = 2;
          saveLiveAgentPhase();
          return waitForCustomerStreamOpen(READINESS_STREAM_WAIT_MS, readinessGeneration);
        });
      })
      .then(function () {
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
      beginLiveAgentRequest(pendingLiveTopic);
      return true;
    }

    if (liveAgentPhase === 0 && isLiveAgentIntent(text)) {
      appendUserMessage(text);
      pendingLiveTopic = text || inferServiceFromConversation();
      liveAgentPhase = 1;
      saveLiveAgentPhase();
      beginLiveAgentRequest(pendingLiveTopic);
      return true;
    }

    if (liveAgentPhase === 1 && !liveNameConfirmed && chatHandler === 'ai') {
      pendingLiveTopic = text || pendingLiveTopic || inferServiceFromConversation();
      beginLiveAgentRequest(pendingLiveTopic);
      return true;
    }

    return false;
  }

  function sendMessage(text, opts) {
    opts = opts || {};
    if (!canUseChat()) {
      showAuthGate();
      return;
    }
    text = (text || input.value).trim();
    if (!text || isStreaming) return;
    if (chatHandler === 'closed') {
      showError('Dieser Chat wurde geschlossen.');
      return;
    }

    clearUserTypingState();
    unlockAudio();
    stopVoiceInput();
    var userBookingIntent = opts.intent === 'booking' || isUserBookingIntent(text);

    if (opts.intent === 'live') {
      setEntryChoice('live');
      text = text || 'Ich möchte mit einem Live-Agent sprechen.';
    }

    input.value = '';
    autoResizeInput();
    updateSendButton();
    composerWantsKeyboard = true;
    keepComposerFocus();

    if (handleLiveAgentFlow(text)) return;

    if (isHumanMode()) {
      var clientMsgId = newClientMessageId();
      var userMsgId = appendUserMessage(text, { skipSync: true, clientMsgId: clientMsgId });
      isStreaming = true;
      updateSendButton();
      sendHumanModeMessage(text, clientMsgId)
        .then(function (serverMessage) {
          clearMessageFailed(threadEl.querySelector('[data-msg-id="' + userMsgId + '"]'));
          if (serverMessage && serverMessage.id) {
            var local = messages.find(function (m) { return m.client_msg_id === clientMsgId; });
            if (local) local.id = serverMessage.id;
            var msgEl = threadEl.querySelector('[data-msg-id="' + userMsgId + '"]');
            if (msgEl) {
              msgEl.setAttribute('data-msg-id', String(serverMessage.id));
              delete domMsgIds[userMsgId];
              domMsgIds[serverMessage.id] = true;
            }
            updateCustomerLinkScanMessage(serverMessage);
          }
        })
        .catch(function (err) {
          markMessageFailed(userMsgId, text);
          showError(err.message || 'Verbindungsfehler.');
        })
        .finally(function () {
          isStreaming = false;
          updateSendButton();
          keepComposerFocus();
        });
      return;
    }

    var aiClientMsgId = newClientMessageId();
    appendUserMessage(text, { clientMsgId: aiClientMsgId });
    lastUserSyncPromise.then(function () {
      isStreaming = true;
      updateSendButton();
      keepComposerFocus();
      showTyping();
      var assistantClientMsgId = newClientMessageId();

      var formData = new FormData();
      formData.append('action', 'paxdesign_chat');
      formData.append('nonce', config.nonce);
      stampChatRequest(formData);
      formData.append('session_id', getSessionId());
      formData.append('client_msg_id', aiClientMsgId);
      formData.append('assistant_client_msg_id', assistantClientMsgId);
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
          return safeJson(response).then(function (data) {
            throw new Error(data.data && data.data.message ? data.data.message : 'Fehler bei der Anfrage.');
          }).catch(function (err) {
            if (err && err.message) throw err;
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
              if (json && json.success && json.data) {
                handleAuthenticatedChatJsonSuccess(json.data);
                return;
              }
              message = apiErrorMessage(json, message);
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
        var persistedAssistantMessage = null;

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
            } else if (data.type === 'done' && data.message) {
              persistedAssistantMessage = data.message;
              if (!gotFirstChunk && data.message) {
                var doneText = messageText(data.message);
                if (doneText) {
                  gotFirstChunk = true;
                  ensureAssistantBubble();
                  fullText = doneText;
                  pendingBubble = bubble;
                  pendingText = fullText;
                  flushStreamBubble();
                }
              }
            } else if (data.type === 'handoff') {
              if (data.handler) applyHandlerState(data.handler, '');
              if (data.assistant) {
                var handoffText = messageText(data.assistant);
                if (handoffText) {
                  gotFirstChunk = true;
                  ensureAssistantBubble();
                  fullText = handoffText;
                  pendingBubble = bubble;
                  pendingText = fullText;
                  flushStreamBubble();
                  persistedAssistantMessage = data.assistant;
                }
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
                  clientMsgId: assistantClientMsgId,
                  serverMessage: persistedAssistantMessage,
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
              maybeRestoreComposerFocus();
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
        maybeRestoreComposerFocus();
      });
    });
  }

  function handleSend(e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (sendInFlight || isStreaming) return;
    if (sendBtn && sendBtn.classList.contains('paxdesign-is-disabled')) return;
    if (!input || !input.value.trim()) return;
    sendInFlight = true;
    try {
      sendMessage();
    } finally {
      window.setTimeout(function () {
        sendInFlight = false;
      }, 350);
    }
  }

  if (replyClearBtn) {
    replyClearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      clearClientReply();
    });
  }

  form.addEventListener('submit', handleSend);
  if (sendBtn) {
    sendBtn.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      handleSend(e);
    });
  }
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
    composerWantsKeyboard = true;
    notifyLayout();
  });
  input.addEventListener('blur', function () {
    clearUserTypingState();
    notifyLayout();
  });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend(e);
    }
  });

  function openForCybercrime(opts) {
    opts = opts || {};
    window.PAXdesignPageContext = window.PAXdesignPageContext || {};
    window.PAXdesignPageContext.intent = 'cybercrime-support';
    if (opts.language) {
      window.PAXdesignPageContext.language = opts.language;
    }
    if (opts.referenceId) {
      window.PAXdesignPageContext.referenceId = opts.referenceId;
    }
    setEntryChoice('ai');
    if (window.PAXdesignBooking && typeof window.PAXdesignBooking.open === 'function') {
      window.PAXdesignBooking.open();
      return;
    }
    var launcher = document.querySelector('.paxdesign-booking-button');
    if (launcher) {
      launcher.click();
    }
  }

  window.PAXdesignChat = {
    init: init,
    onOpen: onWidgetOpen,
    onClose: onWidgetClose,
    abort: abortStream,
    pinToLatestMessage: pinToLatestMessage,
    sendMessage: sendMessage,
    canUseChat: canUseChat,
    beginReadiness: beginChatReadiness,
    openForCybercrime: openForCybercrime,
    ensureAuthGate: function () {
      initAuthGate();
      if (!canUseChat()) {
        showAuthGate();
        return false;
      }
      hideAuthGate();
      return true;
    },
  };

})();
