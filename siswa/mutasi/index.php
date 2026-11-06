<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
require_role('admin');

$activeYear = $pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetch() ?: null;
$yearId = (int)($_GET['tahun_pelajaran_id'] ?? ($activeYear['id'] ?? 0));
$years = $pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY id DESC")->fetchAll();
$yearIds = array_map('intval', array_column($years, 'id'));
if ($yearId > 0 && !in_array($yearId, $yearIds, true)) $yearId = (int)($activeYear['id'] ?? 0);

$summary = ['aktif'=>0,'pindah'=>0,'keluar'=>0];
if ($yearId > 0) {
    $st = $pdo->prepare("SELECT
        SUM(status='aktif') aktif,
        SUM(status='pindah') pindah,
        SUM(status='keluar') keluar
        FROM siswa_kelas WHERE tahun_pelajaran_id=?");
    $st->execute([$yearId]);
    $summary = array_merge($summary, $st->fetch() ?: []);
}

$recent = $pdo->query("SELECT r.id,r.tanggal_status,r.status,r.tujuan,r.keterangan,s.nama,s.nis,tp.nama tahun_nama
    FROM siswa_status_riwayat r
    JOIN siswa s ON s.id=r.siswa_id
    LEFT JOIN tahun_pelajaran tp ON tp.id=r.tahun_pelajaran_id
    WHERE r.status IN ('pindah','keluar')
    ORDER BY r.id DESC LIMIT 10")->fetchAll();

$page_title='Mutasi Siswa';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Mutasi Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content mutasi-page">
<div class="page-header">
    <div><h1><i class="bi bi-arrow-left-right"></i> Mutasi Siswa</h1><p>Pindah dan Keluar dikelola terpisah, tetapi seluruh histori siswa tetap tersimpan.</p></div>
</div>
<div class="form-card">
    <div class="section-title"><div><h2>Konteks Tahun Pelajaran</h2><p>Pilih Tapel tempat status mutasi dicatat.</p></div></div>
    <form method="get" class="form-grid">
        <label>Tahun Pelajaran<select class="form-control" name="tahun_pelajaran_id" onchange="this.form.submit()">
            <?php foreach($years as $y): ?><option value="<?=$y['id']?>" <?=((int)$y['id']===$yearId?'selected':'')?>><?=e($y['nama'])?><?=((int)$y['aktif']===1?' · Aktif':'')?></option><?php endforeach; ?>
        </select></label>
    </form>
</div>
<div class="dashboard-grid mutasi-summary">
    <div class="dashboard-card"><div class="card-label">Aktif pada Tapel</div><div class="card-number"><?= (int)$summary['aktif']?></div></div>
    <div class="dashboard-card"><div class="card-label">Pindah</div><div class="card-number"><?= (int)$summary['pindah']?></div></div>
    <div class="dashboard-card"><div class="card-label">Keluar</div><div class="card-number"><?= (int)$summary['keluar']?></div></div>
</div>
<div class="dashboard-grid mutasi-actions">
    <div class="form-card"><div class="section-title"><div><h2><i class="bi bi-box-arrow-right"></i> Pindah Sekolah</h2><p>Siswa berpindah ke sekolah/lembaga lain. Data identitas dan histori tetap dipertahankan.</p></div></div><a class="btn btn-primary" href="<?=BASE_URL?>siswa/mutasi/pindah.php?tahun_pelajaran_id=<?=$yearId?>"><i class="bi bi-arrow-right-circle"></i> Proses Pindah</a></div>
    <div class="form-card"><div class="section-title"><div><h2><i class="bi bi-person-dash"></i> Keluar</h2><p>Siswa keluar dari sekolah tanpa tujuan sekolah baru yang dicatat.</p></div></div><a class="btn btn-primary" href="<?=BASE_URL?>siswa/mutasi/keluar.php?tahun_pelajaran_id=<?=$yearId?>"><i class="bi bi-arrow-right-circle"></i> Proses Keluar</a></div>
</div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Mutasi Terakhir</h2><p>Catatan mutasi yang sudah diproses.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>NIS</th><th>Nama</th><th>Status</th><th>Tapel</th><th>Tujuan</th><th>Keterangan</th></tr></thead><tbody><?php if(!$recent): ?><tr><td colspan="7" class="empty-state">Belum ada mutasi.</td></tr><?php else: foreach($recent as $r): ?><tr><td><?=e($r['tanggal_status'])?></td><td><?=e($r['nis'])?></td><td><?=e($r['nama'])?></td><td><span class="status-badge status-<?=e($r['status'])?>"><?=e(strtoupper($r['status']))?></span></td><td><?=e((string)($r['tahun_nama']??'-'))?></td><td><?=e((string)($r['tujuan']??'-'))?></td><td><?=e((string)($r['keterangan']??'-'))?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
</section></main>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
