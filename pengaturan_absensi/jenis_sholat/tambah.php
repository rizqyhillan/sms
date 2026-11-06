<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
pengaturan_sholat_config_admin_only();

$errors=[];
$nama=trim((string)($_POST['nama']??''));
$urutan=(int)($_POST['urutan']??0);
$aktif=isset($_POST['aktif']) ? 1 : 0;
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    if($nama==='') $errors[]='Nama jenis sholat wajib diisi.';
    if(mb_strlen($nama)>50) $errors[]='Nama jenis sholat maksimal 50 karakter.';
    if($urutan<0) $errors[]='Urutan tidak boleh negatif.';
    if(!$errors){
        $st=$pdo->prepare('SELECT id FROM jenis_sholat WHERE LOWER(nama)=LOWER(?) LIMIT 1');$st->execute([$nama]);
        if($st->fetch()) $errors[]='Nama jenis sholat sudah ada.';
    }
    if(!$errors){
        $st=$pdo->prepare('INSERT INTO jenis_sholat (nama,urutan,aktif) VALUES (?,?,?)');
        $st->execute([$nama,$urutan,$aktif]);$id=(int)$pdo->lastInsertId();
        pengaturan_absensi_audit($pdo,'CREATE_JENIS_SHOLAT',$id,'Menambah jenis sholat: '.$nama);
        redirect('pengaturan_absensi/jenis_sholat/index.php?saved=1');
    }
}
$page_title='Tambah Jenis Sholat';require_once __DIR__.'/../../layouts/header.php';require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pengaturan Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content sholat-settings-module">
<div class="page-header"><div><h1>Tambah Jenis Sholat</h1><p>Jenis yang aktif akan muncul saat membuat sesi sholat.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/index.php">Kembali</a></div>
<?php foreach($errors as $x):?><div class="alert alert-danger"><?=e($x)?></div><?php endforeach;?>
<div class="form-card"><form method="post"><?=csrf_field()?><div class="form-grid">
<label>Nama Jenis Sholat *<input class="form-control" name="nama" maxlength="50" required value="<?=e($nama)?>" placeholder="Contoh: Ashar"></label>
<label>Urutan *<input class="form-control" type="number" name="urutan" min="0" required value="<?=e((string)$urutan)?>"><small class="muted-text">Angka lebih kecil tampil lebih dahulu.</small></label>
</div><label class="check-row"><input type="checkbox" name="aktif" value="1" <?=$aktif?'checked':''?>> Aktif dan tersedia untuk sesi baru</label>
<div class="form-actions"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/index.php">Batal</a></div></form></div>
</section></main><?php require_once __DIR__.'/../../layouts/footer.php'; ?>
