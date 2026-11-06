<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$years = $pdo->query('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
$activeYearId = 0;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $activeYearId = (int)$y['id']; break; } }
$yearId = (int)($_POST['tahun_pelajaran_id'] ?? $_GET['tahun_pelajaran_id'] ?? $activeYearId);
$selectedYear = null;
foreach ($years as $y) { if ((int)$y['id'] === $yearId) { $selectedYear = $y; break; } }
if (!$selectedYear && $activeYearId > 0) {
    $yearId = $activeYearId;
    foreach ($years as $y) { if ((int)$y['id'] === $yearId) { $selectedYear = $y; break; } }
}

$classes = $pdo->query("SELECT id,nama,tingkat FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama")->fetchAll();
$errors = [];

$nis = trim((string)($_POST['nis'] ?? ''));
$nisn = trim((string)($_POST['nisn'] ?? ''));
$nama = trim((string)($_POST['nama'] ?? ''));
$jk = strtoupper(trim((string)($_POST['jk'] ?? '')));
$noHp = trim((string)($_POST['no_hp'] ?? ''));
$kelasId = (int)($_POST['kelas_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    if (!$selectedYear) $errors[] = 'Tahun Pelajaran tujuan tidak valid.';
    if ($nis === '' || $nama === '') $errors[] = 'NIS dan nama wajib diisi.';
    if (!in_array($jk, ['', 'L', 'P'], true)) $errors[] = 'Jenis kelamin harus L, P, atau kosong.';
    if ($kelasId <= 0) $errors[] = 'Kelas tujuan wajib dipilih untuk alur Siswa Baru.';

    $class = null;
    if ($kelasId > 0) {
        $st = $pdo->prepare('SELECT id,nama,tingkat FROM master_kelas WHERE id=? AND aktif=1 LIMIT 1');
        $st->execute([$kelasId]);
        $class = $st->fetch() ?: null;
        if (!$class) $errors[] = 'Kelas tujuan tidak ditemukan atau sudah nonaktif.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM siswa WHERE nis=? LIMIT 1');
        $check->execute([$nis]);
        if ($check->fetchColumn()) $errors[] = 'NIS sudah terdaftar. Jika siswa ini sudah ada, gunakan Data Siswa/Kelas untuk menempatkannya, bukan membuat identitas baru.';
        if ($nisn !== '') {
            $check = $pdo->prepare('SELECT id FROM siswa WHERE nisn=? LIMIT 1');
            $check->execute([$nisn]);
            if ($check->fetchColumn()) $errors[] = 'NISN sudah terdaftar. Jangan membuat identitas siswa kedua untuk siswa yang sama.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $qr = siswa_generate_qr($pdo);
            $ins = $pdo->prepare("INSERT INTO siswa (nis,nisn,nama,jk,no_hp,kode_qr,status) VALUES (?,NULLIF(?,''),?,NULLIF(?,''),NULLIF(?,''),?,'aktif')");
            $ins->execute([$nis,$nisn,$nama,$jk,$noHp,$qr]);
            $studentId = (int)$pdo->lastInsertId();

            $lock = $pdo->prepare("SELECT MAX(nomor_urut) FROM siswa_kelas WHERE tahun_pelajaran_id=? AND kelas_id=? AND status='aktif' FOR UPDATE");
            $lock->execute([$yearId,$kelasId]);
            $maxNumber = (int)$lock->fetchColumn();
            $nomorUrut = $maxNumber + 1;

            $assign = $pdo->prepare("INSERT INTO siswa_kelas (siswa_id,tahun_pelajaran_id,kelas_id,nomor_urut,status,keterangan) VALUES (?,?,?,?, 'aktif',?)");
            $assign->execute([$studentId,$yearId,$kelasId,$nomorUrut,'Siswa baru']);
            siswa_audit($pdo,'CREATE',$studentId,'Menambahkan siswa baru '.$nama.' ke '.$class['nama'].' / '.$selectedYear['nama']);
            $pdo->commit();
            redirect('siswa/detail.php?id='.$studentId.'&saved=1');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int)($e->errorInfo[1] ?? 0) === 1062) $errors[] = 'Data siswa atau penempatan sudah ada. Periksa NIS/NISN dan Tapel tujuan.';
            else throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$page_title = 'Tambah Siswa Baru';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main siswa-baru-form-page">
<header class="topbar"><div class="topbar-title">Tambah Siswa Baru</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header">
    <div><h1><i class="bi bi-person-plus-fill"></i> Tambah Siswa Baru</h1><p>Siswa akan dibuat sebagai identitas baru dan langsung ditempatkan pada Tapel tujuan.</p></div>
    <a class="btn btn-secondary" href="<?=BASE_URL?>siswa/baru.php?tahun_pelajaran_id=<?=$yearId?>"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endforeach; ?>
<div class="form-card">
<div class="section-title"><div><h2>Penempatan Awal</h2><p>Tapel dan kelas wajib ditentukan pada saat siswa baru dibuat.</p></div></div>
<form method="post">
<?=csrf_field()?>
<div class="form-grid">
<label>Tahun Pelajaran *<select class="form-control" name="tahun_pelajaran_id" required><?php foreach($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $yearId === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif']===1 ? ' (aktif)' : '' ?></option><?php endforeach; ?></select></label>
<label>Kelas Tujuan *<select class="form-control" name="kelas_id" required><option value="">- Pilih Kelas -</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $kelasId === (int)$c['id'] ? 'selected' : '' ?>><?=e((string)$c['nama'])?> · Kelas <?= (int)$c['tingkat'] ?></option><?php endforeach; ?></select></label>
<label>NIS *<input class="form-control" name="nis" value="<?=e($nis)?>" required maxlength="50"></label>
<label>NISN<input class="form-control" name="nisn" value="<?=e($nisn)?>" maxlength="50"></label>
<label>Nama Lengkap *<input class="form-control" name="nama" value="<?=e($nama)?>" required maxlength="150"></label>
<label>Jenis Kelamin<select class="form-control" name="jk"><option value="">- Pilih -</option><option value="L" <?= $jk==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= $jk==='P'?'selected':'' ?>>Perempuan</option></select></label>
<label>No. HP<input class="form-control" name="no_hp" value="<?=e($noHp)?>" maxlength="30"></label>
</div>
<div class="alert alert-info mt-3"><strong>Catatan:</strong> QR dibuat otomatis dan permanen. Jika NIS/NISN sudah terdaftar, sistem menolak pembuatan identitas kedua agar histori siswa tetap satu.</div>
<div class="form-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/baru.php?tahun_pelajaran_id=<?=$yearId?>">Batal</a><button class="btn btn-success" type="submit"><i class="bi bi-check2-circle"></i> Simpan Siswa Baru</button></div>
</form>
</div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
