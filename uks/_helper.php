<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_access.php';
require_once __DIR__ . '/../includes/operational_hours.php';

function uks_require_access(PDO $pdo): void
{
    require_permission('uks');

    if (!in_array(user_role(), ['admin', 'kepala_sekolah', 'guru', 'guru_bk'], true)) {
        deny_access();
    }
}

function uks_is_petugas(PDO $pdo): bool
{
    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) {
        return true;
    }

    $guruId = user_guru_id();
    $tp = active_tapel_id($pdo);
    $sem = active_semester_id($pdo, $tp);
    if (!$guruId || !$tp || !$sem) {
        return false;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM tugas_guru tg
        INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode='PETUGAS_UKS' AND tg.aktif=1");
    $st->execute([$guruId,$tp,$sem]);
    return (int)$st->fetchColumn() > 0;
}

function uks_can_manage(PDO $pdo): bool
{
    return in_array(user_role(), ['admin', 'kepala_sekolah'], true) || uks_is_petugas($pdo);
}

function uks_can_manage_student(PDO $pdo, int $studentId): bool
{
    if ($studentId <= 0) {
        return false;
    }

    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) {
        return true;
    }

    if (!uks_is_petugas($pdo)) {
        return false;
    }

    return uks_teacher_can_view_student($pdo, $studentId);
}

function uks_can_view_sensitive(PDO $pdo): bool
{
    return in_array(user_role(), ['admin', 'kepala_sekolah', 'guru_bk'], true) || uks_is_petugas($pdo);
}

function uks_current_guru_id(PDO $pdo): ?int
{
    $id = user_guru_id();
    if ($id) return $id;

    $st = $pdo->prepare('SELECT guru_id FROM users WHERE id=? LIMIT 1');
    $st->execute([user_id()]);
    $id = (int)($st->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}


function uks_teacher_scope(PDO $pdo, string $studentAlias = 's'): array
{
    if (user_role() !== 'guru') {
        return ['sql' => '1=1', 'params' => []];
    }

    $guruId = user_guru_id();
    $tp = active_tapel_id($pdo);
    $sem = active_semester_id($pdo, $tp);
    if (!$guruId) {
        return ['sql' => '1=0', 'params' => []];
    }

    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $studentAlias) ?: 's';
    $sql = "EXISTS (
        SELECT 1
        FROM siswa_kelas sk_scope
        INNER JOIN tugas_guru tg_scope ON tg_scope.target_id = sk_scope.kelas_id
        INNER JOIN jenis_tugas jt_scope ON jt_scope.id = tg_scope.jenis_tugas_id
        WHERE sk_scope.siswa_id = {$alias}.id
          AND sk_scope.tahun_pelajaran_id = ?
          AND sk_scope.status = 'aktif'
          AND tg_scope.guru_id = ?
          AND tg_scope.semester_id = ?
          AND jt_scope.kode = 'WALI_KELAS'
          AND UPPER(COALESCE(tg_scope.target_tipe,'')) = 'KELAS'
          AND tg_scope.aktif = 1
    )";

    return ['sql' => $sql, 'params' => [$tp, $guruId, $sem]];
}

function uks_teacher_can_view_student(PDO $pdo, int $studentId): bool
{
    if (user_role() !== 'guru') {
        return true;
    }

    $scope = uks_teacher_scope($pdo, 's');
    if ($scope['sql'] === '1=0') {
        return false;
    }

    $st = $pdo->prepare("SELECT 1 FROM siswa s WHERE s.id=? AND {$scope['sql']} LIMIT 1");
    $st->execute(array_merge([$studentId], $scope['params']));
    return (bool)$st->fetchColumn();
}

function uks_audit(PDO $pdo, string $aksi, ?int $referensiId, string $keterangan): void
{
    $st = $pdo->prepare("INSERT INTO audit_log
        (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent)
        VALUES (?, ?, 'uks', ?, ?, ?, ?)");
    $st->execute([
        user_id(), $aksi, $referensiId, $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function uks_flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['uks_flash'] = $message;
        return null;
    }
    $msg = $_SESSION['uks_flash'] ?? null;
    unset($_SESSION['uks_flash']);
    return is_string($msg) ? $msg : null;
}

function uks_student(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.jk,s.status,mk.nama AS kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id FROM siswa_kelas sk2
            WHERE sk2.siswa_id=s.id
              AND sk2.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1)
              AND sk2.status='aktif'
            ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.id=? LIMIT 1");
    $st->execute([$id]);
    $row=$st->fetch();
    return $row ?: null;
}

function uks_stock(PDO $pdo, int $obatId): int
{
    $st=$pdo->prepare('SELECT COALESCE(stok_saat_ini,0) FROM uks_obat WHERE id=? LIMIT 1');
    $st->execute([$obatId]);
    return (int)($st->fetchColumn() ?? 0);
}

function uks_expiry_state(?string $date): array
{
    if (!$date) return ['key'=>'none','label'=>'Belum diatur','class'=>'secondary','days'=>null];
    try {
        $today = new DateTimeImmutable('today');
        $exp = new DateTimeImmutable($date);
    } catch (Throwable $e) {
        return ['key'=>'none','label'=>'Tanggal tidak valid','class'=>'danger','days'=>null];
    }
    $days = (int)$today->diff($exp)->format('%r%a');
    if ($days < 0) return ['key'=>'expired','label'=>'Kedaluwarsa','class'=>'danger','days'=>$days];
    if ($days <= 30) return ['key'=>'soon30','label'=>'≤ 30 hari','class'=>'danger','days'=>$days];
    if ($days <= 90) return ['key'=>'soon90','label'=>'≤ 90 hari','class'=>'warning','days'=>$days];
    return ['key'=>'safe','label'=>'Aman','class'=>'success','days'=>$days];
}

function uks_obat_expired(PDO $pdo, int $obatId): bool
{
    $st=$pdo->prepare('SELECT tanggal_kadaluarsa FROM uks_obat WHERE id=? LIMIT 1');
    $st->execute([$obatId]);
    $date=$st->fetchColumn();
    return is_string($date) && $date !== '' && $date < date('Y-m-d');
}
