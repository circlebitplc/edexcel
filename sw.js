// Legacy alias — keep registration targets on /service-worker.js.
// Mirrors service-worker.js so old clients that still point at /sw.js stay safe.
const CACHE_NAME = 'edexcel-static-v4';
const OFFLINE_URL = '/offline.html';
const STATIC_URLS = [
    '/assets/css/home.css',
    '/assets/css/system.css',
    '/assets/css/a11y-mobile-v2.css',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            await cache.addAll(STATIC_URLS);
            try {
                await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
            } catch (e) {
                // offline.html optional
            }
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    const url = new URL(req.url);
    const isHtml = req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html');
    const isApi = /\/(api|ajax)\//.test(url.pathname);
    if (isHtml || isApi) {
        event.respondWith(
            fetch(req).catch(async () => {
                const cached = await caches.match(req);
                if (cached) {
                    return cached;
                }
                if (isHtml) {
                    const offline = await caches.match(OFFLINE_URL);
                    if (offline) {
                        return offline;
                    }
                }
                return new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
            })
        );
        return;
    }
    event.respondWith(
        fetch(req).then((res) => {
            if (res.ok && url.origin === self.location.origin) {
                const copy = res.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return res;
        }).catch(() => caches.match(req))
    );
});

self.addEventListener('push', (event) => {
    let data = { title: 'Edexcel College', body: 'You have a new update.', url: '/' };
    try {
        if (event.data) {
            const parsed = event.data.json();
            data = Object.assign(data, parsed || {});
        }
    } catch (e) {
        try {
            data.body = event.data ? event.data.text() : data.body;
        } catch (e2) {}
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'Edexcel College', {
            body: data.body || '',
            icon: '/assets/icons/icon-192.png',
            badge: '/assets/icons/icon-192.png',
            data: { url: data.url || data.link || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification && event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(target);
            }
        })
    );
});
