const CACHE_NAME = 'npglow-v2';
const OFFLINE_URL = '/npglow/offline.html';

// Static assets to cache immediately on install
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/npglow/manifest.json',
    '/npglow/assets/images/logo_np_glow.jpeg',
    '/npglow/assets/icons/icon-192.png',
    '/npglow/assets/icons/icon-512.png',
    '/npglow/assets/icons/icon-maskable-192.png',
    '/npglow/assets/icons/icon-maskable-512.png',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// Install Event: cache the offline page and essential assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[SW] Pre-caching non-fatal warning:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate Event: clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => {
                    console.log('[SW] Removing old cache:', key);
                    return caches.delete(key);
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch Event
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // 1. Skip non-GET requests (e.g. POST, PUT, DELETE)
    if (request.method !== 'GET') return;

    // 2. Skip API calls, uploads, and admin backend routes — always go directly to network
    if (
        url.pathname.includes('/api/') ||
        url.pathname.includes('/admin/') ||
        url.pathname.includes('/uploads/')
    ) {
        return;
    }

    // 3. For navigation requests (HTML pages) -> Network First with Offline Fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response && response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(async () => {
                    // Try cached page first
                    const cachedResponse = await caches.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Fallback to offline page
                    return caches.match(OFFLINE_URL);
                })
        );
        return;
    }

    // 4. For static assets (CSS, JS, Fonts, Images, CDN resources) -> Cache First / Stale While Revalidate
    if (
        url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff2?|ttf|eot|ico|json)$/) ||
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com' ||
        url.hostname === 'cdn.tailwindcss.com' ||
        url.hostname === 'cdn.jsdelivr.net' ||
        url.hostname === 'unpkg.com'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    // Background refresh
                    fetch(request)
                        .then((networkResponse) => {
                            if (networkResponse && networkResponse.status === 200) {
                                caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
                            }
                        })
                        .catch(() => {});
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return response;
                }).catch(() => {
                    // Fail silently for static assets if offline
                    return new Response('', { status: 408, statusText: 'Offline' });
                });
            })
        );
        return;
    }
});
