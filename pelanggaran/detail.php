<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pelanggaran_require_access();
$id=(int)($_GET['id']??0);$tapel=pelanggaran_active_tapel($pdo);$tapelId=$tapel?(int)$tapel['id']:0;$errors=[];$success='';
$st=$pdo->prepare("SELECT s.*,mk.nama kelas FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.id=?");$st->execute([$tapelId,$id]);$student=$st->fetch();if(!$student) {http_response_code(404);exit('Siswa tidak ditemukan.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_valid_csrf();$action=(string)($_POST['action']??'');
 if($action==='status'){
  if(!pelanggaran_can_adjust()) deny_access();
  $vid=max(0,(int)($_POST['pelanggaran_id']??0));
  $newStatus=(string)($_POST['status']??'');
  if(!in_array($newStatus,['ditindaklanjuti','selesai'],true)) $errors[]='Status tujuan tidak valid.';
  if(!$tapelId) $errors[]='Tidak ada Tahun Pelajaran aktif.';
  if(!$errors){
   $q=$pdo->prepare('SELECT id,siswa_id,status FROM pelanggaran_siswa WHERE id=? AND siswa_id=? AND tahun_pelajaran_id=? LIMIT 1');
   $q->execute([$vid,$id,$tapelId]);$v=$q->fetch();
   if(!$v) $errors[]='Data pelanggaran tidak ditemukan pada Tahun Pelajaran aktif.';
   else {
    $allowed=($v['status']==='tercatat' && $newStatus==='ditindaklanjuti') || ($v['status']==='ditindaklanjuti' && $newStatus==='selesai');
    if(!$allowed) $errors[]='Perubahan status tidak mengikuti alur yang diizinkan.';
    else {
     $u=$pdo->prepare('UPDATE pelanggaran_siswa SET status=? WHERE id=? AND status=?');
     $u->execute([$newStatus,$vid,$v['status']]);
     if($u->rowCount()!==1) $errors[]='Status tidak berubah. Silakan muat ulang halaman.';
     else {pelanggaran_audit($pdo,'STATUS_CHANGE',$vid,'Status pelanggaran #'.$vid.' berubah dari '.$v['status'].' menjadi '.$newStatus);$success='Status pelanggaran berhasil diperbarui.';}
    }
   }
  }
 }
 if($action==='adjust'){
  if(!pelanggaran_can_reduce_points()) deny_access();
  $delta=(int)($_POST['delta_poin']??0);$tanggal=(string)($_POST['tanggal']??date('Y-m-d'));$alasan=trim((string)($_POST['alasan']??''));$tindakan=trim((string)($_POST['tindakan']??''));$pembinaanId=max(0,(int)($_POST['pembinaan_id']??0));
  if(!$tapelId)$errors[]='Tidak ada Tahun Pelajaran aktif.';
  if($delta>=0)$errors[]='Pengurangan poin harus bernilai negatif.';
  if($alasan==='')$errors[]='Alasan pengurangan wajib diisi.';
  if($pembinaanId<=0)$errors[]='Pengurangan poin wajib berasal dari pembinaan yang selesai dan dinyatakan positif.';
  if(!$errors){
   $q=$pdo->prepare("SELECT id,status,rekomendasi_pengurangan_poin,hasil FROM pembinaan_siswa WHERE id=? AND siswa_id=? AND tahun_pelajaran_id=? LIMIT 1");$q->execute([$pembinaanId,$id,$tapelId]);$pb=$q->fetch();
   if(!$pb)$errors[]='Data pembinaan tidak ditemukan.';
   elseif($pb['status']!=='selesai')$errors[]='Pembinaan harus berstatus Selesai sebelum pengurangan poin diberikan.';
   elseif((int)$pb['rekomendasi_pengurangan_poin']<=0)$errors[]='Pembinaan belum memiliki rekomendasi pengurangan poin.';
   elseif(trim((string)($pb['hasil']??''))==='')$errors[]='Hasil evaluasi pembinaan wajib diisi.';
   elseif(abs($delta)>(int)$pb['rekomendasi_pengurangan_poin'])$errors[]='Pengurangan tidak boleh melebihi rekomendasi pembinaan.';
   else {
    $prefix='Pembinaan #'.$pembinaanId;
    $dupe=$pdo->prepare("SELECT id FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=? AND tindakan LIKE ? LIMIT 1");$dupe->execute([$id,$tapelId,$prefix.'%']);
    if($dupe->fetchColumn())$errors[]='Pengurangan untuk pembinaan ini sudah pernah diterapkan.';
   }
  }
  if(!$errors){$guru=pelanggaran_current_guru_id($pdo);$note=$prefix.' · hasil evaluasi positif'.($tindakan!==''?' · '.$tindakan:'');$st=$pdo->prepare('INSERT INTO pelanggaran_poin_penyesuaian(siswa_id,tahun_pelajaran_id,tanggal,delta_poin,alasan,tindakan,petugas_id,created_by) VALUES(?,?,?,?,?,?,?,?)');$st->execute([$id,$tapelId,$tanggal,$delta,$alasan,$note,$guru,user_id()?:null]);$new=(int)$pdo->lastInsertId();pelanggaran_audit($pdo,'ADJUST_POINT',$new,'Pengurangan poin siswa #'.$id.' sebesar '.$delta.' dari pembinaan #'.$pembinaanId);$success='Pengurangan poin berhasil dicatat dari hasil pembinaan. Riwayat pelanggaran asli tetap tersimpan.';}
 }
}
$st=$pdo->prepare("SELECT COALESCE(SUM(poin),0) FROM pelanggaran_siswa WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$id,$tapelId]);$gross=(int)$st->fetchColumn();$st=$pdo->prepare("SELECT COALESCE(SUM(delta_poin),0) FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$id,$tapelId]);$adjust=(int)$st->fetchColumn();$total=max(0,$gross+$adjust);$statusP=pelanggaran_status_poin($total);
$st=$pdo->prepare("SELECT ps.*,jp.kode,jp.nama jenis,g.nama petugas FROM pelanggaran_siswa ps JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN guru g ON g.id=ps.petugas_id WHERE ps.siswa_id=? AND ps.tahun_pelajaran_id=? ORDER BY ps.tanggal DESC,ps.id DESC");$st->execute([$id,$tapelId]);$violations=$st->fetchAll();
$st=$pdo->prepare("SELECT pp.*,g.nama petugas,u.nama created_nama FROM pelanggaran_poin_penyesuaian pp LEFT JOIN guru g ON g.id=pp.petugas_id LEFT JOIN users u ON u.id=pp.created_by WHERE pp.siswa_id=? AND pp.tahun_pelajaran_id=? ORDER BY pp.tanggal DESC,pp.id DESC");$st->execute([$id,$tapelId]);$adjustments=$st->fetchAll();
$st=$pdo->prepare("SELECT p.id,p.tanggal,p.jenis,p.hasil,p.rekomendasi_pengurangan_poin,p.status FROM pembinaan_siswa p WHERE p.siswa_id=? AND p.tahun_pelajaran_id=? AND p.status='selesai' AND p.rekomendasi_pengurangan_poin>0 AND COALESCE(TRIM(p.hasil),'')<>'' ORDER BY p.tanggal DESC,p.id DESC");$st->execute([$id,$tapelId]);$eligiblePembinaan=$st->fetchAll();
$page_title='Riwayat Pelanggaran';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Riwayat Pelanggaran Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>.pbox{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px}.pstatus{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border-radius:999px;font-weight:850;font-size:12px}.p1{background:#fef3c7;color:#92400e}.p2{background:#ffedd5;color:#9a3412}.p3{background:#fee2e2;color:#b91c1c}.p4{background:#fce7f3;color:#9d174d}.normal{background:#dcfce7;color:#166534}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}.summary .pbox{padding:14px}.summary .num{font-size:24px;font-weight:900}.small-note{font-size:12px;color:#64748b}@media(max-width:800px){.summary{grid-template-columns:repeat(2,1fr)}}@media(max-width:500px){.summary{grid-template-columns:1fr}}</style>
<div class="page-header"><div><h1><i class="bi bi-person-lines-fill"></i> <?=e($student['nama'])?></h1><p>NIS <?=e($student['nis'])?> · <?=e((string)($student['kelas']??'Belum ada kelas'))?> · Tapel <?=e($tapel['nama']??'-')?></p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pelanggaran/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php foreach($errors as $x):?><div class="alert alert-danger"><?=e($x)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<div class="summary"><div class="pbox"><div class="small-note">TOTAL POIN PELANGGARAN</div><div class="num"><?=number_format($gross)?></div></div><div class="pbox"><div class="small-note">PENYESUAIAN</div><div class="num"><?=($adjust>0?'+':'').number_format($adjust)?></div></div><div class="pbox"><div class="small-note">POIN BERJALAN</div><div class="num"><?=number_format($total)?></div></div><div class="pbox"><div class="small-note">STATUS</div><div style="margin-top:7px"><span class="pstatus <?=$statusP['class']?>"><i class="bi <?=$statusP['icon']?>"></i> <?=$statusP['label']?></span></div></div></div>
<div class="dashboard-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);align-items:start"><div class="pbox"><div class="section-title"><div><h2>Riwayat Pelanggaran</h2><p>Riwayat tidak dihapus ketika poin diperbaiki.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Poin</th><th>Status</th><th>Petugas</th><th>Aksi</th></tr></thead><tbody><?php if(!$violations):?><tr><td colspan="6" class="empty-state">Belum ada pelanggaran pada Tapel aktif.</td></tr><?php else:foreach($violations as $v):?><tr><td><?=e($v['tanggal'])?></td><td><strong><?=e($v['jenis'])?></strong><br><span class="small-note"><?=e((string)($v['kronologi']??''))?></span></td><td><strong>+<?=number_format((int)$v['poin'])?></strong></td><td><?=e(ucfirst($v['status']))?></td><td><?=e((string)($v['petugas']??'Sistem'))?></td><td><?php if(pelanggaran_can_adjust() && $v['status']==='tercatat'):?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="status"><input type="hidden" name="pelanggaran_id" value="<?=(int)$v['id']?>"><input type="hidden" name="status" value="ditindaklanjuti"><button class="btn btn-secondary btn-sm" title="Tindak lanjuti" onclick="return confirm('Ubah status pelanggaran menjadi Ditindaklanjuti?')"><i class="bi bi-arrow-right-circle"></i></button></form><?php elseif(pelanggaran_can_adjust() && $v['status']==='ditindaklanjuti'):?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="status"><input type="hidden" name="pelanggaran_id" value="<?=(int)$v['id']?>"><input type="hidden" name="status" value="selesai"><button class="btn btn-secondary btn-sm" title="Tandai selesai" onclick="return confirm('Tandai pelanggaran ini sebagai selesai?')"><i class="bi bi-check2-circle"></i></button></form><?php else:?><span class="small-note">—</span><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<div><?php if(pelanggaran_can_reduce_points()):?><div class="form-card"><div class="section-title"><div><h2><i class="bi bi-arrow-down-circle"></i> Pengurangan Poin dari Pembinaan</h2><p>Hanya BK/Kesiswaan. Pengurangan dilakukan setelah pembinaan selesai dan hasil evaluasi positif.</p></div></div><?php if(!$eligiblePembinaan):?><div class="alert alert-info"><i class="bi bi-info-circle"></i> Belum ada pembinaan selesai yang memenuhi syarat pengurangan poin.</div><?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="adjust"><div class="form-grid" style="grid-template-columns:1fr"><label>Pembinaan yang menjadi dasar *<select class="form-control" name="pembinaan_id" required><option value="">Pilih pembinaan selesai</option><?php foreach($eligiblePembinaan as $pb):?><option value="<?=$pb['id']?>"><?=e($pb['tanggal'])?> · <?=e($pb['jenis'])?> · maksimal <?=number_format((int)$pb['rekomendasi_pengurangan_poin'])?> poin</option><?php endforeach;?></select></label><label>Tanggal<input class="form-control" type="date" name="tanggal" value="<?=date('Y-m-d')?>" required></label><label>Pengurangan Poin *<input class="form-control" type="number" name="delta_poin" min="-999" max="-1" required placeholder="Contoh: -10"><small class="small-note">Masukkan angka negatif. Tidak boleh melebihi rekomendasi pembinaan.</small></label><label>Alasan *<textarea class="form-control" name="alasan" rows="3" required placeholder="Contoh: perilaku membaik dan tidak mengulangi pelanggaran selama masa evaluasi."></textarea></label><label>Catatan hasil/tindak lanjut<textarea class="form-control" name="tindakan" rows="3" placeholder="Catatan tambahan bila diperlukan."></textarea></label></div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Terapkan Pengurangan</button></div></form><?php endif;?></div><?php endif;?><div class="pbox" style="margin-top:18px"><div class="section-title"><div><h2>Riwayat Penyesuaian</h2><p>Setiap perubahan poin dapat diaudit.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Perubahan</th><th>Alasan</th></tr></thead><tbody><?php if(!$adjustments):?><tr><td colspan="3" class="empty-state">Belum ada penyesuaian poin.</td></tr><?php else:foreach($adjustments as $a):?><tr><td><?=e($a['tanggal'])?></td><td><strong><?=((int)$a['delta_poin']>0?'+':'').number_format((int)$a['delta_poin'])?></strong></td><td><?=e($a['alasan'])?><br><span class="small-note"><?=e((string)($a['petugas']??$a['created_nama']??'Sistem'))?></span></td></tr><?php endforeach;endif;?></tbody></table></div></div></div></div>
<div class="alert alert-info" style="margin-top:18px"><i class="bi bi-bell"></i> <?=$statusP['label']==='Normal'?'Belum mencapai ambang peringatan.':e($statusP['pesan'])?> Status dihitung dari poin berjalan pada Tahun Pelajaran aktif.</div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
