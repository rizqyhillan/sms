<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/operational_hours.php';

/**
 * BK helper.
 * Menggunakan schema catatan_bk yang sudah tersedia.
 */

function bk_require_access(PDO $pdo): void
{
    require_permission('bk');

    if (!in_array(user_role(), ['admin', 'kepala_sekolah', 'guru_bk'], true)) {
        http_response_code(403);
        exit('Akses BK ditolak.');
    }
}

function bk_current_guru_id(PDO $pdo): int
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return 0;

    $stmt = $pdo->prepare("SELECT guru_id FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);

    return (int)($stmt->fetchColumn() ?: 0);
}

function bk_can_view_student(PDO $pdo, int $siswaId): bool
{
    if ($siswaId <= 0) return false;

    // Kepala sekolah hanya mendapat monitoring/statistik BK.
    // Detail/catatan konseling bersifat sensitif dan tidak boleh dibuka
    // hanya dengan memanipulasi siswa_id pada URL.
    if (user_role() === 'kepala_sekolah') {
        return false;
    }

    if (user_role() === 'admin') {
        return true;
    }

    if (user_role() !== 'guru_bk') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE id=?");
    $stmt->execute([$siswaId]);

    return (int)$stmt->fetchColumn() > 0;
}

function bk_can_manage_note(PDO $pdo, array $note): bool
{
    $role = user_role();

    if (in_array($role, ['admin', 'kepala_sekolah'], true)) {
        return true;
    }

    if ($role !== 'guru_bk') {
        return false;
    }

    $guruId = bk_current_guru_id($pdo);
    if ($guruId <= 0) return false;

    // Guru BK dapat mengelola catatan yang ditugaskan kepadanya.
    return (int)($note['guru_bk_id'] ?? 0) === $guruId;
}

function bk_get_student(PDO $pdo, int $siswaId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            s.id, s.nis, s.nisn, s.nama, s.jk, s.no_hp, s.status,
            mk.nama AS kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id
            FROM siswa_kelas sk2
            WHERE sk2.siswa_id=s.id
              AND sk2.tahun_pelajaran_id=(
                  SELECT id FROM tahun_pelajaran
                  WHERE aktif=1 ORDER BY id DESC LIMIT 1
              )
              AND sk2.status='aktif'
            ORDER BY sk2.id DESC LIMIT 1
        )
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.id=? LIMIT 1
    ");
    $stmt->execute([$siswaId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function bk_get_note(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            cb.id, cb.siswa_id, cb.tanggal, cb.waktu, cb.jenis,
            cb.deskripsi, cb.hasil, cb.tindak_lanjut, cb.status,
            cb.guru_bk_id, cb.created_at, cb.updated_at,
            s.nis, s.nisn, s.nama AS siswa_nama,
            g.nama AS guru_bk,
            mk.nama AS kelas
        FROM catatan_bk cb
        JOIN siswa s ON s.id=cb.siswa_id
        LEFT JOIN guru g ON g.id=cb.guru_bk_id
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id
            FROM siswa_kelas sk2
            WHERE sk2.siswa_id=s.id
              AND sk2.tahun_pelajaran_id=(
                  SELECT id FROM tahun_pelajaran
                  WHERE aktif=1 ORDER BY id DESC LIMIT 1
              )
              AND sk2.status='aktif'
            ORDER BY sk2.id DESC LIMIT 1
        )
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE cb.id=? LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function bk_audit(PDO $pdo, string $aksi, int $referensiId, string $keterangan): void
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log
            (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent, created_at)
        VALUES
            (?, ?, 'bk', ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        $aksi,
        $referensiId ?: null,
        $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
