<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (user_role() !== 'siswa') {
    http_response_code(403);
    exit('Halaman ini khusus akun siswa.');
}

$siswaId = user_siswa_id();
if (!$siswaId) {
    http_response_code(403);
    exit('Akun siswa belum terhubung ke data siswa.');
}

$tapelId = (int)($_GET['tapel_id'] ?? 0);
$tpRows = $pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY id DESC")->fetchAll();
$activeTp = null;
foreach ($tpRows as $tp) { if ((int)$tp['aktif'] === 1) { $activeTp = $tp; break; } }
if ($tapelId <= 0) $tapelId = (int)($activeTp['id'] ?? ($tpRows[0]['id'] ?? 0));
$tapelName = '-';
foreach ($tpRows as $tp) if ((int)$tp['id'] === $tapelId) $tapelName = (string)$tp['nama'];

$st = $pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.jk,mk.nama kelas_nama
    FROM siswa s
    LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
    LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
    WHERE s.id=? LIMIT 1");
$st->execute([$tapelId,$siswaId]);
$siswa = $st->fetch();
if (!$siswa) { http_response_code(404); exit('Data siswa tidak ditemukan.'); }

$summary = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'pelanggaran'=>0,'poin'=>0,'pembinaan'=>0,'konsultasi'=>0,'home_visit'=>0,'ekstra'=>0];

$st=$pdo->prepare("SELECT COALESCE(SUM(a.status='H'),0) hadir,COALESCE(SUM(a.status='T'),0) terlambat,COALESCE(SUM(a.status='I'),0) izin,COALESCE(SUM(a.status='S'),0) sakit,COALESCE(SUM(a.status='A'),0) alpa
FROM absensi a JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE a.siswa_id=? AND sa.tahun_pelajaran_id=? AND sa.jenis_sholat_id IS NULL");
$st->execute([$siswaId,$tapelId]); $x=$st->fetch()?:[];
foreach(['hadir','terlambat','izin','sakit','alpa'] as $k) $summary[$k]=(int)($x[$k]??0);

$st=$pdo->prepare("SELECT COUNT(*),COALESCE(SUM(poin),0) FROM pelanggaran_siswa WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$siswaId,$tapelId]);$x=$st->fetch(PDO::FETCH_NUM);$summary['pelanggaran']=(int)($x[0]??0);$summary['poin']=(int)($x[1]??0);
// Penyesuaian poin dihitung penuh di bawah agar total tidak bergantung pada batas riwayat tampilan.
$st=$pdo->prepare("SELECT COALESCE(SUM(delta_poin),0) FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$siswaId,$tapelId]);$totalAdjustment=(int)$st->fetchColumn();
$st=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$siswaId,$tapelId]);$summary['pembinaan']=(int)$st->fetchColumn();
$st=$pdo->prepare("SELECT COUNT(*) FROM konsultasi_bk WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$siswaId,$tapelId]);$summary['konsultasi']=(int)$st->fetchColumn();
$st=$pdo->prepare("SELECT COUNT(*) FROM home_visit WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$siswaId,$tapelId]);$summary['home_visit']=(int)$st->fetchColumn();
$st=$pdo->prepare("SELECT COUNT(*) FROM ekstra_anggota WHERE siswa_id=? AND tahun_pelajaran_id=? AND status='aktif'");$st->execute([$siswaId,$tapelId]);$summary['ekstra']=(int)$st->fetchColumn();

$st=$pdo->prepare("SELECT p.tanggal,j.nama jenis,p.poin,p.status,p.tindakan,p.keterangan FROM pelanggaran_siswa p JOIN jenis_pelanggaran j ON j.id=p.jenis_pelanggaran_id WHERE p.siswa_id=? AND p.tahun_pelajaran_id=? ORDER BY p.tanggal DESC,p.id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$pelanggaran=$st->fetchAll();
$st=$pdo->prepare("SELECT tanggal,delta_poin,alasan,tindakan FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$penyesuaian=$st->fetchAll();
$st=$pdo->prepare("SELECT tanggal,jenis,status,hasil,tindak_lanjut FROM pembinaan_siswa WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$pembinaan=$st->fetchAll();
$st=$pdo->prepare("SELECT tanggal,jenis,topik,hasil,kesepakatan,tindak_lanjut,status FROM konsultasi_bk WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$konsultasi=$st->fetchAll();
$st=$pdo->prepare("SELECT tanggal_rencana,tanggal_pelaksanaan,status,tujuan,hasil,tindak_lanjut FROM home_visit WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY COALESCE(tanggal_pelaksanaan,tanggal_rencana) DESC,id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$homeVisit=$st->fetchAll();
$st=$pdo->prepare("SELECT iz.tanggal,iz.jenis,iz.alasan,iz.keterangan,iz.status FROM izin_siswa iz WHERE iz.siswa_id=? AND iz.tahun_pelajaran_id=? ORDER BY iz.tanggal DESC,iz.id DESC LIMIT 50");$st->execute([$siswaId,$tapelId]);$izin=$st->fetchAll();
$st=$pdo->prepare("SELECT e.nama,ea.status FROM ekstra_anggota ea JOIN ekstrakurikuler e ON e.id=ea.ekstrakurikuler_id WHERE ea.siswa_id=? AND ea.tahun_pelajaran_id=? ORDER BY e.nama");$st->execute([$siswaId,$tapelId]);$ekstra=$st->fetchAll();

$totalPoin=$summary['poin']+$totalAdjustment;
$statusPoin=$totalPoin>=100?'P4':($totalPoin>=75?'P3':($totalPoin>=50?'P2':($totalPoin>=25?'P1':'Normal')));
$page_title='Riwayat Saya';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Riwayat Saya</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-person-vcard"></i> Riwayat Saya</h1><p><?=e($siswa['nama'])?> · Kelas <?=e($siswa['kelas_nama']??'-')?> · Tahun Pelajaran <?=e($tapelName)?></p></div><form method="get"><select class="form-control" name="tapel_id" onchange="this.form.submit()"><?php foreach($tpRows as $tp):?><option value="<?=$tp['id']?>" <?=$tapelId===(int)$tp['id']?'selected':''?>><?=e($tp['nama'])?><?=$tp['aktif']?' (Aktif)':''?></option><?php endforeach;?></select></form></div>
<div class="dashboard-grid riwayat-stat-grid">
<?php foreach([['Hadir',$summary['hadir'],'calendar-check-fill','riwayat-blue','Kehadiran'],['Terlambat',$summary['terlambat'],'clock-fill','riwayat-orange','Kehadiran'],['Izin/Sakit',$summary['izin']+$summary['sakit'],'file-earmark-medical-fill','riwayat-cyan','Izin & kesehatan'],['Pelanggaran',$summary['pelanggaran'],'exclamation-triangle-fill','riwayat-red','Kesiswaan'],['Poin',$totalPoin,$statusPoin==='Normal'?'check-circle-fill':'exclamation-circle-fill','riwayat-violet',$statusPoin]] as $c):?><div class="riwayat-stat <?=$c[3]?>"><div class="riwayat-stat-icon"><i class="bi bi-<?=e($c[2])?>"></i></div><div class="riwayat-stat-label"><?=e($c[0])?></div><div class="riwayat-stat-number"><?=number_format((int)$c[1])?></div><div class="riwayat-stat-meta"><?=e($c[4])?></div></div><?php endforeach; ?>
</div>
<div class="riwayat-services"><div class="riwayat-services-title"><i class="bi bi-grid-1x2-fill"></i><div><h2>Ringkasan Layanan</h2><p>Aktivitas layanan dan kegiatan Anda pada tahun pelajaran ini.</p></div></div><div class="riwayat-service-grid"><div class="riwayat-service riwayat-service-amber"><i class="bi bi-person-check-fill"></i><strong><?=number_format($summary['pembinaan'])?></strong><span>Pembinaan</span></div><div class="riwayat-service riwayat-service-violet"><i class="bi bi-chat-dots-fill"></i><strong><?=number_format($summary['konsultasi'])?></strong><span>Konsultasi</span></div><div class="riwayat-service riwayat-service-orange"><i class="bi bi-house-heart"></i><strong><?=number_format($summary['home_visit'])?></strong><span>Home Visit</span></div><div class="riwayat-service riwayat-service-green"><i class="bi bi-trophy-fill"></i><strong><?=number_format($summary['ekstra'])?></strong><span>Ekstrakurikuler</span></div></div></div>
<?php
function portal_table(string $title,array $headers,array $rows,callable $render):void { $theme=['Pelanggaran'=>'riwayat-card-red','Penyesuaian Poin'=>'riwayat-card-violet','Pembinaan'=>'riwayat-card-amber','Konsultasi'=>'riwayat-card-purple','Home Visit'=>'riwayat-card-orange','Izin Siswa'=>'riwayat-card-blue'][$title] ?? 'riwayat-card-green'; echo '<div class="dashboard-table-card portal-card riwayat-card '.e($theme). '"><h2>'.e($title).'</h2>'; if(!$rows){echo '<div class="portal-empty">Belum ada data pada Tahun Pelajaran ini.</div></div>';return;} echo '<div class="table-responsive"><table class="table"><thead><tr>';foreach($headers as $h)echo '<th>'.e($h).'</th>';echo '</tr></thead><tbody>';foreach($rows as $r){echo '<tr>'.$render($r).'</tr>';}echo '</tbody></table></div></div>';}
portal_table('Pelanggaran', ['Tanggal','Jenis','Poin','Status','Tindakan'], $pelanggaran, fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e($r['jenis']).'</td><td>'.e($r['poin']).'</td><td>'.e($r['status']).'</td><td>'.e($r['tindakan']??'-').'</td>');
portal_table('Penyesuaian Poin', ['Tanggal','Perubahan','Alasan','Tindakan'], $penyesuaian, fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e(($r['delta_poin']>0?'+':'').$r['delta_poin']).'</td><td>'.e($r['alasan']).'</td><td>'.e($r['tindakan']??'-').'</td>');
portal_table('Pembinaan', ['Tanggal','Jenis','Status','Hasil','Tindak Lanjut'], $pembinaan, fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e(ucwords(str_replace('_',' ',$r['jenis']))).'</td><td>'.e($r['status']).'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
portal_table('Konsultasi', ['Tanggal','Jenis','Topik','Hasil','Tindak Lanjut'], $konsultasi, fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e(ucwords(str_replace('_',' ',$r['jenis']))).'</td><td>'.e($r['topik']).'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
portal_table('Home Visit', ['Tanggal','Status','Tujuan','Hasil','Tindak Lanjut'], $homeVisit, fn($r)=>'<td>'.e($r['tanggal_pelaksanaan']?:($r['tanggal_rencana']??'-')).'</td><td>'.e($r['status']).'</td><td>'.e($r['tujuan']??'-').'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
portal_table('Izin Siswa', ['Tanggal','Jenis','Alasan','Keterangan','Status'], $izin, fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e($r['jenis']).'</td><td>'.e($r['alasan']).'</td><td>'.e($r['keterangan']??'-').'</td><td>'.e($r['status']).'</td>');
?>
<div class="dashboard-table-card portal-card riwayat-card riwayat-card-green"><h2>Ekstrakurikuler</h2><?php if($ekstra):?><div class="portal-badges"><?php foreach($ekstra as $r):?><span><i class="bi bi-trophy-fill"></i> <?=e($r['nama'])?> · <?=e($r['status'])?></span><?php endforeach;?></div><?php else:?><div class="portal-empty">Belum mengikuti ekstrakurikuler pada Tahun Pelajaran ini.</div><?php endif;?></div>
<div class="portal-note riwayat-note"><i class="bi bi-shield-lock-fill"></i> Riwayat ini hanya menampilkan data milik akun Anda. Catatan internal BK yang bersifat privat tidak ditampilkan.</div>
</section></main>
<style>
/* Riwayat Siswa V66 - full-color energetic, presentation only */
.riwayat-stat-grid{grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}
.riwayat-stat{position:relative;overflow:hidden;border-radius:18px;padding:16px 17px 15px;color:#fff;min-height:128px;box-shadow:0 9px 20px rgba(15,23,42,.12);border:0}
.riwayat-stat:after{content:'';position:absolute;width:82px;height:82px;border-radius:50%;right:-25px;bottom:-28px;background:rgba(255,255,255,.13)}
.riwayat-stat-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);font-size:1.15rem;margin-bottom:9px}
.riwayat-stat-label{font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.03em;opacity:.92}.riwayat-stat-number{font-size:1.9rem;font-weight:900;line-height:1.05;margin-top:3px}.riwayat-stat-meta{font-size:.74rem;font-weight:700;opacity:.82;margin-top:5px}
.riwayat-blue{background:linear-gradient(135deg,#2563eb,#1d4ed8)}.riwayat-orange{background:linear-gradient(135deg,#f97316,#ea580c)}.riwayat-cyan{background:linear-gradient(135deg,#0891b2,#0e7490)}.riwayat-red{background:linear-gradient(135deg,#e11d48,#be123c)}.riwayat-violet{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.riwayat-services{margin-top:18px;border-radius:20px;padding:18px;background:linear-gradient(135deg,#dbeafe 0%,#ede9fe 52%,#fae8ff 100%);box-shadow:0 9px 22px rgba(37,99,235,.10);border-left:7px solid #4f46e5}
.riwayat-services-title{display:flex;gap:12px;align-items:center}.riwayat-services-title>i{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#4f46e5;color:#fff;font-size:1.15rem;box-shadow:0 5px 12px rgba(79,70,229,.22)}.riwayat-services-title h2{margin:0;font-size:1.15rem;font-weight:900}.riwayat-services-title p{margin:2px 0 0;color:#475569;font-size:.82rem}.riwayat-service-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px}.riwayat-service{color:#fff;border-radius:15px;padding:14px;display:grid;grid-template-columns:38px 1fr;column-gap:10px;align-items:center;box-shadow:0 6px 14px rgba(15,23,42,.08)}.riwayat-service i{grid-row:span 2;width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center}.riwayat-service strong{font-size:1.35rem;line-height:1}.riwayat-service span{font-size:.78rem;font-weight:800;opacity:.9}.riwayat-service-amber{background:linear-gradient(135deg,#d97706,#b45309)}.riwayat-service-violet{background:linear-gradient(135deg,#7c3aed,#6d28d9)}.riwayat-service-orange{background:linear-gradient(135deg,#ea580c,#c2410c)}.riwayat-service-green{background:linear-gradient(135deg,#059669,#047857)}
.riwayat-card{border:0!important;border-radius:18px!important;overflow:hidden;box-shadow:0 7px 18px rgba(15,23,42,.08)!important}.riwayat-card h2{margin:0;padding:15px 17px;color:#fff;font-size:1.08rem;font-weight:900}.riwayat-card .table-responsive{margin:0}.riwayat-card .table{margin:0}.riwayat-card .table thead th{color:#fff;border-color:rgba(255,255,255,.22);font-weight:900}.riwayat-card .table tbody td{border-color:#dbe4ef}.riwayat-card .portal-empty{margin:0;padding:18px;background:#fff}
.riwayat-card-red h2{background:#e11d48}.riwayat-card-violet h2{background:#7c3aed}.riwayat-card-amber h2{background:#d97706}.riwayat-card-purple h2{background:#8b5cf6}.riwayat-card-orange h2{background:#ea580c}.riwayat-card-blue h2{background:#2563eb}.riwayat-card-green h2{background:#059669}
.riwayat-card .table tbody tr:nth-child(even){background:#f8fafc}.riwayat-card-red .table tbody tr:nth-child(even){background:#fff1f2}.riwayat-card-amber .table tbody tr:nth-child(even){background:#fffaf0}.riwayat-card-orange .table tbody tr:nth-child(even){background:#fff7ed}.riwayat-card-violet .table tbody tr:nth-child(even),.riwayat-card-purple .table tbody tr:nth-child(even){background:#faf5ff}.riwayat-card-blue .table tbody tr:nth-child(even){background:#eff6ff}.riwayat-card-green .table tbody tr:nth-child(even){background:#ecfdf5}
.riwayat-note{margin-top:16px;border-radius:15px;padding:13px 16px;background:linear-gradient(135deg,#dbeafe,#eef2ff);border-left:6px solid #2563eb;color:#334155;font-weight:700}
@media(max-width:1100px){.riwayat-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.riwayat-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:650px){.riwayat-stat-grid,.riwayat-service-grid{grid-template-columns:1fr 1fr}}
@media(max-width:430px){.riwayat-stat-grid,.riwayat-service-grid{grid-template-columns:1fr}}
.portal-card{margin-top:18px}.portal-card h2{font-size:1.05rem;margin:0 0 14px}.portal-empty{padding:14px;border-radius:10px;background:#f8fafc;color:#64748b}.portal-badges{display:flex;gap:10px;flex-wrap:wrap}.portal-badges span{padding:9px 12px;border-radius:10px;background:#f8fafc;color:#334155;font-size:.86rem}.portal-note{margin-top:18px;padding:13px 15px;border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;color:#475569}.table td,.table th{vertical-align:top}@media(max-width:900px){.dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}</style>
<?php require_once __DIR__.'/../layouts/footer.php';
