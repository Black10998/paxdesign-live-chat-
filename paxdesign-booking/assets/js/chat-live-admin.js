/**
 * PAXdesign Live Chat — Admin console + shortcode dashboard
 * Version: 3.45.0
 */
(function ($) {
  'use strict';

  var DEFAULT_QUICK_REPLIES = [
    { label: 'DE · Hallo', text: 'Hallo, wie kann ich Ihnen helfen?', lang: 'de' },
    { label: 'DE · Moment', text: 'Einen Moment bitte, ich prüfe das für Sie.', lang: 'de' },
    { label: 'DE · Details', text: 'Können Sie mir bitte mehr Details schicken?', lang: 'de' },
    { label: 'AR · مرحباً', text: 'مرحباً، كيف يمكنني مساعدتك؟', lang: 'ar' },
    { label: 'AR · لحظة', text: 'لحظة من فضلك، سأتحقق من ذلك لك.', lang: 'ar' },
    { label: 'AR · تفاصيل', text: 'هل يمكنك إرسال المزيد من التفاصيل؟', lang: 'ar' },
  ];

  $(function () {
    var cfg = window.paxdesignAdmin;
    var meta = window.paxLiveChatAdmin || {};
    var agent = (cfg && cfg.liveAgent) ? cfg.liveAgent : meta;
    var currentEmployee = (cfg && cfg.currentEmployee) ? cfg.currentEmployee : null;
    var sessionAssignedAgent = null;
    var agentName = currentEmployee && currentEmployee.name ? currentEmployee.name : (agent.name || meta.adminName || 'Live Agent');
    var agentAvatar = currentEmployee && currentEmployee.avatar ? currentEmployee.avatar : (agent.avatar || '');
    var adminPanelUrl = (cfg && cfg.adminUrl) ? cfg.adminUrl : ((window.paxLivePwa && window.paxLivePwa.adminUrl) || 'https://paxdesign.at/live-chat-admin/');
    var $root = $('#paxLiveChatDashboard');

    if (!$root.length) return;
    if (!cfg || !cfg.ajaxUrl) {
      $('#paxLiveChatList').html('<p class="pax-live-dashboard__error">Live-Chat-Konfiguration fehlt. Bitte Seite neu laden.</p>');
      return;
    }

    var $list = $('#paxLiveChatList');
    var $count = $('#paxLiveChatCount');
    var $liveCount = $('#paxLiveChatLiveCount');
    var $search = $('#paxLiveChatSearch');
    var $placeholder = $('#paxLiveChatPlaceholder');
    var $active = $('#paxLiveChatActive');
    var $messages = $('#paxLiveChatMessages');
    var $sessionLabel = $('#paxLiveChatSessionLabel');
    var $sessionMeta = $('#paxLiveChatSessionMeta');
    var $service = $('#paxLiveChatService');
    var $updated = $('#paxLiveChatUpdated');
    var $handlerBadge = $('#paxLiveChatHandlerBadge');
    var $takeover = $('#paxLiveChatTakeover');
    var $release = $('#paxLiveChatRelease');
    var $reopen = $('#paxLiveChatReopen');
    var $close = $('#paxLiveChatClose');
    var $assist = $('#paxLiveChatAssist');
    var $compose = $('#paxLiveChatCompose');
    var $composeHint = $('#paxLiveChatComposeHint');
    var $input = $('#paxLiveChatInput');
    var $send = $('#paxLiveChatSend');
    var $attach = $('#paxLiveChatAttach');
    var $attachLabel = $('#paxLiveChatAttachLabel');
    var $back = $('#paxLiveChatBack');
    var $replyBar = $('#paxLiveChatReplyBar');
    var $replyPreview = $('#paxLiveChatReplyPreview');
    var $replyClear = $('#paxLiveChatReplyClear');
    var $quickReplies = $('#paxLiveChatQuickReplies');
    var $aiSuggest = $('#paxLiveChatAiSuggest');
    var $refresh = $('#paxLiveChatRefresh');
    var $sessionRating = $('#paxLiveChatSessionRating');
    var $profileBtn = $('#paxLiveAgentProfileBtn');
    var $profileModal = $('#paxLiveAgentProfileModal');
    var $activityPanel = $('#paxLiveActivityPanel');
    var $activityWaiting = $('#paxLiveActivityWaiting');
    var $activityOpen = $('#paxLiveActivityOpen');
    var $activityUrgent = $('#paxLiveActivityUrgent');
    var $restartTourBtn = $('#paxLiveRestartTour');
    var $languageToggle = $('#paxLiveLanguageToggle');

    var quickReplies = (cfg && cfg.quickReplies && cfg.quickReplies.length)
      ? cfg.quickReplies
      : ((meta && meta.quickReplies && meta.quickReplies.length) ? meta.quickReplies : DEFAULT_QUICK_REPLIES);

    var selectedSession = '';
    var pollSeq = 0;
    var listTimer = null;
    var msgTimer = null;
    var LIST_POLL_MS = 2000;
    var MSG_POLL_MS = 400;
    var LIST_POLL_ACTIVE_MS = 1200;
    var pageVisible = !document.hidden;
    var streamSource = null;
    var streamEventSince = 0;
    var streamInboxSince = 0;
    var streamRestartTimer = null;

    function adminStreamUrl() {
      var parts = [
        'action=paxdesign_chat_live_stream',
        'nonce=' + encodeURIComponent(cfg.nonce),
        'since=' + encodeURIComponent(String(streamEventSince)),
        'since_inbox=' + encodeURIComponent(String(streamInboxSince)),
      ];
      if (selectedSession) {
        parts.push('session_id=' + encodeURIComponent(selectedSession));
      }
      return cfg.ajaxUrl + '?' + parts.join('&');
    }

    function handleStreamPayload(data) {
      if (!data || !data.type) return;
      if (typeof data.id === 'number') {
        if (data.channel && String(data.channel).indexOf('inbox:') === 0) {
          streamInboxSince = Math.max(streamInboxSince, data.id);
        } else {
          streamEventSince = Math.max(streamEventSince, data.id);
        }
      }
      var payload = data.payload || {};
      var sid = payload.session_id || '';
      if (data.type === 'message' && payload.message && sid === selectedSession) {
        renderMessages([payload.message], false);
      }
      if (data.type === 'typing' && sid === selectedSession) {
        if (payload.active && payload.who === 'user') {
          showCustomerTyping();
        } else if (payload.who === 'user') {
          hideCustomerTyping();
        }
      }
      if (data.type === 'message' || data.type === 'handler' || data.type === 'typing') {
        if (!sid || sid === selectedSession) {
          pollMessages();
        }
        loadList();
      } else if (data.type === 'session_update' || data.type === 'conversation_deleted') {
        loadList();
      }
    }

    function stopAdminStream() {
      if (streamRestartTimer) {
        clearTimeout(streamRestartTimer);
        streamRestartTimer = null;
      }
      if (streamSource) {
        streamSource.close();
        streamSource = null;
      }
    }

    function scheduleAdminStreamRestart(delayMs) {
      if (streamRestartTimer) clearTimeout(streamRestartTimer);
      streamRestartTimer = setTimeout(function () {
        streamRestartTimer = null;
        startAdminStream();
      }, delayMs || 600);
    }

    function startAdminStream() {
      if (!pageVisible || typeof EventSource === 'undefined') return;
      stopAdminStream();
      try {
        streamSource = new EventSource(adminStreamUrl());
        streamSource.addEventListener('chat', function (event) {
          try {
            handleStreamPayload(JSON.parse(event.data));
          } catch (e) {}
          scheduleAdminStreamRestart(120);
        });
        streamSource.addEventListener('ping', function () {
          scheduleAdminStreamRestart(120);
        });
        streamSource.onerror = function () {
          stopAdminStream();
          scheduleAdminStreamRestart(900);
        };
      } catch (e) {
        scheduleAdminStreamRestart(1200);
      }
    }

    function scheduleListPoll() {
      if (listTimer) clearInterval(listTimer);
      if (!pageVisible) return;
      var interval = selectedSession ? LIST_POLL_ACTIVE_MS : LIST_POLL_MS;
      listTimer = setInterval(loadList, interval);
    }

    function scheduleMsgPoll() {
      if (msgTimer) clearInterval(msgTimer);
      if (!pageVisible || !selectedSession) return;
      msgTimer = setInterval(pollMessages, MSG_POLL_MS);
    }

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', function (event) {
        var data = event.data || {};
        if (data.type !== 'pax-session-sync') return;
        if (data.session_id && data.session_id === selectedSession) {
          pollMessages();
        }
        loadList();
      });
    }

    document.addEventListener('visibilitychange', function () {
      pageVisible = !document.hidden;
      if (pageVisible) {
        loadList();
        if (selectedSession) pollMessages();
        scheduleListPoll();
        scheduleMsgPoll();
        startAdminStream();
      } else {
        if (listTimer) { clearInterval(listTimer); listTimer = null; }
        if (msgTimer) { clearInterval(msgTimer); msgTimer = null; }
        stopAdminStream();
      }
    });
    var domMsgIds = {};
    var sessionMessageMap = {};
    var replyToId = 0;
    var allSessions = [];
    var audioCtx = null;
    var audioUnlocked = false;
    var typingTimer = null;
    var adminTypingActive = false;
    var customerTypingEl = null;
    var isAdminContext = $root.hasClass('pax-live-dashboard--admin');
    var isConsoleContext = $root.hasClass('pax-live-console');
    var knownSessions = {};
    var seenLiveRequests = {};
    var liveAlarmTimer = null;
    var liveAlarmActive = false;
    var documentTitleBase = document.title;
    var titleFlashTimer = null;
    var $alertBar = null;
    var currentHandler = 'ai';
    var aiSuggestForMsgId = 0;
    var aiSuggestXhr = null;
    var adminTypingSoundActive = false;
    var adminTypingSoundLoopTimer = null;
    var adminTypingSoundAudio = null;
    var ADMIN_TYPING_SOUND_VOLUME = 0.32;
    var ADMIN_TYPING_SOUND_GAP_MS = 70;
    var suppressMessageSoundsUntil = 0;
    var mp3AudioCache = {};
    var uiLanguage = (localStorage.getItem('pax_live_ui_lang') || 'de').toLowerCase();
    var soundUrls = {
      typing: 'https://paxdesign.at/wp-content/uploads/2026/06/freesound_community-writing-a-text-message-41141.mp3',
      openClose: 'https://paxdesign.at/wp-content/uploads/2026/06/u_8e8ungop1x-intro_cinematic-270840.mp3',
    };
    var FEEDBACK_ICONS = {
      like: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
      dislike: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.33 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>',
    };

    try {
      seenLiveRequests = JSON.parse(localStorage.getItem('pax_seen_live_requests') || '{}');
    } catch (e) {
      seenLiveRequests = {};
    }

    function persistSeenLiveRequests() {
      try {
        localStorage.setItem('pax_seen_live_requests', JSON.stringify(seenLiveRequests));
      } catch (e) {}
    }

    function unlockAudio() {
      if (audioUnlocked) return;
      try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        audioUnlocked = true;
      } catch (e) {}
    }

    function playMessengerSound() {
      unlockAudio();
      try {
        if (!audioCtx) return;
        var t = audioCtx.currentTime;
        var master = audioCtx.createGain();
        master.gain.setValueAtTime(0.2, t);
        master.connect(audioCtx.destination);
        [[880, 0, 0.07], [1174.66, 0.06, 0.09]].forEach(function (tone) {
          var osc = audioCtx.createOscillator();
          var gain = audioCtx.createGain();
          var start = t + tone[1];
          osc.type = 'sine';
          osc.frequency.setValueAtTime(tone[0], start);
          gain.gain.setValueAtTime(0.0001, start);
          gain.gain.exponentialRampToValueAtTime(0.85, start + 0.008);
          gain.gain.exponentialRampToValueAtTime(0.0001, start + tone[2]);
          osc.connect(gain);
          gain.connect(master);
          osc.start(start);
          osc.stop(start + tone[2] + 0.02);
        });
      } catch (e) {}
    }

    function playLiveRequestAlarm() {
      unlockAudio();
      if (liveAlarmActive) return;
      liveAlarmActive = true;

      function ringOnce() {
        try {
          if (!audioCtx) return;
          var t = audioCtx.currentTime;
          var master = audioCtx.createGain();
          master.gain.setValueAtTime(0.55, t);
          master.connect(audioCtx.destination);

          [[440, 0, 0.35], [480, 0.38, 0.35], [440, 0.82, 0.35], [480, 1.2, 0.35]].forEach(function (tone) {
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            var start = t + tone[1];
            osc.type = 'square';
            osc.frequency.setValueAtTime(tone[0], start);
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.9, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + tone[2]);
            osc.connect(gain);
            gain.connect(master);
            osc.start(start);
            osc.stop(start + tone[2] + 0.05);
          });
        } catch (e) {}
      }

      ringOnce();
      liveAlarmTimer = window.setInterval(ringOnce, 2800);
    }

    function stopLiveRequestAlarm() {
      liveAlarmActive = false;
      if (liveAlarmTimer) {
        clearInterval(liveAlarmTimer);
        liveAlarmTimer = null;
      }
      if (titleFlashTimer) {
        clearInterval(titleFlashTimer);
        titleFlashTimer = null;
      }
      document.title = documentTitleBase;
    }

    function flashDocumentTitle() {
      if (titleFlashTimer) return;
      var on = true;
      titleFlashTimer = window.setInterval(function () {
        document.title = on ? '🔴 LIVE ANFRAGE — PAXdesign' : documentTitleBase;
        on = !on;
      }, 900);
    }

    function updateAppBadge(count) {
      if (window.PaxLivePwa && typeof window.PaxLivePwa.setBadge === 'function') {
        window.PaxLivePwa.setBadge(count);
      }
    }

    function updateActivityPanel(items) {
      items = items || [];
      var waiting = 0;
      var open = 0;
      var urgent = 0;
      items.forEach(function (s) {
        if (!s) return;
        if (s.handler === 'live_request') {
          waiting += 1;
          urgent += 1;
        }
        if (s.handler === 'admin' || s.handler === 'ai') {
          open += 1;
        }
        if (!knownSessions[s.session_id]) {
          urgent += 1;
        }
      });
      if ($activityWaiting.length) $activityWaiting.text(waiting);
      if ($activityOpen.length) $activityOpen.text(open);
      if ($activityUrgent.length) $activityUrgent.text(urgent);
      if ($activityPanel.length) {
        $activityPanel.removeClass('is-loading').attr('aria-busy', 'false');
      }
    }

    function timelineLoaderMarkup(chip) {
      var safeChip = escapeHtml(chip || 'Lädt');
      return (
        '<div class="pax-live-loader pax-live-loader--compact">' +
          '<div class="pax-live-loader__head">' +
            '<div class="pax-live-loader__title sk"></div>' +
            '<div class="pax-live-loader__chip">' + safeChip + '</div>' +
          '</div>' +
          '<div class="pax-live-loader__tracks">' +
            '<div class="pax-live-loader__track">' +
              '<span class="pax-live-loader__clip c1"></span><span class="pax-live-loader__clip c2"></span><span class="pax-live-loader__clip c3"></span>' +
              '<span class="pax-live-loader__playhead" aria-hidden="true"></span>' +
            '</div>' +
            '<div class="pax-live-loader__track dim">' +
              '<span class="pax-live-loader__clip c2"></span><span class="pax-live-loader__clip c3"></span><span class="pax-live-loader__clip c1"></span>' +
              '<span class="pax-live-loader__playhead" aria-hidden="true"></span>' +
            '</div>' +
          '</div>' +
          '<div class="pax-live-loader__meter">' +
            '<span class="pax-live-loader__meter-fill"></span>' +
            '<span class="pax-live-loader__meter-glint" aria-hidden="true"></span>' +
          '</div>' +
        '</div>'
      );
    }

    function renderListLoadingSkeleton() {
      if (!$list.length) return;
      $list.attr('aria-busy', 'true').html(
        '<div class="pax-live-list-loading" role="status" aria-label="Chats werden geladen">' +
          timelineLoaderMarkup('Sync') +
          timelineLoaderMarkup('Queue') +
        '</div>'
      );
    }

    function renderMessagesLoadingSkeleton() {
      if (!$messages.length) return;
      var html = '<div class="pax-live-dashboard__messages-loading" role="status" aria-label="Nachrichten werden geladen">';
      for (var i = 0; i < 4; i++) {
        var outgoing = (i % 2) === 1;
        html += '<div class="pax-live-dashboard__msg-skeleton' + (outgoing ? ' is-outgoing' : '') + '">';
        if (!outgoing) html += '<span class="pax-live-dashboard__msg-skeleton-avatar"></span>';
        html += '<div class="pax-live-dashboard__msg-skeleton-lines">';
        html += '<span class="pax-live-dashboard__msg-skeleton-line" style="width:' + (outgoing ? '74%' : '62%') + ';"></span>';
        html += '<span class="pax-live-dashboard__msg-skeleton-line" style="width:' + (outgoing ? '92%' : '86%') + ';"></span>';
        html += '<span class="pax-live-dashboard__msg-skeleton-line" style="width:' + (outgoing ? '46%' : '34%') + ';"></span>';
        html += '</div>';
        if (outgoing) html += '<span class="pax-live-dashboard__msg-skeleton-avatar"></span>';
        html += '</div>';
      }
      html += '</div>';
      $messages.attr('aria-busy', 'true').html(html);
    }

    function applyUiLanguage(lang) {
      var allowed = ['de', 'en', 'ar'];
      if (allowed.indexOf(lang) === -1) lang = 'de';
      uiLanguage = lang;
      localStorage.setItem('pax_live_ui_lang', lang);
      if ($languageToggle.length) {
        $languageToggle.text(lang.toUpperCase());
      }
      $root.attr('lang', lang);
      $root.attr('dir', lang === 'ar' ? 'rtl' : 'ltr');
    }

    function initLanguageToggle() {
      applyUiLanguage(uiLanguage);
      if (!$languageToggle.length) return;
      $languageToggle.off('click.paxLang').on('click.paxLang', function () {
        var list = ['de', 'en', 'ar'];
        var idx = list.indexOf(uiLanguage);
        applyUiLanguage(list[(idx + 1) % list.length]);
      });
    }

    var tourState = {
      active: false,
      step: 0,
      steps: [],
      $overlay: null,
      $card: null,
      $pointer: null,
    };

    function getTourSteps() {
      return [
        {
          selector: '#paxLiveChatSearch',
          title: 'Search',
          text: 'Use search to jump to any customer conversation instantly.'
        },
        {
          selector: '#paxLiveChatLiveCount',
          title: 'Live chat requests',
          text: 'This indicator shows how many customers are waiting right now.'
        },
        {
          selector: '#paxLiveActivityPanel',
          title: 'Orders / requests activity',
          text: 'Modern activity cards summarize waiting, open and urgent operations.'
        },
        {
          selector: '#paxLiveChatLiveCount',
          title: 'Notifications',
          text: 'Live alerts trigger banner + sound so urgent chats are never missed.'
        },
        {
          selector: '#paxLiveLanguageToggle',
          title: 'Language switcher',
          text: 'Switch UI direction/language mode from this quick control.'
        },
        {
          selector: '#paxLiveAgentProfileBtn',
          title: 'Profile / settings',
          text: 'Open your profile here and use quick links for account settings.'
        },
        {
          selector: '.pax-live-activity-actions__link[href*="#security"]',
          title: 'Device management',
          text: 'Review device security and approval controls from this shortcut.'
        },
        {
          selector: '.pax-live-activity-actions__link[href*="paxdesign-live-chat-team"]',
          title: 'Admin / staff tools',
          text: 'Manage staff permissions and team operations from this area.'
        }
      ];
    }

    function markTourCompleted(completed) {
      cfg.tourCompleted = !!completed;
      ajax('paxdesign_chat_live_tour_complete', { completed: completed ? '1' : '0' });
    }

    function ensureTourOverlay() {
      if (tourState.$overlay && tourState.$overlay.length) return;
      tourState.$overlay = $(
        '<div class="pax-live-tour" id="paxLiveTour" hidden>' +
          '<div class="pax-live-tour__backdrop"></div>' +
          '<div class="pax-live-tour__card">' +
            '<div class="pax-live-tour__pointer"></div>' +
            '<span class="pax-live-tour__step"></span>' +
            '<h4 class="pax-live-tour__title"></h4>' +
            '<p class="pax-live-tour__text"></p>' +
            '<div class="pax-live-tour__controls">' +
              '<button type="button" class="pax-live-btn pax-live-btn--ghost" data-tour="back">Back</button>' +
              '<button type="button" class="pax-live-btn pax-live-btn--ghost" data-tour="skip">Skip</button>' +
              '<div style="flex:1"></div>' +
              '<button type="button" class="pax-live-btn pax-live-btn--primary" data-tour="next">Next</button>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
      $('body').append(tourState.$overlay);
      tourState.$card = tourState.$overlay.find('.pax-live-tour__card');
      tourState.$pointer = tourState.$overlay.find('.pax-live-tour__pointer');

      tourState.$overlay.on('click', '[data-tour="skip"]', function () { stopTour(true); });
      tourState.$overlay.on('click', '[data-tour="back"]', function () {
        if (tourState.step > 0) {
          tourState.step -= 1;
          renderTourStep();
        }
      });
      tourState.$overlay.on('click', '[data-tour="next"]', function () {
        if (tourState.step >= tourState.steps.length - 1) {
          stopTour(true);
          return;
        }
        tourState.step += 1;
        renderTourStep();
      });
    }

    function positionTourCard(step) {
      var $target = $(step.selector).first();
      var targetRect = null;
      if ($target.length) {
        targetRect = $target[0].getBoundingClientRect();
      }
      var cardRect = tourState.$card[0].getBoundingClientRect();
      var top = 80;
      var left = Math.max(12, window.innerWidth - cardRect.width - 12);
      var pointerTop = -8;
      var pointerLeft = 24;

      if (targetRect) {
        if (targetRect.top > window.innerHeight * 0.55) {
          top = Math.max(12, targetRect.top - cardRect.height - 24);
          pointerTop = cardRect.height - 8;
        } else {
          top = Math.min(window.innerHeight - cardRect.height - 12, targetRect.bottom + 14);
          pointerTop = -8;
        }
        left = Math.min(
          Math.max(12, targetRect.left + (targetRect.width / 2) - (cardRect.width / 2)),
          window.innerWidth - cardRect.width - 12
        );
        pointerLeft = Math.min(Math.max(16, (targetRect.left + targetRect.width / 2) - left - 8), cardRect.width - 24);
      }

      tourState.$card.css({ top: top + 'px', left: left + 'px' });
      tourState.$pointer.css({ top: pointerTop + 'px', left: pointerLeft + 'px' });
    }

    function renderTourStep() {
      if (!tourState.active) return;
      var step = tourState.steps[tourState.step];
      if (!step) {
        stopTour(true);
        return;
      }
      tourState.$overlay.find('.pax-live-tour__step').text('Step ' + (tourState.step + 1) + ' of ' + tourState.steps.length);
      tourState.$overlay.find('.pax-live-tour__title').text(step.title);
      tourState.$overlay.find('.pax-live-tour__text').text(step.text);
      tourState.$overlay.find('[data-tour="back"]').prop('disabled', tourState.step === 0);
      tourState.$overlay.find('[data-tour="next"]').text(tourState.step >= tourState.steps.length - 1 ? 'Finish' : 'Next');
      positionTourCard(step);
    }

    function startTour(force) {
      ensureTourOverlay();
      if (!force && cfg.tourCompleted) return;
      tourState.steps = getTourSteps();
      tourState.step = 0;
      tourState.active = true;
      tourState.$overlay.prop('hidden', false);
      renderTourStep();
    }

    function stopTour(markDone) {
      if (!tourState.$overlay || !tourState.$overlay.length) return;
      tourState.active = false;
      tourState.$overlay.prop('hidden', true);
      if (markDone) {
        markTourCompleted(true);
      }
    }

    function notificationClickUrl(sessionId) {
      if (!sessionId) return adminPanelUrl;
      try {
        var url = new URL(adminPanelUrl, window.location.origin);
        url.searchParams.set('session', sessionId);
        return url.toString();
      } catch (e) {
        return adminPanelUrl + (adminPanelUrl.indexOf('?') >= 0 ? '&' : '?') + 'session=' + encodeURIComponent(sessionId);
      }
    }

    function tryBrowserNotification(body, sessionId, title) {
      title = title || 'Live-Agent-Anfrage';
      var targetUrl = notificationClickUrl(sessionId);
      if (window.PaxLivePwa && typeof window.PaxLivePwa.showNotification === 'function') {
        window.PaxLivePwa.showNotification(title, body || 'Neue Aktivität im Live Chat.', targetUrl, 'pax-live-' + (sessionId || 'alert'));
        return;
      }
      if (!('Notification' in window)) return;
      if (Notification.permission === 'granted') {
        var n = new Notification(title, {
          body: body || 'Ein Kunde wartet auf persönliche Unterstützung.',
          tag: 'pax-live-' + (sessionId || 'alert'),
          requireInteraction: true,
        });
        n.onclick = function () {
          window.focus();
          if (sessionId) selectSession(sessionId);
          else window.location.href = targetUrl;
          n.close();
        };
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission();
      }
    }

    function ensureAlertBar() {
      if ($alertBar && $alertBar.length) return $alertBar;
      $alertBar = $(
        '<div class="pax-live-alert-bar" id="paxLiveChatAlertBar" hidden role="alert">' +
          '<div class="pax-live-alert-bar__content">' +
            '<strong class="pax-live-alert-bar__title">Live-Agent-Anfrage</strong>' +
            '<span class="pax-live-alert-bar__text"></span>' +
          '</div>' +
          '<button type="button" class="pax-live-alert-bar__open">Jetzt öffnen</button>' +
          '<button type="button" class="pax-live-alert-bar__dismiss" aria-label="Alarm schließen">✕</button>' +
        '</div>'
      );
      $root.prepend($alertBar);
      $alertBar.on('click', '.pax-live-alert-bar__open', function () {
        var sid = $alertBar.attr('data-session');
        if (sid) selectSession(sid);
        acknowledgeLiveAlerts();
      });
      $alertBar.on('click', '.pax-live-alert-bar__dismiss', function () {
        acknowledgeLiveAlerts();
      });
      return $alertBar;
    }

    function showLiveAlertBar(sessions) {
      if (!sessions || !sessions.length) return;
      var s = sessions[0];
      var bar = ensureAlertBar();
      bar.attr('data-session', s.session_id);
      bar.find('.pax-live-alert-bar__text').text(
        (s.detected_service ? s.detected_service + ' · ' : '') +
        (s.last_preview || 'Kunde wartet auf Live-Support')
      );
      bar.prop('hidden', false);
      $root.addClass('has-live-alert');
    }

    function hideLiveAlertBar() {
      if ($alertBar) {
        $alertBar.prop('hidden', true).removeAttr('data-session');
      }
      $root.removeClass('has-live-alert');
    }

    function acknowledgeLiveAlerts() {
      Object.keys(knownSessions).forEach(function (sid) {
        if (knownSessions[sid].handler === 'live_request') {
          seenLiveRequests[sid] = Date.now();
        }
      });
      persistSeenLiveRequests();
      stopLiveRequestAlarm();
      hideLiveAlertBar();
    }

    function shouldPlayMessageNotification() {
      if (Date.now() < suppressMessageSoundsUntil) return false;
      if (document.hidden) return true;
      if (selectedSession) return false;
      return true;
    }

    function invalidateListCache() {
      lastListSignature = '';
    }

    function patchSessionInList(sessionId, patch) {
      if (!sessionId || !patch) return;
      allSessions = allSessions.map(function (s) {
        if (s.session_id !== sessionId) return s;
        var next = Object.assign({}, s, patch);
        knownSessions[sessionId] = Object.assign({}, knownSessions[sessionId] || {}, patch);
        return next;
      });
      invalidateListCache();
      renderList(allSessions);
    }

    function removeSessionFromList(sessionId) {
      if (!sessionId) return;
      allSessions = allSessions.filter(function (s) { return s.session_id !== sessionId; });
      delete knownSessions[sessionId];
      invalidateListCache();
      renderList(allSessions);
    }

    function shortSessionId(id) {
      if (!id || id.length < 12) return id || '—';
      return id.slice(0, 10) + '…';
    }

    function pingAdminTyping(stop) {
      if (!selectedSession) return;
      var payload = { session_id: selectedSession };
      if (stop) payload.stop = '1';
      ajax('paxdesign_chat_live_admin_typing', payload);
    }

    function clearAdminTypingState() {
      if (typingTimer) {
        clearTimeout(typingTimer);
        typingTimer = null;
      }
      if (adminTypingActive) {
        adminTypingActive = false;
        pingAdminTyping(true);
      }
      stopAdminTypingSound();
    }

    function scheduleAdminTypingPing() {
      unlockAudio();
      pingAdminTyping(false);
      adminTypingActive = true;
      startAdminTypingSound();
      if (typingTimer) clearTimeout(typingTimer);
      typingTimer = setTimeout(function () {
        typingTimer = null;
        adminTypingActive = false;
        stopAdminTypingSound();
        pingAdminTyping(true);
      }, 1800);
    }

    function showCustomerTyping() {
      if (customerTypingEl) return;
      customerTypingEl = $(
        '<div class="pax-live-dashboard__msg pax-live-dashboard__msg--user pax-live-dashboard__msg--incoming pax-live-dashboard__msg--typing">' +
          '<div class="pax-live-dashboard__msg-row">' +
            '<div class="pax-live-dashboard__msg-avatar pax-live-dashboard__msg-avatar--customer">K</div>' +
            '<div class="pax-live-dashboard__msg-stack">' +
              '<span class="pax-live-dashboard__msg-name">Kunde schreibt …</span>' +
              '<div class="pax-live-dashboard__msg-bubble pax-live-dashboard__typing-bubble">' +
                '<span class="pax-live-dashboard__typing-dot"></span>' +
                '<span class="pax-live-dashboard__typing-dot"></span>' +
                '<span class="pax-live-dashboard__typing-dot"></span>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
      $messages.append(customerTypingEl);
      $messages.scrollTop($messages[0].scrollHeight);
    }

    function hideCustomerTyping() {
      if (customerTypingEl) {
        customerTypingEl.remove();
        customerTypingEl = null;
      }
    }

    function isMobileView() {
      return window.matchMedia('(max-width: 767px)').matches;
    }

    function updateMobilePanels() {
      if (isMobileView() && selectedSession) {
        $root.addClass('is-chat-open');
      } else {
        $root.removeClass('is-chat-open');
      }
    }

    function ajax(action, data) {
      data = data || {};
      data.action = action;
      data.nonce = cfg.nonce;
      return $.post(cfg.ajaxUrl, data);
    }

    function roleLabel(role, msg) {
      if (role === 'user') {
        var cname = sessionCustomerName(knownSessions[selectedSession] || {});
        return cname || 'Kunde';
      }
      if (role === 'admin') {
        if (msg && msg.sender_name) return msg.sender_name;
        if (sessionAssignedAgent && sessionAssignedAgent.name) return sessionAssignedAgent.name;
        return agentName;
      }
      if (role === 'system') return 'System';
      return 'KI-Assistent';
    }

    function escapeHtml(str) {
      return $('<div/>').text(str || '').html();
    }

    function formatTime(ts) {
      if (!ts) return '—';
      var d = new Date(String(ts).replace(' ', 'T'));
      if (isNaN(d.getTime())) return ts;
      return d.toLocaleString('de-AT', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
    }

    function formatTimeShort(ts) {
      if (!ts) return '';
      var d = new Date(String(ts).replace(' ', 'T'));
      if (isNaN(d.getTime())) return '';
      return d.toLocaleString('de-AT', { hour: '2-digit', minute: '2-digit' });
    }

    function formatSessionRatingIcon(rating) {
      var r = parseInt(rating, 10);
      if (r === 5) {
        return '<span class="pax-live-feedback-icon pax-live-feedback-icon--like" title="Gefällt mir">' + FEEDBACK_ICONS.like + '</span>';
      }
      if (r === 1) {
        return '<span class="pax-live-feedback-icon pax-live-feedback-icon--dislike" title="Gefällt mir nicht">' + FEEDBACK_ICONS.dislike + '</span>';
      }
      return '';
    }

    function updateSessionHeader(data) {
      if (!selectedSession) return;
      var known = knownSessions[selectedSession] || {};
      var cname = (data && data.customer_name) ? String(data.customer_name).trim() : '';
      if (!cname && known.customer_name) cname = String(known.customer_name).trim();
      $sessionLabel.text(cname || 'Kunde');
      if ($sessionMeta.length) {
        $sessionMeta.text('#' + shortSessionId(selectedSession));
      } else if (!cname) {
        $sessionLabel.text('Kunde · ' + shortSessionId(selectedSession));
      }
      var topic = (data && data.detected_service) ? String(data.detected_service) : '';
      if (!topic && known.detected_service) topic = String(known.detected_service);
      if ($service.length) {
        if (topic) {
          $service.text(topic).prop('hidden', false);
        } else {
          $service.text('').prop('hidden', true);
        }
      }
      var updatedAt = (data && data.updated_at) ? data.updated_at : (known.updated_at || '');
      if ($updated.length) {
        var time = formatTime(updatedAt);
        $updated.text(isMobileView() ? formatTimeShort(updatedAt) || time : time);
      }
      if ($sessionRating.length) {
        var rating = (data && data.session_rating) ? parseInt(data.session_rating, 10) : 0;
        if (!rating && known.session_rating) rating = parseInt(known.session_rating, 10) || 0;
        if (rating > 0) {
          $sessionRating.html(formatSessionRatingIcon(rating)).prop('hidden', false).attr('title', 'Kundenbewertung');
        } else {
          $sessionRating.empty().prop('hidden', true).removeAttr('title');
        }
      }
    }

    function resetComposerHeight() {
      if (!$input.length) return;
      $input[0].style.height = '';
    }

    function resizeComposer() {
      if (!$input.length) return;
      var el = $input[0];
      el.style.height = 'auto';
      var max = isMobileView() ? 72 : 120;
      el.style.height = Math.min(el.scrollHeight, max) + 'px';
    }

    function bindComposerResize() {
      if (!$input.length) return;
      $input.on('input', resizeComposer);
      $(window).on('resize', function () {
        if ($input.is(':visible')) resizeComposer();
      });
    }

    function playMp3Sound(kind, volume) {
      unlockAudio();
      var url = soundUrls[kind];
      if (!url) return;
      try {
        if (!mp3AudioCache[kind]) {
          mp3AudioCache[kind] = new Audio(url);
          mp3AudioCache[kind].preload = 'auto';
        }
        var audio = mp3AudioCache[kind].cloneNode();
        audio.volume = typeof volume === 'number' ? volume : 0.42;
        audio.play().catch(function () {});
      } catch (e) {}
    }

    function ensureAdminTypingSoundAudio() {
      if (!soundUrls.typing) return null;
      if (!adminTypingSoundAudio) {
        adminTypingSoundAudio = new Audio(soundUrls.typing);
        adminTypingSoundAudio.preload = 'auto';
      }
      return adminTypingSoundAudio;
    }

    function scheduleAdminTypingSoundLoop() {
      if (!adminTypingSoundActive) return;
      adminTypingSoundLoopTimer = null;
      var audio = ensureAdminTypingSoundAudio();
      if (!audio) return;
      unlockAudio();
      audio.onended = null;
      audio.pause();
      audio.currentTime = 0;
      audio.volume = ADMIN_TYPING_SOUND_VOLUME;
      audio.onended = function () {
        audio.onended = null;
        if (!adminTypingSoundActive) return;
        adminTypingSoundLoopTimer = window.setTimeout(scheduleAdminTypingSoundLoop, ADMIN_TYPING_SOUND_GAP_MS);
      };
      var playPromise = audio.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () {
          if (adminTypingSoundActive) {
            adminTypingSoundLoopTimer = window.setTimeout(scheduleAdminTypingSoundLoop, 420);
          }
        });
      }
    }

    function syncAdminTypingSound(shouldPlay) {
      if (shouldPlay) {
        if (adminTypingSoundActive) return;
        adminTypingSoundActive = true;
        scheduleAdminTypingSoundLoop();
        return;
      }
      adminTypingSoundActive = false;
      if (adminTypingSoundLoopTimer) {
        clearTimeout(adminTypingSoundLoopTimer);
        adminTypingSoundLoopTimer = null;
      }
      if (adminTypingSoundAudio) {
        adminTypingSoundAudio.onended = null;
        adminTypingSoundAudio.pause();
        adminTypingSoundAudio.currentTime = 0;
      }
    }

    function startAdminTypingSound() {
      syncAdminTypingSound(true);
    }

    function stopAdminTypingSound() {
      syncAdminTypingSound(false);
    }

    function playClosingSound() {
      playMp3Sound('openClose', 0.42);
    }

    function setAssistVisible(visible) {
      if (!$assist.length) return;
      if (visible) {
        $assist.removeAttr('hidden').addClass('is-assist-visible').css({ display: '', visibility: '', opacity: '' });
        renderQuickReplies();
      } else {
        $assist.attr('hidden', 'hidden').removeClass('is-assist-visible');
        clearAiSuggestions();
      }
    }

    function ensureAssistVisible() {
      if (currentHandler === 'admin' && selectedSession) {
        setAssistVisible(true);
      }
    }

    function renderQuickReplies() {
      if (!$quickReplies.length || !quickReplies.length) return;
      var html = '<div class="pax-live-quick-replies__label">Schnellantworten</div>';
      html += '<div class="pax-live-quick-replies__scroll">';
      quickReplies.forEach(function (item, idx) {
        html += '<button type="button" class="pax-live-quick-replies__chip" data-qr="' + idx + '" title="' +
          escapeHtml(item.text) + '">' + escapeHtml(item.label || item.text.slice(0, 24)) + '</button>';
      });
      html += '</div>';
      $quickReplies.html(html);
    }

    function initQuickReplies() {
      renderQuickReplies();
      if (!$quickReplies.length) return;
      $quickReplies.off('click.paxQr').on('click.paxQr', '.pax-live-quick-replies__chip', function () {
        if ($input.prop('disabled')) return;
        var idx = parseInt($(this).attr('data-qr'), 10);
        var item = quickReplies[idx];
        if (!item || !item.text) return;
        $input.val(item.text);
        resizeComposer();
        $input.focus();
      });
    }

    function clearAiSuggestions() {
      aiSuggestForMsgId = 0;
      if (aiSuggestXhr && aiSuggestXhr.abort) {
        aiSuggestXhr.abort();
        aiSuggestXhr = null;
      }
      if ($aiSuggest.length) {
        $aiSuggest.prop('hidden', true).empty();
      }
    }

    function renderAiSuggestLoading() {
      if (!$aiSuggest.length) return;
      ensureAssistVisible();
      $aiSuggest.removeAttr('hidden').prop('hidden', false).html(
        '<div class="pax-live-ai-suggest__head">' +
          '<span class="pax-live-ai-suggest__icon" aria-hidden="true">✦</span>' +
          '<span class="pax-live-ai-suggest__title">KI-Assistent</span>' +
          '<span class="pax-live-ai-suggest__status">Analysiert …</span>' +
        '</div>' +
        '<div class="pax-live-list-loading">' + timelineLoaderMarkup('Analyse') + '</div>'
      );
    }

    function renderAiSuggestError(message) {
      if (!$aiSuggest.length) return;
      ensureAssistVisible();
      $aiSuggest.removeAttr('hidden').prop('hidden', false).html(
        '<div class="pax-live-ai-suggest__head">' +
          '<span class="pax-live-ai-suggest__icon" aria-hidden="true">✦</span>' +
          '<span class="pax-live-ai-suggest__title">KI-Vorschläge</span>' +
        '</div>' +
        '<p class="pax-live-ai-suggest__error">' + escapeHtml(message || 'Vorschläge nicht verfügbar.') + '</p>'
      );
    }

    function renderAiSuggestions(items) {
      if (!$aiSuggest.length) return;
      if (!items || !items.length) {
        $aiSuggest.prop('hidden', true).empty();
        return;
      }
      ensureAssistVisible();
      var html = '<div class="pax-live-ai-suggest__head">' +
        '<span class="pax-live-ai-suggest__icon" aria-hidden="true">✦</span>' +
        '<span class="pax-live-ai-suggest__title">KI-Vorschläge</span>' +
        '<span class="pax-live-ai-suggest__hint">Klicken zum Einfügen</span>' +
        '</div><div class="pax-live-ai-suggest__scroll">';
      items.forEach(function (text, idx) {
        var preview = String(text).slice(0, 72);
        if (String(text).length > 72) preview += '…';
        html += '<button type="button" class="pax-live-ai-suggest__chip" data-suggest="' + idx + '" title="' +
          escapeHtml(text) + '">' + escapeHtml(preview) + '</button>';
      });
      html += '</div>';
      $aiSuggest.prop('hidden', false).html(html);
      $aiSuggest.data('suggestions', items);
    }

    function fetchAiSuggestions(messageId) {
      if (!selectedSession || !messageId || currentHandler !== 'admin') return;
      if (aiSuggestForMsgId === messageId && $aiSuggest.find('.pax-live-ai-suggest__chip').length) return;

      if (aiSuggestXhr && aiSuggestXhr.abort) {
        aiSuggestXhr.abort();
      }

      aiSuggestForMsgId = messageId;
      renderAiSuggestLoading();

      aiSuggestXhr = ajax('paxdesign_chat_live_admin_suggestions', {
        session_id: selectedSession,
        message_id: messageId,
      }).done(function (res) {
        aiSuggestXhr = null;
        if (!res || !res.success) {
          renderAiSuggestError(res && res.data && res.data.message ? res.data.message : 'KI-Vorschläge konnten nicht geladen werden.');
          return;
        }
        if (parseInt(res.data.message_id, 10) !== messageId) return;
        renderAiSuggestions(res.data.suggestions || []);
      }).fail(function (_xhr, status) {
        aiSuggestXhr = null;
        if (status === 'abort') return;
        renderAiSuggestError('Verbindungsfehler bei KI-Vorschlägen.');
      });
    }

    function maybeSuggestForLatestUserMessage() {
      if (currentHandler !== 'admin' || !selectedSession) return;
      var lastUserId = 0;
      Object.keys(sessionMessageMap).forEach(function (id) {
        var msg = sessionMessageMap[id];
        var mid = parseInt(id, 10);
        if (msg && msg.role === 'user' && mid > lastUserId) {
          lastUserId = mid;
        }
      });
      if (lastUserId) fetchAiSuggestions(lastUserId);
    }

    function initAiSuggestions() {
      if (!$aiSuggest.length) return;
      $aiSuggest.off('click.paxAi').on('click.paxAi', '.pax-live-ai-suggest__chip', function () {
        if ($input.prop('disabled')) return;
        var idx = parseInt($(this).attr('data-suggest'), 10);
        var items = $aiSuggest.data('suggestions') || [];
        var text = items[idx];
        if (!text) return;
        $input.val(text);
        resizeComposer();
        $input.focus();
      });
    }

    function sessionCustomerName(s) {
      return (s && s.customer_name) ? String(s.customer_name).trim() : '';
    }

    function badgeClass(handler) {
      if (handler === 'live_request') return 'pax-live-badge--live';
      if (handler === 'admin') return 'pax-live-badge--admin';
      if (handler === 'closed') return 'pax-live-badge--closed';
      return 'pax-live-badge--ai';
    }

    function handlerLabel(handler, adminName) {
      if (handler === 'live_request') return 'Live-Anfrage';
      if (handler === 'admin') {
        var name = (adminName || '').trim();
        if (name) return name;
        if (sessionAssignedAgent && sessionAssignedAgent.name) return sessionAssignedAgent.name;
        return agentName;
      }
      if (handler === 'closed') return 'Geschlossen';
      return 'KI aktiv';
    }

    function avatarInitial(label) {
      return (label || '?').charAt(0).toUpperCase();
    }

    function renderAvatar(role, label, msg) {
      if (role === 'admin') {
        var avatarUrl = '';
        if (msg && msg.sender_avatar) {
          avatarUrl = msg.sender_avatar;
        } else if (msg && msg.role === 'admin' && msg.sender_name && currentEmployee && msg.sender_name === currentEmployee.name) {
          avatarUrl = currentEmployee.avatar || agentAvatar;
        } else if (sessionAssignedAgent && sessionAssignedAgent.avatar) {
          avatarUrl = sessionAssignedAgent.avatar;
        } else {
          avatarUrl = agentAvatar;
        }
        if (avatarUrl) {
          return '<img class="pax-live-dashboard__avatar" src="' + escapeHtml(avatarUrl) + '" alt="" width="32" height="32" loading="lazy">';
        }
        var adminLabel = (msg && msg.sender_name) ? msg.sender_name : agentName;
        return '<div class="pax-live-dashboard__msg-avatar pax-live-dashboard__msg-avatar--agent">' + escapeHtml(avatarInitial(adminLabel)) + '</div>';
      }
      if (role === 'user') {
        var cname = sessionCustomerName(knownSessions[selectedSession] || {});
        var initial = avatarInitial(cname || 'K');
        return '<div class="pax-live-dashboard__msg-avatar pax-live-dashboard__msg-avatar--customer">' + escapeHtml(initial) + '</div>';
      }
      return '<div class="pax-live-dashboard__msg-avatar pax-live-dashboard__msg-avatar--ai">KI</div>';
    }

    function filterSessions(items) {
      var q = ($search.val() || '').trim().toLowerCase();
      if (!q) return items;
      return items.filter(function (s) {
        var hay = [
          s.session_id,
          s.customer_name,
          s.last_preview,
          s.detected_service,
          s.handler_label,
        ].join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
      });
    }

    var FRESH_CHAT_MINUTES = 30;
    var lastListSignature = '';

    function listSignature(items) {
      return (items || []).map(function (s) {
        return [
          s.session_id,
          s.handler || 'ai',
          s.updated_at || '',
          s.message_count || 0,
          s.last_preview || '',
          s.session_rating || 0,
        ].join(':');
      }).join('|');
    }

    function syncSelectedListItem() {
      if (!$list.length) return;
      $list.find('.pax-live-dashboard__item').removeClass('is-selected');
      if (selectedSession) {
        $list.find('.pax-live-dashboard__item[data-session="' + selectedSession + '"]').addClass('is-selected');
      }
    }

    function minutesSince(ts) {
      if (!ts) return 99999;
      var d = new Date(String(ts).replace(' ', 'T'));
      if (isNaN(d.getTime())) return 99999;
      return (Date.now() - d.getTime()) / 60000;
    }

    function sessionListClasses(s) {
      var h = s.handler || 'ai';
      var cls = 'pax-live-dashboard__item';
      if (s.session_id === selectedSession) cls += ' is-selected';
      if (h === 'live_request') {
        cls += ' is-live is-fresh is-handler-live';
        if (!seenLiveRequests[s.session_id]) cls += ' is-new is-urgent';
      } else if (h === 'admin') {
        cls += ' is-fresh is-handler-admin';
      } else if (h === 'closed') {
        cls += ' is-closed is-stale is-handler-closed';
      } else {
        cls += ' is-handler-ai';
        if (minutesSince(s.updated_at) <= FRESH_CHAT_MINUTES) {
          cls += ' is-fresh';
        } else {
          cls += ' is-stale';
        }
        if (!knownSessions[s.session_id]) cls += ' is-new';
      }
      return cls;
    }

    function sessionTypeIcon(h) {
      if (h === 'live_request') return '📞';
      if (h === 'admin') return '👤';
      if (h === 'closed') return '⏸';
      return '🤖';
    }

    function renderList(items) {
      allSessions = items || [];
      var filtered = filterSessions(allSessions);
      var sig = listSignature(allSessions);
      if (sig === lastListSignature && $list.children('.pax-live-dashboard__item').length) {
        $list.attr('aria-busy', 'false');
        $count.text(allSessions.length);
        var liveTotalQuick = 0;
        allSessions.forEach(function (s) {
          if (s.handler === 'live_request') liveTotalQuick++;
        });
        $liveCount.text(liveTotalQuick);
        updateActivityPanel(allSessions);
        syncSelectedListItem();
        return;
      }
      lastListSignature = sig;

      var liveTotal = 0;
      allSessions.forEach(function (s) {
        if (s.handler === 'live_request') liveTotal++;
      });

      $count.text(allSessions.length);
      $liveCount.text(liveTotal);
      updateActivityPanel(allSessions);

      if (!filtered.length) {
        $list.attr('aria-busy', 'false');
        $list.html('<p class="pax-live-dashboard__empty">' + (allSessions.length ? 'Keine Treffer für die Suche.' : 'Derzeit keine aktiven Chats.') + '</p>');
        return;
      }

      var html = '';
      filtered.forEach(function (s) {
        var h = s.handler || 'ai';
        var cls = sessionListClasses(s);
        html += '<button type="button" class="' + cls + '" data-session="' + escapeHtml(s.session_id) + '">';
        html += '<div class="pax-live-dashboard__item-id">' + sessionTypeIcon(h) + ' ' +
          escapeHtml(sessionCustomerName(s) || ('Chat · ' + shortSessionId(s.session_id))) + '</div>';
        html += '<div class="pax-live-dashboard__item-top">';
        html += '<span class="pax-live-badge ' + badgeClass(h) + '">' + escapeHtml(s.handler_label || handlerLabel(h, s.admin_name)) + '</span>';
        if (!seenLiveRequests[s.session_id] && h === 'live_request') {
          html += '<span class="pax-live-dashboard__item-new">NEU</span>';
        } else if (!knownSessions[s.session_id] && h !== 'live_request') {
          html += '<span class="pax-live-dashboard__item-new pax-live-dashboard__item-new--soft">Neu</span>';
        }
        html += '<span class="pax-live-dashboard__item-time">' + formatTime(s.updated_at) + '</span>';
        html += '</div>';
        if (s.session_rating > 0) {
          html += '<div class="pax-live-dashboard__item-rating">' + formatSessionRatingIcon(s.session_rating) + '</div>';
        }
        html += '<div class="pax-live-dashboard__item-preview">' + escapeHtml(s.last_preview || '—') + '</div>';
        html += '<div class="pax-live-dashboard__item-foot"><span>' + escapeHtml(s.detected_service || '—') + '</span><span>' + s.message_count + '</span></div>';
        html += '</button>';
      });
      $list.attr('aria-busy', 'false');
      $list.html(html);
    }

    function processListNotifications(items) {
      var pendingLive = [];
      (items || []).forEach(function (s) {
        var sid = s.session_id;
        var prev = knownSessions[sid];
        var becameLive = s.handler === 'live_request' && (!prev || prev.handler !== 'live_request');
        var unseenLive = s.handler === 'live_request' && !seenLiveRequests[sid];

        if (becameLive || unseenLive) {
          pendingLive.push(s);
        }

        if (prev && prev.handler !== s.handler && s.handler !== 'live_request') {
          seenLiveRequests[sid] = Date.now();
        }

        knownSessions[sid] = {
          handler: s.handler,
          customer_name: s.customer_name || '',
          session_rating: s.session_rating || 0,
          detected_service: s.detected_service || '',
          seq: s.seq || 0,
          updated_at: s.updated_at,
        };
      });

      if (pendingLive.length) {
        showLiveAlertBar(pendingLive);
        playLiveRequestAlarm();
        flashDocumentTitle();
        tryBrowserNotification(
          pendingLive[0].last_preview || pendingLive[0].detected_service || 'Live-Agent-Anfrage',
          pendingLive[0].session_id,
          '🚨 Live-Agent-Anfrage'
        );
      }

      var badgeCount = 0;
      (items || []).forEach(function (s) {
        if (s.handler === 'live_request' && !seenLiveRequests[s.session_id]) badgeCount++;
      });
      updateAppBadge(badgeCount);
    }

    function loadList() {
      if (!$list.children('.pax-live-dashboard__item').length) {
        renderListLoadingSkeleton();
      }
      return ajax('paxdesign_chat_live_list')
        .done(function (res) {
          if (!res || !res.success) {
            $list.attr('aria-busy', 'false');
            $list.html('<p class="pax-live-dashboard__error">Chats konnten nicht geladen werden.</p>');
            return;
          }
          var sessions = res.data.sessions || [];
          processListNotifications(sessions);
          renderList(sessions);
          if (selectedSession) {
            updateSessionHeader(knownSessions[selectedSession] || {});
          }
        })
        .fail(function () {
          $list.attr('aria-busy', 'false');
          $list.html('<p class="pax-live-dashboard__error">Verbindungsfehler beim Laden der Chats.</p>');
        });
    }

    function manualRefresh() {
      if ($refresh.length) $refresh.addClass('is-spinning');
      loadList();
      if (selectedSession) {
        loadSession(selectedSession, false);
        pollMessages();
      }
      window.setTimeout(function () {
        if ($refresh.length) $refresh.removeClass('is-spinning');
      }, 650);
    }

    function initAgentProfileModal() {
      if (!$profileModal.length) return;
      function openProfile() {
        $profileModal.prop('hidden', false);
        $('body').addClass('pax-live-agent-profile-open');
      }
      function closeProfile() {
        $profileModal.prop('hidden', true);
        $('body').removeClass('pax-live-agent-profile-open');
      }
      if ($profileBtn.length) {
        $profileBtn.on('click', function (e) {
          e.preventDefault();
          openProfile();
        });
      }
      $profileModal.on('click', '[data-live-profile-close]', function (e) {
        e.preventDefault();
        closeProfile();
      });
      $(document).on('keydown.paxLiveProfile', function (e) {
        if (e.key === 'Escape' && $profileModal.length && !$profileModal.prop('hidden')) {
          closeProfile();
        }
      });
    }

    function applySessionAgent(data) {
      if (!data) return;
      if (data.assigned_agent && data.assigned_agent.name) {
        sessionAssignedAgent = data.assigned_agent;
      } else if (data.admin_name) {
        sessionAssignedAgent = { name: data.admin_name, avatar: '', role: '' };
      }
    }

    function updateHandlerUi(handler, adminName) {
      currentHandler = handler || 'ai';
      var isAdmin = currentHandler === 'admin';
      var isClosed = currentHandler === 'closed';
      $handlerBadge
        .removeClass('pax-live-badge--ai pax-live-badge--live pax-live-badge--admin pax-live-badge--closed')
        .addClass(badgeClass(currentHandler))
        .text(handlerLabel(currentHandler, adminName));
      $takeover.prop('hidden', isAdmin || isClosed);
      $release.prop('hidden', !isAdmin);
      $reopen.prop('hidden', !isClosed);
      $close.prop('hidden', isClosed);

      if (!selectedSession || isClosed) {
        $compose.prop('hidden', true);
        $composeHint.prop('hidden', true);
        setAssistVisible(false);
        $input.prop('disabled', true);
        $send.prop('disabled', true);
        if ($attach.length) $attach.prop('disabled', true);
        if ($attachLabel.length) $attachLabel.addClass('is-disabled');
        return;
      }

      $compose.prop('hidden', false);
      setAssistVisible(isAdmin);
      if (!isAdmin) clearAiSuggestions();

      if (isAdmin) {
        $composeHint.prop('hidden', true).text('');
        $input.prop('disabled', false).attr('placeholder', 'Antwort an den Kunden …');
        $send.prop('disabled', false);
        if ($attach.length) $attach.prop('disabled', false);
        if ($attachLabel.length) $attachLabel.removeClass('is-disabled');
        maybeSuggestForLatestUserMessage();
      } else {
        $composeHint
          .prop('hidden', false)
          .text(isMobileView()
            ? 'Tippen Sie auf „Übernehmen“, um zu antworten.'
            : 'Klicken Sie auf „Übernehmen“, um dem Kunden zu antworten. Sie sehen „Kunde schreibt …“, wenn er tippt.');
        $input.prop('disabled', true).attr('placeholder', 'Zuerst Chat übernehmen …');
        $send.prop('disabled', true);
        if ($attach.length) $attach.prop('disabled', true);
        if ($attachLabel.length) $attachLabel.addClass('is-disabled');
      }
    }

    function messageBubbleHtml(msg) {
      var html = '';
      if (msg.image_url) {
        html += '<div class="pax-live-dashboard__msg-image"><img src="' + escapeHtml(msg.image_url) + '" alt="Foto" loading="lazy" decoding="async"></div>';
      }
      if (msg.content) {
        html += escapeHtml(String(msg.content));
      }
      return html || '<span class="pax-live-dashboard__msg-image-only">📷 Foto</span>';
    }

    function resizeChatImage(file) {
      return new Promise(function (resolve, reject) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
          reject(new Error('invalid'));
          return;
        }
        var reader = new FileReader();
        reader.onerror = reject;
        reader.onload = function (ev) {
          var img = new Image();
          img.onerror = reject;
          img.onload = function () {
            var maxW = 960;
            var w = img.width;
            var h = img.height;
            if (w > maxW) {
              h = Math.round(h * (maxW / w));
              w = maxW;
            }
            var canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            var ctx = canvas.getContext('2d');
            if (!ctx) {
              resolve(file);
              return;
            }
            ctx.drawImage(img, 0, 0, w, h);
            canvas.toBlob(function (blob) {
              if (!blob) {
                resolve(file);
                return;
              }
              resolve(new File([blob], 'chat.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.82);
          };
          img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
      });
    }

    function uploadAdminImage(file) {
      if (!selectedSession || !file) return $.Deferred().reject().promise();
      return resizeChatImage(file).then(function (optimized) {
        var formData = new FormData();
        formData.append('action', 'paxdesign_chat_live_admin_image');
        formData.append('nonce', cfg.nonce);
        formData.append('session_id', selectedSession);
        formData.append('image', optimized, optimized.name || 'chat.jpg');
        var caption = ($input.val() || '').trim();
        if (caption) formData.append('caption', caption);
        if (replyToId) formData.append('reply_to', String(replyToId));
        return $.ajax({
          url: cfg.ajaxUrl,
          method: 'POST',
          data: formData,
          processData: false,
          contentType: false,
        });
      });
    }

    var PAX_FEEDBACK_KEYS = {
      like: 'Gefällt mir',
      dislike: 'Gefällt mir nicht',
    };

    function normalizeMessageReaction(reaction) {
      if (reaction === 'like' || reaction === 'dislike') return reaction;
      if (reaction === 'pax-love') return 'like';
      if (reaction === 'pax-top' || reaction === 'pax-thanks' || reaction === 'pax-clear') return 'dislike';
      return '';
    }

    function reactionBadgeHtml(reaction) {
      var normalized = normalizeMessageReaction(reaction);
      if (!normalized) return '';
      var icon = normalized === 'like' ? FEEDBACK_ICONS.like : FEEDBACK_ICONS.dislike;
      return '<div class="pax-live-dashboard__reaction-picked pax-live-dashboard__reaction-picked--' + normalized + '" data-reaction="' + escapeHtml(normalized) + '" title="' + escapeHtml(PAX_FEEDBACK_KEYS[normalized]) + '">' + icon + '</div>';
    }

    function indexMessages(items) {
      (items || []).forEach(function (m) {
        if (m && m.id) sessionMessageMap[m.id] = m;
      });
    }

    function quoteHtml(replyTo) {
      var src = sessionMessageMap[replyTo];
      if (!src) return '';
      return '<div class="pax-live-dashboard__quote"><span class="pax-live-dashboard__quote-author">' +
        escapeHtml(roleLabel(src.role, src)) + '</span>' +
        escapeHtml(String(src.content || '').slice(0, 140)) + '</div>';
    }

    function setReplyTo(msgId) {
      var msg = sessionMessageMap[msgId];
      if (!msg) return;
      replyToId = msgId;
      $replyPreview.text(String(msg.content || '').slice(0, 100));
      $replyBar.prop('hidden', false);
      $input.focus();
    }

    function clearReply() {
      replyToId = 0;
      $replyBar.prop('hidden', true);
      $replyPreview.text('');
    }

    function appendMessageDom(msg) {
      var role = msg.role || 'assistant';
      var html;
      var isPending = !!msg._pending;

      if (role === 'system') {
        html = '<div class="pax-live-dashboard__msg pax-live-dashboard__msg--system' + (isPending ? ' is-pending' : '') + '" data-msg-id="' + msg.id + '">' + escapeHtml(msg.content) + '</div>';
        $messages.append(html);
        return;
      }

      var isOutgoing = role === 'admin';
      var mod = isOutgoing ? 'outgoing' : 'incoming';
      var label = roleLabel(role, msg);

      html = '<div class="pax-live-dashboard__msg pax-live-dashboard__msg--' + role + ' pax-live-dashboard__msg--' + mod + (isPending ? ' is-pending' : '') + '" data-msg-id="' + msg.id + '">';
      html += '<div class="pax-live-dashboard__msg-row">';

      if (!isOutgoing) {
        html += renderAvatar(role, label, msg);
      }

      html += '<div class="pax-live-dashboard__msg-stack">';
      html += '<div class="pax-live-dashboard__msg-head">';
      html += '<span class="pax-live-dashboard__msg-name">' + escapeHtml(label) + '</span>';
      if (role === 'user') {
        html += '<button type="button" class="pax-live-dashboard__reply-btn" data-reply-to="' + msg.id + '">↩ Antworten</button>';
      }
      html += '</div>';
      if (msg.reply_to) html += quoteHtml(msg.reply_to);
      html += '<div class="pax-live-dashboard__msg-bubble">' + messageBubbleHtml(msg) + '</div>';
      html += '</div>';

      if (isOutgoing) {
        html += renderAvatar(role, label, msg);
      }

      html += '</div>';
      if (msg.reaction) {
        html += reactionBadgeHtml(msg.reaction);
      }
      html += '</div>';
      $messages.append(html);
    }

    function newClientMessageId() {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
      }
      return 'admin-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    function appendOptimisticAdminMessage(text, replyTo, clientMsgId) {
      var tempId = 'pending-' + clientMsgId;
      var msg = {
        id: tempId,
        client_msg_id: clientMsgId,
        role: 'admin',
        content: text,
        _pending: true,
      };
      if (replyTo) msg.reply_to = replyTo;
      $messages.find('.pax-live-dashboard__empty').remove();
      appendMessageDom(msg);
      domMsgIds[tempId] = true;
      sessionMessageMap[tempId] = msg;
      $messages.scrollTop($messages[0].scrollHeight);
      return tempId;
    }

    function replaceOptimisticMessage(tempId, realMsg) {
      if (!realMsg || !realMsg.id) return;
      var $pending = $messages.find('[data-msg-id="' + tempId + '"]');
      delete domMsgIds[tempId];
      delete sessionMessageMap[tempId];
      if ($pending.length) {
        $pending.remove();
      }
      if (!domMsgIds[realMsg.id]) {
        renderMessages([realMsg], false);
      }
      $messages.scrollTop($messages[0].scrollHeight);
    }

    function removeOptimisticMessage(tempId) {
      delete domMsgIds[tempId];
      delete sessionMessageMap[tempId];
      $messages.find('[data-msg-id="' + tempId + '"]').remove();
    }

    function renderMessages(items, reset) {
      if (reset) {
        domMsgIds = {};
        sessionMessageMap = {};
        clearReply();
        hideCustomerTyping();
        $messages.empty();
        pollSeq = 0;
      }
      indexMessages(items);
      if (!items || !items.length) {
        if (reset) $messages.html('<p class="pax-live-dashboard__empty">Noch keine Nachrichten.</p>');
        return;
      }
      $messages.find('.pax-live-dashboard__empty').remove();
      items.sort(function (a, b) { return (a.id || 0) - (b.id || 0); });
      items.forEach(function (msg) {
        if (!msg || !msg.id || domMsgIds[msg.id]) return;
        domMsgIds[msg.id] = true;
        pollSeq = Math.max(pollSeq, msg.id);
        appendMessageDom(msg);
      });
      $messages.scrollTop($messages[0].scrollHeight);
    }

    function loadSession(sessionId, full) {
      if (!sessionId) return;
      ajax('paxdesign_chat_live_session', { session_id: sessionId }).done(function (res) {
        if (!res.success || !res.data) return;
        var data = res.data;
        if (full) {
          renderMessages(data.messages || [], true);
          $messages.attr('aria-busy', 'false');
        }
        applySessionAgent(data);
        updateHandlerUi(data.handler, data.admin_name);
        updateSessionHeader(data);
      }).fail(function () {
        if (!full) return;
        $messages.attr('aria-busy', 'false').html('<p class="pax-live-dashboard__error">Nachrichten konnten nicht geladen werden.</p>');
      });
    }

    function pollMessages() {
      if (!selectedSession) return;
      ajax('paxdesign_chat_poll', { session_id: selectedSession, since: pollSeq })
        .done(function (res) {
          if (!res.success || !res.data) return;
          applySessionAgent(res.data);
          updateHandlerUi(res.data.handler, res.data.admin_name);
          if (selectedSession) {
            if (!knownSessions[selectedSession]) knownSessions[selectedSession] = {};
            if (res.data.customer_name) {
              knownSessions[selectedSession].customer_name = res.data.customer_name;
            }
            if (typeof res.data.session_rating === 'number') {
              knownSessions[selectedSession].session_rating = res.data.session_rating;
            }
            if (res.data.detected_service) {
              knownSessions[selectedSession].detected_service = res.data.detected_service;
            }
            if (res.data.updated_at) {
              knownSessions[selectedSession].updated_at = res.data.updated_at;
            }
            updateSessionHeader(res.data);
          }
          var played = false;
          if (Array.isArray(res.data.messages) && res.data.messages.length) {
            var newUserMsgId = 0;
            res.data.messages.forEach(function (msg) {
              if (msg && msg.role === 'user' && !domMsgIds[msg.id]) played = true;
              if (msg && msg.role === 'user') newUserMsgId = msg.id;
            });
            renderMessages(res.data.messages, false);
            if (played && shouldPlayMessageNotification()) {
              playMessengerSound();
              hideCustomerTyping();
              var lastUserMsg = null;
              res.data.messages.forEach(function (msg) {
                if (msg && msg.role === 'user') lastUserMsg = msg;
              });
              if (lastUserMsg) {
                tryBrowserNotification(
                  String(lastUserMsg.content || '').slice(0, 120),
                  selectedSession,
                  'Neue Kundennachricht'
                );
                if (res.data.handler === 'admin' && lastUserMsg.id) {
                  fetchAiSuggestions(lastUserMsg.id);
                }
              }
            } else if (newUserMsgId && res.data.handler === 'admin') {
              fetchAiSuggestions(newUserMsgId);
            }
            $messages.scrollTop($messages[0].scrollHeight);
          }
          if (res.data.user_typing) {
            showCustomerTyping();
          } else {
            hideCustomerTyping();
          }
          if (res.data.reactions) {
            Object.keys(res.data.reactions).forEach(function (id) {
              var $msg = $messages.find('[data-msg-id="' + id + '"]');
              if (!$msg.length) return;
              $msg.find('.pax-live-dashboard__reaction-picked').remove();
              var reaction = res.data.reactions[id];
              if (reaction) $msg.append(reactionBadgeHtml(reaction));
            });
          }
          if (typeof res.data.seq === 'number') pollSeq = Math.max(pollSeq, res.data.seq);
        });
    }

    function selectSession(sessionId) {
      selectedSession = sessionId;
      suppressMessageSoundsUntil = Date.now() + 1800;
      clearAiSuggestions();
      if (knownSessions[sessionId] && knownSessions[sessionId].handler === 'live_request') {
        seenLiveRequests[sessionId] = Date.now();
        persistSeenLiveRequests();
        stopLiveRequestAlarm();
        hideLiveAlertBar();
      }
      $placeholder.prop('hidden', true);
      $active.prop('hidden', false);
      updateSessionHeader({});
      renderMessagesLoadingSkeleton();
      loadSession(sessionId, true);
      pollMessages();
      scheduleMsgPoll();
      startAdminStream();
      syncSelectedListItem();
      updateMobilePanels();
    }

    $back.on('click', function () {
      $root.removeClass('is-chat-open');
      if (isMobileView()) {
        $placeholder.prop('hidden', false);
        $active.prop('hidden', true);
        selectedSession = '';
        if (msgTimer) {
          clearInterval(msgTimer);
          msgTimer = null;
        }
        clearReply();
        hideCustomerTyping();
        $list.find('.pax-live-dashboard__item').removeClass('is-selected');
      }
    });

    $(window).on('resize', updateMobilePanels);

    $root.on('click keydown', unlockAudio);

    $list.on('click', '.pax-live-dashboard__item', function () {
      selectSession($(this).data('session'));
    });

    $refresh.on('click', function () {
      manualRefresh();
    });
    $search.on('input', function () { renderList(allSessions); });

    $takeover.on('click', function () {
      if (!selectedSession) return;
      if (!window.confirm('Möchten Sie diesen Chat übernehmen? Die KI wird sofort pausiert.')) return;
      ajax('paxdesign_chat_live_takeover', { session_id: selectedSession }).done(function (res) {
        if (!res.success) {
          alert(res.data && res.data.message ? res.data.message : 'Übernahme fehlgeschlagen.');
          return;
        }
        seenLiveRequests[selectedSession] = Date.now();
        persistSeenLiveRequests();
        stopLiveRequestAlarm();
        hideLiveAlertBar();
        updateHandlerUi('admin', res.data.admin_name);
        if (res.data.message) renderMessages([res.data.message], false);
        maybeSuggestForLatestUserMessage();
        loadList();
      });
    });

    $release.on('click', function () {
      if (!selectedSession) return;
      if (!window.confirm('Chat wieder an die KI übergeben?')) return;
      ajax('paxdesign_chat_live_release', { session_id: selectedSession }).done(function (res) {
        if (!res.success) {
          alert(res.data && res.data.message ? res.data.message : 'Freigabe fehlgeschlagen.');
          return;
        }
        updateHandlerUi('ai', '');
        if (res.data.message) renderMessages([res.data.message], false);
        loadList();
      });
    });

    $reopen.on('click', function () {
      if (!selectedSession) return;
      if (!window.confirm('Diesen Chat wieder öffnen? Der Kunde kann dann erneut schreiben.')) return;
      var reopenId = selectedSession;
      patchSessionInList(reopenId, {
        handler: 'admin',
        handler_label: 'Live-Agent',
        updated_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
      });
      ajax('paxdesign_chat_live_reopen', { session_id: reopenId }).done(function (res) {
        if (!res.success) {
          loadList();
          alert(res.data && res.data.message ? res.data.message : 'Wiederöffnen fehlgeschlagen.');
          return;
        }
        updateHandlerUi('admin', res.data.admin_name);
        if (res.data.message) renderMessages([res.data.message], false);
        maybeSuggestForLatestUserMessage();
        loadList();
      }).fail(function () {
        loadList();
      });
    });

    $close.on('click', function () {
      if (!selectedSession) return;
      if (!window.confirm('Diesen Chat schließen? Der Kunde kann ein neues Gespräch starten oder Sie können den Chat später wieder öffnen.')) return;
      var closingId = selectedSession;
      patchSessionInList(closingId, {
        handler: 'closed',
        handler_label: 'Geschlossen',
        updated_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
      });
      ajax('paxdesign_chat_live_close', { session_id: closingId }).done(function (res) {
        if (!res.success) {
          loadList();
          alert(res.data && res.data.message ? res.data.message : 'Schließen fehlgeschlagen.');
          return;
        }
        updateHandlerUi('closed', '');
        playClosingSound();
        if (res.data.message) renderMessages([res.data.message], false);
        loadList();
      }).fail(function () {
        loadList();
      });
    });

    $messages.on('click', '.pax-live-dashboard__reply-btn', function () {
      setReplyTo(parseInt($(this).attr('data-reply-to'), 10));
    });

    $replyClear.on('click', clearReply);

    $send.on('click', function () {
      var text = ($input.val() || '').trim();
      if (!text || !selectedSession) return;
      if ($send.prop('disabled')) return;
      clearAdminTypingState();
      var replyTo = replyToId || 0;
      var clientMsgId = newClientMessageId();
      var tempId = appendOptimisticAdminMessage(text, replyTo, clientMsgId);
      $input.val('');
      resetComposerHeight();
      clearReply();
      $send.prop('disabled', true);
      var payload = { session_id: selectedSession, message: text, client_msg_id: clientMsgId };
      if (replyTo) payload.reply_to = replyTo;
      ajax('paxdesign_chat_live_admin_send', payload)
        .done(function (res) {
          if (!res.success) {
            removeOptimisticMessage(tempId);
            alert(res.data && res.data.message ? res.data.message : 'Senden fehlgeschlagen.');
            return;
          }
          replaceOptimisticMessage(tempId, res.data.message);
          pollMessages();
          loadList();
        })
        .fail(function () {
          removeOptimisticMessage(tempId);
          alert('Senden fehlgeschlagen. Bitte erneut versuchen.');
        })
        .always(function () {
          if (!$input.prop('disabled')) $send.prop('disabled', false);
        });
    });

    if ($attach.length) {
      $attach.on('change', function () {
        var file = this.files && this.files[0];
        this.value = '';
        if (!file || !selectedSession || $attach.prop('disabled')) return;
        $send.prop('disabled', true);
        if ($attachLabel.length) $attachLabel.addClass('is-busy');
        uploadAdminImage(file)
          .done(function (res) {
            if (!res.success) {
              alert(res.data && res.data.message ? res.data.message : 'Bild konnte nicht gesendet werden.');
              return;
            }
            $input.val('');
            clearReply();
            if (res.data && res.data.message) renderMessages([res.data.message], false);
            pollMessages();
            $messages.scrollTop($messages[0].scrollHeight);
          })
          .fail(function () {
            alert('Bild konnte nicht gesendet werden.');
          })
          .always(function () {
            if ($attachLabel.length) $attachLabel.removeClass('is-busy');
            if (!$input.prop('disabled')) $send.prop('disabled', false);
          });
      });
    }

    $input.on('input', scheduleAdminTypingPing);
    $input.on('keydown', function (e) {
      scheduleAdminTypingPing();
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        clearAdminTypingState();
        $send.trigger('click');
      }
    });
    $input.on('blur', clearAdminTypingState);

    bindComposerResize();
    initQuickReplies();
    initAiSuggestions();
    initAgentProfileModal();
    initLanguageToggle();
    if ($restartTourBtn.length) {
      $restartTourBtn.on('click', function () {
        markTourCompleted(false);
        startTour(true);
      });
    }
    loadList();
    scheduleListPoll();
    startAdminStream();

    window.setTimeout(function () {
      startTour(false);
    }, 850);

    if (window.paxLiveOpenSession) {
      window.setTimeout(function () {
        selectSession(window.paxLiveOpenSession);
      }, 400);
    }

    window.addEventListener('pax-open-session', function (event) {
      var session = event.detail && event.detail.session;
      if (session) selectSession(session);
    });
    window.addEventListener('resize', function () {
      if (tourState.active) renderTourStep();
    });
  });
})(jQuery);
