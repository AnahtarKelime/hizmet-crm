const CACHE_NAME = 'hizmet-crm-v1';

self.addEventListener('install', (event) => {
        self.skipWaiting();
});

self.addEventListener('activate', (event) => {
        event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
        // Basit fetch işlemi, offline özellikler için burası geliştirilebilir.
        event.respondWith(
                fetch(event.request).catch(() => {
                        return caches.match(event.request);
                })
        );
});