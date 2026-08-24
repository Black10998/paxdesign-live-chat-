/**
 * Site language: Apple-style switcher, auto-detect, RTL, chrome translation.
 */
(function () {
  'use strict';

  var CFG = window.PAX_SITE_I18N || {};
  var SUPPORTED = CFG.supported || ['de', 'en', 'ar', 'tr'];
  var COOKIE = 'pax_site_lang';
  var COOKIE_SRC = 'pax_site_lang_src';
  var YEAR = 365 * 24 * 60 * 60;

  function normalize(raw) {
    var code = String(raw || '').toLowerCase().replace(/_/g, '-');
    if (code.indexOf('-') !== -1) code = code.split('-')[0];
    code = code.slice(0, 2);
    return SUPPORTED.indexOf(code) !== -1 ? code : '';
  }

  function readCookie(name) {
    var parts = ('; ' + document.cookie).split('; ' + name + '=');
    if (parts.length < 2) return '';
    return decodeURIComponent(parts.pop().split(';').shift() || '');
  }

  function writeCookie(name, value) {
    var secure = location.protocol === 'https:' ? ';Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + ';path=/;max-age=' + YEAR + ';SameSite=Lax' + secure;
  }

  function detectBrowserLang() {
    var list = [];
    if (navigator.languages && navigator.languages.length) {
      list = navigator.languages;
    } else if (navigator.language) {
      list = [navigator.language];
    } else if (navigator.userLanguage) {
      list = [navigator.userLanguage];
    }
    for (var i = 0; i < list.length; i++) {
      var code = normalize(list[i]);
      if (code) return code;
    }
    return '';
  }

  function currentLang() {
    return normalize(CFG.lang) || normalize(document.documentElement.lang) || 'de';
  }

  function currentSource() {
    return readCookie(COOKIE_SRC) === 'manual' || CFG.source === 'manual' ? 'manual' : 'auto';
  }

  function applyDocumentLocale(lang) {
    var rtl = lang === 'ar';
    document.documentElement.setAttribute('lang', lang);
    document.documentElement.setAttribute('dir', rtl ? 'rtl' : 'ltr');
    document.body.classList.toggle('pax-dir-rtl', rtl);
    document.body.classList.toggle('pax-lang-ar', lang === 'ar');
    document.body.classList.toggle('rtl', rtl);
    ['pax-lang-de', 'pax-lang-en', 'pax-lang-ar', 'pax-lang-tr'].forEach(function (cls) {
      document.body.classList.remove(cls);
    });
    document.body.classList.add('pax-lang-' + lang);
  }

  function phraseTarget(entry, lang) {
    if (!entry) return '';
    return entry[lang] || entry.en || entry.de || '';
  }

  function applyPhrases(lang) {
    var phrases = CFG.phrases || [];
    if (!phrases.length) return;
    var lookup = {};
    phrases.forEach(function (entry) {
      SUPPORTED.forEach(function (from) {
        var src = entry[from];
        if (src) lookup[src] = entry;
      });
    });

    var skip = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, INPUT: 1, CODE: 1, PRE: 1 };
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var parent = node.parentElement;
        if (!parent || skip[parent.tagName]) return NodeFilter.FILTER_REJECT;
        if (parent.closest('.pax-site-lang, .pax-ccs-portal, #pdx-account-app, .paxdesign-chat-widget')) {
          return NodeFilter.FILTER_REJECT;
        }
        return node.nodeValue && node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });

    var node;
    while ((node = walker.nextNode())) {
      var raw = node.nodeValue;
      var trimmed = raw.replace(/^\s+|\s+$/g, '');
      var entry = lookup[trimmed];
      if (!entry) continue;
      var next = phraseTarget(entry, lang);
      if (!next || next === trimmed) continue;
      node.nodeValue = raw.replace(trimmed, next);
    }

    document.querySelectorAll('[placeholder]').forEach(function (el) {
      if (el.closest('.pax-ccs-portal, #pdx-account-app, .paxdesign-chat-widget')) return;
      var entry = lookup[el.getAttribute('placeholder')];
      if (!entry) return;
      var next = phraseTarget(entry, lang);
      if (next) el.setAttribute('placeholder', next);
    });

    document.querySelectorAll('[aria-label]').forEach(function (el) {
      var entry = lookup[el.getAttribute('aria-label')];
      if (!entry) return;
      var next = phraseTarget(entry, lang);
      if (next) el.setAttribute('aria-label', next);
    });

    var cta = document.querySelector('#dtr-header-global .dtr-header-btn .dtr-btn__text, #dtr-header-global a.dtr-header-btn');
    if (cta && lookup[cta.textContent.trim()]) {
      var ctaNext = phraseTarget(lookup[cta.textContent.trim()], lang);
      if (ctaNext) {
        var textEl = cta.querySelector('.dtr-btn__text');
        if (textEl) textEl.textContent = ctaNext;
        else if (cta.childNodes.length === 1) cta.textContent = ctaNext;
      }
    }
  }

  function setLang(lang, source, reload) {
    lang = normalize(lang) || 'de';
    source = source === 'manual' ? 'manual' : 'auto';
    writeCookie(COOKIE, lang);
    writeCookie(COOKIE_SRC, source);
    CFG.lang = lang;
    CFG.source = source;
    applyDocumentLocale(lang);
    document.querySelectorAll('.pax-site-lang').forEach(function (root) {
      syncSwitcher(root, lang);
    });
    try {
      localStorage.setItem('pax_site_lang', lang);
      localStorage.setItem('pax_site_lang_src', source);
      localStorage.setItem('pax-ccs-lang', lang === 'tr' ? 'en' : lang);
    } catch (e) {}
    document.dispatchEvent(new CustomEvent('pax-site-lang-change', { detail: { lang: lang, source: source } }));
    if (reload) {
      var url = new URL(location.href);
      if (url.searchParams.get('lang')) {
        url.searchParams.delete('lang');
        location.assign(url.toString());
        return;
      }
      location.reload();
    }
  }

  function syncSwitcher(root, lang) {
    if (!root) return;
    root.setAttribute('data-pax-lang', lang);
    var code = root.querySelector('.pax-site-lang__code');
    if (code) code.textContent = lang.toUpperCase();
    var btn = root.querySelector('.pax-site-lang__btn');
    if (btn) {
      var label = (CFG.labels && CFG.labels.language) || 'Language';
      btn.setAttribute('aria-label', label + ': ' + lang.toUpperCase());
    }
    root.querySelectorAll('.pax-site-lang__option').forEach(function (opt) {
      var active = opt.getAttribute('data-lang') === lang;
      opt.classList.toggle('is-active', active);
      opt.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function closeMenus() {
    document.querySelectorAll('.pax-site-lang').forEach(function (root) {
      root.classList.remove('is-open');
      var btn = root.querySelector('.pax-site-lang__btn');
      var menu = root.querySelector('.pax-site-lang__menu');
      if (btn) btn.setAttribute('aria-expanded', 'false');
      if (menu) menu.hidden = true;
    });
  }

  function positionMenu(root) {
    var btn = root.querySelector('.pax-site-lang__btn');
    var menu = root.querySelector('.pax-site-lang__menu');
    if (!btn || !menu) return;
    var rect = btn.getBoundingClientRect();
    var rtl = document.documentElement.getAttribute('dir') === 'rtl';
    menu.style.position = 'fixed';
    menu.style.top = Math.round(rect.bottom + 8) + 'px';
    if (rtl) {
      menu.style.right = Math.max(12, Math.round(window.innerWidth - rect.right)) + 'px';
      menu.style.left = 'auto';
    } else {
      var left = rect.right - 220;
      if (left < 12) left = 12;
      if (left + 220 > window.innerWidth - 12) left = window.innerWidth - 232;
      menu.style.left = Math.round(left) + 'px';
      menu.style.right = 'auto';
    }
  }

  function toggleMenu(root) {
    var open = !root.classList.contains('is-open');
    closeMenus();
    if (!open) return;
    root.classList.add('is-open');
    var btn = root.querySelector('.pax-site-lang__btn');
    var menu = root.querySelector('.pax-site-lang__menu');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    if (menu) {
      menu.hidden = false;
      positionMenu(root);
    }
  }

  function bindSwitcher(root) {
    if (!root || root.getAttribute('data-pax-bound') === '1') return;
    root.setAttribute('data-pax-bound', '1');
    var btn = root.querySelector('.pax-site-lang__btn');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleMenu(root);
      });
    }
    root.querySelectorAll('.pax-site-lang__option').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var lang = opt.getAttribute('data-lang');
        closeMenus();
        if (lang && lang !== currentLang()) {
          setLang(lang, 'manual', true);
        }
      });
    });
  }

  function maybeAutoDetect() {
    if (readCookie(COOKIE) || currentSource() === 'manual') return;
    var detected = detectBrowserLang();
    if (!detected) return;
    if (detected === currentLang()) {
      writeCookie(COOKIE, detected);
      writeCookie(COOKIE_SRC, 'auto');
      return;
    }
    setLang(detected, 'auto', true);
  }

  function init() {
    var lang = currentLang();
    applyDocumentLocale(lang);
    applyPhrases(lang);
    document.querySelectorAll('.pax-site-lang').forEach(function (root) {
      bindSwitcher(root);
      syncSwitcher(root, lang);
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.pax-site-lang')) closeMenus();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenus();
    });
    window.addEventListener('resize', function () {
      document.querySelectorAll('.pax-site-lang.is-open').forEach(positionMenu);
    });
    maybeAutoDetect();
  }

  window.PaxSiteI18n = {
    lang: currentLang,
    setLang: function (lang) {
      lang = normalize(lang) || 'de';
      if (lang === currentLang() && currentSource() === 'manual') return;
      setLang(lang, 'manual', lang !== currentLang());
    },
    supported: SUPPORTED
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
