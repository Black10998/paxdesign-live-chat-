/**
 * Load chat script on first explicit widget interaction (no idle preload).
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

  window.PAXdesignWidgetLoader = {
    ensureChat: loadChatScript,
    preload: loadChatScript,
  };
})();
