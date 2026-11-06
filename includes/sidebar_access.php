<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/task_access.php';

/**
 * PART58 - aturan tampilan sidebar berbasis role + tugas tambahan.
 *
 * Ini mengatur VISIBILITAS menu saja. Pemeriksaan keamanan halaman tetap
 * dilakukan oleh helper/require_permission pada endpoint masing-masing.
 */
function sidebar_role(): string
{
    return (string)(user_role() ?? '');
}

function sidebar_role_label(): string
{
    return match (sidebar_role()) {
        'admin' => 'Administrator',
        'kepala_sekolah' => 'Kepala Sekolah',
        'guru_bk' => 'Guru BK',
        'guru' => 'Guru',
        'tu' => 'Tata Usaha',
        'siswa' => 'Siswa',
        'ortu' => 'Orang Tua / Wali',
        default => 'Pengguna',
    };
}

function sidebar_allowed_permissions(): array
{
    return match (sidebar_role()) {
        'admin' => [
            'siswa','guru','kelas','absensi','sholat','ekstrakurikuler','bk',
            'pelanggaran','pembinaan','home_visit','perpustakaan','uks','laporan',
            'tugas_guru','pengguna','audit_log','pengaturan','izin_siswa'
        ],
        'kepala_sekolah' => [
            // Kepala Sekolah bersifat monitoring/read-only.
            // Operasional tetap dikerjakan oleh Admin/Guru/Tendik sesuai kewenangan.
            'laporan'
        ],
        'guru_bk' => [
            'siswa','bk','pelanggaran','pembinaan','home_visit','laporan','izin_siswa'
        ],
        'guru' => [
            'absensi','sholat','laporan','izin_siswa'
        ],
        'tu' => [
            'siswa','guru','kelas','absensi','sholat','ekstrakurikuler','perpustakaan','uks','laporan','izin_siswa'
        ],
        default => [],
    };
}

function sidebar_can_module(string $permission): bool
{
    if (in_array(sidebar_role(), ['siswa','ortu'], true)) {
        return false;
    }

    if (!in_array($permission, sidebar_allowed_permissions(), true)) {
        if ($permission !== 'perpustakaan') return false;
        global $pdo;
        return sidebar_has_task_capability($pdo, 'perpustakaan');
    }

    if (has_permission($permission)) return true;
    if ($permission === 'perpustakaan') {
        global $pdo;
        return sidebar_has_task_capability($pdo, 'perpustakaan');
    }
    return false;
}


function sidebar_has_task(PDO $pdo, string $kode): bool
{
    if (!in_array(sidebar_role(), ['guru','guru_bk','tu'], true)) {
        return false;
    }
    return user_has_task($pdo, $kode);
}

function sidebar_has_task_capability(PDO $pdo, string $capability): bool
{
    if (!in_array(sidebar_role(), ['guru','guru_bk','tu'], true)) {
        return false;
    }
    return user_has_task_capability($pdo, $capability);
}

function sidebar_task_labels(PDO $pdo): array
{
    if (!in_array(sidebar_role(), ['guru','guru_bk','tu'], true)) {
        return [];
    }

    $gid = user_guru_id();
    $tp = active_tapel_id($pdo);
    $sem = active_semester_id($pdo, $tp);
    if (!$gid || !$tp || !$sem) return [];

    $st = $pdo->prepare('SELECT jt.kode, jt.nama
        FROM tugas_guru tg
        INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=?
          AND jt.aktif=1 AND tg.aktif=1
        GROUP BY jt.kode, jt.nama
        ORDER BY jt.nama');
    $st->execute([$gid, $tp, $sem]);

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
