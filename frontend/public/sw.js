const CACHE_NAME = 'tudu-cache-v1';
const urlsToCache = [
    '/',
    '/index.html',
    '/manifest.json',
    '/logo192.png',
    '/logo512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    // Bypass cache for Vite dev server requests and Chrome extensions
    if (event.request.url.includes(':5173') ||
        event.request.url.includes('@vite') ||
        event.request.url.includes('@react-refresh') ||
        event.request.url.startsWith('chrome-extension://')) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // If found in cache, return it
                if (response) {
                    return response;
                }
                // Otherwise, try fetching from the network
                return fetch(event.request).catch(() => {
                    // Ignore errors silently instead of breaking the entire app load
                    console.warn('[Service Worker] Fetch failed for:', event.request.url);
                });
            })
    );
});
