<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);

if (user_role() === 'kepala_sekolah') {
    http_response_code(403);
    exit('Detail BK tidak tersedia untuk role Kepala Sekolah.');
}

$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$note = bk_get_note($pdo, $id);

if (!$note || !bk_can_manage_note($pdo, $note)) {
    http_response_code(403);
    exit('Catatan BK tidak dapat diedit.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $tanggal = trim((string)($_POST['tanggal'] ?? ''));
    $waktu = trim((string)($_POST['waktu'] ?? ''));
    $jenis = trim((string)($_POST['jenis'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
    $hasil = trim((string)($_POST['hasil'] ?? ''));
    $tindakLanjut = trim((string)($_POST['tindak_lanjut'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'baru'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $error = 'Tanggal tidak valid.';
    } elseif ($jenis === '') {
        $error = 'Jenis layanan wajib diisi.';
    } elseif ($deskripsi === '') {
        $error = 'Deskripsi wajib diisi.';
    } elseif (!in_array($status, ['baru','proses','selesai'], true)) {
        $error = 'Status tidak valid.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE catatan_bk
            SET tanggal=?, waktu=?, jenis=?, deskripsi=?, hasil=?, tindak_lanjut=?, status=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $tanggal,
            $waktu !== '' ? $waktu : null,
            $jenis,
            $deskripsi,
            $hasil !== '' ? $hasil : null,
            $tindakLanjut !== '' ? $tindakLanjut : null,
            $status,
            $id
        ]);

        bk_audit($pdo, 'update', $id, 'Mengubah catatan BK untuk siswa ID '.$note['siswa_id']);

        header('Location: '.BASE_URL.'bk/catatan_detail.php?id='.$id.'&updated=1');
        exit;
    }

    $note = array_merge($note, [
        'tanggal'=>$tanggal, 'waktu'=>$waktu, 'jenis'=>$jenis,
        'deskripsi'=>$deskripsi, 'hasil'=>$hasil,
        'tindak_lanjut'=>$tindakLanjut, 'status'=>$status
    ]);
}

$page_title = 'Edit Catatan BK';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Edit Catatan BK</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<style>
.bk-edit{max-width:900px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px}
.bk-edit-head{padding-bottom:15px;border-bottom:1px solid #e2e8f0;margin-bottom:16px}.bk-edit-head h1{font-size:20px;margin:0;font-weight:850}.bk-edit-head p{margin:5px 0 0;color:#64748b;font-size:12px}
.bk-edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.bk-edit-full{grid-column:1/-1}
.bk-note-private{display:flex;gap:10px;padding:11px 13px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;margin-bottom:16px}
@media(max-width:700px){.bk-edit-grid{grid-template-columns:1fr}.bk-edit-full{grid-column:auto}}
</style>

<div class="page-header">
    <div><h1><i class="bi bi-pencil-square"></i> Edit Catatan BK</h1><p>Perbarui data layanan tanpa mengubah siswa dan penanggung jawab.</p></div>
    <div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>bk/catatan_detail.php?id=<?=$id?>"><i class="bi bi-arrow-left"></i> Batal</a></div>
</div>

<div class="bk-edit">
    <div class="bk-edit-head"><h1><?=e($note['jenis'])?></h1><p><?=e($note['siswa_nama'])?> · Kelas <?=e($note['kelas'] ?? '-')?> · Guru BK <?=e($note['guru_bk'] ?? '-')?></p></div>
    <div class="bk-note-private"><i class="bi bi-shield-lock-fill"></i><div>Data privat. Setiap perubahan akan dicatat pada Audit Log.</div></div>

    <?php if($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif; ?>

    <form method="post">
        <?=csrf_field()?>
        <input type="hidden" name="id" value="<?=$id?>">
        <div class="bk-edit-grid">
            <div><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?=e($note['tanggal'])?>" required></div>
            <div><label class="form-label">Waktu</label><input type="time" name="waktu" class="form-control" value="<?=e(substr((string)$note['waktu'],0,5))?>"></div>
            <div class="bk-edit-full"><label class="form-label">Jenis Layanan</label><input type="text" name="jenis" class="form-control" maxlength="190" value="<?=e($note['jenis'])?>" required></div>
            <div class="bk-edit-full"><label class="form-label">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="6" required><?=e($note['deskripsi'])?></textarea></div>
            <div class="bk-edit-full"><label class="form-label">Hasil</label><textarea name="hasil" class="form-control" rows="5"><?=e($note['hasil'] ?? '')?></textarea></div>
            <div class="bk-edit-full"><label class="form-label">Tindak Lanjut</label><textarea name="tindak_lanjut" class="form-control" rows="5"><?=e($note['tindak_lanjut'] ?? '')?></textarea></div>
            <div><label class="form-label">Status</label><select name="status" class="form-control"><?php foreach(['baru'=>'Baru','proses'=>'Proses','selesai'=>'Selesai'] as $v=>$l): ?><option value="<?=$v?>" <?=$note['status']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?></select></div>
        </div>
        <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end">
            <a class="btn btn-secondary" href="<?=BASE_URL?>bk/catatan_detail.php?id=<?=$id?>">Batal</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>
</section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
