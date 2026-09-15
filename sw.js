/* Papiro Máximo 2.5 — cache de assets com atualização preferencial pela rede. */
const CACHE = 'papiro-v25-1';
const STATIC = [
  './assets/css/style.css','./assets/css/papiro-v2.css','./assets/js/app.js',
  './assets/js/question-previews.js','./assets/vendor/pdfjs/pdf.min.mjs',
  './assets/vendor/pdfjs/pdf.worker.min.mjs','./icons/icon.svg'
];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC).catch(()=>{}))); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k))))); self.clients.claim(); });
self.addEventListener('fetch', e => {
  const u=new URL(e.request.url);
  if(!/\.(css|js|mjs|svg|png|ico)$/.test(u.pathname)) return;
  e.respondWith(fetch(e.request).then(res=>{ const copy=res.clone(); caches.open(CACHE).then(c=>c.put(e.request,copy)); return res; }).catch(()=>caches.match(e.request)));
});
