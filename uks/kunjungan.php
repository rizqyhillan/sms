<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);
$view=(string)($_GET['view']??'antrian');
if(!in_array($view,['antrian','dilayani'],true))$view='antrian';
$canManage=uks_can_manage($pdo) && user_role()!=='kepala_sekolah';
$page_title='Layanan UKS';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main uks-module-page"><header class="topbar"><div class="topbar-title">Layanan UKS</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>
.uks-service .page-header{margin-bottom:16px}
.uks-service .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.uks-service .tab{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;color:#334155;background:#fff;font-weight:800;font-size:13px}
.uks-service .tab.active{background:#0f172a;color:#fff;border-color:#0f172a}
.uks-service .count{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 6px;border-radius:99px;background:#e2e8f0;color:#334155;font-size:11px}
.uks-service .tab.active .count{background:#fff;color:#0f172a}
.uks-service .card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px;margin-bottom:18px}
.uks-service .queue-item{display:grid;grid-template-columns:56px 1fr auto;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid #e2e8f0}
.uks-service .queue-item:last-child{border-bottom:0}
.uks-service .queue-no{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:#e0f2fe;color:#0369a1;font-weight:900;font-size:18px}
.uks-service .queue-name{font-weight:900;color:#0f172a}
.uks-service .queue-meta{font-size:12px;color:#64748b;margin-top:4px}
.uks-service .pill{display:inline-block;padding:4px 8px;border-radius:99px;font-size:10px;font-weight:900;margin-left:6px}
.uks-service .st-baru{background:#dbeafe;color:#1d4ed8}.uks-service .st-ditangani{background:#ffedd5;color:#c2410c}.uks-service .st-selesai{background:#dcfce7;color:#15803d}.uks-service .st-batal{background:#fee2e2;color:#b91c1c}
.uks-service{width:100%;max-width:100%;min-width:0;overflow-x:clip}
.uks-service *{min-width:0}
.uks-service .page-header{width:100%;max-width:100%}
.uks-service .tabs{max-width:100%}
.uks-service .scanner-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:18px;width:100%;max-width:100%;min-width:0}
.uks-service .scanner-grid>div{min-width:0;max-width:100%}
.uks-service .source-card{border:1px solid #e2e8f0;border-radius:12px;padding:13px;background:#f8fafc;width:100%;max-width:100%}
.uks-service .source-head{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
.uks-service .camera-controls{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;margin-top:10px}.uks-service .camera-controls label{min-width:0}
.uks-service .camera-controls .btn{white-space:nowrap}
.uks-service #reader{width:100%;max-width:100%;min-height:250px;border:1px dashed #cbd5e1;border-radius:12px;margin-top:12px;display:grid;place-items:center;overflow:hidden;background:#f8fafc}.uks-service #reader video{display:block;width:100%;max-width:100%;max-height:360px;object-fit:cover}
.uks-service .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;max-width:100%}
.uks-service .result{margin-top:12px;border-radius:10px;padding:12px;min-height:46px;overflow-wrap:anywhere}.uks-service .result.neutral{background:#f8fafc;color:#475569}.uks-service .result.success{background:#dcfce7;color:#166534}.uks-service .result.error{background:#fee2e2;color:#991b1b}
.uks-service .search-results{max-height:240px;overflow:auto;margin-top:6px;max-width:100%}
.uks-service.service-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:2000;display:none;align-items:center;justify-content:center;padding:18px;box-sizing:border-box;overflow:auto}
.uks-service.service-modal .modal-card{width:min(900px,calc(100vw - 36px));max-width:100%;max-height:90vh;overflow:auto;background:#fff;border-radius:16px;padding:20px;box-sizing:border-box;min-width:0}
.uks-service .form-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}.uks-service .form-grid>*{min-width:0}.uks-service .full{grid-column:1/-1}
@media(max-width:1200px){.uks-service .scanner-grid{grid-template-columns:1fr}}
@media(max-width:650px){.uks-service .queue-item{grid-template-columns:46px 1fr}.uks-service .queue-actions{grid-column:2}.uks-service .form-grid{grid-template-columns:1fr}.uks-service .full{grid-column:auto}.uks-service .camera-controls{grid-template-columns:1fr}.uks-service .camera-controls .btn{width:100%}.uks-service.service-modal{padding:10px}.uks-service.service-modal .modal-card{padding:15px;border-radius:12px}}
</style>
<div class="uks-service">
<div class="page-header"><div><h1><i class="bi bi-heart-pulse-fill"></i> Layanan UKS</h1><p>Scan siswa → antrian → layani → catat pemeriksaan dan tindakan → selesai. <strong>Tidak mengubah Absensi Kehadiran maupun Absensi Sholat.</strong></p></div></div>

<div class="tabs">
<a class="tab <?=$view==='antrian'?'active':''?>" href="<?=BASE_URL?>uks/kunjungan.php?view=antrian"><i class="bi bi-list-ol"></i> Antrian Hari Ini <span id="countWaiting" class="count">0</span></a>
<a class="tab <?=$view==='dilayani'?'active':''?>" href="<?=BASE_URL?>uks/kunjungan.php?view=dilayani"><i class="bi bi-person-workspace"></i> Sedang Dilayani <span id="countServing" class="count">0</span></a>
<a class="tab" href="<?=BASE_URL?>uks/riwayat_layanan.php"><i class="bi bi-clock-history"></i> Riwayat Layanan</a>
</div>

<?php if($canManage && $view==='antrian'): ?>
<div class="card">
<h2 style="margin:0 0 5px;font-size:18px;font-weight:900"><i class="bi bi-person-plus-fill"></i> Daftarkan Siswa ke Antrian</h2>
<p style="margin:0 0 12px;color:#64748b;font-size:12px">Keperluan layanan ditentukan saat petugas menekan <strong>Layani</strong>, bukan saat scan.</p>
<?=csrf_field()?>
<div id="msg" class="alert" style="display:none"></div>
<div class="scanner-grid">
<div>
<div class="source-card"><div class="source-head"><div><strong>Pilih sumber scan</strong><div style="font-size:11px;color:#64748b">Kamera laptop/HP, webcam USB, atau scanner USB QR.</div></div><span id="cameraCount" style="font-size:11px;color:#64748b">Belum dideteksi</span></div>
<div class="camera-controls"><label><span style="font-size:11px;font-weight:800">Pilih Kamera</span><select class="form-control" id="cameraSelect" disabled><option value="">Tekan Deteksi Kamera</option></select></label><button class="btn btn-outline-primary" type="button" id="detectCameras"><i class="bi bi-camera-video"></i> Deteksi</button></div>
</div>
<div id="reader"><div><strong>Siap memindai QR</strong><div style="font-size:12px;color:#64748b">Pilih kamera lalu aktifkan.</div></div></div>
<div class="actions"><button class="btn btn-primary" type="button" id="startCamera"><i class="bi bi-camera-video"></i> Aktifkan Kamera</button><button class="btn btn-secondary" type="button" id="stopCamera" disabled><i class="bi bi-camera-video-off"></i> Hentikan</button></div>
<div id="qrDecoderStatus" style="font-size:11px;color:#64748b;margin-top:7px">Pembaca QR: memeriksa dukungan browser…</div>
</div>
<div>
<div class="scanner-feedback-head"><label class="form-label">Input QR / Scanner USB<input class="form-control" id="usb" autocomplete="off" placeholder="Arahkan scanner USB ke sini…"></label><button class="scanner-unified-sound" type="button" id="soundToggle" aria-pressed="true"></button></div>
<div id="usbStatus" style="font-size:11px;color:#64748b;margin-top:6px">Scanner USB dibaca sebagai keyboard.</div>
<div id="result" class="result neutral">Belum ada scan.</div>
<div style="margin-top:14px"><label class="form-label">Cari siswa manual</label><input id="search" class="form-control" placeholder="Nama / NIS / NISN"><div id="results" class="search-results"></div></div>
</div>
</div>
</div>
<?php elseif($view==='dilayani'): ?>
<div class="card"><h2 style="margin:0 0 5px;font-size:18px;font-weight:900"><i class="bi bi-person-workspace"></i> Sedang Dilayani</h2><p style="margin:0 0 12px;color:#64748b;font-size:12px">Lengkapi pemeriksaan dan tindakan, lalu tekan Selesaikan.</p><div id="queueDilayani"></div></div>
<?php endif; ?>

<?php if($view==='antrian'): ?>
<div class="card"><h2 style="margin:0 0 5px;font-size:18px;font-weight:900"><i class="bi bi-people-fill"></i> Antrian Hari Ini</h2><p style="margin:0 0 12px;color:#64748b;font-size:12px">Siswa yang sudah terdaftar dan menunggu layanan.</p><div id="queueAntrian"></div></div>
<?php endif; ?>
</div></section></main>

<div class="uks-service service-modal" id="serviceModal"><div class="modal-card"><div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><h2 id="modalTitle" style="margin:0;font-size:19px;font-weight:900">Layanan UKS</h2><button type="button" class="btn btn-secondary" onclick="closeModal()">Tutup</button></div><div id="modalBody" style="margin-top:16px"></div></div></div>

<script src="<?=BASE_URL?>assets/js/scanner_sound.js"></script><script>
const BASE='<?=BASE_URL?>',CSRF=document.querySelector('input[name="csrf_token"]')?.value||'',VIEW=<?=json_encode($view)?>;
const CAN_MANAGE=<?=json_encode($canManage)?>;
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>'"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[m]));
let stream=null,loop=null,detector=null,qrDecoderPromise=null,lastCode='',lastAt=0,usbBuffer='',usbLastAt=0,usbTimer=null;
function msg(t,type='success'){const x=$('msg');if(!x)return;x.className='alert alert-'+(type==='error'?'danger':'success');x.innerHTML=t;x.style.display='block';setTimeout(()=>x.style.display='none',4000)}
const soundCtl=SPLASMA_SCANNER_SOUND.bind("soundToggle"); function beep(ok=true){return SPLASMA_SCANNER_SOUND.beep(ok)}
async function enqueue(code,method='QR'){
 if(!code)return;
 const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','enqueue');fd.append('kode_qr',code);fd.append('metode',method);
 try{const r=await fetch(BASE+'uks/kunjungan_api.php',{method:'POST',body:fd,cache:'no-store'}),d=await r.json();
  if(d.ok){$('result').className='result success';$('result').innerHTML='<strong>ANTRIAN #'+esc(d.nomor)+'</strong><br>'+esc(d.student.nama)+' · '+esc(d.student.kelas);msg(d.duplicate?'Siswa sudah berada di layanan aktif #'+d.nomor+'.':'Siswa berhasil masuk antrian #'+d.nomor+'.','success');beep(true);loadQueue();}
  else{$('result').className='result error';$('result').textContent=d.message||'Gagal mendaftarkan siswa.';msg(d.message||'Gagal mendaftarkan siswa.','error');beep(false)}
 }catch(e){msg('Tidak dapat terhubung ke server.','error');}
}
function clearUsb(){usbBuffer='';if(usbTimer)clearTimeout(usbTimer);usbTimer=null}
function processUsb(){const c=usbBuffer.trim();clearUsb();if(c){if($('usb'))$('usb').value=c;enqueue(c,'USB')}}
function usbKeyCapture(e){const t=e.target;if(t&&['INPUT','TEXTAREA','SELECT'].includes(t.tagName)&&t!==$('usb'))return;const now=performance.now();if(e.key==='Enter'||e.key==='Tab'){if(usbBuffer){e.preventDefault();processUsb()}return}if(typeof e.key!=='string'||e.key.length!==1)return;if(usbBuffer&&(now-usbLastAt)>90)clearUsb();usbBuffer+=e.key;usbLastAt=now;if(usbTimer)clearTimeout(usbTimer);usbTimer=setTimeout(()=>{if(usbBuffer.length>=3)processUsb();else clearUsb()},80)}
document.addEventListener('keydown',usbKeyCapture,true);
$('usb')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();enqueue(e.target.value,'USB');e.target.value=''}});
async function loadQrFallback(){if(window.BarcodeDetector||window.jsQR)return true;if(qrDecoderPromise)return qrDecoderPromise;qrDecoderPromise=new Promise(resolve=>{const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';s.onload=()=>resolve(!!window.jsQR);s.onerror=()=>resolve(false);document.head.appendChild(s)});return qrDecoderPromise}
async function detectCameras(){if(!navigator.mediaDevices?.getUserMedia)return;try{const p=await navigator.mediaDevices.getUserMedia({video:true,audio:false});p.getTracks().forEach(t=>t.stop());const ds=await navigator.mediaDevices.enumerateDevices(),cams=ds.filter(d=>d.kind==='videoinput'),sel=$('cameraSelect');sel.innerHTML='';cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||`Kamera ${i+1}`;sel.appendChild(o)});sel.disabled=!cams.length;if($('cameraCount'))$('cameraCount').textContent=cams.length+' kamera terdeteksi';msg(cams.length+' kamera terdeteksi.','success')}catch(e){msg('Izin kamera belum tersedia atau kamera tidak dapat dibaca.','error')}}
$('detectCameras')?.addEventListener('click',detectCameras);
async function decode(video,canvas,ctx){let code=null;if(window.BarcodeDetector){try{detector=detector||new BarcodeDetector({formats:['qr_code']});const a=await detector.detect(canvas);if(a.length)code=a[0].rawValue}catch(e){}}if(!code&&window.jsQR){try{const im=ctx.getImageData(0,0,canvas.width,canvas.height),d=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(d)code=d.data}catch(e){}}return code}
$('startCamera')?.addEventListener('click',async()=>{if(!$('cameraSelect').value)await detectCameras();if(!$('cameraSelect').value)return;if(!await loadQrFallback()&&!window.BarcodeDetector){msg('Decoder QR belum tersedia. Gunakan scanner USB.','error');return}try{if(stream)stream.getTracks().forEach(t=>t.stop());stream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:$('cameraSelect').value},width:{ideal:1280},height:{ideal:720}},audio:false});$('reader').innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=stream;$('reader').appendChild(v);$('startCamera').disabled=true;$('stopCamera').disabled=false;const c=document.createElement('canvas'),x=c.getContext('2d',{willReadFrequently:true});const tick=async()=>{if(!stream)return;if(v.readyState>=2&&v.videoWidth){const sc=Math.min(1,960/v.videoWidth);c.width=Math.round(v.videoWidth*sc);c.height=Math.round(v.videoHeight*sc);x.drawImage(v,0,0,c.width,c.height);const code=await decode(v,c,x);if(code&&(code!==lastCode||Date.now()-lastAt>2500)){lastCode=code;lastAt=Date.now();enqueue(code,'QR')}}loop=requestAnimationFrame(tick)};tick();msg('Kamera aktif. Scan QR. Kamera tetap aktif sampai Anda menekan Hentikan.','success')}catch(e){msg('Kamera tidak dapat digunakan.','error')}});
$('stopCamera')?.addEventListener('click',()=>{if(loop)cancelAnimationFrame(loop);loop=null;if(stream){stream.getTracks().forEach(t=>t.stop());stream=null}if($('reader'))$('reader').innerHTML='<div><strong>Kamera dihentikan</strong></div>';$('startCamera').disabled=false;$('stopCamera').disabled=true});
let st;
$('search')?.addEventListener('input',()=>{clearTimeout(st);st=setTimeout(async()=>{const q=$('search').value.trim();if(q.length<2){$('results').innerHTML='';return}const r=await fetch(BASE+'uks/kunjungan_api.php?action=lookup&q='+encodeURIComponent(q),{cache:'no-store'}),d=await r.json();$('results').innerHTML=(d.data||[]).map(s=>`<button type="button" class="btn btn-light w-100 text-start" style="margin-top:6px" data-qr="${esc(s.kode_qr)}"><strong>${esc(s.nama)}</strong><div style="font-size:11px">${esc(s.nis)} · ${esc(s.nisn||'-')} · ${esc(s.kelas)}</div></button>`).join('')||'<div style="padding:8px;color:#64748b">Siswa tidak ditemukan.</div>';document.querySelectorAll('#results [data-qr]').forEach(b=>b.onclick=()=>enqueue(b.dataset.qr,'MANUAL'))},250)});
function statusLabel(s){return {baru:'BARU',ditangani:'DILAYANI',selesai:'SELESAI',batal:'BATAL'}[s]||s}
async function loadQueue(){
 try{const r=await fetch(BASE+'uks/kunjungan_api.php?action=queue',{cache:'no-store',headers:{Accept:'application/json'}}),d=await r.json();if(!d.ok)throw new Error(d.message||'Gagal memuat layanan');
  const q=$(VIEW==='dilayani'?'queueDilayani':'queueAntrian');if(!q)return;
  const waiting=d.data.filter(x=>x.status==='baru'),serving=d.data.filter(x=>x.status==='ditangani');
  if($('countWaiting'))$('countWaiting').textContent=waiting.length;if($('countServing'))$('countServing').textContent=serving.length;
  const visible=d.data.filter(x=>VIEW==='antrian'?x.status==='baru':x.status==='ditangani');
  if(!visible.length){q.innerHTML='<div style="padding:24px;text-align:center;color:#64748b">'+(VIEW==='dilayani'?'Tidak ada siswa yang sedang dilayani.':'Belum ada siswa dalam antrian hari ini.')+'</div>';return}
  q.innerHTML=visible.map(r=>{
    let action='';
    const nm=esc(r.nama).replace(/'/g,"\\'");
    if(r.status==='baru'&&CAN_MANAGE)action=`<button class="btn btn-sm btn-primary" onclick="serviceStart(${r.id},'${nm}')">Layani</button><button class="btn btn-sm btn-outline-secondary" onclick="cancelService(${r.id})">Batal</button>`;
    if(r.status==='ditangani'&&CAN_MANAGE)action=`<button class="btn btn-sm btn-success" onclick="openFinishModal(${r.id},'${nm}')">Selesaikan &amp; Catat</button><button class="btn btn-sm btn-outline-secondary" onclick="cancelService(${r.id})">Batal</button>`;
    const cls=r.status==='baru'?'baru':r.status==='ditangani'?'ditangani':r.status==='selesai'?'selesai':'batal';
    const waktu=(r.waktu||'').slice(0,5);
    const jenis=r.jenis_layanan?esc(r.jenis_layanan):'Keperluan belum ditentukan';
    return `<div class="queue-item"><div class="queue-no">${esc(r.nomor_antrian)}</div><div><div class="queue-name">${esc(r.nama)} <span class="pill st-${cls}">${statusLabel(r.status)}</span></div><div class="queue-meta">${esc(r.kelas||'-')} · Masuk ${esc(waktu)} WIB · ${jenis}</div></div><div class="queue-actions">${action}</div></div>`;
  }).join('');
 }catch(e){console.error(e)}
}
function serviceStart(id,name){$('modalTitle').textContent='Mulai Layanan — '+name;$('modalBody').innerHTML=`<label class="form-label">Keperluan *</label><select id="mjenis" class="form-control"><option value="">Pilih keperluan...</option><option value="pemeriksaan">Pemeriksaan</option><option value="konsultasi">Konsultasi</option><option value="pertolongan">Pertolongan</option><option value="obat">Pemberian Obat</option><option value="rujukan">Rujukan</option><option value="lainnya">Lainnya</option></select><div class="actions"><button class="btn btn-primary" onclick="startService(${id})">Mulai Layanan</button><button class="btn btn-secondary" onclick="closeModal()">Batal</button></div>`;$('serviceModal').style.display='flex'}
async function startService(id){const jenis=$('mjenis').value;if(!jenis)return msg('Pilih keperluan layanan terlebih dahulu.','error');const f=new FormData();f.append('csrf_token',CSRF);f.append('action','start');f.append('id',id);f.append('jenis_layanan',jenis);const r=await fetch(BASE+'uks/kunjungan_api.php',{method:'POST',body:f}),d=await r.json();if(!d.ok)return msg(d.message||'Layanan belum dapat dimulai.','error');closeModal();msg('Layanan dimulai. Status siswa sekarang DILAYANI.','success');loadQueue()}
function openFinishModal(id,name){$('modalTitle').textContent='Selesaikan Layanan — '+name;$('modalBody').innerHTML=`<div class="alert alert-info">Isi catatan pelayanan. Status berubah menjadi <strong>SELESAI</strong> hanya setelah penyimpanan berhasil.</div><div class="form-grid"><div><label class="form-label">Keluhan</label><textarea id="mkel" class="form-control" rows="4"></textarea><label class="form-label" style="margin-top:10px">Hasil Pemeriksaan</label><textarea id="mhasil" class="form-control" rows="5"></textarea></div><div><label class="form-label">Tindakan</label><textarea id="mtind" class="form-control" rows="5"></textarea><label class="form-label" style="margin-top:10px">Obat</label><textarea id="mobat" class="form-control" rows="3" placeholder="Nama/jumlah bila diberikan; stok terstruktur dapat dikelola dari detail kunjungan."></textarea></div><div class="full"><label class="form-label">Catatan / Tindak Lanjut</label><textarea id="mcat" class="form-control" rows="4"></textarea></div></div><div class="actions"><button class="btn btn-success" onclick="submitFinishService(${id})">Simpan &amp; Selesaikan</button><button class="btn btn-secondary" onclick="closeModal()">Batal</button></div>`;$('serviceModal').style.display='flex'}
async function submitFinishService(id){const f=new FormData();f.append('csrf_token',CSRF);f.append('action','finish');f.append('id',id);f.append('keluhan',$('mkel').value);f.append('hasil_pemeriksaan',$('mhasil').value);f.append('tindakan',$('mtind').value);f.append('obat',$('mobat').value);f.append('catatan',$('mcat').value);try{const r=await fetch(BASE+'uks/kunjungan_api.php',{method:'POST',body:f,cache:'no-store'}),d=await r.json();if(!d.ok){msg(d.message||'Layanan belum dapat diselesaikan.','error');return}closeModal();msg('Layanan berhasil diselesaikan. Status siswa sekarang SELESAI.','success');loadQueue()}catch(e){msg('Gagal menyimpan penyelesaian layanan.','error')}}
function cancelService(id){if(!confirm('Batalkan antrian/layanan ini?'))return;const f=new FormData();f.append('csrf_token',CSRF);f.append('action','cancel');f.append('id',id);fetch(BASE+'uks/kunjungan_api.php',{method:'POST',body:f}).then(r=>r.json()).then(d=>{if(!d.ok)msg(d.message||'Gagal membatalkan.','error');else loadQueue()})}
function closeModal(){$('serviceModal').style.display='none'}
loadQueue();
// Gunakan RealtimeManager bersama bila tersedia; fallback tetap menjaga kompatibilitas.
if(window.SPLASMA_REALTIME&&typeof window.SPLASMA_REALTIME.register==='function'&&typeof window.SPLASMA_REALTIME.start==='function'){
 window.SPLASMA_REALTIME.register('uks-kunjungan-queue',loadQueue,5000);
 window.SPLASMA_REALTIME.start('uks-kunjungan-queue');
}else{
 let uksQueueTimer=null;
 const scheduleUksQueue=()=>{if(uksQueueTimer)clearTimeout(uksQueueTimer);if(document.visibilityState!=='hidden')uksQueueTimer=setTimeout(async()=>{await loadQueue();scheduleUksQueue()},5000)};
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'){if(uksQueueTimer)clearTimeout(uksQueueTimer);uksQueueTimer=null}else{loadQueue();scheduleUksQueue()}});
 scheduleUksQueue();
}
(async()=>{if(VIEW!=='antrian'||!CAN_MANAGE)return;if(!('BarcodeDetector'in window)){const ok=await loadQrFallback();if($('qrDecoderStatus'))$('qrDecoderStatus').textContent=ok?'Pembaca QR: jsQR fallback siap.':'Fallback QR belum tersedia.'}else if($('qrDecoderStatus'))$('qrDecoderStatus').textContent='Pembaca QR: BarcodeDetector tersedia.';detectCameras();$('usb')?.focus()})();
</script>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
