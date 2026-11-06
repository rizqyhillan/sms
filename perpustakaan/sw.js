const CACHE='perpus-qr-v2';
const LOCAL='/sms/perpustakaan/jsQR.js';

self.addEventListener('install',e=>e.waitUntil((async()=>{
  const c=await caches.open(CACHE);
  try{
    const r=await fetch(LOCAL,{cache:'no-store'});
    if(r.ok) await c.put(LOCAL,r.clone());
  }catch(e){}
  await self.skipWaiting();
})()));

self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));

self.addEventListener('fetch',e=>{
 if(e.request.method!=='GET')return;
 const u=new URL(e.request.url);
 if(!u.pathname.endsWith('/perpustakaan/jsQR.js'))return;
 e.respondWith((async()=>{
  const cached=await caches.match(LOCAL);
  if(cached)return cached;
  try{
    const r=await fetch(e.request);
    if(r.ok){
      const c=await caches.open(CACHE);
      await c.put(LOCAL,r.clone());
    }
    return r;
  }catch(err){
    return new Response('/* jsQR lokal tidak tersedia */',{status:503,headers:{'Content-Type':'application/javascript; charset=utf-8'}});
  }
 })());
});
