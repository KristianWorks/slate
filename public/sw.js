// /public/sw.js — Slate service worker
//
// Strategy:
//   - Navigations: network-first, fall back to cache, then /offline.html
//   - Same-origin static assets: cache-first with background refresh
//   - Cross-origin requests (CDNs, fonts): not intercepted
//   - POST and non-GET requests pass straight through to the network

const VERSION = 'slate-v1';
const STATIC_CACHE = `${VERSION}-static`;
const RUNTIME_CACHE = `${VERSION}-runtime`;

const PRECACHE_ASSETS = [
    '/',
    '/offline.html',
    '/assets/css/app.css',
    '/favicon.svg',
    '/icon.svg',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_ASSETS))
            .catch(() => { /* if a precache item 404s, don't abort install */ })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            // Evict caches from previous versions.
            caches.keys().then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => !k.startsWith(VERSION))
                        .map((k) => caches.delete(k))
                )
            ),
            self.clients.claim(),
        ])
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only handle GET. POST / PUT / DELETE always hit the network.
    if (req.method !== 'GET') return;

    // Skip cross-origin requests — let the browser handle CDNs and fonts.
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Skip admin pages — admin is auth-gated and we don't want stale caches.
    if (url.pathname.startsWith('/admin')) return;

    // Navigation requests: network-first, fall back to cache, then offline page.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((response) => {
                    // Cache successful HTML responses for offline reads.
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(req).then((cached) => cached || caches.match('/offline.html'))
                )
        );
        return;
    }

    // Static assets: cache-first with network fallback.
    event.respondWith(
        caches.match(req).then((cached) => {
            if (cached) return cached;
            return fetch(req).then((response) => {
                // Only cache valid same-origin responses.
                if (response && response.status === 200 && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy));
                }
                return response;
            });
        })
    );
});
