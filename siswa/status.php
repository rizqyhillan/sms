<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_login();
if (user_role() !== 'admin') { http_response_code(403); exit('Akses hanya untuk administrator.'); }

$id=max(0,(int)($_GET['id']??$_POST['siswa_id']??0));
if($id<=0){
    header('Location: '.BASE_URL.'siswa/index.php');
    exit;
}
$siswa=siswa_find($pdo,$id);
if(!$siswa){http_response_code(404);exit('Siswa tidak ditemukan.');}
$errors=[];$success='';
$activeYear=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetch();
$years=$pdo->query('SELECT id,nama FROM tahun_pelajaran ORDER BY id DESC')->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
 require_valid_csrf();
 $status=(string)($_POST['status']??'');
 $tanggal=(string)($_POST['tanggal_status']??date('Y-m-d'));
 $tahun=(int)($_POST['tahun_pelajaran_id']??($activeYear['id']??0));
 $tujuan=trim((string)($_POST['tujuan']??''));
 $ket=trim((string)($_POST['keterangan']??''));
 if(!in_array($status,['lulus','pindah','keluar'],true))$errors[]='Status tidak valid.';
 if($tanggal==='')$errors[]='Tanggal status wajib diisi.';
 if($status==='pindah' && $tujuan==='')$errors[]='Sekolah/fasilitas tujuan wajib diisi untuk siswa pindah.';
 if($siswa['status']!=='aktif')$errors[]='Siswa sudah berstatus '.ucfirst((string)$siswa['status']).' dan tidak dapat diberi status akhir lagi.';
 $yearIds=array_map('intval',array_column($years,'id'));
 if($tahun && !in_array($tahun,$yearIds,true))$errors[]='Tahun pelajaran tidak valid.';
 if(!$errors){
  $pdo->beginTransaction();
  try{
   $lock=$pdo->prepare('SELECT id,status FROM siswa WHERE id=? FOR UPDATE');$lock->execute([$id]);$locked=$lock->fetch();
   if(!$locked || $locked['status']!=='aktif')throw new RuntimeException('Status siswa berubah. Silakan muat ulang halaman.');
   $ins=$pdo->prepare('INSERT INTO siswa_status_riwayat (siswa_id,tahun_pelajaran_id,status,tanggal_status,tujuan,keterangan,created_by) VALUES (?,?,?,?,?,?,?)');
   $ins->execute([$id,$tahun?:null,$status,$tanggal,$tujuan!==''?$tujuan:null,$ket!==''?$ket:null,user_id()]);
   $rid=(int)$pdo->lastInsertId();
   $up=$pdo->prepare('UPDATE siswa SET status=? WHERE id=?');$up->execute([$status,$id]);
   if($tahun){
    $sk=$pdo->prepare("UPDATE siswa_kelas SET status=? WHERE siswa_id=? AND tahun_pelajaran_id=? AND status='aktif'");
    $sk->execute([$status,$id,$tahun]);
   }
   $pdo->commit();
   $stAudit=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
   $stAudit->execute([user_id(),'STATUS_'.$status,'siswa',$id,'Status siswa diubah menjadi '.strtoupper($status).' (#riwayat '.$rid.')',$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
   $success='Status siswa berhasil dicatat sebagai '.strtoupper($status).'.';
   $siswa=siswa_find($pdo,$id);
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
}

$st=$pdo->prepare('SELECT r.*,tp.nama tahun_nama,u.nama nama_petugas FROM siswa_status_riwayat r LEFT JOIN tahun_pelajaran tp ON tp.id=r.tahun_pelajaran_id LEFT JOIN users u ON u.id=r.created_by WHERE r.siswa_id=? ORDER BY r.id DESC');$st->execute([$id]);$history=$st->fetchAll();
$page_title='Status Siswa';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Status Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-person-check"></i> Status Siswa</h1><p><?=e($siswa['nama'])?> · NIS <?=e($siswa['nis'])?> · Status sekarang <strong><?=e(strtoupper((string)$siswa['status']))?></strong></p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/detail.php?id=<?=$id?>"><i class="bi bi-arrow-left"></i> Detail</a></div></div>
<?php foreach($errors as $err):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<?php if($siswa['status']==='aktif'):?>
<div class="form-card"><div class="section-title"><div><h2>Catat Lulus / Pindah / Keluar</h2><p>Data siswa tidak dihapus. Riwayat kelas dan transaksi lama tetap dipertahankan.</p></div></div>
<form method="post" onsubmit="return confirm('Simpan status akhir siswa? Setelah dicatat, siswa tidak dapat diproses kenaikan kelas lagi.')"><?=csrf_field()?><input type="hidden" name="siswa_id" value="<?=$id?>"><div class="form-grid">
<label>Status Akhir *<select class="form-control" name="status" id="statusSiswa" required><option value="">- Pilih -</option><option value="lulus">Lulus</option><option value="pindah">Pindah</option><option value="keluar">Keluar</option></select></label>
<label>Tanggal *<input class="form-control" type="date" name="tanggal_status" value="<?=e(date('Y-m-d'))?>" required></label>
<label>Tahun Pelajaran<select class="form-control" name="tahun_pelajaran_id"><option value="">- Tidak ditentukan -</option><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=($activeYear && (int)$activeYear['id']===(int)$y['id'])?'selected':''?>><?=e($y['nama'])?></option><?php endforeach;?></select></label>
<label id="tujuanWrap">Sekolah/Tujuan<span id="tujuanRequired">*</span><input class="form-control" name="tujuan" id="tujuan" placeholder="Wajib untuk pindah"></label>
<label style="grid-column:1/-1">Keterangan<textarea class="form-control" name="keterangan" rows="3" placeholder="Catatan tambahan..."></textarea></label>
</div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Status</button></div></form></div>
<?php else:?><div class="alert alert-info"><i class="bi bi-info-circle"></i> Siswa sudah berstatus <strong><?=e(strtoupper((string)$siswa['status']))?></strong>. Riwayat status bersifat arsip dan tidak dihapus.</div><?php endif;?>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Riwayat Status</h2><p>Riwayat ini menjadi bukti perubahan status siswa.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Status</th><th>Tahun</th><th>Tujuan</th><th>Keterangan</th><th>Petugas</th></tr></thead><tbody><?php if(!$history):?><tr><td colspan="6" class="empty-state">Belum ada riwayat status.</td></tr><?php else:foreach($history as $r):?><tr><td><?=e($r['tanggal_status'])?></td><td><span class="status-badge status-<?=e($r['status'])?>"><?=e(strtoupper($r['status']))?></span></td><td><?=e((string)($r['tahun_nama']??'-'))?></td><td><?=e((string)($r['tujuan']??'-'))?></td><td><?=e((string)($r['keterangan']??'-'))?></td><td><?=e((string)($r['nama_petugas']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main><script>const s=document.getElementById('statusSiswa'),t=document.getElementById('tujuan');function sync(){if(!s||!t)return;t.required=s.value==='pindah';document.getElementById('tujuanRequired').style.display=s.value==='pindah'?'inline':'none';}s?.addEventListener('change',sync);sync();</script><?php require_once __DIR__.'/../layouts/footer.php';?>
