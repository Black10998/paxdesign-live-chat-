/**
 * PAXdesign Live Chat shortcode — fullscreen canvas (no theme header/footer).
 */
(function () {
  'use strict';

  var MAX_ATTEMPTS = 24;
  var attempts = 0;
  var done = false;

  function isConsole(el) {
    return el && el.nodeType === 1 && el.classList && el.classList.contains('pax-live-console');
  }

  function shouldKeepNode(node, consoleEl) {
    if (!node || node.nodeType !== 1) return true;
    if (node === consoleEl) return true;
    if (node.contains(consoleEl)) return true;
    var tag = node.tagName;
    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK') return true;
    return false;
  }

  function hideThemeChrome(consoleEl) {
    document.documentElement.classList.add('pax-live-shortcode-fullscreen-root');
    document.body.classList.add('pax-live-shortcode-fullscreen');

    if (consoleEl.parentNode !== document.body) {
      document.body.appendChild(consoleEl);
    }

    Array.prototype.forEach.call(document.body.children, function (node) {
      if (shouldKeepNode(node, consoleEl)) return;
      node.classList.add('pax-live-fs-hidden');
      node.setAttribute('aria-hidden', 'true');
    });

    var adminBar = document.getElementById('wpadminbar');
    if (adminBar) {
      adminBar.classList.add('pax-live-fs-hidden');
    }

    document.body.style.margin = '0';
    document.body.style.padding = '0';
    done = true;
  }

  function boot() {
    if (done) return;

    var consoleEl = document.getElementById('paxLiveChatDashboard');
    if (!isConsole(consoleEl)) {
      attempts += 1;
      if (attempts < MAX_ATTEMPTS) {
        window.setTimeout(boot, 200);
      }
      return;
    }

    hideThemeChrome(consoleEl);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('load', boot);
  window.addEventListener('pageshow', boot);
})();
