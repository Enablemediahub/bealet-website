const CACHE_NAME = 'bealet-site-shell-v2';
const SCOPE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const CORE_ASSETS = [
    `${SCOPE_PATH}/`,
    `${SCOPE_PATH}/assets/images/logo/logo.png`
];

function isCacheableResponse(response) {
    return response && response.ok && (response.type === 'basic' || response.type === 'default');
}

async function networkFirst(request, fallbackUrl = null) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const networkResponse = await fetch(request);
        if (isCacheableResponse(networkResponse)) {
            cache.put(request, networkResponse.clone()).catch(() => Promise.resolve());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        if (fallbackUrl) {
            const fallbackResponse = await cache.match(fallbackUrl);
            if (fallbackResponse) {
                return fallbackResponse;
            }
        }

        throw error;
    }
}

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

    const requestUrl = new URL(event.request.url);
    const isSameOrigin = requestUrl.origin === self.location.origin;
    const isNavigationRequest = event.request.mode === 'navigate';

    if (isNavigationRequest) {
        event.respondWith(networkFirst(event.request, `${SCOPE_PATH}/`));
        return;
    }

    if (isSameOrigin) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
