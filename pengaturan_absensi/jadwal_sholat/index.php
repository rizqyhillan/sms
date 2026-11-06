<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
pengaturan_kehadiran_admin_only();
$tp=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetch();
if(!$tp){exit('Belum ada tahun pelajaran aktif.');}
$semesters=$pdo->prepare('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM semester WHERE tahun_pelajaran_id=? ORDER BY id');$semesters->execute([(int)$tp['id']]);$semesterRows=$semesters->fetchAll();
if(!$semesterRows){exit('Belum ada semester pada tahun pelajaran aktif.');}
$semesterId=(int)($_GET['semester_id']??$_POST['semester_id']??0);if(!$semesterId){foreach($semesterRows as $sr){if((int)$sr['aktif']===1){$semesterId=(int)$sr['id'];break;}}}if(!$semesterId)$semesterId=(int)$semesterRows[0]['id'];
$selectedSemester=null;foreach($semesterRows as $sr){if((int)$sr['id']===$semesterId){$selectedSemester=$sr;break;}}if(!$selectedSemester){$semesterId=(int)$semesterRows[0]['id'];$selectedSemester=$semesterRows[0];}
$error='';$saved='';$editId=(int)($_GET['edit']??0);$editRow=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $action=(string)($_POST['action']??'save');
    $id=(int)($_POST['id']??0);
    $semesterId=(int)($_POST['semester_id']??$semesterId);
    try{
        $sc=$pdo->prepare('SELECT id FROM semester WHERE id=? AND tahun_pelajaran_id=? LIMIT 1');
        $sc->execute([$semesterId,$tp['id']]);
        if(!$sc->fetchColumn()) throw new RuntimeException('Semester tidak sesuai dengan tahun pelajaran aktif.');

        if($action==='delete' || $action==='deactivate'){
            $st=$pdo->prepare('SELECT id,semester_id,jenis_sholat_id,nama_sesi,aktif FROM jadwal_sholat WHERE id=? AND tahun_pelajaran_id=? AND semester_id=? LIMIT 1');
            $st->execute([$id,$tp['id'],$semesterId]);
            $row=$st->fetch();
            if(!$row) throw new RuntimeException('Jadwal sholat tidak ditemukan.');

            $used=$pdo->prepare('SELECT COUNT(*) FROM sesi_absensi WHERE tahun_pelajaran_id=? AND semester_id=? AND jenis_sholat_id=?');
            $used->execute([$tp['id'],$semesterId,(int)$row['jenis_sholat_id']]);
            $sessionCount=(int)$used->fetchColumn();

            if($action==='delete' && $sessionCount>0){
                $action='deactivate';
            }

            if($action==='deactivate'){
                $st=$pdo->prepare('UPDATE jadwal_sholat SET aktif=0 WHERE id=? AND tahun_pelajaran_id=? AND semester_id=?');
                $st->execute([$id,$tp['id'],$semesterId]);
                pengaturan_absensi_audit($pdo,'DEACTIVATE_JADWAL_SHOLAT',$id,'Menonaktifkan jadwal sholat semester '.$semesterId.': '.$row['nama_sesi'].($sessionCount>0?' karena sudah memiliki histori sesi.':''));
                redirect('pengaturan_absensi/jadwal_sholat/index.php?semester_id='.$semesterId.'&saved='.rawurlencode($sessionCount>0?'Jadwal sudah memiliki histori sesi, sehingga dinonaktifkan tanpa menghapus histori.':'Jadwal sholat dinonaktifkan.'));
            }

            $st=$pdo->prepare('DELETE FROM jadwal_sholat WHERE id=? AND tahun_pelajaran_id=? AND semester_id=?');
            $st->execute([$id,$tp['id'],$semesterId]);
            pengaturan_absensi_audit($pdo,'DELETE_JADWAL_SHOLAT',$id,'Menghapus jadwal sholat semester '.$semesterId.': '.$row['nama_sesi']);
            redirect('pengaturan_absensi/jadwal_sholat/index.php?semester_id='.$semesterId.'&saved='.rawurlencode('Jadwal sholat berhasil dihapus.'));
        }

        $jenis=(int)($_POST['jenis_sholat_id']??0);
        $mulai=(string)($_POST['jam_mulai']??'');
        $selesai=(string)($_POST['jam_selesai']??'');
        $nama=trim((string)($_POST['nama_sesi']??''));
        $aktif=isset($_POST['aktif'])?1:0;
        if($jenis<1) throw new RuntimeException('Jenis sholat wajib dipilih.');
        if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$mulai)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$selesai)||$mulai>=$selesai) throw new RuntimeException('Jam mulai dan selesai harus valid dalam format 24 jam WIB.');
        $st=$pdo->prepare('SELECT id,nama,aktif FROM jenis_sholat WHERE id=? LIMIT 1');$st->execute([$jenis]);$jt=$st->fetch();
        if(!$jt) throw new RuntimeException('Jenis sholat tidak ditemukan.');
        if(!(int)$jt['aktif'] && $aktif) throw new RuntimeException('Jenis sholat sedang tidak aktif. Aktifkan jenis sholat terlebih dahulu.');
        if($nama==='') $nama='Sholat '.$jt['nama'];

        // Satu jenis sholat hanya mempunyai satu konfigurasi per semester.
        $dup=$pdo->prepare('SELECT id FROM jadwal_sholat WHERE tahun_pelajaran_id=? AND semester_id=? AND jenis_sholat_id=? AND id<>? LIMIT 1');
        $dup->execute([$tp['id'],$semesterId,$jenis,$id]);
        if($dup->fetchColumn()) throw new RuntimeException('Jenis sholat tersebut sudah memiliki jadwal pada semester ini. Edit jadwal yang sudah ada, jangan membuat duplikat.');

        if($id){
            $st=$pdo->prepare('SELECT id FROM jadwal_sholat WHERE id=? AND tahun_pelajaran_id=? AND semester_id=? LIMIT 1');$st->execute([$id,$tp['id'],$semesterId]);
            if(!$st->fetchColumn()) throw new RuntimeException('Jadwal yang akan diedit tidak ditemukan.');
            $st=$pdo->prepare('UPDATE jadwal_sholat SET jenis_sholat_id=?,jam_mulai=?,jam_selesai=?,nama_sesi=?,aktif=? WHERE id=? AND tahun_pelajaran_id=? AND semester_id=?');
            $st->execute([$jenis,$mulai,$selesai,$nama,$aktif,$id,$tp['id'],$semesterId]);
            $aid=$id;$act='UPDATE_JADWAL_SHOLAT';
        }else{
            $st=$pdo->prepare('INSERT INTO jadwal_sholat (tahun_pelajaran_id,semester_id,jenis_sholat_id,jam_mulai,jam_selesai,nama_sesi,aktif) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$tp['id'],$semesterId,$jenis,$mulai,$selesai,$nama,$aktif]);
            $aid=(int)$pdo->lastInsertId();$act='CREATE_JADWAL_SHOLAT';
        }
        pengaturan_absensi_audit($pdo,$act,$aid,'Menyimpan jadwal sholat semester '.$semesterId.': '.$nama);
        redirect('pengaturan_absensi/jadwal_sholat/index.php?semester_id='.$semesterId.'&saved='.rawurlencode('Jadwal sholat berhasil disimpan.'));
    }catch(PDOException $e){
        $error=$e->getCode()==='23000'?'Jenis sholat tersebut sudah memiliki jadwal pada semester ini.':'Terjadi kesalahan database: '.$e->getMessage();
    }catch(Throwable $e){$error=$e->getMessage();}
}
if(isset($_GET['saved']))$saved=(string)$_GET['saved'];

$types=$pdo->query("SELECT id,nama,aktif FROM jenis_sholat ORDER BY urutan,id")->fetchAll();
$st=$pdo->prepare("SELECT j.*,js.nama jenis_nama, (SELECT COUNT(*) FROM sesi_absensi s WHERE s.tahun_pelajaran_id=j.tahun_pelajaran_id AND s.semester_id=j.semester_id AND s.jenis_sholat_id=j.jenis_sholat_id) AS session_count FROM jadwal_sholat j JOIN jenis_sholat js ON js.id=j.jenis_sholat_id WHERE j.tahun_pelajaran_id=? AND j.semester_id=? ORDER BY j.jam_mulai,j.id");$st->execute([$tp['id'],$semesterId]);$rows=$st->fetchAll();
if($editId>0){$est=$pdo->prepare("SELECT * FROM jadwal_sholat WHERE id=? AND tahun_pelajaran_id=? AND semester_id=? LIMIT 1");$est->execute([$editId,$tp['id'],$semesterId]);$editRow=$est->fetch()?:null;if(!$editRow)$editId=0;}
$page_title='Jadwal Sholat';require_once __DIR__.'/../../layouts/header.php';require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pengaturan Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content sholat-settings-module">
<div class="page-header"><div><h1>Jadwal Sholat</h1><p>Atur sekali per jenis sholat untuk semester yang dipilih.</p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/index.php"><i class="bi bi-list-check"></i> Jenis Sholat</a></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($saved):?><div class="alert alert-success"><?=e($saved)?></div><?php endif;?>
<div class="form-card"><form method="get"><div class="form-grid"><label>Semester *<select class="form-control" name="semester_id" onchange="this.form.submit()" required><?php foreach($semesterRows as $sr):?><option value="<?=$sr['id']?>" <?=$semesterId==(int)$sr['id']?'selected':''?>><?=e($sr['nama'])?><?=((int)$sr['aktif']===1?' · Aktif':'')?> · <?=e((string)$sr['tanggal_mulai'])?>–<?=e((string)$sr['tanggal_selesai'])?></option><?php endforeach;?></select></label><div class="info-box" style="margin:0"><strong>Semester terpilih:</strong> <?=e((string)$selectedSemester['nama'])?><br><small>Jadwal semester lain tidak ikut berubah.</small></div></div></form></div>
<div class="info-box"><strong>Aturan:</strong><p>Satu jenis sholat hanya boleh memiliki satu jadwal dalam satu semester. Jika jadwal sudah memiliki histori sesi, tombol Hapus berubah menjadi Nonaktifkan agar histori tetap aman.</p></div><div class="info-box"><strong>Otomatis:</strong><p>Jika jadwal aktif dan hari tersebut merupakan hari efektif sekolah, sesi akan tersedia otomatis pada rentang jam yang diatur. Hari LIBUR pada Kalender Kehadiran tidak membuat sesi.</p></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Jadwal Sholat · <?=e((string)$selectedSemester['nama'])?></h2><p>Tahun Pelajaran <?=e($tp['nama'])?></p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>Jenis Sholat</th><th>Jam</th><th>Nama Kegiatan</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada jadwal sholat untuk semester ini.</td></tr><?php else:foreach($rows as $i=>$r):?><tr><td><?=($i+1)?></td><td><strong><?=e($r['jenis_nama'])?></strong></td><td><?=e(substr($r['jam_mulai'],0,5))?> – <?=e(substr($r['jam_selesai'],0,5))?></td><td><?=e($r['nama_sesi'])?></td><td><span class="status-badge status-<?=((int)$r['aktif']?'aktif':'nonaktif')?>"><i class="bi bi-<?=((int)$r['aktif']?'check-circle':'pause-circle')?>"></i> <?=((int)$r['aktif']?'Aktif':'Tidak Aktif')?></span></td><td><div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><a class="btn btn-secondary btn-sm btn-icon-sm" title="Edit jadwal" aria-label="Edit jadwal" href="?semester_id=<?=$semesterId?>&edit=<?=((int)$r['id'])?>#editor"><i class="bi bi-pencil-square" aria-hidden="true"></i></a><form method="post" style="display:inline" onsubmit="return confirm('<?=((int)$r['session_count']>0?'Jadwal ini sudah memiliki histori sesi. Jadwal akan dinonaktifkan, bukan dihapus. Lanjutkan?':'Hapus jadwal sholat ini?')?>')"><?=csrf_field()?><input type="hidden" name="action" value="<?=((int)$r['session_count']>0?'deactivate':'delete')?>"><input type="hidden" name="id" value="<?=((int)$r['id'])?>"><input type="hidden" name="semester_id" value="<?=$semesterId?>"><button class="btn btn-<?=((int)$r['session_count']>0?'secondary':'danger')?> btn-sm" type="submit" title="<?=((int)$r['session_count']>0?'Nonaktifkan jadwal':'Hapus jadwal')?>"><?=((int)$r['session_count']>0?'Nonaktifkan':'Hapus')?></button></form></div></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<div id="editor" class="form-card" style="margin-top:20px"><h2 style="margin-top:0"><?= $editRow ? 'Atur Jadwal ' . e($editRow['nama_sesi']) : 'Tambah / Atur Jadwal' ?></h2><form method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?= $editRow ? (int)$editRow['id'] : 0 ?>"><input type="hidden" name="semester_id" value="<?=$semesterId?>"><div class="form-grid"><label>Semester<input class="form-control" value="<?=e((string)$selectedSemester['nama'])?>" readonly></label><label>Jenis Sholat *<select class="form-control" name="jenis_sholat_id" required><option value="">- Pilih -</option><?php foreach($types as $r):?><option value="<?=$r['id']?>" <?=($editRow && (int)$editRow['jenis_sholat_id']===(int)$r['id'])?'selected':''?>><?=e($r['nama'])?></option><?php endforeach;?></select></label><label>Jam Mulai * <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_mulai" value="<?=e($editRow ? substr((string)$editRow['jam_mulai'],0,5) : '')?>" required></label><label>Jam Selesai * <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_selesai" value="<?=e($editRow ? substr((string)$editRow['jam_selesai'],0,5) : '')?>" required></label><label>Nama Kegiatan *<input class="form-control" name="nama_sesi" value="<?=e($editRow ? (string)$editRow['nama_sesi'] : '')?>" placeholder="Contoh: Sholat Dzuhur" required></label><label class="check-row"><input type="checkbox" name="aktif" <?=(!$editRow || (int)$editRow['aktif']===1)?'checked':''?>> Jadwal aktif</label></div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan Jadwal</button><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/jadwal_sholat/index.php?semester_id=<?=$semesterId?>#editor"><i class="bi bi-eraser"></i> Kosongkan</a></div></form></div>
<script src="<?=BASE_URL?>assets/js/time24.js"></script></section></main><?php require_once __DIR__.'/../../layouts/footer.php';?>
