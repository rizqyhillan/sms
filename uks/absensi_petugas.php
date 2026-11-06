<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);
if(!uks_can_manage($pdo)) deny_access();

$today=date('Y-m-d');$hari=(int)date('N');
$tp=active_tapel_id($pdo);$sem=active_semester_id($pdo,$tp);
$flash=uks_flash();$error='';
$days=[1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        require_valid_csrf();
        $action=(string)($_POST['action']??'');
        if($action==='mark'){
            $tipe=(string)($_POST['petugas_tipe']??'');
            $ref=(int)($_POST['petugas_ref_id']??0);
            $status=(string)($_POST['status']??'hadir');
            if(!in_array($tipe,['guru','siswa'],true)||$ref<=0)throw new RuntimeException('Petugas tidak valid.');
            if(!in_array($status,['hadir','terlambat','izin','sakit','alpa'],true))throw new RuntimeException('Status absensi tidak valid.');

            if($tipe==='guru'){
                $chk=$pdo->prepare("SELECT g.id,g.nama FROM guru g JOIN tugas_guru tg ON tg.guru_id=g.id JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
                    WHERE g.id=? AND g.status='aktif' AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND jt.kode='PETUGAS_UKS' LIMIT 1");
                $chk->execute([$ref,$tp,$sem]);
            }else{
                $chk=$pdo->prepare("SELECT s.id,s.nama,uj.id jadwal_id FROM uks_petugas_jadwal uj JOIN siswa_kelas sk ON sk.id=uj.siswa_kelas_id JOIN siswa s ON s.id=sk.siswa_id
                    WHERE s.id=? AND uj.semester_id=? AND uj.hari=? AND uj.aktif=1 AND s.status='aktif' LIMIT 1");
                $chk->execute([$ref,$sem,$hari]);
            }
            $person=$chk->fetch();if(!$person)throw new RuntimeException('Petugas tidak terdaftar/terjadwal sebagai Petugas UKS aktif.');

            $existing=$pdo->prepare("SELECT id FROM uks_petugas_absensi WHERE petugas_tipe=? AND ".($tipe==='guru'?'guru_id':'siswa_id')."=? AND tanggal=? LIMIT 1");
            $existing->execute([$tipe,$ref,$today]);
            if($existing->fetchColumn())throw new RuntimeException('Petugas sudah memiliki absensi hari ini.');

            $waktu=date('H:i:s');
            $jadwalId=$tipe==='siswa'?(int)$person['jadwal_id']:null;
            $pdo->prepare("INSERT INTO uks_petugas_absensi (petugas_jadwal_id,petugas_tipe,guru_id,siswa_id,tanggal,waktu_masuk,status,metode,verified_by,verified_at)
                VALUES(?,?,?,?,?,?,?,'manual',?,NOW())")->execute([$jadwalId,$tipe,$tipe==='guru'?$ref:null,$tipe==='siswa'?$ref:null,$today,$waktu,$status,user_id()]);
            uks_audit($pdo,'ABSENSI_PETUGAS_UKS',$pdo->lastInsertId(),'Absensi manual petugas UKS '.$tipe.' '.$person['nama'].' status '.strtoupper($status).'.');
            uks_flash('Absensi petugas berhasil dicatat.');
            redirect('uks/absensi_petugas.php');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

$guruRows=[];
if($tp && $sem){
    $st=$pdo->prepare("SELECT tg.id tugas_id,g.id guru_id,g.nama,g.nip,jt.nama jenis_tugas,
        tgj.hari,tgj.jam_mulai,tgj.jam_selesai,a.id absensi_id,a.waktu_masuk,a.waktu_keluar,a.status,a.metode
        FROM tugas_guru tg
        JOIN guru g ON g.id=tg.guru_id
        JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        LEFT JOIN tugas_guru_jadwal tgj ON tgj.tugas_guru_id=tg.id AND tgj.aktif=1 AND tgj.hari=?
        LEFT JOIN uks_petugas_absensi a ON a.guru_id=g.id AND a.petugas_tipe='guru' AND a.tanggal=?
        WHERE tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND jt.kode='PETUGAS_UKS' AND g.status='aktif'
        ORDER BY g.nama");
    $st->execute([$hari,$today,$tp,$sem]);$guruRows=$st->fetchAll();
}
$studentRows=[];
if($sem){
    $st=$pdo->prepare("SELECT uj.id jadwal_id,uj.jam_mulai,uj.jam_selesai,s.id siswa_id,s.nama,s.nis,mk.nama kelas_nama,
        a.id absensi_id,a.waktu_masuk,a.waktu_keluar,a.status,a.metode
        FROM uks_petugas_jadwal uj
        JOIN siswa_kelas sk ON sk.id=uj.siswa_kelas_id
        JOIN siswa s ON s.id=sk.siswa_id
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        LEFT JOIN uks_petugas_absensi a ON a.petugas_jadwal_id=uj.id AND a.petugas_tipe='siswa' AND a.tanggal=?
        WHERE uj.hari=? AND uj.aktif=1 AND uj.semester_id=? AND s.status='aktif'
        ORDER BY s.nama");
    $st->execute([$today,$hari,$sem]);$studentRows=$st->fetchAll();
}

$page_title='Absensi Petugas UKS';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main uks-module-page"><header class="topbar"><div class="topbar-title">Absensi Petugas UKS</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>
.ukspa{max-width:1180px;margin:auto}.ukspa .hero{background:linear-gradient(135deg,#07111f,#12324a);color:#fff;border-radius:18px;padding:24px;margin-bottom:18px}.ukspa .hero h1{margin:0;font-size:25px;font-weight:900}.ukspa .hero p{margin:7px 0 0;color:#cbd5e1}.ukspa .grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:18px}.ukspa .card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px;margin-bottom:18px}.ukspa .source{border:1px solid #e2e8f0;border-radius:12px;padding:13px;background:#f8fafc}.ukspa .camera{display:flex;gap:8px;align-items:end;margin-top:10px}.ukspa .camera label{flex:1}.ukspa #reader{min-height:220px;border:1px dashed #cbd5e1;border-radius:12px;margin-top:12px;display:grid;place-items:center;overflow:hidden;background:#f8fafc}.ukspa #reader video{width:100%;max-height:330px;object-fit:cover}.ukspa .actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}.ukspa .result{margin-top:12px;border-radius:10px;padding:12px}.ukspa .ok{background:#dcfce7;color:#166534}.ukspa .err{background:#fee2e2;color:#991b1b}.ukspa .neutral{background:#f8fafc;color:#475569}.ukspa .search-results{max-height:230px;overflow:auto;margin-top:6px}.ukspa .person-tabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:12px}.ukspa .person-tab{border:1px solid #cbd5e1;background:#fff;border-radius:9px;padding:8px 12px;font-weight:800;font-size:12px;cursor:pointer}.ukspa .person-tab.active{background:#0f172a;color:#fff}.ukspa .person-panel{display:none}.ukspa .person-panel.active{display:block}.ukspa .mini{font-size:11px;color:#64748b}.ukspa .badge{font-size:10px;font-weight:900;padding:4px 7px;border-radius:99px}.ukspa .hadir{background:#dcfce7;color:#15803d}.ukspa .terlambat{background:#fef3c7;color:#92400e}.ukspa .izin{background:#dbeafe;color:#1d4ed8}.ukspa .sakit{background:#ede9fe;color:#6d28d9}.ukspa .alpa{background:#fee2e2;color:#b91c1c}.ukspa .belum{background:#f1f5f9;color:#475569}@media(max-width:900px){.ukspa .grid{grid-template-columns:1fr}}
</style>
<div class="ukspa">
<div class="hero"><h1><i class="bi bi-calendar2-check-fill"></i> Absensi Petugas UKS</h1><p>Guru Petugas UKS dan siswa Petugas UKS dicatat pada transaksi UKS tersendiri. <strong>Tidak masuk ke absensi kehadiran siswa.</strong></p></div>
<?php if($flash):?><div class="alert alert-success"><?=e($flash)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="grid">
<div class="card">
<h2 style="margin:0 0 5px;font-size:18px;font-weight:900"><i class="bi bi-qr-code-scan"></i> Scan / Catat Absensi</h2>
<p class="mini" style="margin:0 0 12px">Alurnya dibuat seperti scanner BK: kamera, webcam USB, scanner USB QR, dan pencarian manual. QR guru atau QR siswa diproses otomatis.</p>
<?=csrf_field()?>
<div id="scanMsg" class="alert" style="display:none"></div>
<div class="source"><div style="display:flex;justify-content:space-between;gap:8px;align-items:center"><strong>Pilih sumber scan</strong><span id="cameraCount" class="mini">Belum dideteksi</span></div>
<div class="camera"><label><span class="mini">Pilih Kamera</span><select id="cameraSelect" class="form-control" disabled><option value="">Tekan Deteksi Kamera</option></select></label><button type="button" class="btn btn-outline-primary" id="detectCameras"><i class="bi bi-camera-video"></i> Deteksi</button></div></div>
<div id="reader"><div><strong>Siap memindai QR</strong><div class="mini">Pilih kamera lalu aktifkan.</div></div></div>
<div class="actions"><button type="button" class="btn btn-primary" id="startCamera"><i class="bi bi-camera-video"></i> Aktifkan Kamera</button><button type="button" class="btn btn-secondary" id="stopCamera" disabled><i class="bi bi-camera-video-off"></i> Hentikan</button></div>
<div id="qrStatus" class="mini" style="margin-top:7px">Pembaca QR: memeriksa dukungan browser…</div>
<div class="scanner-feedback-head" style="margin-top:14px"><label class="form-label">QR / Scanner USB<input id="usb" class="form-control" autocomplete="off" placeholder="Scan QR guru atau siswa…"></label><button class="scanner-unified-sound" type="button" id="soundToggle" aria-pressed="true"></button></div><div class="mini" style="margin-top:5px">Scanner USB yang bertindak sebagai keyboard dapat langsung digunakan.</div>
<div id="result" class="result neutral">Belum ada scan.</div>
<div style="margin-top:14px"><label class="form-label">Cari petugas manual</label><input id="search" class="form-control" placeholder="Nama / NIP / NIS"><div id="results" class="search-results"></div></div>
</div>

<div>
<div class="card"><h2 style="margin:0 0 5px;font-size:18px;font-weight:900"><i class="bi bi-calendar-day"></i> Petugas Hari Ini</h2><p class="mini">Hari <?=e($days[$hari])?>, <?=e(date('d-m-Y'))?>.</p>
<div class="person-tabs"><button type="button" class="person-tab active" data-tab="guru">Guru <span>(<?=count($guruRows)?>)</span></button><button type="button" class="person-tab" data-tab="siswa">Siswa <span>(<?=count($studentRows)?>)</span></button></div>
<div id="panel-guru" class="person-panel active"><div class="table-responsive"><table class="data-table"><thead><tr><th>Guru</th><th>Jadwal</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$guruRows):?><tr><td colspan="4" class="empty-state">Belum ada guru dengan tugas Petugas UKS aktif.</td></tr><?php else:foreach($guruRows as $r):?><tr><td><strong><?=e($r['nama'])?></strong><div class="mini"><?=e($r['nip']??'-')?></div></td><td><?=($r['jam_mulai']?e(substr($r['jam_mulai'],0,5)):'Tidak dijadwalkan')?><?= $r['jam_selesai']?'–'.e(substr($r['jam_selesai'],0,5)):'' ?></td><td><?= $r['status']?'<span class="badge '.e($r['status']).'">'.e(strtoupper($r['status'])).'</span>':'<span class="badge belum">BELUM ABSEN</span>' ?></td><td><?php if(!$r['absensi_id']):?><button class="btn btn-sm btn-success" onclick="manualMark('guru',<?=e((string)$r['guru_id'])?>,'<?=e($r['nama'])?>')">Hadir</button><?php else:?><?=e(substr((string)$r['waktu_masuk'],0,5))?> WIB<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<div id="panel-siswa" class="person-panel"><div class="table-responsive"><table class="data-table"><thead><tr><th>Siswa</th><th>Jadwal</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$studentRows):?><tr><td colspan="4" class="empty-state">Tidak ada siswa petugas UKS terjadwal hari ini.</td></tr><?php else:foreach($studentRows as $r):?><tr><td><strong><?=e($r['nama'])?></strong><div class="mini"><?=e($r['nis'])?> · <?=e($r['kelas_nama']??'-')?></div></td><td><?=e($r['jam_mulai']?substr($r['jam_mulai'],0,5):'-')?><?= $r['jam_selesai']?'–'.e(substr($r['jam_selesai'],0,5)):'' ?></td><td><?= $r['status']?'<span class="badge '.e($r['status']).'">'.e(strtoupper($r['status'])).'</span>':'<span class="badge belum">BELUM ABSEN</span>' ?></td><td><?php if(!$r['absensi_id']):?><button class="btn btn-sm btn-success" onclick="manualMark('siswa',<?=e((string)$r['siswa_id'])?>,'<?=e($r['nama'])?>')">Hadir</button><?php else:?><?=e(substr((string)$r['waktu_masuk'],0,5))?> WIB<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</div></div>
</div>
</div></section></main>

<div id="markModal" class="ukspa" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:2000;align-items:center;justify-content:center;padding:18px;overflow:auto"><div style="background:#fff;border-radius:16px;padding:20px;width:min(480px,calc(100vw - 36px));max-width:100%;box-sizing:border-box;min-width:0"><h2 id="markTitle" style="margin:0;font-size:18px">Catat Absensi</h2><form method="post" style="margin-top:14px"><?=csrf_field()?><input type="hidden" name="action" value="mark"><input type="hidden" name="petugas_tipe" id="markType"><input type="hidden" name="petugas_ref_id" id="markRef"><label class="form-label">Status</label><select name="status" class="form-control"><option value="hadir">Hadir</option><option value="terlambat">Terlambat</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpa</option></select><div class="actions"><button class="btn btn-success">Simpan</button><button type="button" class="btn btn-secondary" onclick="closeMark()">Batal</button></div></form></div></div>

<script src="<?=BASE_URL?>assets/js/scanner_sound.js"></script><script>
const BASE='<?=BASE_URL?>',CSRF=document.querySelector('input[name="csrf_token"]')?.value||'';
const $=id=>document.getElementById(id),esc=s=>String(s??'').replace(/[&<>'"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[m]));
let stream=null,loop=null,detector=null,qrPromise=null,lastCode='',lastAt=0,buf='',lastBuf=0,timer=null;
function show(t,ok=true){$('result').className='result '+(ok?'ok':'err');$('result').innerHTML=t}
function showMsg(t,ok=true){const x=$('scanMsg');x.className='alert alert-'+(ok?'success':'danger');x.textContent=t;x.style.display='block';setTimeout(()=>x.style.display='none',3500)}
async function scan(code,method='qr'){code=(code||'').trim();if(!code)return;const f=new FormData();f.append('csrf_token',CSRF);f.append('kode_qr',code);f.append('metode',method);try{const r=await fetch(BASE+'uks/scan_petugas_action.php',{method:'POST',body:f,cache:'no-store'}),d=await r.json();if(d.ok){show('<strong>ABSENSI BERHASIL</strong><br>'+esc(d.nama)+' · '+esc(d.tipe)+' · '+esc(d.status)+' · '+esc(d.waktu),true);showMsg(d.message||'Absensi berhasil dicatat.',true);beep(true);}else{show('<strong>SCAN GAGAL</strong><br>'+esc(d.message||'Tidak dapat mencatat absensi.'),false);showMsg(d.message||'Scan gagal.',false);beep(false)}}catch(e){show('Server tidak dapat dihubungi.',false);showMsg('Koneksi ke server gagal.',false)}}
const soundCtl=SPLASMA_SCANNER_SOUND.bind("soundToggle"); function beep(ok=true){return SPLASMA_SCANNER_SOUND.beep(ok)}
function clearBuf(){buf='';if(timer)clearTimeout(timer);timer=null}
function processBuf(){const c=buf.trim();clearBuf();if(c)scan(c,'usb')}
function keyCapture(e){const t=e.target;if(t&&['INPUT','TEXTAREA','SELECT'].includes(t.tagName)&&t!==$('usb'))return;const now=performance.now();if(e.key==='Enter'||e.key==='Tab'){if(buf){e.preventDefault();processBuf()}return}if(typeof e.key!=='string'||e.key.length!==1)return;if(buf&&(now-lastBuf)>90)clearBuf();buf+=e.key;lastBuf=now;if(timer)clearTimeout(timer);timer=setTimeout(()=>{if(buf.length>=3)processBuf();else clearBuf()},80)}document.addEventListener('keydown',keyCapture,true);$('usb')?.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();scan(e.target.value,'usb');e.target.value=''}});
async function loadQr(){if(window.BarcodeDetector||window.jsQR)return true;if(qrPromise)return qrPromise;qrPromise=new Promise(resolve=>{const s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';s.onload=()=>resolve(!!window.jsQR);s.onerror=()=>resolve(false);document.head.appendChild(s)});return qrPromise}
async function detect(){if(!navigator.mediaDevices?.getUserMedia)return;try{const p=await navigator.mediaDevices.getUserMedia({video:true,audio:false});p.getTracks().forEach(t=>t.stop());const ds=await navigator.mediaDevices.enumerateDevices(),cams=ds.filter(d=>d.kind==='videoinput'),sel=$('cameraSelect');sel.innerHTML='';cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||`Kamera ${i+1}`;sel.appendChild(o)});sel.disabled=!cams.length;$('cameraCount').textContent=cams.length+' kamera terdeteksi'}catch(e){showMsg('Izin kamera belum tersedia.',false)}}$('detectCameras').onclick=detect;
async function decode(v,c,x){let code=null;if(window.BarcodeDetector){try{detector=detector||new BarcodeDetector({formats:['qr_code']});const a=await detector.detect(c);if(a.length)code=a[0].rawValue}catch(e){}}if(!code&&window.jsQR){try{const im=x.getImageData(0,0,c.width,c.height),d=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(d)code=d.data}catch(e){}}return code}
$('startCamera').onclick=async()=>{if(!$('cameraSelect').value)await detect();if(!$('cameraSelect').value)return;if(!await loadQr()&&!window.BarcodeDetector){showMsg('Decoder QR belum tersedia. Gunakan scanner USB.',false);return}try{stream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:$('cameraSelect').value},width:{ideal:1280},height:{ideal:720}},audio:false});$('reader').innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=stream;$('reader').appendChild(v);$('startCamera').disabled=true;$('stopCamera').disabled=false;const c=document.createElement('canvas'),x=c.getContext('2d',{willReadFrequently:true});const tick=async()=>{if(!stream)return;if(v.readyState>=2&&v.videoWidth){const sc=Math.min(1,960/v.videoWidth);c.width=Math.round(v.videoWidth*sc);c.height=Math.round(v.videoHeight*sc);x.drawImage(v,0,0,c.width,c.height);const code=await decode(v,c,x);if(code&&(code!==lastCode||Date.now()-lastAt>2500)){lastCode=code;lastAt=Date.now();scan(code,'qr')}}loop=requestAnimationFrame(tick)};tick();showMsg('Kamera aktif. Scan QR. Kamera tetap aktif sampai Anda menekan Hentikan.',true)}catch(e){showMsg('Kamera tidak dapat digunakan.',false)}};
$('stopCamera').onclick=()=>{if(loop)cancelAnimationFrame(loop);loop=null;if(stream){stream.getTracks().forEach(t=>t.stop());stream=null}$('reader').innerHTML='<div><strong>Kamera dihentikan</strong></div>';$('startCamera').disabled=false;$('stopCamera').disabled=true};
let searchTimer;
$('search')?.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(async()=>{const q=$('search').value.trim();if(q.length<2){$('results').innerHTML='';return}const r=await fetch(BASE+'uks/scan_petugas_action.php?action=lookup&q='+encodeURIComponent(q),{cache:'no-store'}),d=await r.json();$('results').innerHTML=(d.data||[]).map(p=>`<button type="button" class="btn btn-light w-100 text-start" style="margin-top:6px" data-tipe="${esc(p.tipe)}" data-ref="${esc(p.id)}"><strong>${esc(p.nama)}</strong><div class="mini">${esc(p.identitas||'-')} · ${esc(p.jadwal||'Petugas UKS')}</div></button>`).join('')||'<div class="mini" style="padding:8px">Petugas tidak ditemukan.</div>';document.querySelectorAll('#results [data-ref]').forEach(b=>b.onclick=()=>manualMark(b.dataset.tipe,Number(b.dataset.ref),b.querySelector('strong').textContent))},250)});
function manualMark(t,r,n){$('markType').value=t;$('markRef').value=r;$('markTitle').textContent='Catat Absensi — '+n;$('markModal').style.display='flex'}
function closeMark(){$('markModal').style.display='none'}
document.querySelectorAll('.person-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.person-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.person-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');$('panel-'+b.dataset.tab).classList.add('active')});
(async()=>{if(!('BarcodeDetector'in window)){const ok=await loadQr();$('qrStatus').textContent=ok?'Pembaca QR: jsQR fallback siap.':'Fallback QR belum tersedia.'}else $('qrStatus').textContent='Pembaca QR: BarcodeDetector tersedia.';detect();$('usb')?.focus()})();
</script>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
