const CACHE_NAME = 'todo-v2';
const STATIC_ASSETS = [
  './',
  './index.php',
  './favicon.svg',
  './manifest.json',
  'https://cdn.tailwindcss.com',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js'
];

// Install: cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[SW] Caching static assets');
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate: delete old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch strategy:
// 1. API calls: network-only
// 2. Static assets (CDN/Local): Cache-first, then Network
// 3. Main page: Network-first, then Cache
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API calls are never cached
  if (url.pathname.includes('api.php') || event.request.method !== 'GET') {
    return;
  }

  // Network-first for the main page to ensure fresh content
  if (url.pathname.endsWith('index.php') || url.pathname.endsWith('/')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Cache-first for everything else (CSS, JS, Icons)
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request).then(response => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          // If it's a cross-origin request (like CDN), we still might want to cache it
          if (event.request.url.startsWith('https://')) {
             const clone = response.clone();
             caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        }

        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      });
    })
  );
});
