<?php
declare(strict_types=1);
require_once __DIR__ . '/../absensi/_helper.php';
require_once __DIR__ . '/../includes/auto_sessions.php';

$allSessions = auto_sessions_today($pdo);
$now = new DateTimeImmutable('now');
$nowTime = $now->format('H:i:s');
$today = $now->format('Y-m-d');
$todaySessions = array_values(array_filter($allSessions, static fn(array $row): bool => (string)$row['tanggal'] === $today));

// Prioritas scanner: sesi OPEN, lalu sesi DRAFT terdekat, lalu CLOSED terakhir.
// Dengan demikian scanner tidak pernah diam-diam memilih sesi yang sudah selesai.
$open = array_values(array_filter($todaySessions, static fn(array $row): bool => (string)($row['status_sesi'] ?? '') === 'OPEN'));
usort($open, static fn(array $a,array $b): int => strcmp((string)$b['jam_mulai'], (string)$a['jam_mulai']));
$draft = array_values(array_filter($todaySessions, static fn(array $row): bool => (string)($row['status_sesi'] ?? '') === 'DRAFT'));
usort($draft, static fn(array $a,array $b): int => strcmp((string)$a['jam_mulai'], (string)$b['jam_mulai']));
$closed = array_values(array_filter($todaySessions, static fn(array $row): bool => (string)($row['status_sesi'] ?? '') === 'CLOSED'));
usort($closed, static fn(array $a,array $b): int => strcmp((string)$b['jam_selesai'], (string)$a['jam_selesai']));
$scanMode = (string)($_GET['mode'] ?? 'masuk');
if (!in_array($scanMode, ['masuk','pulang'], true)) $scanMode='masuk';
if ($scanMode==='pulang') {
    $kehadiran = array_values(array_filter($todaySessions, static fn(array $row): bool => ($row['kategori']??'')==='kehadiran'));
    usort($kehadiran, static fn(array $a,array $b): int => strcmp((string)$b['jam_mulai'], (string)$a['jam_mulai']));
    $sesi = $kehadiran[0] ?? null;
} else {
    $sesi = $open[0] ?? $draft[0] ?? ($closed[0] ?? null);
}
$sesiId = $sesi ? (int)$sesi['id'] : 0;
if ($sesi && $sesi['kategori'] !== 'kehadiran' && $scanMode==='pulang') $sesi=null;
$pulangAktif = absensi_pulang_aktif($pdo);
$pulangMulai = absensi_setting_value($pdo, 'jam_mulai_pulang', '13:00') ?? '13:00';
$pulangSelesai = absensi_setting_value($pdo, 'jam_selesai_pulang', '16:30') ?? '16:30';
$page_title='Scanner QR'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Scanner QR</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content scanner-module">
<div class="page-header"><div><h1>Scanner QR Terpadu</h1><p>Kehadiran & sholat • kamera atau scanner USB.</p></div></div>
<div id="connection" class="scanner-connection online"><span class="dot"></span><strong>ONLINE</strong><span id="connectionText">Server sekolah terhubung</span><span id="queueBadge" class="queue-badge">Antrean: 0</span></div>
<div class="auto-session" id="autoSession"><div class="auto-session-icon">✓</div><div><div class="auto-session-label" id="scanModeLabel"><?= $scanMode==='pulang' ? 'MODE ABSEN PULANG' : 'MODE ABSEN MASUK' ?></div><strong id="autoSessionName"><?= $sesi ? e(($sesi['kategori']==='sholat' ? 'Absensi Sholat '.($sesi['sholat_nama']??'') : 'Absensi Kehadiran')) : 'Tidak ada kegiatan saat ini' ?></strong><span id="autoSessionMeta"><?= $sesi ? e($sesi['nama_sesi'].' · '.($scanMode==='pulang' ? $pulangMulai.'–'.$pulangSelesai : substr($sesi['jam_mulai'],0,5).'–'.substr($sesi['jam_selesai'],0,5)).' · '.($sesi['status_sesi']??'DRAFT')) : 'Sesi dipilih otomatis sesuai jadwal.' ?></span></div></div>
<div class="form-card">
<?php if ($sesi && $sesi['kategori']==='kehadiran'): ?><div class="scan-mode-switch" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px"><a class="btn btn-sm <?= $scanMode==='masuk'?'btn-primary':'btn-secondary' ?>" href="<?=BASE_URL?>scanner/index.php?mode=masuk"><i class="bi bi-box-arrow-in-right"></i> Absen Masuk</a><?php if ($pulangAktif): ?><a class="btn btn-sm <?= $scanMode==='pulang'?'btn-primary':'btn-secondary' ?>" href="<?=BASE_URL?>scanner/index.php?mode=pulang"><i class="bi bi-box-arrow-right"></i> Absen Pulang</a><?php endif; ?></div><?php endif; ?>
<div class="scanner-source-card"><div class="source-title"><div><strong>Sumber Scan</strong><div class="source-subtitle">Pilih kamera atau gunakan scanner USB.</div></div><span id="cameraCount" class="camera-count">Belum terdeteksi</span></div>
<div class="camera-controls"><label class="camera-select-wrap"><span>Pilih Kamera</span><select class="form-control" id="cameraSelect" disabled><option value="">Deteksi kamera terlebih dahulu</option></select></label><button class="btn btn-outline-primary" type="button" id="detectCameras"><i class="bi bi-camera-video"></i> Deteksi Kamera</button></div>
<div class="source-hints"><div><strong>Kamera</strong><span>Gunakan kamera laptop, HP, atau webcam USB.</span></div><div><strong>Scanner USB</strong><span>Scanner USB dibaca seperti keyboard. Arahkan QR ke input.</span></div></div></div>
<div class="scanner-layout"><div><div id="reader" class="scanner-reader"><div class="scanner-placeholder"><div class="placeholder-icon">⌁</div><strong>Siap Scan</strong><span>Pilih kamera, lalu aktifkan.</span></div></div><div class="form-actions"><button class="btn btn-primary" type="button" id="startCamera"><i class="bi bi-camera-video"></i> Aktifkan Kamera</button><button class="btn btn-secondary" type="button" id="stopCamera"><i class="bi bi-camera-video-off"></i> Stop Kamera</button><button class="btn btn-danger" type="button" id="closeScanner"><i class="bi bi-x-circle"></i> Tutup Scanner</button></div><small id="cameraHelp" class="text-muted">Kamera berjalan di HTTPS atau localhost.</small><div id="qrDecoderStatus" class="decoder-status">QR decoder: memeriksa…</div><button class="btn btn-outline-secondary btn-sm" type="button" id="prepareDecoder" style="margin-top:8px"><i class="bi bi-cloud-arrow-down"></i> Siapkan Decoder</button><div id="lanStatus" class="lan-status"></div><div class="decoder-help">Decoder QR siap digunakan offline.</div></div><div><div class="scanner-feedback-head"><label>QR / Scanner USB<input class="form-control" id="qrInput" autocomplete="off" placeholder="Scan QR di sini…" autofocus></label><button class="sound-toggle" type="button" id="soundToggle" aria-pressed="true"><i class="bi bi-volume-up-fill"></i><span>Suara ON</span></button></div><div id="usbStatus" class="usb-status"><strong>🔌 Mode Scanner USB siap.</strong> Arahkan QR ke Clabel/USB scanner. Input dapat diproses walaupun kolom tidak sedang aktif.</div><div id="result" class="scan-result neutral">Belum ada scan</div><div id="queueInfo" class="scan-result neutral">Antrean offline: 0</div><div class="recent-card"><div class="recent-head"><strong><i class="bi bi-clock-history"></i> Scan Terbaru</strong><span id="recentMeta">10 terakhir</span></div><div id="recentList" class="recent-list"><div class="recent-empty">Belum ada scan.</div></div></div></div></div></div></section></main>
<style>
.scanner-connection{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:10px;margin:0 0 16px;background:#f8fafc;color:#334155}.scanner-connection.online{background:#ecfdf5;color:#166534}.scanner-connection.offline{background:#fff7ed;color:#9a3412}.dot{width:10px;height:10px;border-radius:50%;background:currentColor}.queue-badge{margin-left:auto;font-weight:600}
.auto-session{display:flex;align-items:center;gap:14px;margin:0 0 16px;padding:15px 18px;border:1px solid #bfdbfe;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);color:#0f172a}.auto-session-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:#dcfce7;color:#166534;font-weight:900;font-size:20px}.auto-session-label{font-size:.72rem;font-weight:800;letter-spacing:.08em;color:#2563eb}.auto-session.session-open{border-color:#86efac;background:linear-gradient(135deg,#ecfdf5,#eff6ff)}.auto-session.session-draft{border-color:#fde68a;background:linear-gradient(135deg,#fffbeb,#eff6ff)}.auto-session.session-closed{border-color:#fecaca;background:linear-gradient(135deg,#fef2f2,#f8fafc)}.auto-session strong,.auto-session span{display:block}.auto-session strong{font-size:1.05rem;margin-top:2px}.auto-session span{font-size:.84rem;color:#64748b;margin-top:2px}.scanner-source-card{margin-top:18px;padding:18px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.source-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.source-subtitle{margin-top:3px;color:#64748b;font-size:.9rem}.camera-count{font-size:.86rem;color:#64748b;white-space:nowrap}.camera-controls{display:flex;gap:12px;align-items:end;margin-top:15px}.camera-select-wrap{flex:1;margin:0}.camera-select-wrap>span{display:block;font-size:.85rem;font-weight:600;margin-bottom:6px}.source-hints{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}.source-hints>div{padding:12px;border-radius:10px;background:#f8fafc;color:#475569}.source-hints strong,.source-hints span{display:block}.source-hints span{font-size:.82rem;line-height:1.45;margin-top:3px}.scanner-layout{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px}.scanner-feedback-head{display:flex;align-items:flex-end;gap:10px}.scanner-feedback-head label{flex:1;margin:0}.sound-toggle{height:38px;display:inline-flex;align-items:center;gap:7px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:10px;padding:0 12px;font-weight:700;cursor:pointer;white-space:nowrap}.sound-toggle.on{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}.sound-toggle.off{background:#f8fafc;color:#64748b}.recent-card{margin-top:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;overflow:hidden}.recent-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#334155}.recent-head strong{font-size:.92rem}.recent-head span{font-size:.74rem;color:#64748b}.recent-list{max-height:330px;overflow:auto}.recent-item{display:grid;grid-template-columns:42px 1fr auto;gap:10px;align-items:center;padding:10px 14px;border-bottom:1px solid #eef2f7}.recent-item:last-child{border-bottom:0}.recent-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#dcfce7;color:#15803d}.recent-name{font-weight:800;color:#17304f;font-size:.86rem}.recent-sub{font-size:.72rem;color:#64748b;margin-top:2px}.recent-time{font-size:.72rem;color:#64748b;font-weight:700}.recent-empty{padding:22px;text-align:center;color:#94a3b8;font-size:.82rem}.scanner-reader{min-height:300px;border:2px dashed #cbd5e1;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#f8fafc;overflow:hidden}.scanner-reader video{width:100%;height:auto;display:block}.scanner-placeholder{color:#64748b;text-align:center;padding:30px;display:flex;flex-direction:column;gap:7px}.placeholder-icon{font-size:38px}.scan-result{margin-top:18px;padding:18px;border-radius:12px;min-height:90px}.scan-result.neutral{background:#f8fafc;color:#475569}.scan-result.success{background:#dcfce7;color:#166534}.scan-result.error{background:#fee2e2;color:#991b1b}.usb-status{margin-top:10px;padding:10px 12px;border-radius:9px;background:#f8fafc;color:#475569;font-size:.85rem}.decoder-status{margin-top:8px;font-size:.82rem;color:#64748b}.decoder-help{margin-top:6px;padding:9px 11px;border-radius:9px;background:#f8fafc;color:#64748b;font-size:.8rem;line-height:1.45}.lan-status{margin-top:8px;padding:9px 11px;border-radius:9px;background:#f8fafc;color:#64748b;font-size:.8rem;line-height:1.45}
@media(max-width:800px){.scanner-layout,.source-hints{grid-template-columns:1fr}.camera-controls{align-items:stretch;flex-direction:column}.camera-count{white-space:normal}.page-header-actions{flex-wrap:wrap}.scanner-feedback-head{align-items:stretch;flex-direction:column}.sound-toggle{justify-content:center;width:100%}}
</style>
<script>
const base=<?=json_encode(BASE_URL)?>, csrf=<?=json_encode(csrf_token())?>;
const lanStatus=document.getElementById('lanStatus');
if(lanStatus){const secure=window.isSecureContext;lanStatus.innerHTML=secure?'<strong>LAN/Kamera:</strong> HTTPS aktif — kamera browser dapat digunakan.':'<strong>LAN/Kamera:</strong> halaman HTTP. Scanner USB tetap dapat digunakan; kamera browser membutuhkan HTTPS atau localhost.';}
const input=document.getElementById('qrInput'),result=document.getElementById('result'),reader=document.getElementById('reader'),conn=document.getElementById('connection'),connText=document.getElementById('connectionText'),cameraSelect=document.getElementById('cameraSelect'),cameraCount=document.getElementById('cameraCount'),cameraHelp=document.getElementById('cameraHelp'),autoSessionName=document.getElementById('autoSessionName'),autoSessionMeta=document.getElementById('autoSessionMeta');
let stream=null,detector=null,loop=null,lastCode='',lastAt=0,scannerClosed=false;
let currentSessionId=<?=json_encode($sesiId)?>, currentSession=<?=json_encode($sesi ?: null, JSON_UNESCAPED_UNICODE)?>;
const soundToggle=document.getElementById('soundToggle'), recentList=document.getElementById('recentList'); let soundEnabled=localStorage.getItem('absensi_scanner_sound')!=='off';
let audioContext=null;
const DB='absensi_offline_v1';let db=null,offlineData={sessions:[],students:[]};
function esc(s){return String(s??'').replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;',"\"":'&quot;'}[c]))}
function show(msg,type='error'){if(window.SPLASMA_SCAN_RESULT){var state=type==='success'?'success':(type==='neutral'?'neutral':(type==='duplicate'?'duplicate':'error'));SPLASMA_SCAN_RESULT.render(result,state,{title:state==='success'?'SCAN BERHASIL':state==='duplicate'?'SUDAH TERCATAT':state==='neutral'?'SIAP SCAN':'SCAN GAGAL',detail:msg});}else{result.className='scan-result '+type;result.innerHTML=msg}}
function updateSoundButton(){if(!soundToggle)return;soundToggle.className='sound-toggle '+(soundEnabled?'on':'off');soundToggle.setAttribute('aria-pressed',soundEnabled?'true':'false');soundToggle.innerHTML=soundEnabled?'<i class="bi bi-volume-up-fill"></i><span>Suara ON</span>':'<i class="bi bi-volume-mute-fill"></i><span>Suara OFF</span>'}
async function unlockAudio(){if(!soundEnabled)return false;try{const C=window.AudioContext||window.webkitAudioContext;if(!C)return false;if(!audioContext)audioContext=new C();if(audioContext.state==='suspended')await audioContext.resume();return audioContext.state==='running'}catch(e){return false}}
async function beep(kind='success'){if(!soundEnabled)return;try{if(!await unlockAudio())return;const ctx=audioContext;const o=ctx.createOscillator(),g=ctx.createGain();const cfg=kind==='success'?[880,1320]:kind==='duplicate'?[520,420]:[240,180];o.type='sine';o.frequency.setValueAtTime(cfg[0],ctx.currentTime);o.frequency.setValueAtTime(cfg[1],ctx.currentTime+.09);g.gain.setValueAtTime(.0001,ctx.currentTime);g.gain.exponentialRampToValueAtTime(.16,ctx.currentTime+.015);g.gain.exponentialRampToValueAtTime(.0001,ctx.currentTime+.18);o.connect(g);g.connect(ctx.destination);o.start();o.stop(ctx.currentTime+.2)}catch(e){}}
function renderRecent(rows){if(!recentList)return;if(!rows||!rows.length){recentList.innerHTML='<div class="recent-empty">Belum ada scan.</div>';return}recentList.innerHTML=rows.map(r=>`<div class="recent-item"><div class="recent-icon"><i class="bi bi-check-lg"></i></div><div><div class="recent-name">${esc(r.nama)}</div><div class="recent-sub">${esc(r.kelas||'Belum Ditempatkan')} · ${esc(r.status||'')}</div></div><div class="recent-time">${esc(r.waktu)}</div></div>`).join('')}
async function refreshRecent(){if(!currentSessionId||!recentList)return;try{const r=await fetch(base+'scanner/recent.php?sesi_id='+encodeURIComponent(currentSessionId),{cache:'no-store'});if(!r.ok)return;const d=await r.json();if(d.ok)renderRecent(d.rows||[])}catch(e){}}
if(soundToggle){updateSoundButton();soundToggle.addEventListener('click',async()=>{soundEnabled=!soundEnabled;localStorage.setItem('absensi_scanner_sound',soundEnabled?'on':'off');updateSoundButton();if(soundEnabled)await beep('success')})}
document.addEventListener('pointerdown',()=>{if(soundEnabled)unlockAudio()},{passive:true});
document.addEventListener('keydown',()=>{if(soundEnabled)unlockAudio()},{passive:true});
function dbOpen(){return new Promise((res,rej)=>{const r=indexedDB.open(DB,1);r.onupgradeneeded=()=>r.result.createObjectStore('data');r.onsuccess=()=>{db=r.result;res()};r.onerror=()=>rej(r.error)})}
function dbGet(k){return new Promise((res,rej)=>{const t=db.transaction('data');const r=t.objectStore('data').get(k);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
function dbPut(k,v){return new Promise((res,rej)=>{const t=db.transaction('data','readwrite');t.objectStore('data').put(v,k);t.oncomplete=res;t.onerror=()=>rej(t.error)})}
async function queueGet(){return (await dbGet('queue'))||[]}async function queueSet(q){await dbPut('queue',q);renderQueue(q)}function renderQueue(q){q=q||[];document.getElementById('queueBadge').textContent='Sinkronisasi otomatis: '+q.length;document.getElementById('queueInfo').textContent='Antrean lokal: '+q.length+(q.length?' — akan disinkronkan otomatis saat server tersedia.':'')}
function setConn(on,text){conn.className='scanner-connection '+(on?'online':'offline');connText.textContent=text}
async function prefetch(){try{await dbOpen();const r=await fetch(base+'scanner/offline-data.php',{cache:'no-store'});if(!r.ok)throw new Error();const d=await r.json();if(!d.ok)throw new Error();offlineData={sessions:d.sessions,students:d.students};await dbPut('prefetch',offlineData);await dbPut('csrf',d.csrf_token);renderQueue(await queueGet())}catch(e){}}
async function localScan(code){
  if(scanMode==='pulang'){show('<strong>ABSEN PULANG MEMBUTUHKAN KONEKSI</strong><br>Hubungkan perangkat ke server sekolah.','error');beep('error');return true}
  const s=offlineData.sessions.find(x=>String(x.id)===String(currentSessionId));
  const stu=offlineData.students.find(x=>x.kode_qr===code);
  if(!s||!stu)return false;
  const now=new Date();
  const seconds=now.getHours()*3600+now.getMinutes()*60+now.getSeconds();
  const start=timeToSeconds(s.jam_mulai), end=timeToSeconds(s.jam_selesai);
  if(scanMode!=='pulang' && seconds<start){show('<strong>KEGIATAN BELUM DIBUKA</strong><br>Mulai pukul '+esc(String(s.jam_mulai||'').slice(0,5))+' WIB.','error');beep('error');return true}
  if(scanMode!=='pulang' && s.jam_selesai && seconds>=end){show('<strong>ABSENSI DITUTUP</strong><br>Sesi berakhir pukul '+esc(String(s.jam_selesai||'').slice(0,5))+' WIB.','error');beep('error');return true}
  let status=s.kategori==='sholat'?'SUDAH':'H';if(s.kategori==='kehadiran'&&s.batas_terlambat&&now.toTimeString().slice(0,8)>s.batas_terlambat)status='T';let q=await queueGet();if(q.some(x=>x.sesi_id===Number(s.id)&&x.kode_qr===code)){show('<strong>↻ SUDAH TERCATAT</strong><br>'+esc(stu.nama)+'<br><small>Sudah masuk antrean offline.</small>','error');beep('duplicate');return true}q.push({sesi_id:Number(s.id),kode_qr:code,nama:stu.nama,nis:stu.nis,waktu:now.toISOString()});await queueSet(q);show('<strong>✓ ABSENSI BERHASIL</strong><br>'+esc(stu.nama)+' · '+esc(status)+'<br><small>Tersimpan offline dan akan disinkronkan.</small>','success');beep('success');return true
}
async function syncQueue(){if(!navigator.onLine)return;await prefetch();let q=await queueGet();if(!q.length)return;const currentCsrf=await dbGet('csrf')||csrf;let left=[];for(const item of q){try{const r=await fetch(base+'scanner/scan.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:currentCsrf,sesi_id:item.sesi_id,kode_qr:item.kode_qr,mode:'masuk',offline:'1',waktu_scan:item.waktu})});const d=await r.json();if(!d.ok&&!d.duplicate)left.push(item)}catch(e){left.push(item);break}}await queueSet(left);if(left.length===0)show('Semua transaksi offline berhasil disinkronkan ke server.','success');else if(q.length!==left.length)show(`Sinkronisasi sebagian: ${q.length-left.length} berhasil, ${left.length} masih menunggu.`,'success')}
function timeToSeconds(t){const p=String(t||'00:00:00').split(':').map(Number);return (p[0]||0)*3600+(p[1]||0)*60+(p[2]||0)}
function autoPickSession(){
  const now=new Date();
  const today=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
  const seconds=now.getHours()*3600+now.getMinutes()*60+now.getSeconds();
  const candidates=(offlineData.sessions||[]).filter(s=>String(s.tanggal)===today && (scanMode==='pulang' ? s.kategori==='kehadiran' : true));
  const stateOf=s=>{
    const start=timeToSeconds(s.jam_mulai), end=timeToSeconds(s.jam_selesai);
    if(scanMode==='pulang')return 'OPEN';
    if(seconds<start)return 'DRAFT';
    if(s.jam_selesai && seconds>=end)return 'CLOSED';
    return 'OPEN';
  };
  const open=candidates.filter(s=>stateOf(s)==='OPEN').sort((a,b)=>timeToSeconds(b.jam_mulai)-timeToSeconds(a.jam_mulai));
  const draft=candidates.filter(s=>stateOf(s)==='DRAFT').sort((a,b)=>timeToSeconds(a.jam_mulai)-timeToSeconds(b.jam_mulai));
  const closed=candidates.filter(s=>stateOf(s)==='CLOSED').sort((a,b)=>timeToSeconds(b.jam_selesai)-timeToSeconds(a.jam_selesai));
  const s=scanMode==='pulang' ? (closed[0]||open[0]||draft[0]||null) : (open[0]||draft[0]||closed[0]||null);
  currentSessionId=s?Number(s.id):0;
  currentSession=s;
  const state=s?stateOf(s):'DRAFT';
  if(s){
    autoSessionName.textContent=scanMode==='pulang' ? 'Absensi Kehadiran — PULANG' : (s.kategori==='sholat' ? 'Absensi Sholat '+(s.sholat_nama||'') : 'Absensi Kehadiran');
    const label=state==='OPEN'?'OPEN — BOLEH SCAN':(state==='DRAFT'?'DRAFT — BELUM DIBUKA':'CLOSED — ABSENSI DITUTUP');
    autoSessionMeta.textContent=(s.nama_sesi||'')+' · '+String(s.jam_mulai||'').slice(0,5)+'–'+String(s.jam_selesai||'').slice(0,5)+' · '+label;
    autoSession.classList.toggle('session-open',state==='OPEN');
    autoSession.classList.toggle('session-draft',state==='DRAFT');
    autoSession.classList.toggle('session-closed',state==='CLOSED');
  }else{
    autoSessionName.textContent='Tidak ada kegiatan absensi hari ini';
    autoSessionMeta.textContent='Sesi dipilih otomatis sesuai jadwal.';
    autoSession.classList.remove('session-open','session-draft','session-closed');
  }
}
async function refreshAutoSession(){
  if(navigator.onLine){
    try{const r=await fetch(base+'scanner/offline-data.php',{cache:'no-store'});if(r.ok){const d=await r.json();if(d.ok){offlineData={sessions:d.sessions,students:d.students};await dbPut('prefetch',offlineData);await dbPut('csrf',d.csrf_token);}}}catch(e){}
  }
  autoPickSession();
}

async function scan(code){if(scannerClosed)return;code=(code||'').trim();if(!code)return;if(!currentSessionId){show('Belum ada kegiatan absensi yang aktif saat ini. Sesi dipilih otomatis sesuai jadwal.','error');input.value='';return}try{if(!navigator.onLine)throw new Error('offline');const r=await fetch(base+'scanner/scan.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,sesi_id:currentSessionId,kode_qr:code,mode:scanMode}),cache:'no-store'});if(!r.ok)throw new Error('server');const d=await r.json();if(d.ok){show('<strong>✓ '+(scanMode==='pulang'?'ABSEN PULANG':'ABSEN MASUK')+' BERHASIL</strong><br>'+esc(d.nama)+' · '+esc(d.status)+'<br><small>'+esc(d.waktu)+'</small>','success');beep('success');}else if(d.duplicate){show('<strong>↻ SUDAH TERCATAT</strong><br>'+esc(d.nama||'Siswa')+'<br><small>'+esc(d.waktu||'')+'</small>','error');beep('duplicate');}else{show('<strong>! SCAN GAGAL</strong><br>'+esc(d.message),'error');beep('error');}await refreshRecent();}catch(e){setConn(false,'Koneksi terputus — transaksi disimpan otomatis di perangkat');await localScan(code)}input.value='';input.focus();renderQueue(await queueGet())}
input.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();scan(input.value)}});

// USB HID scanner: sebagian besar scanner 1D/2D bertindak seperti keyboard.
// Tangkap burst karakter yang sangat cepat agar scanner tetap dapat bekerja
// walaupun fokus browser tidak sedang berada di kolom input QR.
let usbBuffer='';
let usbTimer=null;
let usbLastAt=0;
const USB_BURST_MAX_GAP=90;
const USB_FLUSH_DELAY=180;
function clearUsbBuffer(){usbBuffer='';if(usbTimer){clearTimeout(usbTimer);usbTimer=null}}
function processUsbBuffer(){const code=usbBuffer.trim();clearUsbBuffer();if(code){input.value=code;scan(code)}}
function usbKeyCapture(e){
  if(scannerClosed) return;
  const target=e.target;
  // Kolom QR ditangani oleh listener Enter di atas.
  if(target===input) return;
  // Jangan mengganggu field/form lain atau shortcut browser.
  if(target && (target.matches?.('input,textarea,select,button,[contenteditable="true"]'))) return;
  if(e.ctrlKey||e.altKey||e.metaKey) return;
  const now=performance.now();
  if(e.key==='Enter'){
    if(usbBuffer){e.preventDefault();processUsbBuffer();}
    return;
  }
  if(typeof e.key!=='string'||e.key.length!==1) return;
  // Scanner HID mengirim karakter dalam burst cepat. Ketikan manusia normal
  // tidak diperlakukan sebagai input scanner jika jedanya terlalu panjang.
  if(usbBuffer && (now-usbLastAt)>USB_BURST_MAX_GAP) clearUsbBuffer();
  usbBuffer+=e.key;
  usbLastAt=now;
  if(usbTimer) clearTimeout(usbTimer);
  usbTimer=setTimeout(()=>{if(usbBuffer.length>=3)processUsbBuffer();else clearUsbBuffer()},USB_FLUSH_DELAY);
}
document.addEventListener('keydown',usbKeyCapture,true);
input.addEventListener('focus',()=>{if(!scannerClosed)document.getElementById('usbStatus').innerHTML='<strong>🔌 Scanner USB siap.</strong> Scan QR siswa; hasil akan diproses otomatis.'});


const QR_DECODER_LOCAL='./jsQR.js';
let qrDecoderPromise=null;
async function loadQrFallback(){
  const el=document.getElementById('qrDecoderStatus');
  if(window.BarcodeDetector){window.__qrDecoderReady=true;if(el)el.textContent='Pembaca QR: BarcodeDetector tersedia. Fallback jsQR akan disiapkan saat online.';return true}
  if(window.jsQR){window.__qrDecoderReady=true;if(el)el.textContent='Pembaca QR: jsQR fallback siap.';return true}
  if(qrDecoderPromise)return qrDecoderPromise;
  qrDecoderPromise=new Promise((resolve,reject)=>{
    if(el)el.textContent=navigator.onLine?'Menyiapkan pembaca QR fallback…':'Pembaca QR fallback belum tersedia offline. Siapkan decoder saat perangkat online.';
    const script=document.createElement('script');
    script.src=QR_DECODER_LOCAL;
    script.async=true;
    script.onload=()=>{
      if(window.jsQR){window.__qrDecoderReady=true;if(el)el.textContent='Pembaca QR: jsQR lokal siap dan dapat digunakan offline.';resolve(true)}
      else reject(new Error('jsQR tidak tersedia'));
    };
    script.onerror=()=>reject(new Error('Gagal memuat jsQR'));
    document.head.appendChild(script);
  }).catch(err=>{qrDecoderPromise=null;if(el)el.textContent='Pembaca QR fallback tidak tersedia. Gunakan kamera yang mendukung BarcodeDetector atau scanner USB.';return false});
  return qrDecoderPromise;
}
async function prepareDecoderFallback(){
  if(window.jsQR){const el=document.getElementById('qrDecoderStatus');if(el)el.textContent='Pembaca QR: jsQR fallback sudah siap.';return true}
  if(!navigator.onLine){const el=document.getElementById('qrDecoderStatus');if(el)el.textContent='Perangkat sedang offline. Fallback belum tersimpan di cache browser.';return false}
  return loadQrFallback();
}
const prepareDecoderButton=document.getElementById('prepareDecoder');
if(prepareDecoderButton)prepareDecoderButton.addEventListener('click',async()=>{prepareDecoderButton.disabled=true;try{const ok=await prepareDecoderFallback();show(ok?'Fallback decoder QR siap. Kamera akan memakai native decoder lalu mencoba jsQR bila native tidak menemukan QR.':'Fallback decoder belum berhasil disiapkan.',ok?'success':'error')}finally{prepareDecoderButton.disabled=false}});
function canvasCropSquare(source, ctx, canvas){
  const w=source.width||0,h=source.height||0;
  if(!w||!h)return false;
  const side=Math.floor(Math.min(w,h)*0.82);
  const sx=Math.floor((w-side)/2),sy=Math.floor((h-side)/2);
  canvas.width=Math.min(960,Math.max(320,side));
  canvas.height=canvas.width;
  ctx.drawImage(source,sx,sy,side,side,0,0,canvas.width,canvas.height);
  return true;
}
async function decodeQrFrame(video,fullCanvas,fullCtx,cropCanvas,cropCtx){
  let code=null;
  if(window.BarcodeDetector){
    try{detector=detector||new BarcodeDetector({formats:['qr_code']});const native=await detector.detect(fullCanvas);if(native.length)code=native[0].rawValue}catch(e){}
    if(!code&&canvasCropSquare(video,cropCtx,cropCanvas)){
      try{const native=await detector.detect(cropCanvas);if(native.length)code=native[0].rawValue}catch(e){}
    }
  }
  if(!code&&window.jsQR){
    try{const image=fullCtx.getImageData(0,0,fullCanvas.width,fullCanvas.height);const decoded=window.jsQR(image.data,image.width,image.height,{inversionAttempts:'attemptBoth'});if(decoded)code=decoded.data}catch(e){}
    if(!code&&canvasCropSquare(video,cropCtx,cropCanvas)){
      try{const image=cropCtx.getImageData(0,0,cropCanvas.width,cropCanvas.height);const decoded=window.jsQR(image.data,image.width,image.height,{inversionAttempts:'attemptBoth'});if(decoded)code=decoded.data}catch(e){}
    }
  }
  return code;
}
async function detectCameras(){
  cameraSelect.disabled=true;cameraSelect.innerHTML='<option value="">Mendeteksi kamera…</option>';cameraCount.textContent='Mendeteksi…';
  if(!window.isSecureContext){cameraCount.textContent='Butuh HTTPS/localhost';cameraSelect.innerHTML='<option value="">HTTPS atau localhost diperlukan</option>';show('Untuk akses kamera, buka aplikasi melalui <strong>HTTPS</strong> atau <strong>localhost</strong>.','error');return}
  if(!navigator.mediaDevices?.getUserMedia || !navigator.mediaDevices?.enumerateDevices){cameraCount.textContent='Tidak didukung';cameraSelect.innerHTML='<option value="">Browser tidak mendukung kamera</option>';show('Browser tidak menyediakan API kamera. Gunakan Chrome/Edge/Firefox terbaru atau scanner USB.','error');return}
  try{
    // Meminta izin terlebih dahulu agar label dan deviceId kamera dapat terlihat di browser.
    const permissionStream=await navigator.mediaDevices.getUserMedia({video:true,audio:false});
    permissionStream.getTracks().forEach(t=>t.stop());
    const devices=await navigator.mediaDevices.enumerateDevices();
    const cams=devices.filter(d=>d.kind==='videoinput');
    cameraSelect.innerHTML='';
    if(!cams.length){cameraCount.textContent='0 kamera';cameraSelect.innerHTML='<option value="">Tidak ada kamera terdeteksi</option>';show('Tidak ada kamera yang terdeteksi pada perangkat ini. Jika Anda memakai scanner USB QR, gunakan kolom <strong>QR / Scanner USB</strong>.','error');return;}
    cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||`Kamera ${i+1}`;cameraSelect.appendChild(o)});
    cameraSelect.disabled=false;cameraCount.textContent=`${cams.length} kamera terdeteksi`;
    cameraHelp.textContent='Kamera yang muncul adalah kamera pada perangkat yang sedang membuka halaman. Untuk HP, buka halaman ini langsung dari HP.';
    show(`<strong>${cams.length} kamera terdeteksi.</strong><br>Pilih kamera yang diinginkan lalu tekan Aktifkan Kamera.`,'success');
  }catch(e){cameraCount.textContent='Izin kamera ditolak/gagal';cameraSelect.innerHTML='<option value="">Tidak dapat membaca kamera</option>';show('Browser belum memberikan izin kamera. Izinkan kamera untuk situs ini, lalu tekan <strong>Deteksi Kamera</strong> lagi.','error')}
}

document.getElementById('detectCameras').onclick=detectCameras;
document.getElementById('startCamera').onclick=async()=>{
  scannerClosed=false;input.disabled=false;
  await unlockAudio();
  if(!currentSessionId){show('Belum ada kegiatan absensi yang aktif saat ini. Sesi dipilih otomatis sesuai jadwal.','error');return}
  if(!window.isSecureContext){show('Kamera browser memerlukan HTTPS atau localhost. Gunakan HTTPS di server sekolah atau localhost.','error');return}
  if(!navigator.mediaDevices?.getUserMedia){show('Browser tidak menyediakan akses kamera. Gunakan Chrome/Edge/Firefox terbaru atau scanner USB.','error');return}
  if(!cameraSelect.value){await detectCameras();if(!cameraSelect.value)return}
  if(!window.__qrDecoderReady){show('Menyiapkan pembaca QR lokal…','neutral');try{await loadQrFallback()}catch(e){show('Pembaca QR belum tersedia di perangkat. Gunakan Chrome/Edge terbaru atau scanner USB jika browser tidak mendukung BarcodeDetector.','error');return}}
  try{
    if(stream)stream.getTracks().forEach(t=>t.stop()); if(loop)cancelAnimationFrame(loop);
    stream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:cameraSelect.value},width:{ideal:1280},height:{ideal:720}},audio:false});
    reader.innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=stream;reader.appendChild(v);
    const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});
    const cropCanvas=document.createElement('canvas'),cropCtx=cropCanvas.getContext('2d',{willReadFrequently:true});
    let decoding=false,lastFrameAt=0;
    const tick=async()=>{if(!stream)return;const now=performance.now();if(now-lastFrameAt<120||decoding){loop=requestAnimationFrame(tick);return}lastFrameAt=now;try{if(v.readyState>=2&&v.videoWidth){const max=960,scale=Math.min(1,max/v.videoWidth);canvas.width=Math.max(320,Math.round(v.videoWidth*scale));canvas.height=Math.max(240,Math.round(v.videoHeight*scale));ctx.drawImage(v,0,0,canvas.width,canvas.height);decoding=true;const code=await decodeQrFrame(v,canvas,ctx,cropCanvas,cropCtx);decoding=false;if(code&&(code!==lastCode||Date.now()-lastAt>2500)){lastCode=code;lastAt=Date.now();await scan(code)}}}catch(e){decoding=false}loop=requestAnimationFrame(tick)};tick();
    try{const track=stream.getVideoTracks()[0],caps=track&&track.getCapabilities?track.getCapabilities():{};if(caps.focusMode&&caps.focusMode.includes('continuous'))await track.applyConstraints({advanced:[{focusMode:'continuous'}]})}catch(e){}
    show('Kamera aktif. Arahkan QR ke kotak pemindaian. Jaga QR cukup besar dan terang.','success');
  }catch(e){show('Kamera tidak dapat digunakan. Pastikan kamera tidak sedang dipakai aplikasi lain dan izin kamera sudah diberikan.','error')}
};
document.getElementById('stopCamera').onclick=()=>{
  if(stream)stream.getTracks().forEach(t=>t.stop());
  stream=null;
  if(loop)cancelAnimationFrame(loop);
  reader.innerHTML='<div class="scanner-placeholder"><div class="placeholder-icon">⌁</div><strong>Kamera dihentikan</strong><span>Scanner USB tetap dapat digunakan.</span></div>';
  if(!scannerClosed)input.focus();
};
document.getElementById('closeScanner').onclick=()=>{
  scannerClosed=true;
  if(stream)stream.getTracks().forEach(t=>t.stop());
  stream=null;
  if(loop)cancelAnimationFrame(loop);
  input.disabled=true;
  reader.innerHTML='<div class="scanner-placeholder"><div class="placeholder-icon">■</div><strong>Scanner ditutup manual</strong><span>Muat ulang halaman jika ingin membuka scanner kembali.</span></div>';
  show('<strong>Scanner ditutup.</strong><br>Sesi absensi tetap tersimpan dan tidak difinalisasi.','neutral');
};
window.addEventListener('online',async()=>{setConn(true,'Server sekolah terhubung');await syncQueue()});window.addEventListener('offline',()=>setConn(false,'Koneksi terputus — transaksi disimpan otomatis di perangkat'));
(async()=>{if('serviceWorker'in navigator){try{await navigator.serviceWorker.register(base+'scanner/sw.js?v=<?= e((string) (is_file(__DIR__ . '/sw.js') ? filemtime(__DIR__ . '/sw.js') : 1)) ?>',{scope:base+'scanner/'})}catch(e){}}await prefetch();setConn(navigator.onLine,navigator.onLine?'Server sekolah terhubung':'Koneksi terputus — transaksi disimpan otomatis di perangkat');renderQueue(await queueGet());if(navigator.onLine)syncQueue();autoPickSession();setInterval(refreshAutoSession,15000);refreshRecent();setInterval(refreshRecent,3000);if(!('BarcodeDetector'in window)){loadQrFallback().catch(()=>{})}else if(navigator.onLine&&!window.jsQR){setTimeout(()=>prepareDecoderFallback(),1200)}detectCameras()})();
</script><?php require_once __DIR__.'/../layouts/footer.php';?>
