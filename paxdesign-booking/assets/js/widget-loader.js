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

  if ('requestIdleCallback' in window) {
    requestIdleCallback(preloadChat, { timeout: 5000 });
  } else {
    setTimeout(preloadChat, 4000);
  }

  document.addEventListener(
    'click',
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
        preloadChat();
      }
    },
    true
  );

  document.addEventListener(
    'mouseover',
    function (event) {
      var target = event.target;
      if (!target || !target.closest) {
        return;
      }
      if (target.closest('.paxdesign-booking-button')) {
        preloadChat();
      }
    },
    { passive: true, once: true }
  );
})();
