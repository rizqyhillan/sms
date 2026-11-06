<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/task_registry.php';

/**
 * Task-based access layer. A task is evaluated only in the active school year
 * and within the active semester. Role remains the primary identity.
 */
function active_tapel_id(PDO $pdo): ?int
{
    static $loaded = false, $id = null;
    if ($loaded) return $id;
    $loaded = true;
    $v = $pdo->query("SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC, id DESC LIMIT 1")->fetchColumn();
    return $id = ($v !== false ? (int)$v : null);
}

function active_semester_id(PDO $pdo, ?int $tapelId = null): ?int
{
    $tapelId = $tapelId ?? active_tapel_id($pdo);
    if (!$tapelId) return null;
    $st = $pdo->prepare("SELECT id FROM semester WHERE tahun_pelajaran_id=? AND aktif=1 ORDER BY id DESC LIMIT 1");
    $st->execute([$tapelId]);
    $v = $st->fetchColumn();
    return $v !== false ? (int)$v : null;
}

function user_has_task(PDO $pdo, string $kode, ?int $targetId = null, ?string $targetType = null): bool
{
    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) return true;
    $gid = user_guru_id();
    $tp = active_tapel_id($pdo);
    $sem = active_semester_id($pdo, $tp);
    if (!$gid || !$tp || !$sem) return false;

    $sql = "SELECT 1
            FROM tugas_guru tg
            INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
            WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=?
              AND jt.kode=? AND jt.aktif=1 AND tg.aktif=1";
    $params = [$gid, $tp, $sem, $kode];
    if ($targetId !== null) { $sql .= " AND tg.target_id=?"; $params[] = $targetId; }
    if ($targetType !== null) { $sql .= " AND UPPER(COALESCE(tg.target_tipe,''))=?"; $params[] = strtoupper($targetType); }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

function user_task_targets(PDO $pdo, string $kode, string $targetType): array
{
    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) return [];
    $gid = user_guru_id(); $tp = active_tapel_id($pdo); $sem = active_semester_id($pdo, $tp);
    if (!$gid || !$tp || !$sem) return [];
    $st = $pdo->prepare("SELECT tg.target_id
        FROM tugas_guru tg INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode=? AND jt.aktif=1 AND tg.aktif=1
          AND UPPER(COALESCE(tg.target_tipe,''))=?");
    $st->execute([$gid,$tp,$sem,$kode,strtoupper($targetType)]);
    return array_values(array_unique(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))));
}


function user_has_task_on_date(PDO $pdo, string $kode, string $date, ?int $targetId = null, ?string $targetType = null): bool
{
    if (in_array(user_role(), ['admin','kepala_sekolah'], true)) return true;
    $gid=user_guru_id(); $tp=active_tapel_id($pdo); $sem=active_semester_id($pdo,$tp);
    if (!$gid || !$tp || !$sem) return false;
    $sql="SELECT tg.id
      FROM tugas_guru tg INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
      WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode=? AND jt.aktif=1 AND tg.aktif=1";
    $params=[$gid,$tp,$sem,$kode];
    if($targetId!==null){$sql.=' AND tg.target_id=?';$params[]=$targetId;}
    if($targetType!==null){$sql.=" AND UPPER(COALESCE(tg.target_tipe,''))=?";$params[]=strtoupper($targetType);}
    $sql.=' LIMIT 1';
    $st=$pdo->prepare($sql);$st->execute($params);
    $ids=$st->fetchAll(PDO::FETCH_COLUMN);
    if(!$ids) return false;
    $day=(int)(new DateTimeImmutable($date))->format('N');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $q=$pdo->prepare("SELECT COUNT(*) FROM tugas_guru_jadwal WHERE tugas_guru_id IN ($ph) AND hari=? AND aktif=1");
    $q->execute(array_merge(array_map('intval',$ids),[$day]));
    $hasSchedule=(int)$q->fetchColumn()>0;
    return $hasSchedule;
}

function task_menu_available(PDO $pdo, string $kode): bool
{
    return user_has_task($pdo, $kode);
}

/**
 * True jika user mempunyai salah satu jenis tugas yang dipetakan ke capability.
 * Capability adalah lapisan abstraksi agar menu/fungsi tidak bergantung pada
 * satu kode tugas saja. Jika kode baru belum dipetakan, akses ditolak (fail-closed).
 */
function user_has_task_capability(PDO $pdo, string $capability, ?int $targetId = null, ?string $targetType = null): bool
{
    foreach (task_registry_capability_codes($capability) as $kode) {
        if (user_has_task($pdo, $kode, $targetId, $targetType)) {
            return true;
        }
    }
    return false;
}

/**
 * Ambil seluruh target yang dimiliki user untuk sebuah capability.
 * Admin/KS tetap tidak dibatasi target karena keduanya bersifat monitoring/admin.
 */
function user_task_capability_targets(PDO $pdo, string $capability, string $targetType): array
{
    if (in_array(user_role(), ['admin', 'kepala_sekolah'], true)) return [];

    $out = [];
    foreach (task_registry_capability_codes($capability) as $kode) {
        foreach (user_task_targets($pdo, $kode, $targetType) as $id) {
            $out[(int)$id] = (int)$id;
        }
    }
    return array_values($out);
}

/**
 * Mendapatkan target type yang direkomendasikan oleh registry.
 */
function task_default_target_type(string $kode): ?string
{
    $entry = task_registry_entry($kode);
    return $entry['target_type'] ?? null;
}

/**
 * Diagnostik: kode tugas aktif di database yang belum dipetakan ke registry.
 * Tidak membuka akses; hanya membantu admin mengetahui tugas baru yang perlu
 * diputuskan hak aksesnya.
 */
function task_unregistered_active_codes(PDO $pdo): array
{
    $rows = $pdo->query("SELECT kode, nama FROM jenis_tugas WHERE aktif=1 ORDER BY kategori,nama,id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $registry = task_registry();
    $out = [];
    foreach ($rows as $row) {
        $kode = strtoupper((string)$row['kode']);
        if (!isset($registry[$kode])) {
            $out[] = ['kode' => $kode, 'nama' => (string)$row['nama']];
        }
    }
    return $out;
}
