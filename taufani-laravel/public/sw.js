const CACHE = 'taufani-v1';

// Assets to pre-cache on install
const PRECACHE = ['/manifest.json', '/icons/icon.svg'];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE).then(c => c.addAll(PRECACHE))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle same-origin GET requests
    if (request.method !== 'GET' || url.origin !== location.origin) return;

    // Never cache Livewire update endpoint or CSRF-sensitive routes
    if (url.pathname.startsWith('/livewire') || url.pathname.startsWith('/sanctum')) return;

    // Versioned build assets (hashed filenames) — cache-first
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(resp => {
                    if (resp.ok) {
                        const clone = resp.clone();
                        caches.open(CACHE).then(c => c.put(request, clone));
                    }
                    return resp;
                });
            })
        );
        return;
    }

    // Navigation requests and app routes — network-first, fall back to cache
    event.respondWith(
        fetch(request)
            .then(resp => {
                if (resp.ok && request.mode === 'navigate') {
                    const clone = resp.clone();
                    caches.open(CACHE).then(c => c.put(request, clone));
                }
                return resp;
            })
            .catch(() => caches.match(request))
    );
});
