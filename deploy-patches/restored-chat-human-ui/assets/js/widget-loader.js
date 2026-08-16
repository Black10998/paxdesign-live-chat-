/**
 * Lazy-load chat script on page load and first widget interaction (keeps initial load light).
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

  preloadChat();

  function shouldPreloadFromEvent(target) {
    if (!target || !target.closest) {
      return false;
    }
    return !!target.closest(
      '.paxdesign-booking-button, #paxdesign-booking-root, [data-paxdesign-open-chat]'
    );
  }

  document.addEventListener(
    'pointerdown',
    function (event) {
      if (shouldPreloadFromEvent(event.target)) {
        preloadChat();
      }
    },
    true
  );

  document.addEventListener(
    'mousedown',
    function (event) {
      if (shouldPreloadFromEvent(event.target)) {
        preloadChat();
      }
    },
    true
  );

  document.addEventListener(
    'click',
    function (event) {
      if (shouldPreloadFromEvent(event.target)) {
        preloadChat();
      }
    },
    true
  );

  document.addEventListener(
    'mouseover',
    function (event) {
      if (event.target && event.target.closest && event.target.closest('.paxdesign-booking-button')) {
        preloadChat();
      }
    },
    { passive: true }
  );
})();
