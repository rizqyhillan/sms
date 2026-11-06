<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/task_access.php';

function wali_kelas_require(): void
{
    global $pdo;
    require_login();
    if (!user_has_task($pdo, 'WALI_KELAS')) deny_access();
}

function wali_kelas_targets(PDO $pdo): array
{
    return user_task_targets($pdo, 'WALI_KELAS', 'kelas');
}

function wali_kelas_allowed(PDO $pdo, int $kelasId): bool
{
    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) return true;
    return in_array($kelasId, wali_kelas_targets($pdo), true);
}

function wali_kelas_assignment(PDO $pdo, int $kelasId): ?array
{
    $tp = active_tapel_id($pdo);
    $sem = active_semester_id($pdo, $tp);
    $gid = user_guru_id();
    if (!$tp || !$sem || !$gid || !wali_kelas_allowed($pdo, $kelasId)) return null;
    $st = $pdo->prepare("SELECT tg.id, tg.target_id AS kelas_id,
                mk.nama AS kelas_nama, mk.tingkat, tp.nama AS tapel_nama
        FROM tugas_guru tg
        JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        JOIN master_kelas mk ON mk.id=tg.target_id
        JOIN tahun_pelajaran tp ON tp.id=tg.tahun_pelajaran_id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode='WALI_KELAS'
          AND tg.target_tipe='kelas' AND tg.target_id=? AND tg.aktif=1
        ORDER BY tg.id DESC LIMIT 1");
    $st->execute([$gid, $tp, $sem, $kelasId]);
    return $st->fetch() ?: null;
}

function wali_kelas_classes(PDO $pdo): array
{
    $ids = wali_kelas_targets($pdo);
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT mk.id,mk.nama,mk.tingkat,mk.urutan,
        (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.kelas_id=mk.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif') AS jumlah
        FROM master_kelas mk WHERE mk.id IN ($ph) ORDER BY mk.tingkat,mk.urutan,mk.nama");
    $st->execute(array_merge([active_tapel_id($pdo)], $ids));
    return $st->fetchAll();
}
