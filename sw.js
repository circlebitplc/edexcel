// sw.js – Cache assets for offline support

const CACHE_NAME = 'edexcel-timetable-v1';
const urlsToCache = [
    '/',
    '/student/index.php',
    '/assets/css/student.css',
    '/assets/js/student.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version or fetch from network
                return response || fetch(event.request).catch(() => {
                    // Offline fallback – you can show a custom page
                    return new Response('Offline – please connect to the internet.', { status: 503 });
                });
            })
    );
});

self.addEventListener('activate', event => {
    // Clean old caches
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
});