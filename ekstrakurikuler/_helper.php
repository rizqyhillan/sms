<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/task_access.php';
require_login();

function ekstra_is_admin(): bool
{
    return in_array(user_role(), ['admin', 'kepala_sekolah'], true);
}

function ekstra_allowed(PDO $pdo, int $extraId): bool
{
    if ($extraId < 1) return false;
    if (ekstra_is_admin()) return true;
    return user_has_task_capability($pdo, 'ekstrakurikuler', $extraId, 'ekstrakurikuler');
}

function ekstra_require(int $extraId): void
{
    global $pdo;
    if (!ekstra_allowed($pdo, $extraId)) deny_access();
}

function ekstra_task_codes(PDO $pdo, int $extraId): array
{
    if (ekstra_is_admin()) return ['ADMIN'];
    $out = [];
    foreach (task_registry_capability_codes('ekstrakurikuler') as $kode) {
        if (user_has_task($pdo, $kode, $extraId, 'ekstrakurikuler')) $out[] = $kode;
    }
    return $out;
}

function ekstra_detail(PDO $pdo, int $extraId): ?array
{
    $st = $pdo->prepare('SELECT id,kode,nama,aktif,keterangan FROM ekstrakurikuler WHERE id=? LIMIT 1');
    $st->execute([$extraId]);
    return $st->fetch() ?: null;
}

function ekstra_active_tapel(PDO $pdo): ?array
{
    $st = $pdo->query('SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1');
    return $st->fetch() ?: null;
}

function ekstra_tapel_rows(PDO $pdo): array
{
    return $pdo->query('SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
}

function ekstra_member_count(PDO $pdo, int $extraId, int $tapelId): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM ekstra_anggota WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND status='aktif'");
    $st->execute([$extraId, $tapelId]);
    return (int)$st->fetchColumn();
}


/**
 * Get or create the internal activity record for today's active schedule.
 * The record remains internal so teachers do not have to manage "sessions"
 * as a separate workflow.
 */
function ekstra_hari_nama(int $dayNumber): string
{
    return [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'][$dayNumber] ?? '';
}

function ekstra_sync_session_lifecycle(PDO $pdo, int $sesiId): ?array
{
    $st = $pdo->prepare("SELECT es.*, se.nama semester_nama FROM ekstra_sesi es JOIN semester se ON se.id=es.semester_id WHERE es.id=? LIMIT 1");
    $st->execute([$sesiId]);
    $session = $st->fetch();
    if (!$session) return null;
    if (in_array((string)$session['status'], ['dibatalkan','ditutup'], true)) return $session;

    $today = date('Y-m-d');
    $sessionDate = (string)$session['tanggal'];
    $status = (string)$session['status'];
    if ($sessionDate < $today) {
        $newStatus = 'ditutup';
    } elseif ($sessionDate > $today) {
        $newStatus = 'draft';
    } else {
        $now = date('H:i:s');
        $mulai = trim((string)($session['jam_mulai'] ?? ''));
        $selesai = trim((string)($session['jam_selesai'] ?? ''));
        if ($mulai !== '' && $now < $mulai) $newStatus = 'draft';
        elseif ($selesai !== '' && $now >= $selesai) $newStatus = 'ditutup';
        else $newStatus = 'aktif';
    }

    if ($newStatus !== $status) {
        if ($newStatus === 'aktif') {
            $up = $pdo->prepare("UPDATE ekstra_sesi SET status='aktif', dibuka_oleh=COALESCE(dibuka_oleh, ?), dibuka_at=COALESCE(dibuka_at, NOW()), updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='draft'");
            $up->execute([user_id(), $sesiId]);
        } elseif ($newStatus === 'ditutup') {
            $up = $pdo->prepare("UPDATE ekstra_sesi SET status='ditutup', ditutup_oleh=COALESCE(ditutup_oleh, ?), ditutup_at=COALESCE(ditutup_at, NOW()), updated_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ('draft','aktif')");
            $up->execute([user_id(), $sesiId]);
        } else {
            $up = $pdo->prepare("UPDATE ekstra_sesi SET status='draft', updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='aktif'");
            $up->execute([$sesiId]);
        }
        $st->execute([$sesiId]);
        $session = $st->fetch() ?: $session;
    }
    return $session;
}

/** Get/create today's internal activity record. Lifecycle: DRAFT -> aktif -> ditutup. */
function ekstra_get_or_create_today_session(PDO $pdo, int $extraId, int $tpId, array $schedule): ?array
{
    $today = date('Y-m-d');
    $st = $pdo->prepare("SELECT es.*, se.nama semester_nama FROM ekstra_sesi es JOIN semester se ON se.id=es.semester_id WHERE es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=? AND es.tanggal=? AND es.jadwal_id=? AND es.status<>'dibatalkan' ORDER BY es.id DESC LIMIT 1");
    $st->execute([$extraId, $tpId, $today, (int)($schedule['id'] ?? 0)]);
    $session = $st->fetch();
    if (!$session) {
        $st = $pdo->prepare("SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? AND tanggal_mulai<=? AND (tanggal_selesai IS NULL OR tanggal_selesai>=?) ORDER BY aktif DESC, tanggal_mulai DESC, id DESC LIMIT 1");
        $st->execute([$tpId, $today, $today]);
        $semester = $st->fetch();
        if (!$semester) {
            $st = $pdo->prepare("SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? ORDER BY aktif DESC, id DESC LIMIT 1");
            $st->execute([$tpId]); $semester = $st->fetch();
        }
        if (!$semester) return null;
        $ins = $pdo->prepare("INSERT INTO ekstra_sesi (ekstrakurikuler_id,jadwal_id,tahun_pelajaran_id,semester_id,tanggal,jam_mulai,jam_selesai,materi,status,dibuka_oleh,dibuka_at) VALUES(?,?,?,?,?,?,?,NULL,'draft',NULL,NULL)");
        $ins->execute([$extraId,(int)($schedule['id']??0)?:null,$tpId,(int)$semester['id'],$today,($schedule['jam_mulai']??'')!==''?$schedule['jam_mulai']:null,($schedule['jam_selesai']??'')!==''?$schedule['jam_selesai']:null]);
        $session = ekstra_sync_session_lifecycle($pdo,(int)$pdo->lastInsertId());
    } else {
        $session = ekstra_sync_session_lifecycle($pdo,(int)$session['id']);
    }
    return $session ?: null;
}

/** Resolve/create today's internal activity record from the active schedule. */
function ekstra_session_for_date(PDO $pdo, int $extraId, int $tpId, string $date): ?array
{
    $day = (int)date('N', strtotime($date));
    $hari = ekstra_hari_nama($day);
    $st = $pdo->prepare("SELECT * FROM ekstra_jadwal
        WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND hari=? AND aktif=1
        ORDER BY jam_mulai,id LIMIT 1");
    $st->execute([$extraId,$tpId,$hari]);
    $schedule=$st->fetch();
    if(!$schedule) return null;

    $st=$pdo->prepare("SELECT es.*,se.nama semester_nama FROM ekstra_sesi es
        JOIN semester se ON se.id=es.semester_id
        WHERE es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=? AND es.tanggal=?
          AND es.jadwal_id=?
          AND es.status<>'dibatalkan'
        ORDER BY es.id DESC LIMIT 1");
    $st->execute([$extraId,$tpId,$date,(int)$schedule['id']]);
    $session=$st->fetch();
    if($session) return ekstra_sync_session_lifecycle($pdo,(int)$session['id']);

    $st=$pdo->prepare("SELECT id,nama FROM semester WHERE tahun_pelajaran_id=?
        AND tanggal_mulai<=? AND (tanggal_selesai IS NULL OR tanggal_selesai>=?)
        ORDER BY aktif DESC,tanggal_mulai DESC,id DESC LIMIT 1");
    $st->execute([$tpId,$date,$date]); $semester=$st->fetch();
    if(!$semester){
        $st=$pdo->prepare("SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? ORDER BY aktif DESC,id DESC LIMIT 1");
        $st->execute([$tpId]); $semester=$st->fetch();
    }
    if(!$semester) return null;

    $ins=$pdo->prepare("INSERT INTO ekstra_sesi
        (ekstrakurikuler_id,jadwal_id,tahun_pelajaran_id,semester_id,tanggal,jam_mulai,jam_selesai,materi,status)
        VALUES(?,?,?,?,?,?,?,NULL,'draft')");
    $ins->execute([$extraId,(int)$schedule['id'],$tpId,(int)$semester['id'],$date,$schedule['jam_mulai'],$schedule['jam_selesai']]);
    $id=(int)$pdo->lastInsertId();
    return ekstra_sync_session_lifecycle($pdo,$id);
}

function ekstra_today_sessions(PDO $pdo, int $tpId, ?int $extraId = null): array
{
    $hari = ekstra_hari_nama((int)date('N'));
    $params = [$tpId, $hari];
    $sql = "SELECT ej.* , e.kode, e.nama extra_nama
        FROM ekstra_jadwal ej
        JOIN ekstrakurikuler e ON e.id=ej.ekstrakurikuler_id
        WHERE ej.tahun_pelajaran_id=? AND ej.hari=? AND ej.aktif=1 AND e.aktif=1";
    if ($extraId !== null && $extraId > 0) { $sql .= " AND ej.ekstrakurikuler_id=?"; $params[] = $extraId; }
    $sql .= " ORDER BY ej.jam_mulai,e.nama,ej.id";
    $st = $pdo->prepare($sql); $st->execute($params);
    $schedules = $st->fetchAll();
    $out = [];
    foreach ($schedules as $schedule) {
        $session = ekstra_get_or_create_today_session($pdo, (int)$schedule['ekstrakurikuler_id'], $tpId, $schedule);
        if (!$session) continue;
        $schedule['sesi_id'] = (int)$session['id'];
        $schedule['semester_nama'] = $session['semester_nama'];
        $schedule['status'] = $session['status'];
        $schedule['materi'] = $session['materi'] ?? null;
        $schedule['jumlah_absen'] = 0;
        $count = $pdo->prepare('SELECT COUNT(*) FROM ekstra_absensi WHERE sesi_id=?');
        $count->execute([(int)$session['id']]);
        $schedule['jumlah_absen'] = (int)$count->fetchColumn();
        $out[] = $schedule;
    }
    return $out;
}
