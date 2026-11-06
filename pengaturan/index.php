<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_permission('pengaturan');
$page_title='Pengaturan Sistem';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pengaturan Sistem</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content settings-module">
<div class="page-header settings-page-header"><div><h1><i class="bi bi-gear-fill"></i> Pengaturan Sistem</h1><p>Pusat konfigurasi sekolah, absensi, pengguna, keamanan, dan pemeliharaan.</p></div></div>

<div class="settings-section-head"><span class="settings-section-icon tone-blue"><i class="bi bi-mortarboard-fill"></i></span><div><h2>Akademik &amp; Penugasan</h2><p>Konfigurasi periode belajar dan penugasan yang menjadi dasar operasional sekolah.</p></div></div>
<div class="settings-card-grid settings-card-grid-3">
<a class="settings-card settings-blue" href="<?=BASE_URL?>pengaturan/tahun_pelajaran/index.php"><span class="settings-card-icon"><i class="bi bi-calendar3-range"></i></span><strong>Tahun Pelajaran</strong><span>Kelola Tapel serta Semester Ganjil dan Genap.</span></a>
<?php if (user_role() === 'admin'): ?>
<a class="settings-card settings-indigo" href="<?=BASE_URL?>jenis_tugas/index.php"><span class="settings-card-icon"><i class="bi bi-list-task"></i></span><strong>Jenis Tugas Tambahan</strong><span>Kelola master jenis penugasan guru dan tendik.</span></a>
<?php endif; ?>
<a class="settings-card settings-cyan" href="<?=BASE_URL?>tugas_guru/index.php"><span class="settings-card-icon"><i class="bi bi-person-workspace"></i></span><strong>Tugas Tambahan</strong><span>Kelola penugasan guru per semester.</span></a>
</div>

<div class="settings-section-head"><span class="settings-section-icon tone-violet"><i class="bi bi-calendar2-check-fill"></i></span><div><h2>Absensi</h2><p>Semua konfigurasi kehadiran dan sholat dikumpulkan dalam satu pusat pengaturan.</p></div></div>
<div class="settings-card-grid settings-card-grid-2">
<a class="settings-card settings-violet settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/index.php"><span class="settings-card-icon"><i class="bi bi-sliders"></i></span><div><strong>Pengaturan Absensi</strong><span>Jadwal kehadiran, kalender sekolah, jenis sholat, dan jadwal sholat.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
<a class="settings-card settings-teal settings-card-feature" href="<?=BASE_URL?>pengaturan_absensi/kalender/index.php"><span class="settings-card-icon"><i class="bi bi-calendar3"></i></span><div><strong>Kalender Kehadiran</strong><span>Hari efektif, libur, dan pengecualian kalender absensi.</span></div><i class="bi bi-arrow-right-circle settings-card-arrow"></i></a>
</div>

<div class="settings-section-head"><span class="settings-section-icon tone-green"><i class="bi bi-shield-check"></i></span><div><h2>Pengguna &amp; Keamanan</h2><p>Akun, jejak aktivitas, dan pemeriksaan integritas sistem.</p></div></div>
<div class="settings-card-grid settings-card-grid-3">
<a class="settings-card settings-green" href="<?=BASE_URL?>pengguna/index.php"><span class="settings-card-icon"><i class="bi bi-person-lines-fill"></i></span><strong>Pengguna &amp; Hak Akses</strong><span>Akun, role, dan akses pengguna.</span></a>
<a class="settings-card settings-amber" href="<?=BASE_URL?>audit_log/index.php"><span class="settings-card-icon"><i class="bi bi-shield-check"></i></span><strong>Audit Log</strong><span>Jejak aktivitas administrasi dan keamanan.</span></a>
<a class="settings-card settings-emerald" href="<?=BASE_URL?>pengaturan/integritas.php"><span class="settings-card-icon"><i class="bi bi-shield-fill-check"></i></span><strong>Integritas Sistem</strong><span>Pemeriksaan read-only struktur dan konsistensi database.</span></a>
</div>

<div class="settings-section-head"><span class="settings-section-icon tone-orange"><i class="bi bi-building"></i></span><div><h2>Identitas &amp; Pemeliharaan</h2><p>Identitas sekolah dan operasi sistem yang memerlukan perhatian Administrator.</p></div></div>
<div class="settings-card-grid settings-card-grid-3">
<?php if (user_role() === 'admin'): ?>
<a class="settings-card settings-sky" href="<?=BASE_URL?>profil_sekolah/index.php"><span class="settings-card-icon"><i class="bi bi-building"></i></span><strong>Profil Sekolah</strong><span>Identitas, logo, dan informasi sekolah.</span></a>
<a class="settings-card settings-orange" href="<?=BASE_URL?>pengaturan/pemeliharaan/index.php"><span class="settings-card-icon"><i class="bi bi-tools"></i></span><strong>Pemeliharaan Sistem</strong><span>Backup, restore, reset, dan operasi data sensitif.</span></a>
<?php endif; ?>
<a class="settings-card settings-slate" href="<?=BASE_URL?>pengaturan/tentang.php"><span class="settings-card-icon"><i class="bi bi-info-circle-fill"></i></span><strong>Tentang Sistem</strong><span>Versi aplikasi, build, dan informasi sistem.</span></a>
</div>

<div class="settings-note"><i class="bi bi-info-circle-fill"></i><div><strong>Catatan Administrator</strong><span>Operasi pemeliharaan dan tindakan penting tetap dibatasi sesuai hak akses serta dicatat pada Audit Log.</span></div></div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
