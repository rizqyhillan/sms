<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$errors = [];
$nis = trim((string)($_POST['nis'] ?? ''));
$nisn = trim((string)($_POST['nisn'] ?? ''));
$nama = trim((string)($_POST['nama'] ?? ''));
$jk = (string)($_POST['jk'] ?? '');
$no_hp = trim((string)($_POST['no_hp'] ?? ''));
$status = (string)($_POST['status'] ?? 'aktif');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    if ($nis === '' || $nama === '') $errors[] = 'NIS dan nama wajib diisi.';
    if (!in_array($jk, ['', 'L', 'P'], true)) $errors[] = 'Jenis kelamin tidak valid.';
    if (!in_array($status, ['aktif','lulus','nonaktif'], true)) $errors[] = 'Status tidak valid.';
    if (!$errors) {
        try {
            $qr = siswa_generate_qr($pdo);
            $st = $pdo->prepare('INSERT INTO siswa (nis, nisn, nama, jk, no_hp, kode_qr, status) VALUES (?, NULLIF(?, \'\'), ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?)');
            $st->execute([$nis, $nisn, $nama, $jk, $no_hp, $qr, $status]);
            $id = (int)$pdo->lastInsertId();
            siswa_audit($pdo, 'CREATE', $id, 'Menambahkan siswa ' . $nama);
            redirect('siswa/detail.php?id=' . $id . '&saved=1');
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) $errors[] = 'NIS atau NISN sudah digunakan.'; else throw $e;
        }
    }
}
$page_title = 'Tambah Siswa';
require_once __DIR__ . '/../layouts/header.php'; require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Tambah Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content siswa-form-page siswa-tambah-page"><div class="page-header"><div><h1>Tambah Siswa</h1><p>Buat identitas siswa baru dan QR permanen.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/index.php">Kembali</a></div>
<?php foreach($errors as $error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach; ?>
<div class="form-card"><form method="post"><?=csrf_field()?><div class="form-grid">
<label>NIS *<input class="form-control" name="nis" required value="<?=e($nis)?>"></label><label>NISN<input class="form-control" name="nisn" value="<?=e($nisn)?>"></label><label>Nama Lengkap *<input class="form-control" name="nama" required value="<?=e($nama)?>"></label><label>Jenis Kelamin<select class="form-control" name="jk"><option value="">- Pilih -</option><option value="L" <?=$jk==='L'?'selected':''?>>Laki-laki</option><option value="P" <?=$jk==='P'?'selected':''?>>Perempuan</option></select></label><label>No. HP<input class="form-control" name="no_hp" value="<?=e($no_hp)?>"></label><label>Status<select class="form-control" name="status"><option value="aktif" <?=$status==='aktif'?'selected':''?>>Aktif</option><option value="lulus" <?=$status==='lulus'?'selected':''?>>Lulus</option><option value="nonaktif" <?=$status==='nonaktif'?'selected':''?>>Nonaktif</option></select></label>
</div><div class="form-actions"><button class="btn btn-success btn-save">Simpan Siswa</button><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/index.php">Batal</a></div></form></div></section></main><?php require_once __DIR__ . '/../layouts/footer.php'; ?>
