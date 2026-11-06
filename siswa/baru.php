<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$years = $pdo->query('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
$activeYearId = 0;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $activeYearId = (int)$y['id']; break; } }
$yearId = (int)($_GET['tahun_pelajaran_id'] ?? $activeYearId);
$selectedYear = null;
foreach ($years as $y) { if ((int)$y['id'] === $yearId) { $selectedYear = $y; break; } }
if (!$selectedYear && $activeYearId > 0) {
    $yearId = $activeYearId;
    foreach ($years as $y) { if ((int)$y['id'] === $yearId) { $selectedYear = $y; break; } }
}

$classes = $pdo->query("SELECT id,nama,tingkat FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama")->fetchAll();

$stats = ['total'=>0,'placed'=>0,'unplaced'=>0];
if ($yearId > 0) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM siswa_kelas WHERE tahun_pelajaran_id=? AND status='aktif'");
    $st->execute([$yearId]);
    $stats['placed'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM siswa s WHERE s.status='aktif' AND NOT EXISTS (SELECT 1 FROM siswa_kelas sk WHERE sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif')");
    $st->execute([$yearId]);
    $stats['unplaced'] = (int)$st->fetchColumn();
    $stats['total'] = $stats['placed'] + $stats['unplaced'];
}

$page_title = 'Siswa Baru';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main siswa-baru-page">
<header class="topbar"><div class="topbar-title">Siswa Baru</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header">
    <div><h1><i class="bi bi-person-plus-fill"></i> Siswa Baru</h1><p>Alur khusus untuk memasukkan siswa baru ke Tahun Pelajaran dan, bila diperlukan, langsung ke kelas tujuan.</p></div>
    <a class="btn btn-secondary" href="<?=BASE_URL?>siswa/index.php?tahun_pelajaran_id=<?=$yearId?>"><i class="bi bi-arrow-left"></i> Kembali ke Data Siswa</a>
</div>

<div class="form-card siswa-baru-context-card">
    <div class="section-title siswa-baru-context-title"><div><h2>1. Pilih Tahun Pelajaran</h2><p>Semua siswa baru dari halaman ini akan dicatat pada Tapel yang dipilih.</p></div></div>
    <form method="get" class="siswa-baru-tapel-form">
        <label for="tahun-pelajaran-baru">Tahun Pelajaran</label>
        <select id="tahun-pelajaran-baru" class="form-control siswa-baru-tapel-select" name="tahun_pelajaran_id" onchange="this.form.submit()" aria-label="Tahun Pelajaran">
            <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>" <?= $yearId === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif'] === 1 ? ' (aktif)' : '' ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-secondary" type="submit">Terapkan</button></noscript>
    </form>
</div>

<div class="siswa-baru-group-title"><h2>Status Siswa Baru</h2><p>Ringkasan kondisi siswa pada Tapel yang dipilih.</p></div>
<div class="siswa-baru-stats dashboard-alert-grid">
    <div class="dashboard-alert alert-info siswa-baru-stat"><div class="alert-icon"><i class="bi bi-calendar-range"></i></div><div><div class="alert-label">Tapel Tujuan</div><div class="alert-value siswa-tapel-value"><?=e((string)($selectedYear['nama'] ?? '-'))?></div><div class="alert-text">Tahun Pelajaran siswa baru</div></div></div>
    <div class="dashboard-alert alert-success siswa-baru-stat"><div class="alert-icon"><i class="bi bi-person-check-fill"></i></div><div><div class="alert-label">Sudah Ditempatkan</div><div class="alert-value"><?=number_format($stats['placed'])?></div><div class="alert-text">Aktif pada Tapel</div></div></div>
    <div class="dashboard-alert alert-warning siswa-baru-stat"><div class="alert-icon"><i class="bi bi-person-dash-fill"></i></div><div><div class="alert-label">Belum Ditempatkan</div><div class="alert-value"><?=number_format($stats['unplaced'])?></div><div class="alert-text">Perlu penempatan kelas</div></div></div>
</div>

<div class="siswa-baru-group-title siswa-baru-action-group-title"><h2>Aksi Siswa Baru</h2><p>Pilih tindakan yang ingin dilakukan.</p></div>
<div class="siswa-baru-actions">
    <a class="dashboard-alert alert-info siswa-baru-action" href="<?=BASE_URL?>siswa/baru_tambah.php?tahun_pelajaran_id=<?=$yearId?>">
        <div class="alert-icon"><i class="bi bi-person-plus-fill"></i></div><div><div class="alert-label">Tambah Siswa Baru</div><div class="alert-text">Input satu siswa dan tentukan kelas tujuan.</div></div>
    </a>
    <a class="dashboard-alert alert-success siswa-baru-action" href="<?=BASE_URL?>siswa/import.php?tahun_pelajaran_id=<?=$yearId?>">
        <div class="alert-icon"><i class="bi bi-file-earmark-spreadsheet-fill"></i></div><div><div class="alert-label">Impor Siswa XLSX</div><div class="alert-text">Impor banyak siswa sekaligus dari Excel.</div></div>
    </a>
    <a class="dashboard-alert alert-purple siswa-baru-action" href="<?=BASE_URL?>siswa/template_import.xlsx">
        <div class="alert-icon"><i class="bi bi-file-earmark-arrow-down-fill"></i></div><div><div class="alert-label">Template XLSX</div><div class="alert-text">Unduh template dengan format kolom yang sesuai.</div></div>
    </a>
</div>

<div class="form-card siswa-baru-flow">
    <div class="section-title"><div><h2><i class="bi bi-info-circle"></i> Alur Siswa Baru</h2><p>Data identitas tetap satu; penempatan masuk ke histori siswa pada Tapel yang dipilih.</p></div></div>
    <ol>
        <li>Pilih Tahun Pelajaran.</li>
        <li>Tambah satu siswa atau impor XLSX.</li>
        <li>Tentukan kelas tujuan bila siswa sudah diketahui rombelnya.</li>
        <li>Sistem membuat identitas siswa dan QR permanen.</li>
        <li>Sistem membuat penempatan aktif pada Tapel tersebut.</li>
    </ol>
</div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
