/* PAXdesign Live Chat Admin — Service Worker */
'use strict';

var ADMIN_URL = '__PAX_ADMIN_URL__';
var CACHE_SHELL = 'pax-live-chat-v1';

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_SHELL).then(function (cache) {
      return cache.addAll([ADMIN_URL, ADMIN_URL + '?source=pwa-shell']);
    }).catch(function () {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (k) { return k !== CACHE_SHELL; }).map(function (k) { return caches.delete(k); })
      );
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') return;
  var url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;
  if (!url.pathname.includes('live-chat-admin') && !url.pathname.includes('pax-live-')) return;

  event.respondWith(
    fetch(event.request).catch(function () {
      return caches.match(event.request).then(function (cached) {
        return cached || caches.match(ADMIN_URL);
      });
    })
  );
});

self.addEventListener('push', function (event) {
  var data = { title: 'PAX Live Chat', body: 'Neue Aktivität', url: ADMIN_URL, badge: 1, tag: 'pax-live-chat' };
  try {
    if (event.data) {
      data = Object.assign(data, event.data.json());
    }
  } catch (e) {}

  var options = {
    body: data.body || '',
    icon: data.icon || '',
    badge: data.icon || '',
    tag: data.tag || 'pax-live-chat',
    renotify: true,
    requireInteraction: true,
    vibrate: [200, 100, 200, 100, 400],
    data: { url: data.url || ADMIN_URL, session: data.session || '' },
  };

  if (typeof data.badge === 'number' && self.registration.setAppBadge) {
    event.waitUntil(
      Promise.all([
        self.registration.showNotification(data.title || 'PAX Live Chat', options),
        self.registration.setAppBadge(data.badge),
      ])
    );
  } else {
    event.waitUntil(self.registration.showNotification(data.title || 'PAX Live Chat', options));
  }
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var data = event.notification.data || {};
  var target = data.url || ADMIN_URL;
  if (data.session) {
    try {
      var url = new URL(target, self.location.origin);
      url.searchParams.set('session', data.session);
      target = url.toString();
    } catch (e) {}
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].url.indexOf('live-chat-admin') !== -1 && 'focus' in list[i]) {
          if (data.session && 'navigate' in list[i]) {
            return list[i].navigate(target).then(function (client) { return client.focus(); });
          }
          list[i].postMessage({ type: 'pax-open-session', session: data.session || '' });
          return list[i].focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(target);
      }
    })
  );
});

self.addEventListener('pushsubscriptionchange', function (event) {
  event.waitUntil(Promise.resolve());
});
