<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
if (user_role() !== 'kepala_sekolah') { deny_access(); }
$userId = user_id();
$st = $pdo->prepare("SELECT username,nama,password FROM users WHERE id=? AND role='kepala_sekolah' LIMIT 1");
$st->execute([$userId]);
$akun = $st->fetch();
if (!$akun) { http_response_code(404); exit('Akun Kepala Sekolah tidak ditemukan.'); }
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_valid_csrf();
    $lama=(string)($_POST['password_lama']??'');
    $baru=(string)($_POST['password_baru']??'');
    $konfirmasi=(string)($_POST['konfirmasi']??'');
    if($lama===''||$baru===''||$konfirmasi==='') $error='Semua kolom password wajib diisi.';
    elseif(!password_verify($lama,(string)$akun['password'])) $error='Password lama tidak sesuai.';
    elseif(strlen($baru)<8) $error='Password baru minimal 8 karakter.';
    elseif($baru!==$konfirmasi) $error='Konfirmasi password baru tidak sama.';
    elseif(password_verify($baru,(string)$akun['password'])) $error='Password baru harus berbeda dari password lama.';
    else try {
        $up=$pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='kepala_sekolah'");
        $up->execute([password_hash($baru,PASSWORD_DEFAULT),$userId]);
        $audit=$pdo->prepare("INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,'pengguna',?,?,?,?)");
        $audit->execute([$userId,'CHANGE_PASSWORD_KEPALA_SEKOLAH',$userId,'Kepala Sekolah mengubah password akun jabatan.',$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
        $success='Password berhasil diubah.';
        $st->execute([$userId]); $akun=$st->fetch()?:$akun;
    } catch(Throwable) { $error='Password gagal diubah. Silakan coba lagi.'; }
}
$page_title='Ubah Password';
require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Ubah Password</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-key-fill"></i> Ubah Password</h1><p>Kelola password akun jabatan Kepala Sekolah.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=e($error)?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?=e($success)?></div><?php endif;?>
<div class="form-card" style="max-width:850px"><div class="section-title"><div><h2><?=e($akun['nama'])?></h2><p>Username: <code><?=e($akun['username'])?></code></p></div></div><form method="post" autocomplete="off"><?=csrf_field()?><div class="form-grid"><label>Password Lama<input class="form-control" type="password" name="password_lama" autocomplete="current-password" required></label><label>Password Baru<input class="form-control" type="password" name="password_baru" minlength="8" autocomplete="new-password" required></label><label>Konfirmasi Password Baru<input class="form-control" type="password" name="konfirmasi" minlength="8" autocomplete="new-password" required></label></div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-shield-check"></i> Simpan Password Baru</button></div></form></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
