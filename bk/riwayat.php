<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);

if (user_role() === 'kepala_sekolah') {
    http_response_code(403);
    exit('Detail BK tidak tersedia untuk role Kepala Sekolah.');
}

$siswaId = max(0, (int)($_GET['siswa_id'] ?? 0));
if (!bk_can_view_student($pdo, $siswaId)) {
    http_response_code(403);
    exit('Akses siswa BK ditolak.');
}
$siswa = bk_get_student($pdo, $siswaId);
if (!$siswa) {
    http_response_code(404);
    exit('Siswa tidak ditemukan.');
}

$notesStmt = $pdo->prepare("
    SELECT cb.id, cb.tanggal, cb.waktu, cb.jenis, cb.status, cb.guru_bk_id, g.nama AS guru_bk
    FROM catatan_bk cb
    LEFT JOIN guru g ON g.id=cb.guru_bk_id
    WHERE cb.siswa_id=?
    ORDER BY cb.tanggal DESC, cb.id DESC
");
$notesStmt->execute([$siswaId]);
$notes = $notesStmt->fetchAll();

$page_title = 'Riwayat BK';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Riwayat BK</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<style>
.bk-history-head{background:#111827;color:#fff;border-radius:16px;padding:20px;margin-bottom:18px}.bk-history-head h1{font-size:21px;margin:0;font-weight:850}.bk-history-head p{color:#cbd5e1;margin:5px 0 0;font-size:12px}
.bk-history-card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px}
.bk-status{font-size:11px;font-weight:800;padding:5px 8px;border-radius:7px}.bk-status-baru{background:#dbeafe;color:#1d4ed8}.bk-status-proses{background:#ffedd5;color:#c2410c}.bk-status-selesai{background:#dcfce7;color:#15803d}
</style>
<div class="page-header">
    <div><h1><i class="bi bi-clock-history"></i> Riwayat BK</h1><p>Riwayat layanan siswa secara privat.</p></div>
    <div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>bk/index.php"><i class="bi bi-arrow-left"></i> Kembali</a><a class="btn btn-primary" href="<?=BASE_URL?>bk/catatan.php?siswa_id=<?=$siswaId?>"><i class="bi bi-plus-lg"></i> Catatan Baru</a></div>
</div>
<div class="bk-history-head"><h1><?=e($siswa['nama'])?></h1><p>NIS <?=e($siswa['nis'])?> · Kelas <?=e($siswa['kelas'] ?? '-')?></p></div>
<div class="bk-history-card">
<div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Guru BK</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$notes): ?><tr><td colspan="5" class="empty-state">Belum ada riwayat.</td></tr>
<?php else: foreach($notes as $n): ?><tr>
<td><?=e($n['tanggal'])?><?=!empty($n['waktu'])?' '.e($n['waktu']):''?></td>
<td><strong><?=e($n['jenis'])?></strong></td><td><?=e($n['guru_bk'] ?? '-')?></td>
<td><span class="bk-status bk-status-<?=e($n['status'])?>"><?=e(ucfirst($n['status']))?></span></td>
<td><a class="btn btn-sm btn-secondary" href="<?=BASE_URL?>bk/catatan_detail.php?id=<?=$n['id']?>"><i class="bi bi-eye"></i></a><?php $nn=bk_get_note($pdo,(int)$n['id']); if($nn && bk_can_manage_note($pdo,$nn)): ?><a class="btn btn-sm btn-primary" href="<?=BASE_URL?>bk/catatan_edit.php?id=<?=$n['id']?>"><i class="bi bi-pencil-square"></i></a><?php endif; ?></td>
</tr><?php endforeach; endif; ?>
</tbody></table></div>
</div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
