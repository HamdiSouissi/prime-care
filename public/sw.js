/**
 * Prime Care — Service Worker (Cache-First static assets, Network-First HTML with Offline fallback)
 */

const CACHE_NAME = 'prime-care-v2';
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
  '/assets/js/app.js',
  '/assets/fonts/inter-var.woff2',
  '/assets/brand/prime-care-logo-complet.svg',
  '/assets/brand/prime-care-icone-transparent.svg',
  '/assets/brand/icon-192.png',
  '/assets/brand/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

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
            caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for HTML navigations with offline fallback
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match(OFFLINE_URL))
    );
  }
});
