/**
 * Lazy-load chat script after idle or first widget interaction (keeps initial load light).
 */
(function () {
  'use strict';

  var cfg = window.paxdesignWidgetLoader || {};
  var chatPromise = null;

  function loadChatScript() {
    if (chatPromise) {
      return chatPromise;
    }
    if (window.PAXdesignChat && typeof window.PAXdesignChat.init === 'function') {
      return Promise.resolve();
    }
    if (!cfg.chatSrc) {
      return Promise.resolve();
    }

    chatPromise = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = cfg.chatSrc;
      script.async = true;
      script.onload = function () {
        resolve();
      };
      script.onerror = function () {
        chatPromise = null;
        reject(new Error('chat-script-load-failed'));
      };
      document.body.appendChild(script);
    });

    return chatPromise;
  }

  function preloadChat() {
    loadChatScript().catch(function () {});
  }

  window.PAXdesignWidgetLoader = {
    ensureChat: loadChatScript,
    preload: preloadChat,
  };

  // Warm the chat bundle early enough that clicking the launcher opens the panel
  // instantly instead of waiting for a ~200KB lazy load. We keep the initial page
  // load light (the bundle is still fetched async, never render-blocking) but warm
  // it on the FIRST real user interaction — including touch, which the old
  // mouseover-only hint never caught on mobile — and shortly after load as a
  // fallback. The launcher itself warms on approach/touch for an extra head start.
  var warmed = false;
  var INTERACTION_EVENTS = ['pointerdown', 'touchstart', 'keydown', 'scroll'];

  function removeInteractionWarmers() {
    INTERACTION_EVENTS.forEach(function (evt) {
      document.removeEventListener(evt, warmOnFirstInteraction, true);
    });
  }

  function warmOnFirstInteraction() {
    if (warmed) {
      return;
    }
    warmed = true;
    removeInteractionWarmers();
    preloadChat();
  }

  INTERACTION_EVENTS.forEach(function (evt) {
    document.addEventListener(evt, warmOnFirstInteraction, {
      capture: true,
      passive: true,
    });
  });

  // Fallback: warm shortly after load even without interaction (mobile Safari has
  // no requestIdleCallback, so the old 4s setTimeout left early taps cold).
  if ('requestIdleCallback' in window) {
    requestIdleCallback(warmOnFirstInteraction, { timeout: 2000 });
  } else {
    setTimeout(warmOnFirstInteraction, 1200);
  }

  // Extra head start the moment the pointer approaches or touches the launcher.
  ['pointerenter', 'pointerdown', 'touchstart', 'focusin'].forEach(function (evt) {
    document.addEventListener(
      evt,
      function (event) {
        var target = event.target;
        if (!target || !target.closest) {
          return;
        }
        if (
          target.closest(
            '.paxdesign-booking-button, #paxdesign-booking-root, [data-paxdesign-open-chat]'
          )
        ) {
          warmOnFirstInteraction();
        }
      },
      { capture: true, passive: true }
    );
  });
})();
