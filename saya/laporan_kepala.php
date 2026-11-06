<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
if (user_role() !== 'kepala_sekolah') { deny_access(); }
require_once __DIR__ . '/../includes/school_profile.php';
$profil = school_profile($pdo);
$today = date('Y-m-d');
function ks_scalar(PDO $pdo,string $sql,array $params=[]):int{try{$st=$pdo->prepare($sql);$st->execute($params);return (int)($st->fetchColumn()??0);}catch(Throwable){return 0;}}
function ks_rows(PDO $pdo,string $sql,array $params=[]):array{try{$st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}catch(Throwable){return [];}}
$tp=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC)?:[];
$tpId=(int)($tp['id']??0); $tpName=(string)($tp['nama']??'-');
$sem=$tpId?($pdo->prepare("SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? AND aktif=1 ORDER BY id DESC LIMIT 1")):null; $semester=[]; if($sem){$sem->execute([$tpId]);$semester=$sem->fetch(PDO::FETCH_ASSOC)?:[];}
$semId=(int)($semester['id']??0); $semName=(string)($semester['nama']??'-');
$totalSiswa=ks_scalar($pdo,"SELECT COUNT(*) FROM siswa WHERE status='aktif'");
$totalGuru=ks_scalar($pdo,"SELECT COUNT(*) FROM guru WHERE status='aktif'");
$totalKelas=ks_scalar($pdo,"SELECT COUNT(*) FROM master_kelas WHERE aktif=1");
$abs=['H'=>0,'T'=>0,'I'=>0,'S'=>0,'A'=>0];
if($tpId&&$semId){$r=ks_rows($pdo,"SELECT a.status,COUNT(DISTINCT a.siswa_id) jumlah FROM absensi a JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE sa.tahun_pelajaran_id=? AND sa.semester_id=? AND sa.tanggal=? GROUP BY a.status",[$tpId,$semId,$today]);foreach($r as $x){$k=(string)$x['status'];if(isset($abs[$k]))$abs[$k]=(int)$x['jumlah'];}}
$absTotal=array_sum($abs); $efektif=$absTotal?round(($abs['H']+$abs['T'])/$absTotal*100,1):0;
$sholat=['SUDAH'=>0,'BERHALANGAN'=>0,'BELUM'=>0];
if($tpId&&$semId){$r=ks_rows($pdo,"SELECT SUM(ash.status='SUDAH') sudah,SUM(ash.status='BERHALANGAN') berhalangan,COUNT(DISTINCT s.id) siswa FROM siswa s JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) JOIN sesi_absensi sa ON sa.tahun_pelajaran_id=? AND sa.semester_id=? AND sa.tanggal=? JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id AND ja.kategori='sholat' LEFT JOIN absensi_sholat ash ON ash.sesi_id=sa.id AND ash.siswa_id=s.id WHERE s.status='aktif'",[$tpId,$tpId,$semId,$today]);$x=$r[0]??[];$sholat['SUDAH']=(int)($x['sudah']??0);$sholat['BERHALANGAN']=(int)($x['berhalangan']??0);$sholat['BELUM']=max(0,(int)($x['siswa']??0)-$sholat['SUDAH']-$sholat['BERHALANGAN']);}
$reports=[
 ['Siswa','Total siswa aktif',$totalSiswa,'people-fill','blue'],
 ['Guru/Tendik','Guru aktif',$totalGuru,'person-badge-fill','green'],
 ['Kelas','Rombel aktif',$totalKelas,'grid-3x3-gap-fill','purple'],
 ['Kehadiran','Efektif hari ini',$efektif.'%','calendar2-check-fill','cyan'],
 ['Sholat','Sudah hari ini',$sholat['SUDAH'],'moon-stars-fill','indigo'],
 ['BK','Catatan belum selesai',ks_scalar($pdo,"SELECT COUNT(*) FROM catatan_bk WHERE status IN ('baru','proses')"),'chat-heart-fill','violet'],
 ['Kesiswaan','Pelanggaran hari ini',ks_scalar($pdo,"SELECT COUNT(*) FROM pelanggaran_siswa WHERE tanggal=?",[$today]),'exclamation-triangle-fill','orange'],
 ['Pembinaan','Masih berjalan',ks_scalar($pdo,"SELECT COUNT(*) FROM pembinaan_siswa WHERE status IN ('direncanakan','proses')"),'person-check-fill','amber'],
 ['Home Visit','Masih direncanakan',ks_scalar($pdo,"SELECT COUNT(*) FROM home_visit WHERE status='direncanakan'"),'house-door-fill','teal'],
 ['UKS','Kunjungan hari ini',ks_scalar($pdo,"SELECT COUNT(*) FROM uks_kunjungan WHERE tanggal=?",[$today]),'heart-pulse-fill','red'],
 ['Perpustakaan','Kunjungan hari ini',ks_scalar($pdo,"SELECT COUNT(*) FROM perpustakaan_kunjungan WHERE DATE(waktu_kunjungan)=?",[$today]),'book-half','blue'],
 ['Ekstrakurikuler','Sesi aktif/ditutup hari ini',ks_scalar($pdo,"SELECT COUNT(*) FROM ekstra_sesi WHERE tanggal=? AND status IN ('aktif','ditutup')",[$today]),'trophy-fill','yellow'],
];
$timeline=[];
$timeline=array_merge($timeline,ks_rows($pdo,"SELECT tanggal,nama,jenis,keterangan FROM kalender_kehadiran WHERE tahun_pelajaran_id=? AND tanggal>=? ORDER BY tanggal LIMIT 8",[$tpId,$today]));
$hv=ks_rows($pdo,"SELECT hv.tanggal_rencana tanggal,CONCAT('Home Visit · ',s.nama) nama,hv.status jenis,hv.tujuan keterangan FROM home_visit hv JOIN siswa s ON s.id=hv.siswa_id WHERE hv.tanggal_rencana>=? AND hv.status='direncanakan' ORDER BY hv.tanggal_rencana LIMIT 8",[$today]);
foreach($hv as $x){$timeline[]=$x;}
$es=ks_rows($pdo,"SELECT es.tanggal,CONCAT('Ekstrakurikuler · ',e.nama) nama,es.status jenis,es.materi keterangan FROM ekstra_sesi es JOIN ekstrakurikuler e ON e.id=es.ekstrakurikuler_id WHERE es.tahun_pelajaran_id=? AND es.tanggal>=? AND es.status IN ('aktif','ditutup') ORDER BY es.tanggal LIMIT 8",[$tpId,$today]);
foreach($es as $x){$timeline[]=$x;}
usort($timeline,fn($a,$b)=>strcmp((string)$a['tanggal'],(string)$b['tanggal']));$timeline=array_slice($timeline,0,12);
$page_title='Dashboard';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main ks-report-page"><header class="topbar"><div><h1>Dashboard</h1><div class="topbar-subtitle"><?=e($profil['nama_sekolah'])?></div></div><div class="topbar-user"><strong><?=e(user_name())?></strong><span>Kepala Sekolah</span></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-speedometer2"></i> Dashboard</h1><p>Semua informasi disajikan langsung secara ringkas dan visual. Tidak perlu memilih jenis laporan satu per satu.</p></div></div>
<div class="dashboard-context"><span class="context-chip"><i class="bi bi-calendar3"></i><?=e(date('d-m-Y',strtotime($today)))?></span><span class="context-chip"><i class="bi bi-calendar-range"></i><?=e($tpName)?></span><span class="context-chip"><i class="bi bi-layers"></i>Semester <?=e($semName)?></span></div>
<div class="dashboard-alert-grid">
<?php foreach($reports as $r): ?><div class="dashboard-alert alert-<?=e($r[4])?>"><div class="alert-icon"><i class="bi bi-<?=e($r[3])?>"></i></div><div><div class="alert-label"><?=e($r[0])?></div><div class="alert-value"><?=e((string)$r[2])?></div><div class="alert-text"><?=e($r[1])?></div></div></div><?php endforeach; ?>
</div>
<div class="dashboard-section ks-section"><div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Kehadiran Siswa Hari Ini</h3><p>Ringkasan kehadiran siswa yang tercatat pada sistem.</p></div></div><div class="dashboard-grid-modern ks-attendance-grid">
<?php $att=[['Hadir',$abs['H'],'success','check-circle-fill'],['Terlambat',$abs['T'],'warning','clock-fill'],['Izin',$abs['I'],'info','file-earmark-text-fill'],['Sakit',$abs['S'],'teal','heart-pulse-fill'],['Alpa',$abs['A'],'danger','x-circle-fill']]; foreach($att as $a): ?><div class="modern-card ks-status-card ks-status-<?=e($a[2])?>"><div class="card-top"><span class="card-label"><?=e($a[0])?></span><span class="card-icon"><i class="bi bi-<?=e($a[3])?>"></i></span></div><div class="card-number"><?=number_format((int)$a[1])?></div><div class="card-meta">Status hari ini</div></div><?php endforeach; ?></div></div>
<div class="dashboard-section ks-section"><div class="dashboard-section-head"><div><h3><span class="section-accent"></span><i class="bi bi-moon-stars-fill"></i> Kehadiran Sholat Hari Ini</h3><p>Informasi kehadiran sholat ditampilkan terpisah dari kehadiran umum.</p></div></div><div class="dashboard-grid-modern ks-attendance-grid">
<?php $pr=[['Sudah','SUDAH','success','check-circle-fill'],['Berhalangan','BERHALANGAN','warning','person-raised-hand'],['Belum / Tidak Hadir','BELUM','danger','x-circle-fill']]; foreach($pr as $a): ?><div class="modern-card ks-prayer-card"><div class="card-top"><span class="card-label"><?=e($a[0])?></span><span class="card-icon"><i class="bi bi-<?=e($a[3])?>"></i></span></div><div class="card-number"><?=number_format((int)$sholat[$a[1]])?></div><div class="card-meta">Status sholat hari ini</div></div><?php endforeach; ?></div></div>
<div class="dashboard-section"><div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Timeline Program & Kegiatan</h3><p>Hanya kegiatan yang memang memiliki tanggal pada data aplikasi.</p></div></div><div class="dashboard-table"><table><thead><tr><th>Tanggal</th><th>Program/Kegiatan</th><th>Status</th><th>Keterangan</th></tr></thead><tbody><?php if(!$timeline): ?><tr><td colspan="4" class="empty-state">Belum ada agenda bertanggal yang tercatat.</td></tr><?php else: foreach($timeline as $t): ?><tr><td><strong><?=e(date('d-m-Y',strtotime((string)$t['tanggal'])))?></strong></td><td><?=e((string)$t['nama'])?></td><td><?=e((string)$t['jenis'])?></td><td><?=e((string)($t['keterangan']??'-'))?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<div class="dashboard-note note-role"><i class="bi bi-eye-fill"></i><div><strong>Mode Kepala Sekolah — Read Only</strong>Informasi ini bersifat laporan/monitoring. Kepala Sekolah tidak melakukan perubahan data operasional dari halaman ini.</div></div>

<style>
/* V74 — Dashboard Pimpinan: presentation-only, data/query unchanged. */
.ks-report-page{background:linear-gradient(180deg,#f5f8fc 0%,#eef4f8 100%);min-height:100vh}
.ks-report-page .topbar{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:82px;padding:18px 28px;border-bottom:1px solid #dbe4ee;background:#fff}
.ks-report-page .topbar h1{font-size:28px;line-height:1.1;margin:0;font-weight:800;color:#16213a;letter-spacing:-.03em}
.ks-report-page .topbar-subtitle{margin-top:5px;font-size:13px;color:#64748b}
.ks-report-page .topbar-user{display:flex;align-items:flex-end;flex-direction:column;gap:2px;white-space:nowrap;color:#16213a}
.ks-report-page .topbar-user strong{font-size:15px}.ks-report-page .topbar-user span{font-size:12px;color:#64748b}
.ks-report-page .content{padding:24px 28px 36px;max-width:1500px;margin:0 auto}
.ks-report-page .page-header{position:relative;overflow:hidden;border:1px solid #bfdbfe;border-left:7px solid #2563eb;border-radius:18px;background:linear-gradient(135deg,#eff6ff,#ffffff);padding:24px 26px;margin-bottom:16px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
.ks-report-page .page-header h1{font-size:25px;margin:0 0 6px;color:#172554}.ks-report-page .page-header p{margin:0;color:#64748b;max-width:900px}
.ks-report-page .dashboard-context{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.ks-report-page .context-chip{background:#fff;border:1px solid #dbe4ee;border-radius:12px;padding:8px 11px;color:#334155;font-size:12px;box-shadow:0 2px 8px rgba(15,23,42,.04)}
.ks-report-page .dashboard-alert-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}
.ks-report-page .dashboard-alert{position:relative!important;overflow:hidden;display:flex;align-items:center;gap:13px;min-width:0}
.ks-report-page .dashboard-alert>div:last-child{min-width:0}.ks-report-page .alert-label,.ks-report-page .alert-text,.ks-report-page .alert-value{color:#fff!important}.ks-report-page .alert-value{font-size:29px!important;line-height:1.05;word-break:break-word}.ks-report-page .alert-label{font-weight:700}.ks-report-page .alert-text{opacity:.88}
.ks-report-page .dashboard-section{background:#fff;border:1px solid #dbe4ee;border-radius:18px;padding:18px;margin-bottom:18px;box-shadow:0 7px 20px rgba(15,23,42,.05)}
.ks-report-page .dashboard-section-head{margin-bottom:13px}.ks-report-page .dashboard-section-head h3{margin:0;font-size:18px;color:#172554}.ks-report-page .dashboard-section-head p{margin:4px 0 0;color:#64748b;font-size:12px}
.ks-report-page .dashboard-grid-modern{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.ks-report-page .ks-attendance-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
.ks-report-page .modern-card{border:1px solid #dbe4ee;border-radius:14px;padding:15px;background:#f8fafc;min-width:0}.ks-report-page .card-top{display:flex;align-items:center;justify-content:space-between;gap:8px}.ks-report-page .card-label{font-weight:700;color:#334155;font-size:13px}.ks-report-page .card-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;background:#e0e7ff;color:#3730a3}.ks-report-page .card-number{font-size:27px;font-weight:800;color:#0f172a;margin-top:8px}.ks-report-page .card-meta{font-size:11px;color:#64748b;margin-top:2px}
.ks-report-page .ks-prayer-card:nth-child(1){background:#ecfdf5;border-color:#bbf7d0}.ks-report-page .ks-prayer-card:nth-child(2){background:#fffbeb;border-color:#fde68a}.ks-report-page .ks-prayer-card:nth-child(3){background:#fef2f2;border-color:#fecaca}
.ks-report-page .dashboard-table{overflow:auto}.ks-report-page .dashboard-table table{width:100%;border-collapse:separate;border-spacing:0;font-size:12px}.ks-report-page .dashboard-table th{background:#172554;color:#fff;padding:10px 11px;text-align:left}.ks-report-page .dashboard-table td{padding:10px 11px;border-bottom:1px solid #e2e8f0;color:#334155}.ks-report-page .dashboard-table tbody tr:nth-child(even){background:#f8fafc}.ks-report-page .dashboard-table tbody tr:hover{background:#eff6ff}
.ks-report-page .dashboard-note{display:flex;gap:10px;align-items:flex-start;border-radius:14px;padding:13px 15px}.ks-report-page .dashboard-note strong{display:block;margin-bottom:3px}.ks-report-page .note-role{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.ks-report-page .note-role i{font-size:18px}
@media(max-width:1100px){.ks-report-page .dashboard-alert-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.ks-report-page .topbar{padding:15px 18px}.ks-report-page .topbar-user{display:none}.ks-report-page .content{padding:18px}.ks-report-page .dashboard-alert-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:1fr 1fr}.ks-report-page .page-header h1{font-size:21px}}
@media(max-width:520px){.ks-report-page .dashboard-alert-grid,.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:1fr}}
/* V77 — Kepala Sekolah: full-color proportional cards. Presentation only. */
.ks-report-page .dashboard-alert-grid{
    grid-auto-rows:118px;
}
.ks-report-page .dashboard-alert-grid .dashboard-alert{
    height:118px;
    min-height:118px;
    padding:14px 15px;
    border-radius:16px;
    box-sizing:border-box;
    box-shadow:0 8px 20px rgba(15,23,42,.12);
}
.ks-report-page .dashboard-alert-grid .dashboard-alert::after{
    content:"";
    position:absolute;
    width:110px;height:110px;
    right:-34px;bottom:-52px;
    border-radius:50%;
    background:rgba(255,255,255,.12);
    pointer-events:none;
}
.ks-report-page .dashboard-alert-grid .dashboard-alert>div{position:relative;z-index:1}
.ks-report-page .dashboard-alert-grid .alert-icon{
    width:44px;height:44px;flex:0 0 44px;border-radius:12px;
    font-size:19px;background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.20);color:#fff;
}
.ks-report-page .dashboard-alert-grid .alert-label{
    font-size:12px;line-height:1.2;font-weight:850;color:#fff!important;
}
.ks-report-page .dashboard-alert-grid .alert-value{
    font-size:28px!important;line-height:1.05;font-weight:900;color:#fff!important;
    margin-top:2px;
}
.ks-report-page .dashboard-alert-grid .alert-text{
    font-size:10.5px;line-height:1.25;color:rgba(255,255,255,.90)!important;
    margin-top:4px;
}
.ks-report-page .dashboard-alert.alert-blue{background:linear-gradient(135deg,#2563eb,#1d4ed8);border-color:#1e40af}
.ks-report-page .dashboard-alert.alert-green{background:linear-gradient(135deg,#16a34a,#15803d);border-color:#166534}
.ks-report-page .dashboard-alert.alert-purple{background:linear-gradient(135deg,#7c3aed,#6d28d9);border-color:#5b21b6}
.ks-report-page .dashboard-alert.alert-cyan{background:linear-gradient(135deg,#06b6d4,#0284c7);border-color:#0369a1}
.ks-report-page .dashboard-alert.alert-indigo{background:linear-gradient(135deg,#4f46e5,#4338ca);border-color:#3730a3}
.ks-report-page .dashboard-alert.alert-violet{background:linear-gradient(135deg,#8b5cf6,#7c3aed);border-color:#6d28d9}
.ks-report-page .dashboard-alert.alert-orange{background:linear-gradient(135deg,#f97316,#ea580c);border-color:#c2410c}
.ks-report-page .dashboard-alert.alert-amber{background:linear-gradient(135deg,#f59e0b,#d97706);border-color:#b45309}
.ks-report-page .dashboard-alert.alert-teal{background:linear-gradient(135deg,#14b8a6,#0f766e);border-color:#115e59}
.ks-report-page .dashboard-alert.alert-red{background:linear-gradient(135deg,#ef4444,#dc2626);border-color:#b91c1c}
.ks-report-page .dashboard-alert.alert-yellow{background:linear-gradient(135deg,#eab308,#ca8a04);border-color:#a16207}

.ks-report-page .ks-attendance-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
.ks-report-page .ks-status-card{height:104px;min-height:104px;padding:13px 14px;box-sizing:border-box;border:1px solid transparent;color:#fff;box-shadow:0 7px 17px rgba(15,23,42,.10);overflow:hidden;position:relative}
.ks-report-page .ks-status-card .card-label,.ks-report-page .ks-status-card .card-number,.ks-report-page .ks-status-card .card-meta{color:#fff!important;position:relative;z-index:1}
.ks-report-page .ks-status-card .card-label{font-size:12px;font-weight:850}
.ks-report-page .ks-status-card .card-number{font-size:27px;font-weight:900;margin-top:7px}
.ks-report-page .ks-status-card .card-meta{font-size:10.5px;color:rgba(255,255,255,.85)!important}
.ks-report-page .ks-status-card .card-icon{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.18)}
.ks-report-page .ks-status-success{background:linear-gradient(135deg,#16a34a,#15803d);border-color:#166534}
.ks-report-page .ks-status-warning{background:linear-gradient(135deg,#f59e0b,#d97706);border-color:#b45309}
.ks-report-page .ks-status-info{background:linear-gradient(135deg,#2563eb,#1d4ed8);border-color:#1e40af}
.ks-report-page .ks-status-teal{background:linear-gradient(135deg,#14b8a6,#0f766e);border-color:#115e59}
.ks-report-page .ks-status-danger{background:linear-gradient(135deg,#ef4444,#dc2626);border-color:#b91c1c}
.ks-report-page .ks-prayer-card{height:104px;min-height:104px;padding:13px 14px;box-sizing:border-box;color:#fff;border:1px solid transparent!important;box-shadow:0 7px 17px rgba(15,23,42,.10)}
.ks-report-page .ks-prayer-card .card-label,.ks-report-page .ks-prayer-card .card-number,.ks-report-page .ks-prayer-card .card-meta{color:#fff!important}
.ks-report-page .ks-prayer-card .card-icon{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.18)}
.ks-report-page .ks-prayer-card:nth-child(1){background:linear-gradient(135deg,#16a34a,#15803d);border-color:#166534!important}
.ks-report-page .ks-prayer-card:nth-child(2){background:linear-gradient(135deg,#f59e0b,#d97706);border-color:#b45309!important}
.ks-report-page .ks-prayer-card:nth-child(3){background:linear-gradient(135deg,#ef4444,#dc2626);border-color:#b91c1c!important}
@media(max-width:1100px){.ks-report-page .dashboard-alert-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.ks-report-page .dashboard-alert-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.ks-report-page .dashboard-alert-grid,.ks-report-page .dashboard-grid-modern,.ks-report-page .ks-attendance-grid{grid-template-columns:1fr}}

</style>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
