<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
wali_kelas_require();

$classes=wali_kelas_classes($pdo); $kelasId=(int)($_GET['kelas_id']??($classes[0]['id']??0));
if(!$kelasId || !wali_kelas_allowed($pdo,$kelasId)) deny_access();
$assignment=wali_kelas_assignment($pdo,$kelasId); if(!$assignment) deny_access();
$tp=active_tapel_id($pdo);
$from=(string)($_GET['from']??date('Y-m-01')); $to=(string)($_GET['to']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01'); if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d'); if($from>$to){[$from,$to]=[$to,$from];}
$st=$pdo->prepare("SELECT s.id,s.nis,s.nama,
 SUM(CASE WHEN a.status='H' THEN 1 ELSE 0 END) h,
 SUM(CASE WHEN a.status='T' THEN 1 ELSE 0 END) t,
 SUM(CASE WHEN a.status='I' THEN 1 ELSE 0 END) i,
 SUM(CASE WHEN a.status='S' THEN 1 ELSE 0 END) ss,
 SUM(CASE WHEN a.status='A' THEN 1 ELSE 0 END) a,
 COUNT(a.id) AS tercatat
 FROM siswa_kelas sk JOIN siswa s ON s.id=sk.siswa_id
 LEFT JOIN absensi a ON a.siswa_id=s.id
    AND EXISTS (SELECT 1 FROM sesi_absensi sa WHERE sa.id=a.sesi_id AND sa.tahun_pelajaran_id=? AND sa.tanggal BETWEEN ? AND ?)
 WHERE sk.kelas_id=? AND sk.tahun_pelajaran_id=? AND sk.status='aktif'
 GROUP BY s.id,s.nis,s.nama ORDER BY s.nama");
$st->execute([$tp,$from,$to,$kelasId,$tp]);$rows=$st->fetchAll();
// Guard against LEFT JOIN rows from absensi outside the requested session range.
foreach($rows as &$r){
    // Re-query only if an implementation/database variant has a permissive join plan.
    $r['h']=(int)$r['h'];$r['t']=(int)$r['t'];$r['i']=(int)$r['i'];$r['ss']=(int)$r['ss'];$r['a']=(int)$r['a'];$r['tercatat']=(int)$r['tercatat'];
}
unset($r);
$page_title='Rekap Kelas'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main guru-wali-page guru-wali-table-page"><header class="topbar"><div class="topbar-title">Wali Kelas</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Rekap Kelas</h1><p><?=e($assignment['kelas_nama'])?> · <?=e($assignment['tapel_nama'])?></p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>wali_kelas/index.php"><i class="bi bi-arrow-left"></i> Kembali</a><a class="btn btn-secondary" href="<?=BASE_URL?>wali_kelas/siswa.php?kelas_id=<?=$kelasId?>"><i class="bi bi-people"></i> Siswa</a></div></div>
<div class="form-card" style="margin-bottom:18px"><form method="get" class="form-grid" style="align-items:end"><label>Kelas<select name="kelas_id" class="form-control"><?php foreach($classes as $c):?><option value="<?=$c['id']?>" <?=$kelasId===(int)$c['id']?'selected':''?>><?=e($c['nama'])?></option><?php endforeach;?></select></label><label>Dari<input class="form-control" type="date" name="from" value="<?=e($from)?>"></label><label>Sampai<input class="form-control" type="date" name="to" value="<?=e($to)?>"></label><div><button class="btn btn-primary"><i class="bi bi-funnel"></i> Tampilkan</button></div></form></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Rekap Kehadiran</h2><p>Hanya record absensi yang benar-benar tersimpan. Siswa tanpa scan tidak otomatis dianggap Alpa.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>NIS</th><th>Nama</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Tercatat</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="9" class="empty-state">Belum ada siswa aktif pada kelas ini.</td></tr><?php else:foreach($rows as $i=>$r):?><tr><td><?=$i+1?></td><td><?=e($r['nis'])?></td><td><strong><?=e($r['nama'])?></strong></td><td><?=$r['h']?></td><td><?=$r['t']?></td><td><?=$r['i']?></td><td><?=$r['ss']?></td><td><?=$r['a']?></td><td><?=$r['tercatat']?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php';
