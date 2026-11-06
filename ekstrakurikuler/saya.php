<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$tp = active_tapel_id($pdo);
$sem = active_semester_id($pdo, $tp);
$extraTaskCodes = task_registry_capability_codes('ekstrakurikuler');
$extraTaskPh = implode(',', array_fill(0, count($extraTaskCodes), '?'));
$rows = [];
if (ekstra_is_admin()) {
    $st = $pdo->query("SELECT e.id,e.kode,e.nama,e.aktif,
        (SELECT COUNT(*) FROM ekstra_anggota ea WHERE ea.ekstrakurikuler_id=e.id AND ea.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1) AND ea.status='aktif') anggota
        FROM ekstrakurikuler e WHERE e.aktif=1 ORDER BY e.nama");
    $rows = $st->fetchAll();
} else {
    $gid = user_guru_id();
    if ($gid && $tp) {
        $st = $pdo->prepare("SELECT DISTINCT e.id,e.kode,e.nama,e.aktif,
            (SELECT COUNT(*) FROM ekstra_anggota ea WHERE ea.ekstrakurikuler_id=e.id AND ea.tahun_pelajaran_id=tg.tahun_pelajaran_id AND ea.status='aktif') anggota,
            GROUP_CONCAT(DISTINCT jt.nama ORDER BY jt.nama SEPARATOR ', ') jenis_tugas
            FROM tugas_guru tg
            JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
            JOIN ekstrakurikuler e ON e.id=tg.target_id
            WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.target_tipe='ekstrakurikuler'
              AND jt.kode IN ($extraTaskPh)
              AND jt.aktif=1 AND tg.aktif=1 AND e.aktif=1
            GROUP BY e.id,e.kode,e.nama,e.aktif,tg.tahun_pelajaran_id ORDER BY e.nama");
        $st->execute(array_merge([$gid,$tp,$sem], $extraTaskCodes));
        $rows = $st->fetchAll();
    }
}

$tpName = '';
if ($tp) {
    $q = $pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');
    $q->execute([$tp]); $tpName = (string)($q->fetchColumn() ?: '');
}

$page_title='Ekstrakurikuler Saya';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main extra-page">
<header class="topbar"><div class="topbar-title">Ekstrakurikuler Saya</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1>Ekstrakurikuler Saya</h1><p>Pengelolaan kegiatan sesuai penugasan pada Tahun Pelajaran aktif: <strong><?=e($tpName ?: '-')?></strong>.</p></div></div>
<?php if(!$rows): ?>
<div class="form-card"><div class="empty-state"><i class="bi bi-trophy" style="font-size:2rem"></i><p>Belum ada ekstrakurikuler yang menjadi tanggung jawab Anda pada Tapel aktif.</p></div></div>
<?php else: ?>
<div class="extra-assignment-list extra-formal-list">
<?php foreach($rows as $r): ?>
<div class="extra-program extra-program-formal">
  <div class="extra-formal-heading">
    <div>
      <div class="extra-card-kicker">Ekstrakurikuler</div>
      <h2><?=e($r['nama'])?></h2>
      <div class="extra-formal-subtitle">Pembina: <strong><?=e(user_name())?></strong> &nbsp;·&nbsp; <?=e($r['jenis_tugas'] ?? 'Penugasan Ekstrakurikuler')?></div>
    </div>
    <span class="extra-code extra-code-formal"><?=e($r['kode'])?></span>
  </div>

  <div class="extra-status-grid">
    <div class="extra-status-card extra-status-active"><span class="extra-status-icon"><i class="bi bi-check-circle-fill"></i></span><div><small>Status</small><strong>AKTIF</strong></div></div>
    <div class="extra-status-card extra-status-members"><span class="extra-status-icon"><i class="bi bi-people-fill"></i></span><div><small>Anggota Aktif</small><strong><?=number_format((int)$r['anggota'])?> siswa</strong></div></div>
    <div class="extra-status-card extra-status-year"><span class="extra-status-icon"><i class="bi bi-calendar3"></i></span><div><small>Tahun Pelajaran</small><strong><?=e($tpName ?: '-')?></strong></div></div>
  </div>

  <div class="extra-work-menu">
    <a class="extra-work-item extra-work-green" href="<?=BASE_URL?>ekstrakurikuler/anggota.php?extra_id=<?=$r['id']?>&menu=ekstra_anggota"><span class="extra-work-icon"><i class="bi bi-people-fill"></i></span><span><b>Anggota</b><small>Kelola peserta ekstrakurikuler</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-blue" href="<?=BASE_URL?>ekstrakurikuler/jadwal.php?extra_id=<?=$r['id']?>&menu=ekstra_jadwal"><span class="extra-work-icon"><i class="bi bi-calendar-week"></i></span><span><b>Jadwal</b><small>Kelola jadwal kegiatan</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-orange" href="<?=BASE_URL?>ekstrakurikuler/absensi_index.php?extra_id=<?=$r['id']?>&menu=ekstra_absensi"><span class="extra-work-icon"><i class="bi bi-qr-code-scan"></i></span><span><b>Absensi Peserta</b><small>Pencatatan kehadiran peserta</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-violet" href="<?=BASE_URL?>ekstrakurikuler/rekap.php?extra_id=<?=$r['id']?>&menu=ekstra_rekap"><span class="extra-work-icon"><i class="bi bi-bar-chart-fill"></i></span><span><b>Rekap Peserta</b><small>Ringkasan keikutsertaan dan kehadiran</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-cyan" href="<?=BASE_URL?>ekstrakurikuler/pembina.php?extra_id=<?=$r['id']?>&menu=ekstra_pembina"><span class="extra-work-icon"><i class="bi bi-person-badge"></i></span><span><b>Absensi Pembina</b><small>Pencatatan kehadiran pembina</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-red" href="<?=BASE_URL?>ekstrakurikuler/jurnal.php?extra_id=<?=$r['id']?>&menu=ekstra_jurnal"><span class="extra-work-icon"><i class="bi bi-journal-text"></i></span><span><b>Jurnal Kegiatan</b><small>Catatan pelaksanaan kegiatan</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-amber" href="<?=BASE_URL?>ekstrakurikuler/kendala.php?extra_id=<?=$r['id']?>&menu=ekstra_kendala"><span class="extra-work-icon"><i class="bi bi-exclamation-triangle"></i></span><span><b>Jurnal Kendala</b><small>Catatan kendala kegiatan</small></span><i class="bi bi-chevron-right"></i></a>
    <a class="extra-work-item extra-work-pink" href="<?=BASE_URL?>ekstrakurikuler/foto.php?extra_id=<?=$r['id']?>&menu=ekstra_foto"><span class="extra-work-icon"><i class="bi bi-images"></i></span><span><b>Dokumentasi Foto</b><small>Kelola dokumentasi kegiatan</small></span><i class="bi bi-chevron-right"></i></a>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section></main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
