(() => {
const DB='absensi_offline_v1', STORE='data', base='../', $=id=>document.getElementById(id);
let db, data={sessions:[],students:[]}, stream=null, detector=null, loop=null, lastCode='', lastAt=0;
function openDB(){return new Promise((res,rej)=>{const r=indexedDB.open(DB,1);r.onupgradeneeded=()=>r.result.createObjectStore(STORE);r.onsuccess=()=>{db=r.result;res(db)};r.onerror=()=>rej(r.error)})}
function put(k,v){return new Promise((res,rej)=>{const t=db.transaction(STORE,'readwrite');t.objectStore(STORE).put(v,k);t.oncomplete=()=>res();t.onerror=()=>rej(t.error)})}
function get(k){return new Promise((res,rej)=>{const t=db.transaction(STORE);const r=t.objectStore(STORE).get(k);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}
function show(msg,type='neutral'){const x=$('result');x.className='scan-result '+type;x.innerHTML=msg}
function queueCount(){return get('queue').then(q=>q||[])}
async function renderQueue(){const q=await queueCount();$('queue').textContent=`Antrean offline: ${q.length} transaksi`;}
function selected(){return data.sessions.find(x=>String(x.id)===$('sesi').value)}
async function enqueue(item){let q=await queueCount();if(q.some(x=>x.sesi_id===item.sesi_id&&x.kode_qr===item.kode_qr)){show('Siswa sudah ada di antrean offline untuk sesi ini.','error');return false}q.push({...item,queued_at:new Date().toISOString()});await put('queue',q);await renderQueue();return true}
async function process(code){code=(code||'').trim();if(!code)return;const s=selected();if(!s){show('Pilih sesi terlebih dahulu.','error');return}const stu=data.students.find(x=>x.kode_qr===code);if(!stu){show('QR tidak ditemukan pada data offline perangkat. Sambungkan ke server lalu perbarui data offline.','error');return}const now=new Date();let status=s.kategori==='sholat'?'SUDAH':'H';if(s.kategori==='kehadiran'&&s.batas_terlambat&&now.toTimeString().slice(0,8)>s.batas_terlambat)status='T';const ok=await enqueue({sesi_id:Number(s.id),kode_qr:code,nama:stu.nama,nis:stu.nis,status,waktu:now.toISOString()});if(ok)show(`<strong>${esc(stu.nama)}</strong><br>NIS: ${esc(stu.nis)}<br>Status sementara: ${status}<br><small>Disimpan offline.</small>`,'success');$('qr').value='';$('qr').focus()}
async function sync(){if(!navigator.onLine)return;const csrf=await get('csrf');if(!csrf)return;let q=await queueCount();if(!q.length)return;let left=[];for(const item of q){try{const r=await fetch('../scanner/scan.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,sesi_id:item.sesi_id,kode_qr:item.kode_qr,offline:'1',waktu_scan:item.waktu})});const d=await r.json();if(!d.ok&&!d.duplicate)left.push(item)}catch(e){left.push(item);break}}await put('queue',left);await renderQueue();if(q.length!==left.length)show(`Sinkronisasi berhasil: ${q.length-left.length} transaksi.`,'success')}
async function load(){await openDB();data=await get('prefetch')||data;if(!data.sessions.length){$('conn').textContent='Belum ada data offline. Buka scanner saat server online terlebih dahulu.';return}data.sessions.forEach(s=>{const o=document.createElement('option');o.value=s.id;o.textContent=`${s.tanggal} · ${s.nama_sesi}${s.sholat_nama?' · '+s.sholat_nama:''}`;$('sesi').appendChild(o)});$('conn').textContent=navigator.onLine?'Data offline siap.':'OFFLINE — scanner tetap dapat digunakan.';await renderQueue();if(navigator.onLine)sync()}
$('qr').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();process($('qr').value)}});

const QR_DECODER_LOCAL='./jsQR.js';
let qrDecoderPromise=null;
async function loadQrFallback(){
  if(window.BarcodeDetector){window.__qrDecoderReady=true;const el=$('decoderStatus');if(el)el.textContent='Pembaca QR: BarcodeDetector tersedia; fallback jsQR digunakan bila diperlukan.';return true}
  if(window.jsQR){window.__qrDecoderReady=true;const el=$('decoderStatus');if(el)el.textContent='Pembaca QR: jsQR fallback siap.';return true}
  if(qrDecoderPromise)return qrDecoderPromise;
  qrDecoderPromise=new Promise((resolve,reject)=>{const el=$('decoderStatus');if(el)el.textContent=navigator.onLine?'Menyiapkan pembaca QR fallback…':'Pembaca QR fallback belum tersedia offline. Siapkan decoder saat online.';const script=document.createElement('script');script.src=QR_DECODER_LOCAL;script.async=true;script.onload=()=>window.jsQR?(window.__qrDecoderReady=true,el&&(el.textContent='Pembaca QR: jsQR fallback siap.'),resolve(true)):reject(new Error('jsQR tidak tersedia'));script.onerror=()=>reject(new Error('Gagal memuat jsQR'));document.head.appendChild(script)}).catch(()=>{qrDecoderPromise=null;const el=$('decoderStatus');if(el)el.textContent='Pembaca QR fallback tidak tersedia. Gunakan BarcodeDetector atau scanner USB.';return false});
  return qrDecoderPromise;
}
function canvasCropSquare(source,ctx,canvas){const w=source.videoWidth||source.width||0,h=source.videoHeight||source.height||0;if(!w||!h)return false;const side=Math.floor(Math.min(w,h)*0.82),sx=Math.floor((w-side)/2),sy=Math.floor((h-side)/2);canvas.width=Math.min(960,Math.max(320,side));canvas.height=canvas.width;ctx.drawImage(source,sx,sy,side,side,0,0,canvas.width,canvas.height);return true}
async function decodeQrFrame(video,fullCanvas,fullCtx,cropCanvas,cropCtx){let c=null;if(window.BarcodeDetector){try{detector=detector||new BarcodeDetector({formats:['qr_code']});const x=await detector.detect(fullCanvas);if(x.length)c=x[0].rawValue}catch(e){}if(!c&&canvasCropSquare(video,cropCtx,cropCanvas)){try{const x=await detector.detect(cropCanvas);if(x.length)c=x[0].rawValue}catch(e){}}}if(!c&&window.jsQR){try{const im=fullCtx.getImageData(0,0,fullCanvas.width,fullCanvas.height),x=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(x)c=x.data}catch(e){}if(!c&&canvasCropSquare(video,cropCtx,cropCanvas)){try{const im=cropCtx.getImageData(0,0,cropCanvas.width,cropCanvas.height),x=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(x)c=x.data}catch(e){}}}return c}

async function enumerateCameras(){
  if(!window.isSecureContext) throw new Error('secure-context');
  if(!navigator.mediaDevices?.getUserMedia||!navigator.mediaDevices?.enumerateDevices) throw new Error('unsupported');
  // Permission is requested first. Without it Chromium may hide device labels and device IDs.
  let probe;
  try{probe=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});}
  catch(e){
    // Some desktops do not accept facingMode. Retry with generic video.
    probe=await navigator.mediaDevices.getUserMedia({video:true,audio:false});
  }
  probe.getTracks().forEach(t=>t.stop());
  const devices=await navigator.mediaDevices.enumerateDevices();
  return devices.filter(d=>d.kind==='videoinput');
}
async function detectCameras(){
  const cs=$('cameraSelect'),cc=$('cameraCount');cs.disabled=true;cs.innerHTML='<option value="">Mendeteksi kamera…</option>';cc.textContent='Mendeteksi…';
  if(!window.isSecureContext){cc.textContent='Butuh HTTPS/localhost';show('Mode offline tetap bisa menerima QR USB. Untuk kamera browser, halaman harus dibuka melalui <strong>HTTPS atau localhost</strong>.','error');return}
  try{
    const cams=await enumerateCameras();cs.innerHTML='';
    if(!cams.length){cc.textContent='0 kamera';cs.innerHTML='<option value="">Tidak ada kamera yang diberikan browser</option>';show('<strong>Tidak ada kamera yang terdeteksi.</strong><br>Jika webcam USB sudah terpasang, pastikan Windows mengenalinya dan tidak sedang dipakai aplikasi lain. Tekan Deteksi Kamera lagi setelah memasang webcam.','error');return}
    cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||`Kamera ${i+1}`;cs.appendChild(o)});
    cs.disabled=false;cc.textContent=`${cams.length} kamera terdeteksi`;show(`<strong>${cams.length} kamera terdeteksi.</strong><br>Mode offline siap. Pilih kamera lalu tekan Mulai Kamera.`,'success');
  }catch(e){cc.textContent='Tidak dapat mengakses kamera';cs.innerHTML='<option value="">Izin/perangkat kamera bermasalah</option>';show('Kamera belum dapat diakses. Izinkan kamera pada browser, pastikan webcam USB dikenali Windows, lalu tekan Deteksi Kamera lagi.','error')}
}
$('detectCameras').onclick=detectCameras;
$('start').onclick=async()=>{
  if(!selected()){show('Pilih sesi terlebih dahulu.','error');return}
  if(!window.isSecureContext){show('Kamera browser memerlukan HTTPS atau localhost. Mode USB QR tetap dapat digunakan.','error');return}
  if(!navigator.mediaDevices?.getUserMedia){show('Browser tidak menyediakan akses kamera. Gunakan Chrome/Edge/Firefox terbaru.','error');return}
  if(!$('cameraSelect').value){await detectCameras();if(!$('cameraSelect').value)return}
  if(!window.__qrDecoderReady){show('Menyiapkan pembaca QR lokal…','neutral');try{await loadQrFallback()}catch(e){show('Pembaca QR belum tersedia di perangkat. Gunakan Chrome/Edge terbaru atau scanner USB jika browser tidak mendukung BarcodeDetector.','error');return}}
  try{
    if(stream)stream.getTracks().forEach(t=>t.stop());if(loop)cancelAnimationFrame(loop);
    let constraints={video:{deviceId:{exact:$('cameraSelect').value},width:{ideal:1280},height:{ideal:720},facingMode:undefined},audio:false};
    delete constraints.video.facingMode;
    try{stream=await navigator.mediaDevices.getUserMedia(constraints)}catch(e){stream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:$('cameraSelect').value}},audio:false})}
    $('reader').innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=stream;$('reader').appendChild(v);
    const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});const cropCanvas=document.createElement('canvas'),cropCtx=cropCanvas.getContext('2d',{willReadFrequently:true});let decoding=false,lastFrameAt=0;const tick=async()=>{if(!stream)return;const now=performance.now();if(now-lastFrameAt<120||decoding){loop=requestAnimationFrame(tick);return}lastFrameAt=now;try{if(v.readyState>=2&&v.videoWidth){const max=960,scale=Math.min(1,max/v.videoWidth);canvas.width=Math.max(320,Math.round(v.videoWidth*scale));canvas.height=Math.max(240,Math.round(v.videoHeight*scale));ctx.drawImage(v,0,0,canvas.width,canvas.height);decoding=true;const c=await decodeQrFrame(v,canvas,ctx,cropCanvas,cropCtx);decoding=false;if(c&&(c!==lastCode||Date.now()-lastAt>2500)){lastCode=c;lastAt=Date.now();await process(c)}}}catch(e){decoding=false}loop=requestAnimationFrame(tick)};tick();try{const track=stream.getVideoTracks()[0],caps=track&&track.getCapabilities?track.getCapabilities():{};if(caps.focusMode&&caps.focusMode.includes('continuous'))await track.applyConstraints({advanced:[{focusMode:'continuous'}]})}catch(e){}show('Kamera aktif — OFFLINE. Arahkan QR ke kotak pemindaian.','success');
  }catch(e){show('Kamera tidak dapat digunakan. Periksa izin kamera, koneksi HTTPS/localhost, dan apakah webcam sedang dipakai aplikasi lain.','error')}
};
$('stop').onclick=()=>{if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;if(loop)cancelAnimationFrame(loop);$('reader').innerHTML='<div class="scanner-placeholder">Kamera dihentikan</div>';}
if(navigator.mediaDevices?.addEventListener)navigator.mediaDevices.addEventListener('devicechange',()=>{detectCameras().catch(()=>{})});
window.addEventListener('online',async()=>{$('conn').textContent='ONLINE — mencoba sinkronisasi…';await sync()});window.addEventListener('offline',()=>{$('conn').textContent='OFFLINE — transaksi disimpan di perangkat'});
(async()=>{if('serviceWorker'in navigator){try{await navigator.serviceWorker.register(base+'scanner/sw.js',{scope:base+'scanner/'})}catch(e){}}await load();if(!('BarcodeDetector'in window)){loadQrFallback().catch(()=>{const el=$('decoderStatus');if(el)el.textContent=navigator.onLine?'Pembaca QR: memeriksa dukungan browser…':'Pembaca QR: browser belum mendukung BarcodeDetector.'})}await detectCameras()})().catch(()=>{});
})();
