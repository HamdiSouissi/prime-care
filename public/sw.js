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
const OFFLINE_NAV_WHITELIST = ['/', '/today', '/journal'];

const PRECACHE_ASSETS = [
  OFFLINE_URL,
  '/manifest.webmanifest',
  '/assets/css/app.min.css',
  '/assets/js/app.js',
  '/assets/js/offline-queue.js',
  '/assets/fonts/inter-var.woff2',
  '/assets/brand/prime-care-logo-complet.svg',
  '/assets/brand/prime-care-icone-transparent.svg',
  '/assets/brand/icon-192.png',
  '/assets/brand/icon-512.png',
];

function isForbiddenCacheUrl(pathname) {
  return (
    pathname.startsWith('/api/') ||
    pathname.startsWith('/media/') ||
    pathname.includes('/export') ||
    pathname.includes('/csv') ||
    pathname.includes('/pdf')
  );
}

function hasForbiddenCacheHeaders(response) {
  const disposition = response.headers.get('Content-Disposition');
  if (disposition) {
    return true;
  }
  const cacheControl = (response.headers.get('Cache-Control') || '').toLowerCase();
  return cacheControl.includes('no-store');
}

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

  // Never intercept or cache API, exports, CSV, PDF, or private media files
  if (isForbiddenCacheUrl(url.pathname)) {
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
          if (
            response.ok &&
            response.status === 200 &&
            response.type === 'basic' &&
            !hasForbiddenCacheHeaders(response)
          ) {
            const clone = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for HTML navigations: only cache exact whitelist ['/', '/today', '/journal']
  if (request.mode === 'navigate') {
    const normalizedPath = url.pathname.length > 1 ? url.pathname.replace(/\/+$/, '') : '/';
    const isWhitelistedScreen = OFFLINE_NAV_WHITELIST.includes(normalizedPath) && url.search === '';

    event.respondWith(
      fetch(request)
        .then((response) => {
          if (
            isWhitelistedScreen &&
            response.ok &&
            response.status === 200 &&
            !hasForbiddenCacheHeaders(response)
          ) {
            const clone = response.clone();
            caches.open(HTML_CACHE).then((cache) => cache.put(normalizedPath, clone));
          }
          return response;
        })
        .catch(() =>
          caches.open(HTML_CACHE).then((cache) =>
            cache
              .match(normalizedPath)
              .then((cachedPage) => cachedPage || caches.match(OFFLINE_URL))
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
