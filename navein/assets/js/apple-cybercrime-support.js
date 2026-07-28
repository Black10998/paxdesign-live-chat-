/**
 * Cybercrime Support page — language toggle + Live Chat page context (no extra chat UI).
 */
(function () {
  'use strict';

  var root = document.querySelector('.pax-ccs');
  if (!root) {
    return;
  }

  function setPageContext(lang) {
    window.PAXdesignPageContext = window.PAXdesignPageContext || {};
    window.PAXdesignPageContext.intent = 'cybercrime-support';
    if (lang) {
      window.PAXdesignPageContext.language = lang;
    }
  }

  function setLang(lang) {
    if (lang !== 'ar' && lang !== 'de') {
      lang = 'ar';
    }

    root.setAttribute('data-ccs-lang', lang);
    root.setAttribute('lang', lang);
    root.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

    root.querySelectorAll('.pax-ccs-t').forEach(function (el) {
      el.hidden = el.getAttribute('data-lang') !== lang;
    });

    root.querySelectorAll('[data-ccs-switch]').forEach(function (btn) {
      var active = btn.getAttribute('data-ccs-switch') === lang;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    setPageContext(lang);

    try {
      localStorage.setItem('pax-ccs-lang', lang);
    } catch (e) {}
  }

  root.querySelectorAll('[data-ccs-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setLang(btn.getAttribute('data-ccs-switch'));
    });
  });

  var saved = '';
  try {
    saved = localStorage.getItem('pax-ccs-lang') || '';
  } catch (e) {}

  setLang(saved === 'de' ? 'de' : 'ar');
})();
