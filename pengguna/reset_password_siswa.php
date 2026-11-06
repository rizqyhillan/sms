<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pengguna_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deny_access();
}
require_valid_csrf();
$userId = max(0, (int)($_POST['user_id'] ?? 0));
if ($userId <= 0) {
    pengguna_flash('Akun siswa tidak valid.');
    redirect('pengguna/akun_siswa.php');
}

try {
    $st = $pdo->prepare("SELECT u.id,u.username,u.siswa_id,s.nama,s.nis FROM users u INNER JOIN siswa s ON s.id=u.siswa_id WHERE u.id=? AND u.role='siswa' LIMIT 1");
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        pengguna_flash('Akun siswa tidak ditemukan.');
        redirect('pengguna/akun_siswa.php');
    }
    $nis = trim((string)$row['nis']);
    if ($nis === '') {
        pengguna_flash('NIS siswa tidak tersedia, password tidak dapat direset.');
        redirect('pengguna/akun_siswa.php');
    }
    $passwordAwal = $nis . '*';
    $up = $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='siswa'");
    $up->execute([password_hash($passwordAwal, PASSWORD_DEFAULT), $userId]);
    pengguna_audit('RESET_PASSWORD_SISWA', $userId, 'Reset password akun siswa '.$row['username'].' ke password awal NIS + *');
    pengguna_flash('Password siswa berhasil direset ke NIS + *.');
} catch (Throwable $e) {
    pengguna_flash('Reset password gagal.');
}
redirect('pengguna/akun_siswa.php');
