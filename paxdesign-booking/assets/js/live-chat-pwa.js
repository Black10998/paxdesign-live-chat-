/**
 * PAXdesign Live Chat — PWA install, push subscription, app badge.
 */
(function () {
  'use strict';

  var cfg = window.paxLivePwa || {};
  var adminUrl = cfg.adminUrl || '/live-chat-admin/';
  var deferredInstallPrompt = null;

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(base64);
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; ++i) arr[i] = raw.charCodeAt(i);
    return arr;
  }

  function post(action, data) {
    data = data || {};
    data.action = action;
    data.nonce = cfg.nonce;
    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(data).toString(),
    }).then(function (r) { return r.json(); });
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !cfg.swUrl) return Promise.resolve(null);
    return navigator.serviceWorker.register(cfg.swUrl, { scope: '/' })
      .then(function (reg) { return reg; })
      .catch(function () { return null; });
  }

  function fetchVapidKey() {
    return fetch(cfg.vapidUrl || (cfg.ajaxUrl + '?action=paxdesign_live_push_vapid'), {
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.data && res.data.publicKey) return res.data.publicKey;
        return '';
      })
      .catch(function () { return ''; });
  }

  function subscribePush(registration) {
    if (!registration || !registration.pushManager) return Promise.resolve(false);

    return fetchVapidKey().then(function (publicKey) {
      if (!publicKey) return false;
      return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
      }).then(function (sub) {
        return post('paxdesign_live_push_subscribe', {
          subscription: JSON.stringify(sub.toJSON ? sub.toJSON() : sub),
        });
      }).then(function () { return true; })
        .catch(function () { return false; });
    });
  }

  function requestNotificationPermission() {
    if (!('Notification' in window)) return Promise.resolve('unsupported');
    if (Notification.permission === 'granted') return Promise.resolve('granted');
    if (Notification.permission === 'denied') return Promise.resolve('denied');
    return Notification.requestPermission();
  }

  function setAppBadge(count) {
    if (navigator.setAppBadge) {
      if (count > 0) navigator.setAppBadge(count).catch(function () {});
      else navigator.clearAppBadge().catch(function () {});
    }
  }

  function showInstallBanner() {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (localStorage.getItem('pax_pwa_install_dismissed') === '1') return;

    var bar = document.createElement('div');
    bar.className = 'pax-live-app-banner';
    bar.innerHTML =
      '<div class="pax-live-app-banner__text">' +
        '<strong>Als App installieren</strong>' +
        '<span id="paxPwaInstallHint">Für Vollbild & Push: Teilen → „Zum Home-Bildschirm“</span>' +
      '</div>' +
      '<button type="button" class="pax-live-app-banner__btn" id="paxPwaInstallBtn" hidden>App installieren</button>' +
      '<button type="button" class="pax-live-app-banner__btn" id="paxPwaEnablePush">Push aktivieren</button>' +
      '<button type="button" class="pax-live-app-banner__close" id="paxPwaDismiss" aria-label="Schließen">×</button>';

    var root = document.getElementById('paxLiveChatDashboard');
    if (root) root.prepend(bar);

    bar.querySelector('#paxPwaDismiss').addEventListener('click', function () {
      localStorage.setItem('pax_pwa_install_dismissed', '1');
      bar.remove();
    });

    var installBtn = bar.querySelector('#paxPwaInstallBtn');
    if (installBtn) {
      installBtn.addEventListener('click', function () {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        deferredInstallPrompt.userChoice.finally(function () {
          deferredInstallPrompt = null;
          installBtn.hidden = true;
        });
      });
    }

    bar.querySelector('#paxPwaEnablePush').addEventListener('click', function () {
      requestNotificationPermission().then(function () {
        return registerServiceWorker();
      }).then(function (reg) {
        return subscribePush(reg);
      }).then(function (ok) {
        if (ok) bar.querySelector('.pax-live-app-banner__text span').textContent = 'Push-Benachrichtigungen aktiv.';
      });
    });
  }

  function openSessionFromQuery() {
    var params = new URLSearchParams(window.location.search);
    var session = params.get('session');
    if (!session) return;
    window.paxLiveOpenSession = session;
  }

  window.PaxLivePwa = {
    adminUrl: adminUrl,
    setBadge: setAppBadge,
    register: registerServiceWorker,
    subscribePush: subscribePush,
    requestPermission: requestNotificationPermission,
    showNotification: function (title, body, url, tag) {
      if (!('Notification' in window) || Notification.permission !== 'granted') return;
      var n = new Notification(title, {
        body: body,
        tag: tag || 'pax-live-chat',
        requireInteraction: true,
        data: { url: url || adminUrl },
      });
      n.onclick = function () {
        window.focus();
        if (url) window.location.href = url;
        n.close();
      };
    },
  };

  openSessionFromQuery();

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    var installBtn = document.getElementById('paxPwaInstallBtn');
    var hint = document.getElementById('paxPwaInstallHint');
    if (installBtn) installBtn.hidden = false;
    if (hint) hint.textContent = 'Installieren Sie die App für Vollbild und schnelleren Zugriff.';
  });

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function (event) {
      if (!event.data || event.data.type !== 'pax-open-session' || !event.data.session) return;
      window.paxLiveOpenSession = event.data.session;
      window.dispatchEvent(new CustomEvent('pax-open-session', { detail: { session: event.data.session } }));
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (window.matchMedia('(display-mode: standalone)').matches) {
      document.body.classList.add('pax-live-standalone');
      document.documentElement.style.setProperty('--plc-viewport', '100dvh');
    }
    registerServiceWorker().then(function (reg) {
      if (Notification.permission === 'granted') {
        subscribePush(reg);
      }
    });
    showInstallBanner();
  });
})();
