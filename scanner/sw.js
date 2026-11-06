const CACHE='absensi-scanner-v10';
const QR_FALLBACK='./jsQR.js';
const ASSETS=[
  './offline.html',
  './offline.js',
  './qr-decoder.php',
  '../assets/css/app.css',
  '../manifest.json',
  '../assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  '../assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  '../assets/js/vendor/qrcode.min.php',
  './jsQR.js'
];

self.addEventListener('install',e=>e.waitUntil(
  caches.open(CACHE)
    .then(async c=>{await c.addAll([...ASSETS, QR_FALLBACK]);})
    .then(()=>self.skipWaiting())
));

self.addEventListener('activate',e=>e.waitUntil((async()=>{
  const keys=await caches.keys();
  await Promise.all(keys.filter(k=>k.startsWith('absensi-scanner-')&&k!==CACHE).map(k=>caches.delete(k)));
  await self.clients.claim();
})()));

self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET') return;
  const u=new URL(e.request.url);
  if(u.origin===self.location.origin && u.pathname.endsWith('/scanner/jsQR.js')){e.respondWith(caches.match(QR_FALLBACK).then(cached=>cached||fetch(e.request).then(response=>{if(response.ok)caches.open(CACHE).then(c=>c.put(QR_FALLBACK,response.clone()));return response})));return;}
  if(u.origin!==self.location.origin) return;

  // Semua resource statis yang dibutuhkan scanner diprioritaskan dari cache.
  // Jika belum ada, ambil dari server lalu simpan agar reload berikutnya
  // tetap dapat berjalan tanpa internet.
  const staticAsset =
    u.pathname.endsWith('/scanner/offline.html') ||
    u.pathname.endsWith('/scanner/offline.js') ||
    u.pathname.endsWith('/scanner/qr-decoder.php') ||
    u.pathname.endsWith('/assets/css/app.css') ||
    u.pathname.endsWith('/manifest.json') ||
    u.pathname.endsWith('/assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ||
    u.pathname.endsWith('/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2') ||
    u.pathname.endsWith('/assets/js/vendor/qrcode.min.php') ||
    u.pathname.endsWith('/scanner/jsQR.js');

  if(staticAsset){
    // CSS/JS may change while the scanner service worker is already installed.
    // Online: prefer the server and refresh the cache. Offline: fall back to cache.
    const isVersionedStyle = u.pathname.endsWith('/assets/css/app.css') ||
      u.pathname.endsWith('/assets/vendor/bootstrap-icons/bootstrap-icons.min.css');
    e.respondWith((isVersionedStyle && navigator.onLine)
      ? fetch(e.request, {cache:'no-store'}).then(response=>{
          if(response.ok){
            const copy=response.clone();
            caches.open(CACHE).then(c=>c.put(e.request,copy));
          }
          return response;
        }).catch(()=>caches.match(e.request))
      : caches.match(e.request).then(cached=>cached||fetch(e.request).then(response=>{
          if(response.ok){
            const copy=response.clone();
            caches.open(CACHE).then(c=>c.put(e.request,copy));
          }
          return response;
        }))
    );
  }
});
