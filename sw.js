/* Papiro Máximo — Service Worker (cache de assets) */
const CACHE = 'papiro-v1';
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(['./assets/css/style.css', './assets/js/app.js', './icons/icon.svg']).catch(() => {}))
  );
});
self.addEventListener('fetch', (e) => {
  const u = new URL(e.request.url);
  if (/\.(css|js|svg|png|ico)$/.test(u.pathname)) {
    e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request)));
  }
});
