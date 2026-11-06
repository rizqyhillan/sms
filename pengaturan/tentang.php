<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_permission('pengaturan');
$page_title = 'Tentang Sistem';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
$build = '2026.09.27';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Tentang Sistem</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-info-circle-fill"></i> Tentang Sistem</h1><p>Informasi versi, identitas aplikasi, dan pengembang.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/index.php"><i class="bi bi-arrow-left"></i> Pengaturan</a></div>
<div class="about-system-card"><div class="about-logo"><i class="bi bi-building"></i></div><div><span class="about-kicker">Sistem Informasi</span><h2><?=e(APP_NAME)?></h2><p class="about-school"><?=e(NAMA_SEKOLAH)?></p><div class="about-meta"><span><i class="bi bi-tag-fill"></i> Versi <?=e($build)?></span><span><i class="bi bi-calendar3"></i> Build 27 September 2026</span></div></div></div>
<div class="about-grid"><div class="about-panel"><i class="bi bi-code-slash"></i><div><h3>Dikembangkan oleh</h3><p class="about-developer">@irulcoe</p><p>Pengembangan dan pemeliharaan sistem manajemen sekolah.</p></div></div><div class="about-panel"><i class="bi bi-shield-check"></i><div><h3>Prinsip Sistem</h3><p>Berbasis hak akses, pencatatan audit, pemeriksaan integritas read-only, dan desain yang mendukung operasional sekolah.</p></div></div></div>
<div class="about-footer-note">© 2026 <strong>@irulcoe</strong> · <?=e(APP_NAME)?> · <?=e(NAMA_SEKOLAH)?></div>
</section></main><?php require_once __DIR__ . '/../layouts/footer.php'; ?>
