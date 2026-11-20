// ============================================================
// sw.js — FeedPulse AI Service Worker (PWA Shell & Cache)
// ============================================================

const CACHE_NAME = 'feedpulse-v2';
const STATIC_ASSETS = [
  '/',
  '/assets/css/feedpulse.css',
  '/assets/js/main.js',
  '/favicon.ico',
  '/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Network-first strategy for dynamic app pages, cache fallback for assets
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip POST, API requests, and external resources
  if (event.request.method !== 'GET' || url.pathname.startsWith('/api/') || url.pathname.includes('cron')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Cache successful static assets
        if (networkResponse && networkResponse.status === 200 && (url.pathname.startsWith('/assets/') || url.pathname === '/favicon.ico')) {
          const clone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
