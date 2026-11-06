<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

if (user_role() !== 'siswa') {
    http_response_code(403);
    exit('Halaman ini khusus akun siswa.');
}

$userId = user_id();
$siswaId = user_siswa_id();
if (!$userId || !$siswaId) {
    http_response_code(403);
    exit('Akun siswa belum terhubung ke data siswa.');
}

$st = $pdo->prepare('SELECT u.username,u.password,s.nama,s.nis FROM users u INNER JOIN siswa s ON s.id=u.siswa_id WHERE u.id=? AND u.role=\'siswa\' LIMIT 1');
$st->execute([$userId]);
$akun = $st->fetch();
if (!$akun) {
    http_response_code(404);
    exit('Akun siswa tidak ditemukan.');
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $passwordLama = (string)($_POST['password_lama'] ?? '');
    $passwordBaru = (string)($_POST['password_baru'] ?? '');
    $konfirmasi = (string)($_POST['konfirmasi'] ?? '');

    if ($passwordLama === '' || $passwordBaru === '' || $konfirmasi === '') {
        $error = 'Semua kolom password wajib diisi.';
    } elseif (!password_verify($passwordLama, (string)$akun['password'])) {
        $error = 'Password lama tidak sesuai.';
    } elseif (strlen($passwordBaru) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($passwordBaru !== $konfirmasi) {
        $error = 'Konfirmasi password baru tidak sama.';
    } elseif (password_verify($passwordBaru, (string)$akun['password'])) {
        $error = 'Password baru harus berbeda dari password lama.';
    } else {
        try {
            $up = $pdo->prepare('UPDATE users SET password=? WHERE id=? AND role=\'siswa\'');
            $up->execute([password_hash($passwordBaru, PASSWORD_DEFAULT), $userId]);
            $audit = $pdo->prepare("INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,'pengguna',?,?,?,?)");
            $audit->execute([$userId, 'CHANGE_PASSWORD_SISWA', $userId, 'Siswa mengubah password akun sendiri.', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            $success = 'Password berhasil diubah. Gunakan password baru saat login berikutnya.';
            $st->execute([$userId]);
            $akun = $st->fetch() ?: $akun;
        } catch (Throwable $e) {
            $error = 'Password gagal diubah. Silakan coba lagi.';
        }
    }
}

$page_title = 'Ubah Password';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Ubah Password</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><i class="bi bi-key-fill"></i> Ubah Password</h1><p>Jaga akun Portal Siswa tetap aman dengan password pribadi Anda.</p></div></div>
<?php if($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=e($error)?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?=e($success)?></div><?php endif; ?>
<div class="password-page-grid">
<div class="password-hero"><div class="password-hero-icon"><i class="bi bi-shield-lock-fill"></i></div><div><span class="password-kicker">KEAMANAN AKUN</span><h2><?=e($akun['nama'])?></h2><p>Username: <strong><?=e($akun['username'])?></strong> · NIS: <strong><?=e($akun['nis'])?></strong></p></div></div>
<div class="password-card"><div class="password-card-head"><div class="password-card-icon"><i class="bi bi-key-fill"></i></div><div><h2>Password Baru</h2><p>Masukkan password lama, lalu buat password pribadi minimal 8 karakter.</p></div></div>
<form method="post" autocomplete="off"><?=csrf_field()?><div class="password-form-grid"><label>Password Lama<input class="form-control" type="password" name="password_lama" autocomplete="current-password" required></label><label>Password Baru<input class="form-control" type="password" name="password_baru" minlength="8" autocomplete="new-password" required></label><label>Konfirmasi Password Baru<input class="form-control" type="password" name="konfirmasi" minlength="8" autocomplete="new-password" required></label></div><div class="password-tip"><i class="bi bi-lightbulb-fill"></i><span>Gunakan password yang mudah Anda ingat tetapi tidak mudah ditebak orang lain.</span></div><div class="password-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>saya/portal.php"><i class="bi bi-arrow-left"></i> Kembali ke Portal</a><button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Simpan Password Baru</button></div></form></div>
</div>
</section></main>
<style>
.password-page-grid{max-width:980px;display:grid;gap:16px}.password-hero{display:flex;align-items:center;gap:15px;padding:20px;border-radius:20px;color:#fff;background:linear-gradient(120deg,#1d4ed8,#4f46e5,#7c3aed);box-shadow:0 10px 24px rgba(37,99,235,.2)}.password-hero-icon{width:56px;height:56px;border-radius:17px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);font-size:1.45rem}.password-kicker{font-size:.72rem;font-weight:900;letter-spacing:.08em;opacity:.82}.password-hero h2{margin:2px 0;font-size:1.45rem}.password-hero p{margin:0;opacity:.9}.password-card{border:0;border-radius:20px;padding:20px;background:linear-gradient(135deg,#eff6ff,#eef2ff 52%,#faf5ff);box-shadow:0 9px 22px rgba(15,23,42,.08)}.password-card-head{display:flex;gap:12px;align-items:center;margin-bottom:17px}.password-card-icon{width:44px;height:44px;border-radius:13px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 5px 12px rgba(37,99,235,.2)}.password-card h2{margin:0;font-size:1.18rem;font-weight:900}.password-card-head p{margin:2px 0 0;color:#64748b;font-size:.82rem}.password-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.password-form-grid label{font-weight:800;color:#334155}.password-form-grid input{margin-top:6px}.password-tip{margin-top:14px;padding:11px 13px;border-radius:13px;background:#fef3c7;border-left:5px solid #d97706;color:#78350f;display:flex;gap:9px;align-items:flex-start;font-size:.83rem;font-weight:700}.password-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:17px}.password-actions .btn{min-height:42px}@media(max-width:760px){.password-form-grid{grid-template-columns:1fr}.password-actions{justify-content:stretch;flex-wrap:wrap}.password-actions .btn{flex:1}}
</style>
<?php require_once __DIR__.'/../layouts/footer.php';
