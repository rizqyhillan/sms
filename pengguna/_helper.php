<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

function pengguna_require_admin(): void
{
    require_login();
    if (user_role() !== 'admin') {
        deny_access();
    }
}

function pengguna_audit(string $aksi, ?int $referensiId, string $keterangan): void
{
    global $pdo;
    $st = $pdo->prepare("INSERT INTO audit_log
        (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent)
        VALUES (?, ?, 'pengguna', ?, ?, ?, ?)");
    $st->execute([
        user_id(), $aksi, $referensiId, $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function pengguna_roles(): array
{
    return [
        'admin' => 'Administrator',
        'kepala_sekolah' => 'Kepala Sekolah',
        'guru' => 'Guru',
        'guru_bk' => 'Guru BK',
        'siswa' => 'Siswa',
        'ortu' => 'Orang Tua/Wali',
    ];
}

function pengguna_flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['pengguna_flash'] = $message;
        return null;
    }
    $msg = $_SESSION['pengguna_flash'] ?? null;
    unset($_SESSION['pengguna_flash']);
    return is_string($msg) ? $msg : null;
}

function pengguna_old_input(array $data): void
{
    $_SESSION['pengguna_old'] = $data;
}

function pengguna_take_old(): array
{
    $old = $_SESSION['pengguna_old'] ?? [];
    unset($_SESSION['pengguna_old']);
    return is_array($old) ? $old : [];
}
