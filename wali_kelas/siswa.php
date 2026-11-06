<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
wali_kelas_require();

$classes = wali_kelas_classes($pdo);
$kelasId = (int)($_GET['kelas_id'] ?? ($classes[0]['id'] ?? 0));
if (!$kelasId || !wali_kelas_allowed($pdo, $kelasId)) deny_access();
$assignment = wali_kelas_assignment($pdo, $kelasId);
if (!$assignment) deny_access();
$search = trim((string)($_GET['q'] ?? ''));
$tp = active_tapel_id($pdo);
$where = "sk.kelas_id=? AND sk.tahun_pelajaran_id=? AND sk.status='aktif'";
$params = [$kelasId, $tp];
if ($search !== '') {
    $where .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR COALESCE(s.nisn,'') LIKE ?)";
    $q = '%' . $search . '%'; array_push($params, $q, $q, $q);
}
$st = $pdo->prepare("SELECT sk.nomor_urut,s.id,s.nis,s.nisn,s.nama,s.jk,s.status
    FROM siswa_kelas sk JOIN siswa s ON s.id=sk.siswa_id
    WHERE $where ORDER BY COALESCE(sk.nomor_urut,999999),s.nama");
$st->execute($params); $rows=$st->fetchAll();
$page_title='Siswa Kelas Saya'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main guru-wali-page guru-wali-table-page"><header class="topbar"><div class="topbar-title">Wali Kelas</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Siswa Kelas Saya</h1><p><?=e($assignment['kelas_nama'])?> · Tapel <?=e($assignment['tapel_nama'])?></p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>wali_kelas/index.php"><i class="bi bi-arrow-left"></i> Kembali</a><a class="btn btn-primary" href="<?=BASE_URL?>wali_kelas/rekap.php?kelas_id=<?=$kelasId?>"><i class="bi bi-bar-chart"></i> Rekap Kelas</a></div></div>
<div class="form-card" style="margin-bottom:18px"><form method="get" class="form-grid" style="align-items:end"><label>Kelas<select name="kelas_id" class="form-control" onchange="this.form.submit()"><?php foreach($classes as $c):?><option value="<?=$c['id']?>" <?=$kelasId===(int)$c['id']?'selected':''?>><?=e($c['nama'])?> (<?=$c['jumlah']?> siswa)</option><?php endforeach;?></select></label><label style="grid-column:span 2">Cari siswa<input class="form-control" name="q" value="<?=e($search)?>" placeholder="NIS / NISN / Nama"></label><div><button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button><a class="btn btn-secondary" href="<?=BASE_URL?>wali_kelas/siswa.php?kelas_id=<?=$kelasId?>">Reset</a></div></form></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Daftar Siswa</h2><p><?=count($rows)?> siswa sesuai filter.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>NIS</th><th>NISN</th><th>Nama</th><th>JK</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-state">Tidak ada siswa.</td></tr><?php else:foreach($rows as $i=>$r):?><tr><td><?= $r['nomor_urut']!==null?(int)$r['nomor_urut']:$i+1?></td><td><?=e($r['nis'])?></td><td><?=e((string)($r['nisn']??'-'))?></td><td><strong><?=e($r['nama'])?></strong></td><td><?=e((string)($r['jk']??'-'))?></td><td><a class="btn btn-secondary btn-sm btn-icon-sm" title="Detail siswa" aria-label="Detail siswa" href="<?=BASE_URL?>siswa/detail.php?id=<?=$r['id']?>&tahun_pelajaran_id=<?=$tp?>"><i class="bi bi-eye" aria-hidden="true"></i></a></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php';
