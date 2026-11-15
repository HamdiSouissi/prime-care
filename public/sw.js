/**
 * Prime Care — Service Worker (Phase 3)
 * - Cache-First static assets (/assets/...)
 * - Network-First HTML navigation with per-page cache & offline.html fallback
 * - Never caches /api/* medical JSON endpoints
 * - Purges HTML page cache on logout (`PURGE_AUTH_CACHE`)
 * - Web Push VAPID listener (`push`) and notification click routing (`notificationclick`)
 */

const STATIC_CACHE = 'prime-care-static-v3';
const HTML_CACHE = 'prime-care-html-v3';
const OFFLINE_URL = '/offline.html';

const PRECACHE_ASSETS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/assets/css/tokens.css',
  '/assets/css/base.css',
  '/assets/css/components.css',
  '/assets/css/utilities.css',
  '/assets/css/app.css',
  '/assets/vendor/apexcharts.min.js',
  '/assets/vendor/jsQR.min.js',
  '/assets/js/app.js',
  '/assets/js/offline-queue.js',
  '/assets/fonts/inter-var.woff2',
  '/assets/brand/prime-care-logo-complet.svg',
  '/assets/brand/prime-care-icone-transparent.svg',
  '/assets/brand/icon-192.png',
  '/assets/brand/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== STATIC_CACHE && key !== HTML_CACHE)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

// Purge authenticated HTML cache on logout
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'PURGE_AUTH_CACHE') {
    event.waitUntil(caches.delete(HTML_CACHE));
  }
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Never cache medical or JSON API responses in the Service Worker
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // Cache-first for static assets (/assets/...)
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(request).then((response) => {
          if (response.ok && response.status === 200 && response.type === 'basic') {
            const clone = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for HTML navigations with cached page fallback -> offline.html
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok && response.status === 200 && !url.pathname.startsWith('/login')) {
            const clone = response.clone();
            caches.open(HTML_CACHE).then((cache) => cache.put(request, clone));
          }
          return response;
        })
        .catch(() =>
          caches.open(HTML_CACHE).then((cache) =>
            cache.match(request).then((cachedPage) => cachedPage || caches.match(OFFLINE_URL))
          )
        )
    );
  }
});

// Web Push VAPID notification display (§3.8 / Phase 3)
self.addEventListener('push', (event) => {
  let payload = {
    title: 'Prime Care',
    body: 'Nouvelle alerte médicale ou d’équipe.',
    url: '/alerts',
    severity: 'warning',
    tag: 'prime-care-alert',
  };

  if (event.data) {
    try {
      const parsed = event.data.json();
      payload = { ...payload, ...parsed };
    } catch {
      payload.body = event.data.text() || payload.body;
    }
  }

  const options = {
    body: payload.body,
    icon: '/assets/brand/icon-192.png',
    badge: '/assets/brand/icon-192.png',
    tag: payload.tag || 'prime-care-alert',
    renotify: true,
    requireInteraction: payload.severity === 'critical',
    data: {
      url: payload.url || '/alerts',
    },
  };

  event.waitUntil(self.registration.showNotification(payload.title, options));
});

// Open or focus target screen when clicking a Web Push notification
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/alerts';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if ('focus' in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
      return null;
    })
  );
});
