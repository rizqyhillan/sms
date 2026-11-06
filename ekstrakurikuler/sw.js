const CACHE='absensi-ekstra-qr-v1';
const LOCAL='./jsqr.js';
const CDN='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';

self.addEventListener('install',e=>e.waitUntil((async()=>{
  const c=await caches.open(CACHE);
  try{
    const existing=await caches.match(CDN);
    if(existing) await c.put(LOCAL,existing.clone());
    else {
      const r=await fetch(CDN,{mode:'cors',cache:'no-store'});
      if(r.ok) await c.put(LOCAL,r.clone());
    }
  }catch(e){}
  await self.skipWaiting();
})()));

self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));

self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET') return;
  const u=new URL(e.request.url);
  if(u.pathname.endsWith('/ekstrakurikuler/jsqr.js')){
    e.respondWith((async()=>{
      const c=await caches.open(CACHE);
      const cached=await c.match(LOCAL);
      if(cached) return cached;
      try{
        const r=await fetch(CDN,{mode:'cors'});
        if(r.ok) await c.put(LOCAL,r.clone());
        return r;
      }catch(err){
        return new Response('/* jsQR offline asset belum tersedia */',{status:503,headers:{'Content-Type':'application/javascript; charset=utf-8'}});
      }
    })());
  }
});
