<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0);$sesiId=(int)($_GET['sesi_id']??$_POST['sesi_id']??0);ekstra_require($extraId);
$extra=ekstra_detail($pdo,$extraId);if(!$extra)deny_access();$tp=active_tapel_id($pdo);if(!$tp)exit('Tahun Pelajaran aktif belum tersedia.');
$st=$pdo->prepare("SELECT es.*,se.nama semester_nama FROM ekstra_sesi es JOIN semester se ON se.id=es.semester_id WHERE es.id=? AND es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=? LIMIT 1");$st->execute([$sesiId,$extraId,$tp]);$session=$st->fetch();if(!$session)deny_access();$session=ekstra_sync_session_lifecycle($pdo,$sesiId) ?: $session;
$error='';$success='';$canAttend = ($session['status'] === 'aktif');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $aksi=(string)($_POST['aksi']??'manual');
    if ($session['status'] !== 'aktif') {
        $error = 'Absensi hanya dapat diubah ketika sesi masih Aktif.';
    } elseif ($aksi==='scan_qr') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $qr=trim((string)($_POST['kode_qr']??''));
            if($qr==='') throw new RuntimeException('QR siswa wajib diisi.');
            $st=$pdo->prepare("SELECT id,nis,nama,status FROM siswa WHERE kode_qr=? LIMIT 1");
            $st->execute([$qr]);
            $siswa=$st->fetch();
            if(!$siswa) throw new RuntimeException('QR tidak dikenali sebagai siswa.');
            if($siswa['status']!=='aktif') throw new RuntimeException('Siswa berstatus '.ucfirst((string)$siswa['status']).' dan tidak dapat melakukan scan.');
            $st=$pdo->prepare("SELECT 1 FROM ekstra_anggota WHERE siswa_id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND status='aktif' LIMIT 1");
            $st->execute([(int)$siswa['id'],$extraId,$tp]);
            if(!$st->fetchColumn()) throw new RuntimeException('QR terbaca, tetapi siswa bukan anggota aktif ekstrakurikuler ini.');
            $now=new DateTimeImmutable('now');
            if((string)$session['tanggal']!==$now->format('Y-m-d')) throw new RuntimeException('Tanggal scan tidak sesuai dengan tanggal sesi.');
            if(!empty($session['jam_mulai']) && $now->format('H:i:s') < (string)$session['jam_mulai']) throw new RuntimeException('Kegiatan belum dimulai sesuai jadwal.');
            if(!empty($session['jam_selesai']) && $now->format('H:i:s') >= (string)$session['jam_selesai']) throw new RuntimeException('Waktu kegiatan sudah berakhir. Absensi QR ditutup.');
            $st=$pdo->prepare('SELECT id,status,waktu_scan FROM ekstra_absensi WHERE sesi_id=? AND siswa_id=? LIMIT 1');
            $st->execute([$sesiId,(int)$siswa['id']]);
            $existing=$st->fetch();
            if($existing){ echo json_encode(['ok'=>false,'duplicate'=>true,'nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'status'=>$existing['status'],'waktu'=>$existing['waktu_scan'],'message'=>'Siswa sudah tercatat pada sesi ini.'],JSON_UNESCAPED_UNICODE); exit; }
            $up=$pdo->prepare("INSERT INTO ekstra_absensi(sesi_id,siswa_id,status,waktu_scan,scan_oleh,metode,keterangan) VALUES(?,?, 'H',?,?, 'QR',NULL)");
            $waktu=$now->format('Y-m-d H:i:s');
            $up->execute([$sesiId,(int)$siswa['id'],$waktu,user_id()]);
            echo json_encode(['ok'=>true,'nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'status'=>'H','waktu'=>$waktu,'message'=>'Absensi QR berhasil dicatat.'],JSON_UNESCAPED_UNICODE); exit;
        } catch(Throwable $e) {
            http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE); exit;
        }
    } else {
        $student = (int)($_POST['siswa_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $ket = trim((string)($_POST['keterangan'] ?? ''));
        if (!in_array($status, ['H','T','I','S','A'], true)) {
            $error = 'Status tidak valid.';
        } else {
            $st = $pdo->prepare("SELECT ea.siswa_id FROM ekstra_anggota ea WHERE ea.siswa_id=? AND ea.ekstrakurikuler_id=? AND ea.tahun_pelajaran_id=? AND ea.status='aktif'");
            $st->execute([$student, $extraId, $tp]);
            $validStudent = $st->fetchColumn();
            if (!$validStudent) {
                $error = 'Siswa bukan anggota aktif pada kegiatan ini.';
            } else {
                $up = $pdo->prepare("INSERT INTO ekstra_absensi(sesi_id,siswa_id,status,waktu_scan,scan_oleh,metode,keterangan) VALUES(?,?,?,?,?,'MANUAL',NULLIF(?,'')) ON DUPLICATE KEY UPDATE status=VALUES(status),waktu_scan=VALUES(waktu_scan),scan_oleh=VALUES(scan_oleh),metode='MANUAL',keterangan=VALUES(keterangan)");
                $up->execute([$sesiId, $student, $status, date('Y-m-d H:i:s'), user_id(), $ket]);
                $success = 'Absensi siswa diperbarui.';
            }
        }
    }
}

$st=$pdo->prepare("SELECT ea.id anggota_id,s.id siswa_id,s.nis,s.nisn,s.nama,mk.nama kelas_nama,COALESCE(xa.status,'-') status_absensi,xa.metode,xa.waktu_scan,xa.keterangan FROM ekstra_anggota ea JOIN siswa s ON s.id=ea.siswa_id LEFT JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=ea.tahun_pelajaran_id AND sk.status='aktif' LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN ekstra_absensi xa ON xa.sesi_id=? AND xa.siswa_id=s.id WHERE ea.ekstrakurikuler_id=? AND ea.tahun_pelajaran_id=? AND ea.status='aktif' ORDER BY s.nama");$st->execute([$sesiId,$extraId,$tp]);$members=$st->fetchAll();
$page_title='Absensi Ekstrakurikuler';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?><main class="main extra-page"><header class="topbar"><div class="topbar-title">Absensi Ekstrakurikuler</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content"><div class="page-header"><div><h1><?=e($extra['nama'])?> · Absensi</h1><p><?=e($session['tanggal'])?> · <?=e($session['semester_nama'])?> · Status <strong><?=e($session['status'])?></strong></p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/absensi_index.php?extra_id=<?=$extraId?>&menu=ekstra_absensi">← Kembali</a></div><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><div class="form-card qr-extra-card"><div class="section-title"><div><h2><i class="bi bi-qr-code-scan"></i> Absensi QR</h2><p>Scan QR siswa untuk mencatat Hadir. Scanner USB dan kamera dapat digunakan.</p></div><span class="status-badge <?= $canAttend ? 'status-aktif' : ($session['status']==='ditutup' ? 'status-nonaktif' : 'status-draft') ?>"><?= $canAttend ? 'QR AKTIF' : ($session['status']==='ditutup' ? 'QR DITUTUP' : 'MENUNGGU JAM MULAI') ?></span></div><div class="qr-extra-grid"><div><div id="extraQrReader" class="extra-qr-reader"><div class="extra-qr-placeholder"><i class="bi bi-qr-code"></i><strong>Siap memindai QR</strong><span>Pilih kamera lalu aktifkan.</span></div></div><div class="form-actions"><button type="button" class="btn btn-secondary" id="extraDetectCamera" <?= $canAttend ? '' : 'disabled' ?>>Deteksi Kamera</button><select id="extraCameraSelect" class="form-control" style="max-width:260px" disabled><option value="">Pilih kamera</option></select><button type="button" class="btn btn-primary" id="extraStartCamera" <?= $canAttend ? '' : 'disabled' ?>>Aktifkan Kamera</button><button type="button" class="btn btn-outline-secondary" id="extraStopCamera" <?= $canAttend ? '' : 'disabled' ?>>Hentikan Kamera</button></div></div><div><div class="scanner-feedback-head"><label>Input QR / Scanner USB<input id="extraQrInput" class="form-control" autocomplete="off" placeholder="<?= $canAttend ? 'Scan QR dengan scanner USB…' : 'Absensi belum dibuka' ?>" <?= $canAttend ? '' : 'disabled' ?>></label><button class="scanner-unified-sound" type="button" id="soundToggle" aria-pressed="true"></button></div><div id="extraQrResult" class="scan-result neutral">Belum ada scan.</div><div id="extraDecoderStatus" class="decoder-help">Pembaca QR: memeriksa dukungan browser…</div><div class="decoder-help"><?= $canAttend ? 'QR hanya diterima untuk anggota aktif selama sesi berlangsung.' : ($session['status']==='draft' ? 'Absensi akan tersedia setelah jam mulai kegiatan.' : 'Sesi sudah ditutup. Data hanya dapat dilihat.') ?></div></div></div></div><div class="dashboard-table-card"><div class="section-title"><div><h2>Daftar Anggota</h2><p>Absensi tidak otomatis mengubah siswa tanpa catatan menjadi Alpa.</p></div><button type="button" class="btn btn-secondary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Absensi Pertemuan</button></div><div class="table-responsive"><table class="data-table" id="extraAttendanceTable"><thead><tr><th>Nama</th><th>NIS</th><th>Kelas</th><th>Status</th><th>Metode</th><th>Waktu</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody id="extraAttendanceRows"><?php foreach($members as $m):?><tr><td><strong><?=e($m['nama'])?></strong></td><td><?=e($m['nis'])?></td><td><?=e($m['kelas_nama']??'-')?></td><td><span class="status-badge"><?=e($m['status_absensi'])?></span></td><td><?=e($m['metode']??'-')?></td><td><?=e($m['waktu_scan']??'-')?></td><td><?=e($m['keterangan']??'-')?></td><td><?php if($session['status']==='aktif'):?><form method="post" class="form-actions" style="margin:0;flex-wrap:wrap"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="sesi_id" value="<?=$sesiId?>"><input type="hidden" name="siswa_id" value="<?=$m['siswa_id']?>"><select class="form-control" name="status" style="width:90px"><option value="H">H</option><option value="T">T</option><option value="I">I</option><option value="S">S</option><option value="A">A</option></select><input class="form-control" name="keterangan" placeholder="Keterangan" style="max-width:180px"><button class="btn btn-sm btn-primary">Simpan</button></form><?php else:?>- pakai mode baca -<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div><div class="print-signatures no-print-sign-fix"><div>Mengetahui,<br>Kepala Sekolah<br><br><br>________________________<br>NIP. ____________________</div><div>Pembina/Pelatih<br><br><br><br>________________________<br>NIP. ____________________</div></div></section></main><script src="<?=BASE_URL?>assets/js/scanner_sound.js"></script><script>
const soundCtl=SPLASMA_SCANNER_SOUND.bind("soundToggle");
const EXTRA_BASE=<?=json_encode(BASE_URL)?>, EXTRA_CSRF=<?=json_encode(csrf_token())?>;
const EXTRA_QR_CDN='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
const EXTRA_QR_LOCAL='./jsqr.js';
let extraSwReady=false;
async function extraRegisterQrWorker(){
  if(!('serviceWorker' in navigator)) return false;
  try{
    const reg=await navigator.serviceWorker.register('./sw.js?v=20261001-qr1',{scope:'./'});
    await navigator.serviceWorker.ready;
    extraSwReady=true;
    return !!reg;
  }catch(e){ return false; }
}
const extraInput=document.getElementById('extraQrInput'), extraResult=document.getElementById('extraQrResult'), extraReader=document.getElementById('extraQrReader'), extraCameraSelect=document.getElementById('extraCameraSelect'), extraDecoderStatus=document.getElementById('extraDecoderStatus');
let extraStream=null, extraLoop=null, extraDetector=null, extraLastCode='', extraLastAt=0, extraQrPromise=null, extraDecoding=false, extraLastFrameAt=0;
function extraEsc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]))}
function extraShow(msg,type='error'){if(window.SPLASMA_SCAN_RESULT){const state=type==='success'?'success':(type==='duplicate'?'duplicate':(type==='neutral'?'neutral':(type==='rejected'?'rejected':'error')));SPLASMA_SCAN_RESULT.render(extraResult,state,{title:state==='success'?'SCAN BERHASIL':state==='duplicate'?'SUDAH TERCATAT':state==='neutral'?'SIAP SCAN':state==='rejected'?'SCAN DITOLAK':'SCAN GAGAL',detail:msg})}else{extraResult.className='scan-result '+type;extraResult.innerHTML=msg}}
function extraDecoderText(msg){if(extraDecoderStatus)extraDecoderStatus.textContent=msg}
async function extraLoadQrFallback(){
  if(window.jsQR){extraDecoderText('Pembaca QR: jsQR lokal siap.');return true}
  if(extraQrPromise)return extraQrPromise;
  extraDecoderText(navigator.onLine?'Pembaca QR: menyiapkan decoder lokal…':'Pembaca QR: memeriksa decoder lokal offline…');
  extraQrPromise=(async()=>{
    await extraRegisterQrWorker();
    if(window.jsQR){extraDecoderText('Pembaca QR: jsQR lokal siap.');return true}
    return await new Promise((resolve)=>{
      const script=document.createElement('script');
      script.src=EXTRA_QR_LOCAL;script.async=true;
      script.onload=()=>{const ok=typeof window.jsQR==='function';extraDecoderText(ok?'Pembaca QR: jsQR lokal siap.':(navigator.onLine?'Pembaca QR lokal belum tersedia.':'Decoder QR belum tersimpan di perangkat. Gunakan scanner USB.'));resolve(ok)};
      script.onerror=()=>{extraDecoderText(navigator.onLine?'Decoder QR lokal gagal disiapkan. Coba muat ulang saat online.':'Decoder QR belum tersimpan di perangkat. Gunakan scanner USB.');resolve(false)};
      document.head.appendChild(script);
    });
  })().finally(()=>{extraQrPromise=null});
  return extraQrPromise;
}
async function extraScan(code){code=(code||'').trim();if(!code)return;try{const r=await fetch(window.location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:EXTRA_CSRF,aksi:'scan_qr',kode_qr:code,extra_id:<?=json_encode($extraId)?>,sesi_id:<?=json_encode($sesiId)?>}),cache:'no-store'});const d=await r.json();if(d.ok){SPLASMA_SCANNER_SOUND.beep(true);extraShow('<strong>'+extraEsc(d.nama)+'</strong><br>NIS: '+extraEsc(d.nis)+'<br>Status: <strong>'+extraEsc(d.status)+'</strong><br>'+extraEsc(d.waktu),'success');}else if(d.duplicate){SPLASMA_SCANNER_SOUND.beep(false);extraShow('<strong>'+extraEsc(d.nama||'Siswa')+'</strong><br>'+extraEsc(d.message||'Sudah tercatat.'),'duplicate')}else {SPLASMA_SCANNER_SOUND.beep(false);extraShow(extraEsc(d.message||'QR tidak dapat diproses.'),'rejected')}}catch(e){extraShow('Server tidak dapat memproses scan QR.','error')}finally{extraInput.value='';extraInput.focus()}}
async function extraDecodeFrame(video,canvas,ctx,cropCanvas,cropCtx){
  let code=null;
  if(window.BarcodeDetector){
    try{extraDetector=extraDetector||new BarcodeDetector({formats:['qr_code']});const found=await extraDetector.detect(canvas);if(found.length)code=found[0].rawValue}catch(e){}
    if(!code){try{const side=Math.floor(Math.min(video.videoWidth,video.videoHeight)*.82);if(side>0){cropCanvas.width=Math.min(960,Math.max(320,side));cropCanvas.height=cropCanvas.width;cropCtx.drawImage(video,(video.videoWidth-side)/2,(video.videoHeight-side)/2,side,side,0,0,cropCanvas.width,cropCanvas.height);const found=await extraDetector.detect(cropCanvas);if(found.length)code=found[0].rawValue}}catch(e){}}
  }
  if(!code&&window.jsQR){
    try{const image=ctx.getImageData(0,0,canvas.width,canvas.height),decoded=window.jsQR(image.data,image.width,image.height,{inversionAttempts:'attemptBoth'});if(decoded)code=decoded.data}catch(e){}
    if(!code){try{const side=Math.floor(Math.min(video.videoWidth,video.videoHeight)*.82);if(side>0){cropCanvas.width=Math.min(960,Math.max(320,side));cropCanvas.height=cropCanvas.width;cropCtx.drawImage(video,(video.videoWidth-side)/2,(video.videoHeight-side)/2,side,side,0,0,cropCanvas.width,cropCanvas.height);const image=cropCtx.getImageData(0,0,cropCanvas.width,cropCanvas.height),decoded=window.jsQR(image.data,image.width,image.height,{inversionAttempts:'attemptBoth'});if(decoded)code=decoded.data}}catch(e){}}
  }
  return code;
}
extraInput?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();extraScan(extraInput.value)}});
// USB HID scanner: tangkap burst keyboard agar scanner tetap bekerja walaupun fokus bukan di input QR.
let extraUsbBuffer='',extraUsbTimer=null,extraUsbLastAt=0;const EXTRA_USB_BURST_MAX_GAP=90,EXTRA_USB_FLUSH_DELAY=180;
function extraClearUsbBuffer(){extraUsbBuffer='';if(extraUsbTimer){clearTimeout(extraUsbTimer);extraUsbTimer=null}}
function extraProcessUsbBuffer(){const code=extraUsbBuffer.trim();extraClearUsbBuffer();if(code&&extraInput){extraInput.value=code;extraScan(code)}}
function extraCaptureUsbKey(e){const target=e.target;if(target===extraInput)return;if(target&&(target.matches?.('input,textarea,select,button,[contenteditable="true"]')))return;if(e.ctrlKey||e.altKey||e.metaKey)return;const now=performance.now();if(e.key==='Enter'){if(extraUsbBuffer){e.preventDefault();extraProcessUsbBuffer()}return}if(typeof e.key!=='string'||e.key.length!==1)return;if(extraUsbBuffer&&(now-extraUsbLastAt)>EXTRA_USB_BURST_MAX_GAP)extraClearUsbBuffer();extraUsbBuffer+=e.key;extraUsbLastAt=now;if(extraUsbTimer)clearTimeout(extraUsbTimer);extraUsbTimer=setTimeout(()=>{if(extraUsbBuffer.length>=3)extraProcessUsbBuffer();else extraClearUsbBuffer()},EXTRA_USB_FLUSH_DELAY)}
document.addEventListener('keydown',extraCaptureUsbKey,true);
// Beberapa scanner USB tidak mengirim Enter/Tab. Field khusus USB tetap memproses
// nilai setelah burst input berhenti sejenak.
extraInput?.addEventListener('input',()=>{const value=extraInput.value.trim();if(!value)return;if(extraUsbTimer)clearTimeout(extraUsbTimer);extraUsbTimer=setTimeout(()=>{const code=extraInput.value.trim();if(code.length>=3){extraClearUsbBuffer();extraScan(code)}},EXTRA_USB_FLUSH_DELAY)});
document.getElementById('extraDetectCamera')?.addEventListener('click',async()=>{extraCameraSelect.disabled=true;extraCameraSelect.innerHTML='<option value="">Mendeteksi...</option>';if(!window.isSecureContext){extraShow('Kamera membutuhkan HTTPS atau localhost. Scanner USB tetap dapat digunakan.','error');return}try{const p=await navigator.mediaDevices.getUserMedia({video:true,audio:false});p.getTracks().forEach(t=>t.stop());const ds=await navigator.mediaDevices.enumerateDevices();const cams=ds.filter(d=>d.kind==='videoinput');extraCameraSelect.innerHTML='';cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||('Kamera '+(i+1));extraCameraSelect.appendChild(o)});extraCameraSelect.disabled=!cams.length;if(!cams.length)extraShow('Tidak ada kamera terdeteksi. Gunakan scanner USB QR.','error');else extraShow(cams.length+' kamera terdeteksi.','success')}catch(e){extraShow('Izin kamera ditolak/gagal. Gunakan scanner USB QR atau izinkan kamera.','error')}});
document.getElementById('extraStartCamera')?.addEventListener('click',async()=>{if(!window.isSecureContext){extraShow('Kamera membutuhkan HTTPS atau localhost. Scanner USB tetap dapat digunakan.','error');return}if(!extraCameraSelect.value){document.getElementById('extraDetectCamera').click();await new Promise(r=>setTimeout(r,500));if(!extraCameraSelect.value)return}try{
  if(!window.BarcodeDetector){const ok=await extraLoadQrFallback();if(!ok){extraShow('Pembaca QR lokal belum tersedia. Jika perangkat baru pertama kali dipakai, buka saat online sekali untuk menyiapkan decoder. Scanner USB tetap dapat digunakan.','error');return}}
  if(extraStream)extraStream.getTracks().forEach(t=>t.stop());if(extraLoop)cancelAnimationFrame(extraLoop);extraStream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:extraCameraSelect.value},width:{ideal:1280},height:{ideal:720},frameRate:{ideal:15,max:20}},audio:false});extraReader.innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=extraStream;extraReader.appendChild(v);const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true}),cropCanvas=document.createElement('canvas'),cropCtx=cropCanvas.getContext('2d',{willReadFrequently:true});
  const tick=async()=>{if(!extraStream)return;const now=performance.now();if(now-extraLastFrameAt<120||extraDecoding){extraLoop=requestAnimationFrame(tick);return}extraLastFrameAt=now;try{if(v.readyState>=2&&v.videoWidth){const scale=Math.min(1,960/v.videoWidth);canvas.width=Math.max(320,Math.round(v.videoWidth*scale));canvas.height=Math.max(240,Math.round(v.videoHeight*scale));ctx.drawImage(v,0,0,canvas.width,canvas.height);extraDecoding=true;const code=await extraDecodeFrame(v,canvas,ctx,cropCanvas,cropCtx);extraDecoding=false;if(code&&(code!==extraLastCode||Date.now()-extraLastAt>2500)){extraLastCode=code;extraLastAt=Date.now();await extraScan(code)}}}catch(e){extraDecoding=false}extraLoop=requestAnimationFrame(tick)};tick();try{const track=extraStream.getVideoTracks()[0],caps=track?.getCapabilities?track.getCapabilities():{};if(caps.focusMode?.includes('continuous'))await track.applyConstraints({advanced:[{focusMode:'continuous'}]})}catch(e){}extraShow('Kamera aktif. Scan QR. Kamera tetap aktif sampai Anda menekan Hentikan.','success')}catch(e){extraShow('Kamera tidak dapat digunakan. Gunakan scanner USB QR.','error')}});
document.getElementById('extraStopCamera')?.addEventListener('click',()=>{if(extraStream)extraStream.getTracks().forEach(t=>t.stop());extraStream=null;if(extraLoop)cancelAnimationFrame(extraLoop);extraReader.innerHTML='<div class="extra-qr-placeholder"><i class="bi bi-qr-code"></i><strong>Kamera dihentikan</strong><span>Scanner USB tetap dapat digunakan.</span></div>';extraInput.focus()});
(async()=>{await extraRegisterQrWorker();if(window.BarcodeDetector)extraDecoderText('Pembaca QR: BarcodeDetector tersedia; jsQR lokal digunakan bila diperlukan.');else if(await extraLoadQrFallback())extraDecoderText('Pembaca QR: jsQR lokal siap.');else extraDecoderText(navigator.onLine?'Pembaca QR: decoder lokal sedang disiapkan. Muat ulang bila perlu.':'Pembaca QR: decoder lokal belum tersimpan. Gunakan scanner USB.');})();
</script>
<script src="<?=BASE_URL?>assets/js/realtime.js"></script>
<script>
(function(){
  'use strict';
  const rows=document.getElementById('extraAttendanceRows');
  const table=document.getElementById('extraAttendanceTable');
  if(!rows||!table) return;
  let refreshing=false;
  async function refreshExtraAttendance(){
    if(refreshing || document.visibilityState==='hidden') return;
    // Jangan mengganti formulir manual saat pengguna sedang mengedit status/keterangan.
    const active=document.activeElement;
    if(active && table.contains(active) && active.matches('input,select,textarea,button')) return;
    refreshing=true;
    try{
      const response=await fetch(window.location.href,{method:'GET',headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store',credentials:'same-origin'});
      if(!response.ok) return;
      const html=await response.text();
      const doc=new DOMParser().parseFromString(html,'text/html');
      const fresh=doc.getElementById('extraAttendanceRows');
      if(fresh) rows.innerHTML=fresh.innerHTML;
    }catch(e){}finally{refreshing=false;}
  }
  if(window.SPLASMA_REALTIME){
    window.SPLASMA_REALTIME.register('ekstra-attendance-rows',refreshExtraAttendance,5000);
    window.SPLASMA_REALTIME.start('ekstra-attendance-rows');
  }
})();
</script><style>
.qr-extra-card{margin-bottom:18px}.qr-extra-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.extra-qr-reader{min-height:260px;border:2px dashed #cbd5e1;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#f8fafc;overflow:hidden}.extra-qr-reader video{width:100%;height:auto;display:block}.extra-qr-placeholder{text-align:center;color:#64748b;display:flex;flex-direction:column;gap:7px;padding:30px}.extra-qr-placeholder i{font-size:42px}.extra-qr-placeholder strong{color:#334155}.extra-qr-placeholder span{font-size:.85rem}.scan-result{margin-top:18px;padding:18px;border-radius:12px;min-height:90px}.scan-result.neutral{background:#f8fafc;color:#475569}.scan-result.success{background:#dcfce7;color:#166534}.scan-result.error{background:#fee2e2;color:#991b1b}@media(max-width:800px){.qr-extra-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>

<style>@media print{.sidebar,.topbar,.page-header,.qr-extra-card,.no-print{display:none!important}.main{margin:0!important}.content{padding:0!important}.dashboard-table-card{border:0!important;box-shadow:none!important}.dashboard-table-card .section-title{display:flex!important}.dashboard-table-card .section-title .no-print{display:none!important}.data-table{width:100%!important;border-collapse:collapse!important;font-size:9px!important}.data-table th,.data-table td{border:1px solid #000!important;padding:4px!important}.data-table th:last-child,.data-table td:last-child{display:none!important}.print-signatures{display:grid!important;grid-template-columns:1fr 1fr;gap:50px;margin-top:35px;text-align:center;page-break-inside:avoid}.no-print-sign-fix{display:grid!important}}.print-signatures{display:none}</style>
