<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);

if (user_role() === 'kepala_sekolah') {
    http_response_code(403);
    exit('Detail BK tidak tersedia untuk role Kepala Sekolah.');
}

$id = max(0, (int)($_GET['id'] ?? 0));
$note = bk_get_note($pdo, $id);

if (!$note || !bk_can_view_student($pdo, (int)$note['siswa_id'])) {
    http_response_code(404);
    exit('Catatan BK tidak ditemukan.');
}

$canManage = bk_can_manage_note($pdo, $note);

$page_title = 'Detail Catatan BK';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Detail Catatan BK</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<style>
.bk-detail-box{max-width:1000px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px}
.bk-detail-head{display:flex;justify-content:space-between;gap:15px;border-bottom:1px solid #e2e8f0;padding-bottom:15px;margin-bottom:17px}
.bk-detail-title{font-size:21px;font-weight:850}.bk-detail-meta{margin-top:5px;color:#64748b;font-size:12px}
.bk-detail-section{margin-top:18px;padding:14px;border:1px solid #e2e8f0;border-radius:11px;background:#f8fafc}.bk-detail-section h3{font-size:12px;font-weight:850;margin:0 0 7px;color:#334155;text-transform:uppercase;letter-spacing:.04em}
.bk-detail-body{white-space:pre-wrap;line-height:1.7;font-size:14px;color:#334155}
.bk-confidential{padding:9px 11px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:9px;font-size:11px;font-weight:800;height:max-content}
.bk-status{font-size:11px;font-weight:800;padding:5px 8px;border-radius:7px}.bk-status-baru{background:#dbeafe;color:#1d4ed8}.bk-status-proses{background:#ffedd5;color:#c2410c}.bk-status-selesai{background:#dcfce7;color:#15803d}
@media(max-width:650px){.bk-detail-head{flex-direction:column}}
</style>

<div class="page-header">
    <div><h1><i class="bi bi-file-earmark-lock-fill"></i> Detail Catatan BK</h1><p>Informasi layanan BK bersifat privat.</p></div>
    <div class="page-header-actions">
        <?php if($canManage): ?><a class="btn btn-primary" href="<?=BASE_URL?>bk/catatan_edit.php?id=<?=$id?>"><i class="bi bi-pencil-square"></i> Edit</a><?php endif; ?>
        <a class="btn btn-secondary" href="<?=BASE_URL?>bk/catatan.php?siswa_id=<?=$note['siswa_id']?>"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if(isset($_GET['updated'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Catatan berhasil diperbarui.</div><?php endif; ?>

<div class="bk-detail-box">
    <div class="bk-detail-head">
        <div>
            <div class="bk-detail-title"><?=e($note['jenis'])?></div>
            <div class="bk-detail-meta">
                <?=e($note['tanggal'])?><?=!empty($note['waktu'])?' '.e($note['waktu']):''?>
                · <?=e($note['siswa_nama'])?> · Kelas <?=e($note['kelas'] ?? '-')?>
                · Guru BK <?=e($note['guru_bk'] ?? '-')?>
            </div>
        </div>
        <div class="bk-confidential"><i class="bi bi-lock-fill"></i> <?=e(ucfirst($note['status']))?></div>
    </div>

    <div class="bk-detail-section"><h3>Deskripsi</h3><div class="bk-detail-body"><?=e($note['deskripsi'])?></div></div>
    <div class="bk-detail-section"><h3>Hasil</h3><div class="bk-detail-body"><?=($note['hasil'] ?? '') !== '' ? e($note['hasil']) : '<span style="color:#94a3b8">Belum diisi.</span>'?></div></div>
    <div class="bk-detail-section"><h3>Tindak Lanjut</h3><div class="bk-detail-body"><?=($note['tindak_lanjut'] ?? '') !== '' ? e($note['tindak_lanjut']) : '<span style="color:#94a3b8">Belum diisi.</span>'?></div></div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
