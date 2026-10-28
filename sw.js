const CACHE_NAME = 'proxcel-v3';
const OFFLINE_URL = 'offline.html';

const STATIC_ASSETS = [
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/img/logo.png',
    'manifest.json',
    'offline.html'
];

// Install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(err => {
                console.log('Cache addAll failed:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Fetch: Network First for navigation, Cache First for assets
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Skip non-GET requests
    if (request.method !== 'GET') return;

    // Skip external requests
    const url = new URL(request.url);
    if (url.origin !== location.origin) return;

    // Network First para navegação de páginas HTML
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(async () => {
                    const cache = await caches.open(CACHE_NAME);
                    const cachedResponse = await cache.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    const offlinePage = await cache.match(OFFLINE_URL);
                    if (offlinePage) {
                        return offlinePage;
                    }
                    return new Response(
                        '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Offline — Proxcel</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Sem conexão com a internet</h2><p>Verifique sua conexão e tente novamente.</p></body></html>',
                        {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: { 'Content-Type': 'text/html; charset=utf-8' }
                        }
                    );
                })
        );
        return;
    }

    // Cache First para recursos estáticos locais (CSS, JS, imagens, fontes)
    if (request.destination === 'style' || request.destination === 'script' ||
        request.destination === 'image' || request.destination === 'font') {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then(response => {
                    if (response && response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                }).catch(() => {
                    return new Response('', { status: 408, statusText: 'Request Timeout' });
                });
            })
        );
        return;
    }

    // Demais requisições (como AJAX/API, JSON, downloads): deixar o navegador lidar diretamente
});

