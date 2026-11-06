<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0);
ekstra_require($extraId);
$extra=ekstra_detail($pdo,$extraId);
if(!$extra) deny_access();
$tp=active_tapel_id($pdo);
if(!$tp) exit('Tahun Pelajaran aktif belum tersedia.');
$error='';
$success='';
$editId=(int)($_GET['edit_id']??$_POST['edit_id']??0);
$editRow=null;

$days=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

function jadwal_session_exists(PDO $pdo, int $jadwalId, int $extraId, int $tp, string $hari, string $mulai, string $selesai): bool {
    // ekstra_sesi memiliki jadwal_id. Gunakan relasi tersebut agar histori sesi
    // tetap terdeteksi walaupun jam/hari master jadwal pernah diperbarui.
    $st=$pdo->prepare("SELECT COUNT(*) FROM ekstra_sesi
        WHERE jadwal_id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=?");
    $st->execute([$jadwalId,$extraId,$tp]);
    return (int)$st->fetchColumn()>0;
}

function jadwal_transaction_exists(PDO $pdo, int $jadwalId, int $extraId, int $tp, string $hari, string $mulai, string $selesai): bool {
    // Yang mengunci jadwal adalah transaksi nyata pada sesi: absensi, jurnal,
    // kendala, atau dokumentasi. Sesi kosong tidak mengunci jadwal sehingga
    // koreksi jadwal masih aman dilakukan.
    $sql="SELECT COUNT(*) FROM ekstra_sesi es
        WHERE es.jadwal_id=? AND es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=?
          AND (
              EXISTS (SELECT 1 FROM ekstra_absensi ea WHERE ea.sesi_id=es.id)
              OR EXISTS (SELECT 1 FROM ekstra_jurnal ej WHERE ej.sesi_id=es.id)
              OR EXISTS (SELECT 1 FROM ekstra_jurnal_kendala ejk WHERE ejk.sesi_id=es.id)
              OR EXISTS (SELECT 1 FROM ekstra_dokumentasi ed WHERE ed.sesi_id=es.id)
          )";
    $st=$pdo->prepare($sql);
    $st->execute([$jadwalId,$extraId,$tp]);
    return (int)$st->fetchColumn()>0;
}

/**
 * Sinkronkan sesi internal yang belum memiliki transaksi setelah master jadwal
 * diedit. Ini penting agar perubahan jam pada jadwal hari ini langsung berlaku
 * pada sesi yang sudah terbentuk otomatis.
 */
function jadwal_sync_sessions_after_update(PDO $pdo, int $jadwalId, int $extraId, int $tp, string $oldHari, string $newHari, string $newMulai, string $newSelesai): void {
    $today=date('Y-m-d');
    $st=$pdo->prepare("SELECT es.id,es.tanggal,es.status,
        EXISTS (SELECT 1 FROM ekstra_absensi ea WHERE ea.sesi_id=es.id) AS has_absensi,
        EXISTS (SELECT 1 FROM ekstra_jurnal ej WHERE ej.sesi_id=es.id) AS has_jurnal,
        EXISTS (SELECT 1 FROM ekstra_jurnal_kendala ek WHERE ek.sesi_id=es.id) AS has_kendala,
        EXISTS (SELECT 1 FROM ekstra_dokumentasi ed WHERE ed.sesi_id=es.id) AS has_dokumentasi
        FROM ekstra_sesi es
        WHERE es.jadwal_id=? AND es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=?
          AND es.tanggal>=? AND es.status<>'dibatalkan'
        ORDER BY es.tanggal,es.id");
    $st->execute([$jadwalId,$extraId,$tp,$today]);
    $sessions=$st->fetchAll();

    foreach($sessions as $session){
        $hasTx=((int)$session['has_absensi']>0 || (int)$session['has_jurnal']>0 || (int)$session['has_kendala']>0 || (int)$session['has_dokumentasi']>0);
        if($hasTx) continue;

        if($oldHari!==$newHari && (string)$session['tanggal']===$today){
            // Jadwal dipindah hari sebelum sesi dipakai: sesi hari lama tidak
            // boleh tetap muncul sebagai kegiatan aktif.
            $up=$pdo->prepare("UPDATE ekstra_sesi SET status='dibatalkan',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status<>'dibatalkan'");
            $up->execute([(int)$session['id']]);
            continue;
        }

        // Untuk sesi hari ini/future yang masih kosong, jadwal baru menjadi
        // sumber waktu. Status dikembalikan ke draft agar lifecycle menghitung
        // ulang: draft -> aktif -> ditutup berdasarkan jam terbaru.
        $up=$pdo->prepare("UPDATE ekstra_sesi
            SET jam_mulai=?,jam_selesai=?,status='draft',dibuka_oleh=NULL,dibuka_at=NULL,ditutup_oleh=NULL,ditutup_at=NULL,updated_at=CURRENT_TIMESTAMP
            WHERE id=? AND status<>'dibatalkan'");
        $up->execute([$newMulai,$newSelesai,(int)$session['id']]);
        ekstra_sync_session_lifecycle($pdo,(int)$session['id']);
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $action=(string)($_POST['action']??'');

    if($action==='add'){
        $hari=(string)($_POST['hari']??'');
        $mulai=(string)($_POST['jam_mulai']??'');
        $selesai=(string)($_POST['jam_selesai']??'');
        $tempat=trim((string)($_POST['tempat']??''));
        $ket=trim((string)($_POST['keterangan']??''));

        if(!in_array($hari,$days,true)||$mulai===''||$selesai===''){
            $error='Hari, jam mulai, dan jam selesai wajib diisi.';
        }elseif($selesai<=$mulai){
            $error='Jam selesai harus setelah jam mulai.';
        }else{
            $dupe=$pdo->prepare('SELECT COUNT(*) FROM ekstra_jadwal WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND hari=? AND jam_mulai=? AND jam_selesai=?');
            $dupe->execute([$extraId,$tp,$hari,$mulai,$selesai]);
            if((int)$dupe->fetchColumn()>0){
                $error='Jadwal dengan hari dan jam yang sama sudah ada.';
            }else{
                $st=$pdo->prepare("INSERT INTO ekstra_jadwal(ekstrakurikuler_id,tahun_pelajaran_id,hari,jam_mulai,jam_selesai,tempat,aktif,keterangan) VALUES(?,?,?,?,?,?,1,NULLIF(?,''))");
                $st->execute([$extraId,$tp,$hari,$mulai,$selesai,$tempat,$ket]);
                $success='Jadwal berhasil ditambahkan.';
            }
        }
    }elseif($action==='update'){
        $id=(int)($_POST['edit_id']??0);
        $hari=(string)($_POST['hari']??'');
        $mulai=(string)($_POST['jam_mulai']??'');
        $selesai=(string)($_POST['jam_selesai']??'');
        $tempat=trim((string)($_POST['tempat']??''));
        $ket=trim((string)($_POST['keterangan']??''));

        $find=$pdo->prepare('SELECT * FROM ekstra_jadwal WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? LIMIT 1');
        $find->execute([$id,$extraId,$tp]);
        $old=$find->fetch();
        if(!$old){
            $error='Jadwal yang akan diedit tidak ditemukan.';
        }elseif(!in_array($hari,$days,true)||$mulai===''||$selesai===''){
            $error='Hari, jam mulai, dan jam selesai wajib diisi.';
            $editId=$id;
        }elseif($selesai<=$mulai){
            $error='Jam selesai harus setelah jam mulai.';
            $editId=$id;
        }elseif(jadwal_transaction_exists($pdo,$id,$extraId,$tp,(string)$old['hari'],(string)$old['jam_mulai'],(string)$old['jam_selesai'])){
            $error='Jadwal tidak dapat diedit karena sudah memiliki transaksi kegiatan. Gunakan jadwal baru agar histori tetap aman.';
            $editId=$id;
        }else{
            $dupe=$pdo->prepare('SELECT COUNT(*) FROM ekstra_jadwal WHERE id<>? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND hari=? AND jam_mulai=? AND jam_selesai=?');
            $dupe->execute([$id,$extraId,$tp,$hari,$mulai,$selesai]);
            if((int)$dupe->fetchColumn()>0){
                $error='Jadwal dengan hari dan jam yang sama sudah ada.';
                $editId=$id;
            }else{
                $st=$pdo->prepare("UPDATE ekstra_jadwal SET hari=?,jam_mulai=?,jam_selesai=?,tempat=?,keterangan=NULLIF(?, '') WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=?");
                $st->execute([$hari,$mulai,$selesai,$tempat,$ket,$id,$extraId,$tp]);
                // Jika sesi otomatis sudah terbentuk tetapi belum memiliki transaksi,
                // sinkronkan dengan jadwal baru agar perubahan jam langsung berlaku.
                jadwal_sync_sessions_after_update($pdo,$id,$extraId,$tp,(string)$old['hari'],$hari,$mulai,$selesai);
                $success='Jadwal berhasil diperbarui dan sesi yang belum dipakai disinkronkan.';
                $editId=0;
            }
        }
    }elseif($action==='delete'){
        $id=(int)($_POST['id']??0);
        $find=$pdo->prepare('SELECT * FROM ekstra_jadwal WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? LIMIT 1');
        $find->execute([$id,$extraId,$tp]);
        $row=$find->fetch();
        if(!$row){
            $error='Jadwal yang akan dihapus tidak ditemukan.';
        }elseif(jadwal_session_exists($pdo,$id,$extraId,$tp,(string)$row['hari'],(string)$row['jam_mulai'],(string)$row['jam_selesai'])){
            $error='Jadwal tidak dapat dihapus karena sudah memiliki histori sesi. Gunakan Nonaktifkan agar histori tetap aman.';
        }else{
            $st=$pdo->prepare('DELETE FROM ekstra_jadwal WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=?');
            $st->execute([$id,$extraId,$tp]);
            $success='Jadwal berhasil dihapus.';
        }
    }elseif($action==='toggle'){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare('UPDATE ekstra_jadwal SET aktif=IF(aktif=1,0,1) WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=?');
        $st->execute([$id,$extraId,$tp]);
        $success='Status jadwal diperbarui.';
    }
}

if($editId>0){
    $q=$pdo->prepare('SELECT * FROM ekstra_jadwal WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? LIMIT 1');
    $q->execute([$editId,$extraId,$tp]);
    $editRow=$q->fetch()?:null;
    if(!$editRow && $error==='') $error='Jadwal yang akan diedit tidak ditemukan.';
}

$st=$pdo->prepare('SELECT * FROM ekstra_jadwal WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? ORDER BY FIELD(hari,"Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"),jam_mulai,id');
$st->execute([$extraId,$tp]);
$rows=$st->fetchAll();
$q=$pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');
$q->execute([$tp]);
$tpName=(string)($q->fetchColumn()?:'');
$page_title='Jadwal Ekstrakurikuler';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main extra-page">
<header class="topbar"><div class="topbar-title">Jadwal Ekstrakurikuler</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><?=e($extra['nama'])?> · Jadwal</h1><p>Tapel <?=e($tpName)?>.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/saya.php">← Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="form-card">
<h2><i class="bi bi-calendar-week"></i> <?=$editRow?'Edit Jadwal':'Tambah Jadwal'?></h2>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="extra_id" value="<?=$extraId?>">
<input type="hidden" name="action" value="<?=$editRow?'update':'add'?>">
<?php if($editRow):?><input type="hidden" name="edit_id" value="<?= (int)$editRow['id']?>"><?php endif;?>
<div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<label>Hari<select name="hari" class="form-control" required><option value="">Pilih</option><?php foreach($days as $d):?><option value="<?=e($d)?>" <?=($editRow&&$editRow['hari']===$d)?'selected':''?>><?=e($d)?></option><?php endforeach;?></select></label>
<label>Tempat<input class="form-control" name="tempat" value="<?=e((string)($editRow['tempat']??''))?>"></label>
<label>Jam Mulai<input class="form-control" type="time" name="jam_mulai" value="<?=e(substr((string)($editRow['jam_mulai']??''),0,5))?>" required></label>
<label>Jam Selesai<input class="form-control" type="time" name="jam_selesai" value="<?=e(substr((string)($editRow['jam_selesai']??''),0,5))?>" required></label>
<label style="grid-column:1/-1">Keterangan<input class="form-control" name="keterangan" value="<?=e((string)($editRow['keterangan']??''))?>"></label>
</div>
<div class="form-actions">
<button class="btn btn-primary"><i class="bi bi-save"></i> <?=$editRow?'Simpan Perubahan':'Simpan Jadwal'?></button>
<?php if($editRow):?><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/jadwal.php?extra_id=<?=$extraId?>&menu=ekstra_jadwal">Batal</a><?php endif;?>
</div>
</form>
</div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Jadwal Berjalan</h2><p class="text-muted">Sesi dapat terbentuk otomatis. Jadwal tetap bisa diedit selama belum memiliki transaksi absensi/jurnal/dokumentasi. Setelah dipakai, jadwal dikunci dan dapat dinonaktifkan.</p></div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Hari</th><th>Waktu</th><th>Tempat</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada jadwal.</td></tr>
<?php else: foreach($rows as $r):
$hasTransaction=jadwal_transaction_exists($pdo,(int)$r['id'],$extraId,$tp,(string)$r['hari'],(string)$r['jam_mulai'],(string)$r['jam_selesai']);
$hasSession=jadwal_session_exists($pdo,(int)$r['id'],$extraId,$tp,(string)$r['hari'],(string)$r['jam_mulai'],(string)$r['jam_selesai']);
?>
<tr>
<td><strong><?=e($r['hari'])?></strong></td>
<td><?=e(substr($r['jam_mulai'],0,5))?> - <?=e(substr($r['jam_selesai'],0,5))?></td>
<td><?=e($r['tempat']??'-')?></td>
<td><?=e($r['keterangan']??'-')?></td>
<td><span class="status-badge <?=$r['aktif']?'status-aktif':'status-nonaktif'?>"><?=$r['aktif']?'Aktif':'Nonaktif'?></span></td>
<td>
<?php if($hasTransaction):?><button type="button" class="btn btn-sm btn-secondary" disabled title="Sudah memiliki transaksi kegiatan">Terkunci</button><?php else:?><a class="btn btn-sm btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/jadwal.php?extra_id=<?=$extraId?>&menu=ekstra_jadwal&edit_id=<?=(int)$r['id']?>">Edit</a><?php endif;?>
<form method="post" style="display:inline" onsubmit="return confirm('Hapus jadwal ini? Data sesi/histori tidak akan dihapus.');"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-sm btn-danger" <?=$hasSession?'disabled title="Sudah memiliki sesi; gunakan Nonaktifkan agar histori tetap aman"':''?>>Hapus</button></form>
<form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-sm <?=$r['aktif']?'btn-warning':'btn-success'?>"><?=$r['aktif']?'Nonaktifkan':'Aktifkan'?></button></form>
</td></tr>
<?php endforeach; endif;?></tbody></table></div></div>
</section></main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
