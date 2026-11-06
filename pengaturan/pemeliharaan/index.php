<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();
$page_title='Pemeliharaan Sistem';
$backupRows = maintenance_backup_files();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$uploadCount = 0; $uploadBytes = 0;
$uploadRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads';
if (is_dir($uploadRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) { $uploadCount++; $uploadBytes += (int)$f->getSize(); }
}
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pemeliharaan Sistem</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-gear-fill"></i> Pemeliharaan Sistem</h1><p>Operasi berisiko tinggi dipusatkan di sini dan dibatasi untuk Administrator.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/index.php"><i class="bi bi-arrow-left"></i> Pengaturan</a></div>
<div class="dashboard-grid maintenance-stats-grid">
<div class="dashboard-card maintenance-stat maintenance-stat-backup"><div class="card-icon"><i class="bi bi-hdd-fill"></i></div><div class="card-label">Backup Tersimpan</div><div class="card-number"><?=number_format(count($backupRows))?></div><div class="card-description">File backup lokal</div></div>
<div class="dashboard-card maintenance-stat maintenance-stat-students"><div class="card-icon"><i class="bi bi-people-fill"></i></div><div class="card-label">Data Siswa</div><div class="card-number"><?=number_format($studentCount)?></div><div class="card-description">Dapat dihapus satu per satu dengan pemeriksaan relasi.</div></div>
<div class="dashboard-card maintenance-stat maintenance-stat-upload"><div class="card-icon"><i class="bi bi-folder2-open"></i></div><div class="card-label">File Upload</div><div class="card-number"><?=number_format($uploadCount)?></div><div class="card-description"><?=e(maintenance_format_bytes($uploadBytes))?></div></div>
</div>
<div class="dashboard-grid maintenance-actions-grid">
<a class="dashboard-card text-decoration-none maintenance-action maintenance-action-backup" href="<?=BASE_URL?>pengaturan/pemeliharaan/backup.php"><div class="maintenance-action-icon"><i class="bi bi-hdd-fill"></i></div><div class="card-label">Backup &amp; Restore</div><div class="card-description">Buat backup database + file upload, download, dan pulihkan backup.</div></a>
<a class="dashboard-card text-decoration-none maintenance-action maintenance-action-integrity" href="<?=BASE_URL?>pengaturan/integritas.php"><div class="maintenance-action-icon"><i class="bi bi-shield-check"></i></div><div class="card-label">Integritas Sistem</div><div class="card-description">Pemeriksaan read-only struktur, relasi, orphan, dan konsistensi database.</div></a>
</div>

<div class="maintenance-danger-group" style="margin-top:18px;border:1px solid rgba(220,53,69,.28);border-radius:20px;padding:18px;background:linear-gradient(180deg,rgba(220,53,69,.045),rgba(255,255,255,.75));box-shadow:0 8px 26px rgba(31,41,55,.05)">
<div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:14px"><div style="width:44px;height:44px;border-radius:13px;display:grid;place-items:center;background:#fff1f2;color:#b91c1c;font-size:20px;flex:0 0 auto"><i class="bi bi-shield-lock-fill"></i></div><div><h2 style="margin:0;font-size:20px">Operasi Data Sensitif</h2><p style="margin:4px 0 0;color:#71809a">Reset dan penghapusan data dipusatkan di sini. Semua operasi wajib melalui verifikasi ulang Administrator, CSRF, konfirmasi, dan safety backup terverifikasi.</p></div></div>
<div class="dashboard-grid maintenance-actions-grid" style="margin-top:0">
<a class="dashboard-card text-decoration-none maintenance-action maintenance-action-reset" href="<?=BASE_URL?>pengaturan/pemeliharaan/reset.php"><div class="maintenance-action-icon"><i class="bi bi-arrow-repeat"></i></div><div class="card-label">Reset Data</div><div class="card-description">Bersihkan data transaksi tertentu atau mulai Lingkungan Pengujian dari kondisi kosong.</div></a>
<a class="dashboard-card text-decoration-none maintenance-action maintenance-action-danger-dark" href="<?=BASE_URL?>pengaturan/pemeliharaan/hapus_siswa.php?mode=individu"><div class="maintenance-action-icon"><i class="bi bi-person-x-fill"></i></div><div class="card-label">Hapus Siswa</div><div class="card-description">Hapus per siswa, per kelas, atau massal dengan pemeriksaan relasi dan perlindungan data.</div></a>
</div>
<div class="maintenance-warning" style="margin-top:14px"><div class="maintenance-warning-icon"><i class="bi bi-shield-exclamation"></i></div><div><strong>Zona Data Berisiko</strong><p>Jangan gunakan operasi ini sebagai bagian dari pekerjaan harian. Sebelum eksekusi, sistem meminta password Administrator kembali dan membuat safety backup baru. Jika backup gagal diverifikasi, penghapusan dibatalkan.</p></div></div>
</div>
<div class="maintenance-warning"><div class="maintenance-warning-icon"><i class="bi bi-shield-exclamation"></i></div><div><strong>Peringatan Operasi Berisiko</strong><p>Backup terlebih dahulu sebelum Reset atau Hapus Permanen. Periksa kembali data yang dipilih sebelum menjalankan operasi penghapusan. Audit Log dan data master penting tidak dihapus oleh fitur reset siswa.</p></div></div>
</section></main><?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
