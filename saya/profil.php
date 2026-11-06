<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (user_role() !== 'kepala_sekolah') { deny_access(); }
$userId = user_id();
$st = $pdo->prepare("SELECT id,username,nama,role,status,last_login,created_at FROM users WHERE id=? AND role='kepala_sekolah' LIMIT 1");
$st->execute([$userId]);
$akun = $st->fetch();
if (!$akun) { http_response_code(404); exit('Akun Kepala Sekolah tidak ditemukan.'); }
$page_title='Profil Saya';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Profil Saya</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-person-vcard"></i> Profil Saya</h1><p>Identitas akun jabatan Kepala Sekolah.</p></div></div>
<div class="dashboard-grid"><div class="dashboard-card"><div class="card-label">Nama</div><div class="card-number" style="font-size:1.35rem"><?=e($akun['nama'])?></div><div class="card-description">Kepala Sekolah aktif.</div></div><div class="dashboard-card"><div class="card-label">Username</div><div class="card-number" style="font-size:1.35rem"><code><?=e($akun['username'])?></code></div><div class="card-description">Username jabatan tetap.</div></div><div class="dashboard-card"><div class="card-label">Hak Akses</div><div class="card-number" style="font-size:1.35rem">Read Only</div><div class="card-description">Dashboard dan laporan.</div></div></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Informasi Akun</h2><p>Akun ini mengikuti jabatan, bukan nama pribadi.</p></div></div><table class="data-table"><tbody><tr><th>Nama</th><td><?=e($akun['nama'])?></td></tr><tr><th>Username</th><td><code><?=e($akun['username'])?></code></td></tr><tr><th>Role</th><td>Kepala Sekolah</td></tr><tr><th>Status</th><td><span class="status-badge status-aktif">Aktif</span></td></tr><tr><th>Login Terakhir</th><td><?= $akun['last_login'] ? e(date('d-m-Y H:i',strtotime((string)$akun['last_login']))) : 'Belum login' ?></td></tr></tbody></table></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
