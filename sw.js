const CACHE_NAME = 'bealet-site-shell-v1';
const SCOPE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const CORE_ASSETS = [
    `${SCOPE_PATH}/`,
    `${SCOPE_PATH}/assets/css/style.css`,
    `${SCOPE_PATH}/assets/js/main.js`,
    `${SCOPE_PATH}/assets/images/logo/logo.png`
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(CORE_ASSETS)).catch(() => Promise.resolve())
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request).then(networkResponse => {
                const clonedResponse = networkResponse.clone();
                caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, clonedResponse);
                }).catch(() => Promise.resolve());
                return networkResponse;
            });
        }).catch(() => caches.match(`${SCOPE_PATH}/`))
    );
});
