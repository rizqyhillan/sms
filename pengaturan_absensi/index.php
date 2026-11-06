<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_permission('pengaturan');
$page_title='Pengaturan Absensi';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pengaturan Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content settings-module attendance-settings-module">
<div class="page-header settings-page-header"><div><h1><i class="bi bi-calendar2-check-fill"></i> Pengaturan Absensi</h1><p>Satu pusat untuk mengatur kehadiran harian dan kegiatan sholat.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/index.php"><i class="bi bi-arrow-left"></i> Pengaturan Sistem</a></div>
<div class="settings-card-grid settings-card-grid-2">
<a class="settings-card settings-blue settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/jadwal/index.php"><span class="settings-card-icon"><i class="bi bi-calendar-week"></i></span><div><strong>Jadwal Kehadiran</strong><span>Atur rentang waktu sesi absensi kehadiran dan ketentuan waktunya.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
<a class="settings-card settings-teal settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/kalender/index.php"><span class="settings-card-icon"><i class="bi bi-calendar3"></i></span><div><strong>Kalender Kehadiran</strong><span>Atur hari efektif, libur, dan pengecualian kalender sekolah.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
<a class="settings-card settings-violet settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/index.php"><span class="settings-card-icon"><i class="bi bi-list-check"></i></span><div><strong>Jenis Sholat</strong><span>Kelola jenis kegiatan sholat yang digunakan pada absensi.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
<a class="settings-card settings-indigo settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/jadwal_sholat/index.php"><span class="settings-card-icon"><i class="bi bi-clock-fill"></i></span><div><strong>Jadwal Sholat</strong><span>Atur waktu mulai, batas, dan ketentuan sesi absensi sholat.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
</div>
<div class="settings-note"><i class="bi bi-lightbulb-fill"></i><div><strong>Alur pengaturan</strong><span>Master Jenis Sholat → Jadwal Sholat. Untuk kehadiran: Kalender Kehadiran → Jadwal Kehadiran. Sesi absensi kemudian dibuat otomatis berdasarkan konfigurasi aktif.</span></div></div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
