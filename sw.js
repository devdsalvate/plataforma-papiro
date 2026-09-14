/* Papiro Máximo — Service Worker de assets estáticos. */
const CACHE = 'papiro-v2-2';
const STATIC = [
  './assets/css/style.css',
  './assets/css/papiro-v2.css',
  './assets/js/app.js',
  './assets/js/question-previews.js',
  './assets/vendor/pdfjs/pdf.min.mjs',
  './assets/vendor/pdfjs/pdf.worker.min.mjs',
  './icons/icon.svg'
];
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC).catch(() => {})));
  self.skipWaiting();
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', (e) => {
  const u = new URL(e.request.url);
  if (/\.(css|js|mjs|svg|png|ico)$/.test(u.pathname)) {
    e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request)));
  }
});
