<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
$todaySessions = sholat_get_or_create_hari_ini($pdo);
$today = (new DateTimeImmutable('now'))->format('Y-m-d');

$page_title='Kehadiran Sholat';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Kehadiran Sholat</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content sholat-module">
<div class="page-header"><div><h1>Kehadiran Sholat</h1><p>Sesi sholat dibuat otomatis sesuai jadwal dan hari efektif sekolah.</p></div><div class="page-header-actions sholat-actions"><a class="btn btn-primary" href="<?=BASE_URL?>scanner/index.php"><i class="bi bi-qr-code-scan"></i> Scanner QR</a><span class="action-divider"></span><a class="btn btn-secondary" href="<?=BASE_URL?>sholat/rekap.php"><i class="bi bi-bar-chart"></i> Rekap</a><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jadwal_sholat/index.php"><i class="bi bi-clock"></i> Jadwal</a><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/index.php"><i class="bi bi-list-check"></i> Jenis</a></div></div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Kegiatan Sholat Hari Ini</h2><p><?=e($today)?> · sesi dibuat otomatis oleh sistem.</p></div></div>
<div class="dashboard-grid">
<?php if(!$todaySessions): ?>
<div class="info-box" style="grid-column:1/-1"><strong>Tidak ada kegiatan sholat otomatis saat ini.</strong><p>Periksa jadwal dan kalender sekolah.</p></div>
<?php else: foreach($todaySessions as $r):
  $mul=substr((string)$r['jam_mulai'],0,5); $sel=substr((string)$r['jam_selesai'],0,5);
  $state=(string)($r['status_sesi']??'DRAFT');
  $statusLabel=$state==='OPEN'?'Sedang berlangsung':($state==='CLOSED'?'Sudah ditutup':'Terjadwal');
?>
<div class="dashboard-card sholat-session-card sholat-state-<?= $state==='OPEN'?'open':($state==='CLOSED'?'closed':'draft') ?>">
  <div class="card-label"><?=e($r['nama_sesi']??('Sholat '.$r['jenis_sholat_id']))?></div>
  <div class="card-number" style="font-size:1.55rem"><?=e($mul)?>–<?=e($sel)?></div>
  <div class="card-description"><span class="status-badge status-<?= $state==='OPEN'?'aktif':($state==='CLOSED'?'ditutup':'draft') ?>"><?=e($statusLabel)?></span></div>
  <div class="form-actions" style="margin-top:12px"><a class="btn btn-primary btn-sm" href="<?=BASE_URL?>scanner/index.php"><i class="bi bi-qr-code-scan"></i> Scanner QR</a><a class="btn btn-secondary btn-sm btn-icon-sm" title="Monitoring sholat" aria-label="Monitoring sholat" href="<?=BASE_URL?>sholat/detail.php?id=<?=((int)$r['id'])?>"><i class="bi bi-display" aria-hidden="true"></i></a></div>
</div>
<?php endforeach; endif; ?>
</div>
</div>
<div class="info-box"><strong>Catatan:</strong><p>Sesi tersimpan untuk histori, transaksi, verval, laporan, dan audit.</p></div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
