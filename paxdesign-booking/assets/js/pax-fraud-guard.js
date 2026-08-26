(function () {
  'use strict';
  if (window.__paxFraudGuard) {
    return;
  }
  window.__paxFraudGuard = 1;

  var cfg = window.paxFraudGuard || {};
  var STORAGE_KEY = 'pax_did';
  var deviceId = readId();
  var challengeToken = '';
  var collecting = false;
  var modalOpen = false;

  attachHeaders();
  idle(collectAndSend);

  function idle(fn) {
    if (typeof window.requestIdleCallback === 'function') {
      window.requestIdleCallback(fn, { timeout: 1500 });
      return;
    }
    setTimeout(fn, 0);
  }

  function readId() {
    try {
      var id = sessionStorage.getItem(STORAGE_KEY) || localStorage.getItem(STORAGE_KEY) || '';
      if (isUuid(id)) {
        return id;
      }
    } catch (e) {}
    var created = uuid();
    try {
      sessionStorage.setItem(STORAGE_KEY, created);
      localStorage.setItem(STORAGE_KEY, created);
    } catch (e2) {}
    return created;
  }

  function isUuid(id) {
    return /^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(String(id || ''));
  }

  function uuid() {
    var buf = new Uint8Array(16);
    if (window.crypto && crypto.getRandomValues) {
      crypto.getRandomValues(buf);
    } else {
      for (var i = 0; i < 16; i++) buf[i] = (Math.random() * 256) | 0;
    }
    buf[6] = (buf[6] & 0x0f) | 0x40;
    buf[8] = (buf[8] & 0x3f) | 0x80;
    var hex = [];
    for (var j = 0; j < 16; j++) hex.push((buf[j] + 256).toString(16).slice(1));
    return hex.slice(0, 4).join('') + '-' + hex.slice(4, 6).join('') + '-' + hex.slice(6, 8).join('') + '-' + hex.slice(8, 10).join('') + '-' + hex.slice(10).join('');
  }

  function collectAndSend() {
    if (collecting) return;
    collecting = true;
    var started = now();
    var signals;
    try {
      signals = collectSignals();
    } catch (e) {
      signals = { ua: navigator.userAgent || '' };
    }
    signals.collected_ms = Math.max(0, now() - started);
    postRisk(signals);
  }

  function now() {
    return (window.performance && performance.now) ? performance.now() : Date.now();
  }

  function collectSignals() {
    var nav = navigator || {};
    var scr = window.screen || {};
    var langs = [];
    try {
      langs = nav.languages ? Array.prototype.slice.call(nav.languages, 0, 8) : (nav.language ? [nav.language] : []);
    } catch (e) {}
    var tz = '';
    try {
      tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e2) {}
    var gl = webglInfo();
    return {
      webdriver: !!nav.webdriver,
      ua: String(nav.userAgent || '').slice(0, 240),
      platform: String(nav.platform || '').slice(0, 80),
      vendor: String(nav.vendor || '').slice(0, 80),
      languages: langs,
      timezone: tz,
      timezone_offset: new Date().getTimezoneOffset(),
      screen_w: scr.width || 0,
      screen_h: scr.height || 0,
      avail_w: scr.availWidth || 0,
      avail_h: scr.availHeight || 0,
      color_depth: scr.colorDepth || 0,
      pixel_ratio: window.devicePixelRatio || 1,
      hardware_concurrency: nav.hardwareConcurrency || 0,
      device_memory: nav.deviceMemory || 0,
      max_touch_points: nav.maxTouchPoints || 0,
      canvas: canvasHash(),
      webgl_vendor: gl.v,
      webgl_renderer: gl.r,
      plugins: (nav.plugins && nav.plugins.length) || 0,
      cookie_enabled: nav.cookieEnabled !== false,
      dnt: String(nav.doNotTrack || ''),
      has_storage: hasStorage(),
      touch: ('ontouchstart' in window) || (nav.maxTouchPoints > 0)
    };
  }

  function canvasHash() {
    try {
      var c = document.createElement('canvas');
      c.width = 220;
      c.height = 28;
      var x = c.getContext && c.getContext('2d');
      if (!x) return '';
      x.textBaseline = 'top';
      x.font = '14px Arial';
      x.fillStyle = '#f60';
      x.fillRect(0, 0, 220, 28);
      x.fillStyle = '#069';
      x.fillText('PAX,device', 2, 4);
      var data = c.toDataURL();
      return data.slice(-48);
    } catch (e) {
      return '';
    }
  }

  function webglInfo() {
    try {
      var c = document.createElement('canvas');
      var g = c.getContext('webgl') || c.getContext('experimental-webgl');
      if (!g) return { v: '', r: '' };
      var ext = g.getExtension('WEBGL_debug_renderer_info');
      return {
        v: ext ? String(g.getParameter(ext.UNMASKED_VENDOR_WEBGL) || '') : '',
        r: ext ? String(g.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '') : String(g.getParameter(g.RENDERER) || '')
      };
    } catch (e) {
      return { v: '', r: '' };
    }
  }

  function hasStorage() {
    try {
      sessionStorage.setItem('__paxfg', '1');
      sessionStorage.removeItem('__paxfg');
      return true;
    } catch (e) {
      return false;
    }
  }

  function postRisk(signals) {
    var url = cfg.riskUrl;
    if (!url || typeof window.fetch !== 'function') return;
    var ctrl = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () {
      if (ctrl) ctrl.abort();
    }, 2500);
    window.fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      signal: ctrl ? ctrl.signal : undefined,
      headers: withHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ device_id: deviceId, signals: signals })
    }).then(function (res) {
      return res.json().catch(function () { return {}; });
    }).then(function (body) {
      if (body && isUuid(body.device_id)) {
        deviceId = body.device_id;
        try {
          sessionStorage.setItem(STORAGE_KEY, deviceId);
          localStorage.setItem(STORAGE_KEY, deviceId);
        } catch (e) {}
      }
    }).catch(function () {}).then(function () {
      clearTimeout(timer);
    });
  }

  function withHeaders(base) {
    var headers = base || {};
    if (deviceId) headers['X-PAX-Device-Id'] = deviceId;
    if (challengeToken) headers['X-PAX-Challenge'] = challengeToken;
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    return headers;
  }

  function attachHeaders() {
    if (typeof window.fetch === 'function') {
      var origFetch = window.fetch;
      window.fetch = function (input, init) {
        init = init || {};
        init.headers = mergeHeaders(init.headers);
        return origFetch.call(this, input, init).then(function (res) {
          if (res.status !== 428) return res;
          return res.clone().json().then(function (body) {
            var ch = challengeFrom(body);
            if (!ch) return res;
            return openChallenge(ch).then(function (ok) {
              if (!ok) return res;
              init.headers = mergeHeaders(init.headers);
              return origFetch.call(window, input, init);
            });
          }).catch(function () { return res; });
        });
      };
    }

    if (window.XMLHttpRequest && XMLHttpRequest.prototype) {
      var origSend = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype.send = function (body) {
        try {
          if (deviceId) this.setRequestHeader('X-PAX-Device-Id', deviceId);
          if (challengeToken) this.setRequestHeader('X-PAX-Challenge', challengeToken);
        } catch (e) {}
        return origSend.call(this, body);
      };
    }

    if (window.jQuery && typeof window.jQuery.ajaxPrefilter === 'function') {
      window.jQuery.ajaxPrefilter(function (_opts, _orig, xhr) {
        try {
          if (deviceId) xhr.setRequestHeader('X-PAX-Device-Id', deviceId);
          if (challengeToken) xhr.setRequestHeader('X-PAX-Challenge', challengeToken);
        } catch (e) {}
      });
      window.jQuery(document).ajaxError(function (_e, xhr) {
        if (!xhr || xhr.status !== 428 || modalOpen) return;
        var body = {};
        try { body = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        var payload = body.data || body;
        var ch = challengeFrom(payload) || challengeFrom(body);
        if (ch) openChallenge(ch);
      });
    }
  }

  function mergeHeaders(existing) {
    var headers = {};
    if (existing && typeof existing.forEach === 'function') {
      existing.forEach(function (v, k) { headers[k] = v; });
    } else if (existing) {
      Object.keys(existing).forEach(function (k) { headers[k] = existing[k]; });
    }
    return withHeaders(headers);
  }

  function challengeFrom(body) {
    if (!body) return null;
    var code = body.code || body.error || (body.data && (body.data.code || body.data.error));
    if (code !== 'pax_challenge_required') return null;
    var data = body.data && typeof body.data === 'object' ? body.data : body;
    return {
      token: data.challenge_token || body.challenge_token || '',
      message: data.message || body.message || '',
      hint: data.email_hint || body.email_hint || ''
    };
  }

  function t() {
    var lang = (document.documentElement.lang || '').toLowerCase();
    if (lang.indexOf('ar') === 0) {
      return {
        title: 'تحقق إضافي',
        body: 'يرجى تأكيد أنك لست روبوتًا. أرسلنا رمزًا مكوّنًا من 6 أرقام إلى بريدك.',
        placeholder: '000000',
        submit: 'تأكيد',
        cancel: 'إلغاء',
        error: 'الرمز غير صالح أو منتهٍ.'
      };
    }
    if (lang.indexOf('en') === 0) {
      return {
        title: 'Additional verification',
        body: 'Please confirm you are human. We sent a 6-digit code to your email.',
        placeholder: '000000',
        submit: 'Confirm',
        cancel: 'Cancel',
        error: 'That code is invalid or expired.'
      };
    }
    return {
      title: 'Zusätzliche Bestätigung',
      body: 'Bitte bestätigen Sie, dass Sie kein Bot sind. Wir haben einen 6-stelligen Code an Ihre E-Mail gesendet.',
      placeholder: '000000',
      submit: 'Bestätigen',
      cancel: 'Abbrechen',
      error: 'Der Code ist ungültig oder abgelaufen.'
    };
  }

  function openChallenge(ch) {
    if (modalOpen) {
      return Promise.resolve(!!challengeToken);
    }
    modalOpen = true;
    var copy = t();
    return new Promise(function (resolve) {
      var overlay = document.createElement('div');
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif';
      overlay.innerHTML = '<form style="background:#fff;color:#111;max-width:420px;width:100%;border-radius:16px;padding:24px;box-shadow:0 16px 60px rgba(0,0,0,.25)">' +
        '<h2 style="margin:0 0 8px;font-size:20px">' + esc(copy.title) + '</h2>' +
        '<p style="margin:0 0 16px;line-height:1.45;color:#444">' + esc(ch.message || copy.body) + (ch.hint ? ' (' + esc(ch.hint) + ')' : '') + '</p>' +
        '<input inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-label="' + esc(copy.placeholder) + '" style="width:100%;font-size:22px;letter-spacing:8px;text-align:center;padding:12px;border:1px solid #ddd;border-radius:10px;margin-bottom:10px" placeholder="' + esc(copy.placeholder) + '">' +
        '<p class="pax-fg-err" style="display:none;color:#b00020;margin:0 0 10px;font-size:13px">' + esc(copy.error) + '</p>' +
        '<div style="display:flex;gap:8px;justify-content:flex-end">' +
        '<button type="button" data-act="cancel" style="padding:10px 14px;border:0;background:transparent;cursor:pointer">' + esc(copy.cancel) + '</button>' +
        '<button type="submit" style="padding:10px 16px;border:0;border-radius:980px;background:#000;color:#fff;cursor:pointer">' + esc(copy.submit) + '</button>' +
        '</div></form>';
      document.body.appendChild(overlay);
      var input = overlay.querySelector('input');
      var err = overlay.querySelector('.pax-fg-err');
      var form = overlay.querySelector('form');
      setTimeout(function () { input && input.focus(); }, 0);

      function close(ok) {
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        modalOpen = false;
        resolve(!!ok);
      }

      overlay.querySelector('[data-act="cancel"]').addEventListener('click', function () { close(false); });
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var code = String(input.value || '').replace(/\D/g, '');
        if (code.length !== 6) {
          err.style.display = 'block';
          return;
        }
        submitCode(ch.token, code).then(function (ok) {
          if (ok) close(true);
          else err.style.display = 'block';
        });
      });
    });
  }

  function submitCode(token, code) {
    var url = cfg.challengeUrl;
    if (!url || typeof window.fetch !== 'function') return Promise.resolve(false);
    return window.fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: withHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ token: token, code: code, device_id: deviceId })
    }).then(function (res) { return res.json().then(function (body) { return { res: res, body: body }; }); })
      .then(function (pack) {
        if (pack.res.ok && pack.body && pack.body.success) {
          challengeToken = pack.body.challenge_token || token;
          return true;
        }
        return false;
      }).catch(function () { return false; });
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }
})();
