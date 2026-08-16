/**
 * Prefetch and lazy-load chat script so the widget can open without waiting.
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

  // Start downloading the chat bundle immediately after this deferred loader
  // runs — do not wait for the first tap. Opening the panel must never stall
  // on a ~200KB parse if the file is already in flight / cached.
  preloadChat();
})();
