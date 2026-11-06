<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

function pembinaan_require_access(): void
{
    require_permission('pembinaan');
}

function pembinaan_active_tapel(PDO $pdo): ?array
{
    $st = $pdo->query("SELECT id, nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC, id DESC LIMIT 1");
    $row = $st->fetch();
    return $row ?: null;
}

function pembinaan_current_guru_id(PDO $pdo): ?int
{
    $gid = user_guru_id();
    if ($gid) return $gid;
    $uid = user_id();
    if (!$uid) return null;
    $st = $pdo->prepare('SELECT guru_id FROM users WHERE id=? LIMIT 1');
    $st->execute([$uid]);
    $v = $st->fetchColumn();
    return $v !== false && $v !== null ? (int)$v : null;
}

function pembinaan_can_manage(): bool
{
    return in_array(user_role(), ['admin','kepala_sekolah','guru_bk'], true);
}

function pembinaan_can_create(): bool
{
    return in_array(user_role(), ['admin','kepala_sekolah','guru_bk','guru'], true);
}

function pembinaan_can_reduce_points(): bool
{
    // Kesiswaan dioperasikan oleh admin pada arsitektur saat ini; BK memakai role guru_bk.
    return in_array(user_role(), ['admin','guru_bk'], true);
}

function pembinaan_status_label(string $status): string
{
    return match ($status) {
        'direncanakan' => 'Perlu Ditangani',
        'proses' => 'Sedang Dibina',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        default => ucfirst($status),
    };
}

function pembinaan_status_class(string $status): string
{
    return match ($status) {
        'direncanakan' => 'pb-status-plan',
        'proses' => 'pb-status-proses',
        'selesai' => 'pb-status-selesai',
        'dibatalkan' => 'pb-status-batal',
        default => 'pb-status-proses',
    };
}

function pembinaan_audit(PDO $pdo, string $aksi, ?int $referensiId, string $keterangan): void
{
    $st = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent, created_at) VALUES (?, ?, 'pembinaan', ?, ?, ?, ?, NOW())");
    $st->execute([
        user_id() ?: null,
        $aksi,
        $referensiId ?: null,
        $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}
