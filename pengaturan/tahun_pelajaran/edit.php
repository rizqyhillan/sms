<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$id=(int)($_GET['id']??0);
$row=tp_find($pdo,$id);
if(!$row){http_response_code(404);exit('Tahun pelajaran tidak ditemukan.');}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $nama=trim((string)($_POST['nama']??''));
    $mulai=trim((string)($_POST['tanggal_mulai']??''));
    $selesai=trim((string)($_POST['tanggal_selesai']??''));
    if($nama==='')$errors[]='Nama tahun pelajaran wajib diisi.';
    if($mulai!==''&&$selesai!==''&&$mulai>$selesai)$errors[]='Tanggal selesai tidak boleh sebelum tanggal mulai.';
    if(!$errors){
        $pdo->beginTransaction();
        $st=$pdo->prepare('UPDATE tahun_pelajaran SET nama=?,tanggal_mulai=?,tanggal_selesai=? WHERE id=?');
        $st->execute([$nama,$mulai!==''?$mulai:null,$selesai!==''?$selesai:null,$id]);
        $firstYear=null;$secondYear=null;
        if(preg_match('/^(\d{4})\/(\d{4})$/',$nama,$m)){$firstYear=(int)$m[1];$secondYear=(int)$m[2];}
        $gm=$mulai!==''?$mulai:($firstYear?sprintf('%04d-07-01',$firstYear):null);
        $gs=$firstYear?sprintf('%04d-12-31',$firstYear):null;
        $nm=$secondYear?sprintf('%04d-01-01',$secondYear):null;
        $ns=$selesai!==''?$selesai:($secondYear?sprintf('%04d-06-30',$secondYear):null);
        $st=$pdo->prepare("UPDATE semester SET tanggal_mulai=?,tanggal_selesai=? WHERE tahun_pelajaran_id=? AND nama='Ganjil'");
        $st->execute([$gm,$gs,$id]);
        $st=$pdo->prepare("UPDATE semester SET tanggal_mulai=?,tanggal_selesai=? WHERE tahun_pelajaran_id=? AND nama='Genap'");
        $st->execute([$nm,$ns,$id]);
        $pdo->commit();
        tp_audit($pdo,'UPDATE',$id,'Mengubah tahun pelajaran '.$row['nama'].' menjadi '.$nama.'.');
        header('Location: '.BASE_URL.'pengaturan/tahun_pelajaran/index.php?updated=1');
        exit;
    }
}
$page_title='Edit Tahun Pelajaran';
require_once __DIR__.'/../../layouts/header.php';
require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Edit Tahun Pelajaran</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><i class="bi bi-pencil-square"></i> Edit Tahun Pelajaran</h1><p>Perbarui nama dan periode akademik. Semester Ganjil dan Genap akan diselaraskan dengan perubahan ini.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/tahun_pelajaran/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php foreach($errors as $err):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach;?>
<div class="form-card"><div class="section-title"><div><h2><?=e($row['nama'])?></h2><p>Status: <?=((int)$row['aktif']?'Aktif':'Arsip')?></p></div></div>
<form method="post"><?=csrf_field()?>
<div class="form-grid">
<label>Nama Tahun Pelajaran *<input class="form-control" name="nama" required maxlength="20" value="<?=e((string)$row['nama'])?>" placeholder="Contoh: 2027/2028"></label>
<label>Tanggal Mulai<input type="date" class="form-control" name="tanggal_mulai" value="<?=e((string)($row['tanggal_mulai']??''))?>"></label>
<label>Tanggal Selesai<input type="date" class="form-control" name="tanggal_selesai" value="<?=e((string)($row['tanggal_selesai']??''))?>"></label>
</div>
<div class="form-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/tahun_pelajaran/index.php">Batal</a></div>
</form></div>
<div class="form-card"><div class="section-title"><div><h2><i class="bi bi-info-circle"></i> Catatan</h2><p>Perubahan tidak menghapus data siswa atau riwayat.</p></div></div><ul class="mb-0"><li>Semester Ganjil dan Genap tetap dipertahankan.</li><li>Periode semester diselaraskan berdasarkan nama dan tanggal Tahun Pelajaran.</li><li>Tahun Pelajaran aktif tidak dapat dihapus.</li></ul></div>
</section></main>
<?php require_once __DIR__.'/../../layouts/footer.php'; ?>
