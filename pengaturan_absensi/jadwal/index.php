<?php
declare(strict_types=1);
require_once __DIR__.'/../_helper.php';
require_once __DIR__.'/../../absensi/_helper.php';
pengaturan_kehadiran_admin_only();

$tp=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetch();
if(!$tp){exit('Belum ada tahun pelajaran aktif.');}

$semesters=$pdo->prepare('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM semester WHERE tahun_pelajaran_id=? ORDER BY id');
$semesters->execute([(int)$tp['id']]);
$semesterRows=$semesters->fetchAll();
$semesterId=(int)($_GET['semester_id']??$_POST['semester_id']??0);
if(!$semesterId){
    foreach($semesterRows as $sr){ if((int)$sr['aktif']===1){$semesterId=(int)$sr['id'];break;} }
}
$validSemester=false;
foreach($semesterRows as $sr){if((int)$sr['id']===$semesterId){$validSemester=true;break;}}
if(!$validSemester && $semesterRows){$semesterId=(int)$semesterRows[0]['id'];}
if(!$semesterRows){exit('Belum ada semester pada tahun pelajaran aktif.');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $action=(string)($_POST['action']??'save');
    $id=(int)($_POST['id']??0);
    if($action==='save'){

    $semesterId=(int)($_POST['semester_id']??$semesterId);
    $aktif=isset($_POST['aktif'])?1:0;
    $mulai=(string)($_POST['jam_mulai']??'06:30');
    $selesai=(string)($_POST['jam_selesai']??'09:00');
    $batas=trim((string)($_POST['batas_terlambat']??''));
    $nama=trim((string)($_POST['nama_sesi']??'Absensi Kehadiran'));
    $pulangMulai=trim((string)($_POST['jam_mulai_pulang']??'13:00'));
    $pulangSelesai=trim((string)($_POST['jam_selesai_pulang']??'16:30'));
    $finalOtomatis=trim((string)($_POST['batas_finalisasi_kehadiran']??'17:00'));
    $err='';
    $validTime24=static fn(string $v): bool => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$v)===1;
    if(!$validTime24($mulai) || !$validTime24($selesai) || ($batas!=='' && !$validTime24($batas)) || !$validTime24($pulangMulai) || !$validTime24($pulangSelesai) || !$validTime24($finalOtomatis)){$err='Semua jam harus menggunakan format 24 jam WIB (HH:mm).';}
    elseif($mulai >= $selesai){$err='Jam selesai datang harus setelah jam mulai datang.';}
    elseif($batas!=='' && ($batas<$mulai || $batas>$selesai)){$err='Batas terlambat harus berada di antara jam mulai dan selesai datang.';}
    elseif($pulangMulai >= $pulangSelesai){$err='Batas akhir pulang harus setelah jam mulai pulang.';}
    elseif($finalOtomatis < $pulangSelesai){$err='Batas finalisasi tidak boleh lebih awal daripada batas akhir jam pulang.';}
    $semCheck=$pdo->prepare('SELECT id FROM semester WHERE id=? AND tahun_pelajaran_id=? LIMIT 1');
    $semCheck->execute([$semesterId,$tp['id']]);
    if(!$semCheck->fetchColumn()){$err='Semester tidak sesuai dengan tahun pelajaran aktif.';}
    elseif($err==='') {
        if($nama===''){$err='Nama sesi wajib diisi.';}
    }
    if($err===''){
        try{
            $st=$pdo->prepare('UPDATE jadwal_kehadiran SET semester_id=?,aktif=?,jam_mulai=?,jam_selesai=?,batas_terlambat=?,nama_sesi=? WHERE id=? AND tahun_pelajaran_id=?');
            $pdo->beginTransaction();
            $st->execute([$semesterId,$aktif,$mulai,$selesai,$batas?:null,$nama,$id,$tp['id']]);
            $saveSetting=$pdo->prepare('INSERT INTO pengaturan (kunci,nilai,keterangan) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai), keterangan=VALUES(keterangan)');
            foreach ([['jam_mulai_pulang',$pulangMulai,'Jam mulai Absen Pulang (WIB).'],['jam_selesai_pulang',$pulangSelesai,'Batas akhir Absen Pulang (WIB).'],['batas_finalisasi_kehadiran',$finalOtomatis,'Batas finalisasi otomatis kehadiran harian (WIB).']] as $cfg) $saveSetting->execute($cfg);
            $pdo->commit();
            pengaturan_kehadiran_audit($pdo,'UPDATE_JADWAL_KEHADIRAN',$id,'Mengubah jadwal semester '.$semesterId.' hari '.$id.'; jam pulang '.$pulangMulai.'-'.$pulangSelesai.'; finalisasi '.$finalOtomatis);
            $ok='Jadwal datang, pulang, dan batas finalisasi berhasil disimpan.';
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e instanceof PDOException && $e->getCode()==='23000'?'Hari tersebut sudah memiliki jadwal pada semester ini.':'Jadwal gagal disimpan. Periksa konfigurasi database.';}
    }
    } else {
        $err='Aksi tidak dikenali.';
    }
}

$pulangMulai = absensi_setting_value($pdo, 'jam_mulai_pulang', '13:00') ?? '13:00';
$pulangSelesai = absensi_setting_value($pdo, 'jam_selesai_pulang', '16:30') ?? '16:30';
$finalOtomatis = absensi_batas_finalisasi($pdo);

$st=$pdo->prepare('SELECT * FROM jadwal_kehadiran WHERE tahun_pelajaran_id=? AND semester_id=? ORDER BY hari');
$st->execute([$tp['id'],$semesterId]);
$rows=$st->fetchAll();
$selectedSemester=null;
foreach($semesterRows as $sr){if((int)$sr['id']===$semesterId){$selectedSemester=$sr;break;}}
$page_title='Jadwal Kehadiran'; require_once __DIR__.'/../../layouts/header.php'; require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pengaturan Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Jadwal Kehadiran</h1><p>Jadwal disimpan per semester · Tahun <?=e($tp['nama'])?>.</p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan_absensi/kalender/index.php"><i class="bi bi-calendar-event"></i> Kalender</a><a class="btn btn-secondary" href="<?=BASE_URL?>absensi/index.php"><i class="bi bi-calendar-check"></i> Absensi Kehadiran</a></div></div>
<?php if(!empty($err)):?><div class="alert alert-danger"><?=e($err)?></div><?php endif;?><?php if(!empty($ok)):?><div class="alert alert-success"><?=e($ok)?></div><?php endif;?>
<div class="form-card"><form method="get"><div class="form-grid"><label>Semester *<select class="form-control" name="semester_id" onchange="this.form.submit()" required><?php foreach($semesterRows as $sr):?><option value="<?=$sr['id']?>" <?=$semesterId==(int)$sr['id']?'selected':''?>><?=e($sr['nama'])?><?=((int)$sr['aktif']===1?' · Aktif':'')?> · <?=e((string)$sr['tanggal_mulai'])?>–<?=e((string)$sr['tanggal_selesai'])?></option><?php endforeach;?></select></label><div class="info-box" style="margin:0"><strong>Semester terpilih:</strong> <?=e((string)$selectedSemester['nama'])?><br><small>Perubahan di sini hanya berlaku untuk semester tersebut.</small></div></div></form></div>
<div class="info-box"><strong>Default:</strong><p>Senin–Jumat aktif, Sabtu–Minggu nonaktif. Hari libur dan masuk khusus diatur melalui Kalender Kehadiran. Jadwal tidak lagi berbagi konfigurasi antar-semester.</p></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Jadwal Kehadiran — Jam Datang · <?=e((string)$selectedSemester['nama'])?></h2><p>Tahun <?=e($tp['nama'])?></p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Hari</th><th>Status</th><th>Jam Datang</th><th>Jam Pulang</th><th>Batas Terlambat</th><th>Batas Finalisasi</th><th>Nama Sesi</th><th>Aksi</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=e(nama_hari_id((int)$r['hari']))?></strong></td><td><span class="status-badge status-<?=((int)$r['aktif']?'aktif':'nonaktif')?>"><?=((int)$r['aktif']?'Hari Sekolah':'Libur Rutin')?></span></td><td><?=e(substr($r['jam_mulai'],0,5))?>–<?=e(substr($r['jam_selesai'],0,5))?></td><td><?=e($pulangMulai)?>–<?=e($pulangSelesai)?></td><td><?=e($r['batas_terlambat']?substr($r['batas_terlambat'],0,5):'-')?></td><td><?=e($finalOtomatis)?></td><td><?=e($r['nama_sesi'])?></td><td><button class="btn btn-secondary btn-sm btn-icon-sm" type="button" onclick="editJadwal(<?=htmlspecialchars(json_encode($r),ENT_QUOTES,'UTF-8')?>)" title="Edit jadwal" aria-label="Edit jadwal"><i class="bi bi-pencil-square" aria-hidden="true"></i></button></td></tr><?php endforeach;?></tbody></table></div></div>
<div id="editor" class="form-card" style="display:none;margin-top:20px"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="j_id"><input type="hidden" name="semester_id" id="j_semester" value="<?=e((string)$semesterId)?>"><div class="form-grid"><label>Semester<input class="form-control" id="j_semester_name" value="<?=e((string)$selectedSemester['nama'])?>" readonly></label><label>Hari<input class="form-control" id="j_hari" readonly></label><label class="check-row" style="margin-top:28px"><input type="checkbox" name="aktif" id="j_aktif"> Hari ini merupakan hari sekolah</label><label>Jam Mulai <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_mulai" id="j_mulai" required></label><label>Jam Selesai <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_selesai" id="j_selesai" required></label><label>Jam Mulai Pulang <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_mulai_pulang" value="<?=e($pulangMulai)?>" required></label><label>Batas Akhir Pulang <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="jam_selesai_pulang" value="<?=e($pulangSelesai)?>" required></label><label>Batas Finalisasi Otomatis <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="batas_finalisasi_kehadiran" value="<?=e($finalOtomatis)?>" required></label><label>Batas Terlambat <small>(WIB · 24 jam)</small><input class="form-control time-24" type="text" inputmode="numeric" maxlength="5" name="batas_terlambat" id="j_batas"></label><label>Nama Sesi<input class="form-control" name="nama_sesi" id="j_nama" required></label></div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan</button><button class="btn btn-secondary" type="button" onclick="document.getElementById('editor').style.display='none'"><i class="bi bi-x-circle"></i> Batal</button></div></form></div>
<script src="<?=BASE_URL?>assets/js/time24.js"></script></section></main><script>function editJadwal(r){document.getElementById('editor').style.display='block';j_id.value=r.id;j_hari.value=({1:'Senin',2:'Selasa',3:'Rabu',4:'Kamis',5:'Jumat',6:'Sabtu',7:'Minggu'}[r.hari]||'-');j_aktif.checked=Number(r.aktif)===1;j_mulai.value=r.jam_mulai.slice(0,5);j_selesai.value=r.jam_selesai.slice(0,5);j_batas.value=r.batas_terlambat?r.batas_terlambat.slice(0,5):'';j_nama.value=r.nama_sesi;document.getElementById('editor').scrollIntoView({behavior:'smooth',block:'center'});}</script><?php require_once __DIR__.'/../../layouts/footer.php';?>
