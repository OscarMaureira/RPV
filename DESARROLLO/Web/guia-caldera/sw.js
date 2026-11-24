const CACHE = 'rpv-guia-caldera-v3-20261118';
const SCOPE = new URL('./', self.location.href).pathname;
const ASSETS = [
  './',
  './index.html',
  './manifest.webmanifest',
  './assets/guia-original.webp',
  './assets/icon-192.png',
  './assets/icon-512.png',
  './assets/logo-rpv-construcciones.webp',
  './qr-clientes.html'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith('rpv-guia-caldera-') && key !== CACHE)
      .map(key => caches.delete(key))
  )).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith(SCOPE)) return;
  event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
    if (response.ok) {
      const copy = response.clone();
      event.waitUntil(caches.open(CACHE).then(cache => cache.put(event.request, copy)));
    }
    return response;
  })));
});