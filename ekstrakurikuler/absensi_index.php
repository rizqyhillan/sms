<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$tp = active_tapel_id($pdo);
$sem = active_semester_id($pdo, $tp);
$extraTaskCodes = task_registry_capability_codes('ekstrakurikuler');
$extraTaskPh = implode(',', array_fill(0, count($extraTaskCodes), '?'));
if (!$tp) exit('Tahun Pelajaran aktif belum tersedia.');

$extraId = (int)($_GET['extra_id'] ?? 0);
if ($extraId > 0) ekstra_require($extraId);

$today = date('Y-m-d');
$rows = [];

if (ekstra_is_admin()) {
    $rows = ekstra_today_sessions($pdo, $tp, $extraId > 0 ? $extraId : null);
} else {
    $gid = user_guru_id();
    if ($gid) {
        $params = [$gid, $tp, $sem];
        $sql = "SELECT DISTINCT ej.* , e.kode, e.nama extra_nama
            FROM tugas_guru tg
            JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
            JOIN ekstrakurikuler e ON e.id=tg.target_id
            JOIN ekstra_jadwal ej ON ej.ekstrakurikuler_id=e.id AND ej.tahun_pelajaran_id=tg.tahun_pelajaran_id
            WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.target_tipe='ekstrakurikuler'
              AND jt.kode IN ($extraTaskPh)
              AND jt.aktif=1 AND tg.aktif=1 AND e.aktif=1 AND ej.aktif=1
              AND ej.hari=?";
        $params = array_merge($params, $extraTaskCodes);
        $params[] = ekstra_hari_nama((int)date('N'));
        if ($extraId > 0) { $sql .= " AND ej.ekstrakurikuler_id=?"; $params[] = $extraId; }
        $sql .= " ORDER BY ej.jam_mulai,e.nama,ej.id";
        $st = $pdo->prepare($sql); $st->execute($params);
        $schedules = $st->fetchAll();
        foreach ($schedules as $schedule) {
            $session = ekstra_get_or_create_today_session($pdo, (int)$schedule['ekstrakurikuler_id'], $tp, $schedule);
            if (!$session) continue;
            $schedule['sesi_id'] = (int)$session['id'];
            $schedule['semester_nama'] = $session['semester_nama'];
            $schedule['status'] = $session['status'];
            $schedule['materi'] = $session['materi'] ?? null;
            $count = $pdo->prepare('SELECT COUNT(*) FROM ekstra_absensi WHERE sesi_id=?');
            $count->execute([(int)$session['id']]);
            $schedule['jumlah_absen'] = (int)$count->fetchColumn();
            $rows[] = $schedule;
        }
    }
}

$q = $pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');
$q->execute([$tp]);
$tpName = (string)($q->fetchColumn() ?: '');

$page_title = 'Absensi Ekstrakurikuler';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main extra-page">
<header class="topbar"><div class="topbar-title">Absensi Ekstrakurikuler</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header">
    <div><h1>Absensi Ekstrakurikuler</h1><p>Absensi mengikuti jadwal kegiatan hari ini. Tapel: <strong><?=e($tpName)?></strong>.</p></div>
</div>
<div class="form-card">
    <div class="section-title"><div><h2><i class="bi bi-qr-code-scan"></i> Kegiatan Hari Ini</h2><p>Sistem membuat catatan kegiatan secara otomatis dari jadwal. Guru tidak perlu membuat atau membuka sesi secara manual.</p></div></div>
    <?php if (!$rows): ?>
        <div class="empty-state"><i class="bi bi-calendar-x" style="font-size:2rem"></i><p>Tidak ada jadwal ekstrakurikuler untuk Anda hari ini.</p><p class="text-muted">Pastikan jadwal kegiatan sudah dibuat dan berstatus Aktif.</p></div>
    <?php else: ?>
    <div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
    <?php foreach ($rows as $r):
        $now = date('H:i:s');
        $belumMulai = !empty($r['jam_mulai']) && $now < (string)$r['jam_mulai'];
        $selesai = !empty($r['jam_selesai']) && $now >= (string)$r['jam_selesai'];
    ?>
        <div class="form-card extra-attendance-session">
            <div class="text-muted"><i class="bi bi-trophy-fill"></i> <?=e($r['kode'])?></div>
            <h2 style="margin:6px 0"><?=e($r['extra_nama'])?></h2>
            <div class="session-meta">
                <div><i class="bi bi-calendar3"></i> <?=e($today)?></div>
                <div><i class="bi bi-clock"></i> <?=e(substr((string)$r['jam_mulai'],0,5))?><?= $r['jam_selesai'] ? ' - '.e(substr((string)$r['jam_selesai'],0,5)) : '' ?></div>
                <div><i class="bi bi-geo-alt"></i> <?=e($r['tempat'] ?: 'Tempat belum ditentukan')?></div>
                <div><i class="bi bi-person-check"></i> <?=number_format((int)$r['jumlah_absen'])?> sudah tercatat</div>
            </div>
            <div class="form-actions" style="margin-top:14px">
                <?php if ($r['status'] === 'aktif' && !$selesai): ?>
                    <a class="btn btn-primary" href="<?=BASE_URL?>ekstrakurikuler/absensi.php?extra_id=<?=$r['ekstrakurikuler_id']?>&sesi_id=<?=$r['sesi_id']?>&menu=ekstra_absensi"><i class="bi bi-qr-code-scan"></i> <?= $belumMulai ? 'Buka Absensi' : 'Scan Absensi QR' ?></a>
                <?php elseif ($r['status'] === 'ditutup' || $selesai): ?>
                    <a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/absensi.php?extra_id=<?=$r['ekstrakurikuler_id']?>&sesi_id=<?=$r['sesi_id']?>&menu=ekstra_absensi"><i class="bi bi-eye"></i> Lihat Absensi</a>
                <?php else: ?>
                    <span class="status-badge"><?=e(ucfirst($r['status']))?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</section>
</main>
<style>
.extra-attendance-session{border:1px solid #e2e8f0;box-shadow:none}.session-meta{display:grid;gap:7px;color:#475569;font-size:.9rem}.session-meta i{width:20px}.extra-attendance-session .form-actions{flex-wrap:wrap}
</style>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
