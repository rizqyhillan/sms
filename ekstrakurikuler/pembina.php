<?php
declare(strict_types=1);
require_once __DIR__.'/_helper.php';
$extraTaskCodes = task_registry_capability_codes('ekstrakurikuler');
$extraTaskPh = implode(',', array_fill(0, count($extraTaskCodes), '?'));
$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0); ekstra_require($extraId);
$extra=ekstra_detail($pdo,$extraId); if(!$extra) deny_access();
$tp=active_tapel_id($pdo); $sem=active_semester_id($pdo,$tp); if(!$tp) exit('Tahun Pelajaran aktif belum tersedia.');
$month=(string)($_GET['month']??$_POST['month']??date('Y-m')); if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$from=$month.'-01'; $to=date('Y-m-t',strtotime($from));
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_valid_csrf(); $action=(string)($_POST['action']??'');
 if($action==='save'){
  $guru=(int)($_POST['guru_id']??0);$sesiId=(int)($_POST['sesi_id']??0);$status=(string)($_POST['status']??'H');$waktu=trim((string)($_POST['waktu']??''));$ket=trim((string)($_POST['keterangan']??''));
  $valid=['H','I','S','A'];
  $ss=$pdo->prepare("SELECT id,tanggal FROM ekstra_sesi WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND tanggal BETWEEN ? AND ? AND status<>'dibatalkan' LIMIT 1");
  $ss->execute([$sesiId,$extraId,$tp,$from,$to]);$sessionRow=$ss->fetch();
  if(!$guru||!$sesiId||!$sessionRow||!in_array($status,$valid,true))$error='Data absensi pembina tidak valid.';
  else{ $tanggal=(string)$sessionRow['tanggal'];
   $st=$pdo->prepare("SELECT 1 FROM tugas_guru tg JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.target_tipe='ekstrakurikuler' AND tg.target_id=? AND jt.kode IN ($extraTaskPh) AND tg.aktif=1 LIMIT 1");
   $st->execute(array_merge([$guru,$tp,$extraId], $extraTaskCodes));
   if(!$st->fetchColumn())$error='Guru bukan pembina/koordinator aktif untuk ekstra ini.';
   else{
    $q=$pdo->prepare("INSERT INTO ekstra_pembina_absensi(ekstrakurikuler_id,tahun_pelajaran_id,guru_id,sesi_id,tanggal,status,waktu,keterangan,dicatat_oleh) VALUES(?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE tanggal=VALUES(tanggal),status=VALUES(status),waktu=VALUES(waktu),keterangan=VALUES(keterangan),dicatat_oleh=VALUES(dicatat_oleh)");
    $q->execute([$extraId,$tp,$guru,$sesiId,$tanggal,$status,$waktu!==''?$waktu:null,$ket!==''?$ket:null,user_id()]);
    $success='Absensi pembina disimpan.';
   }
  }
 }
}
$error=$error??'';$success=$success??'';
$st=$pdo->prepare("SELECT DISTINCT g.id,g.nama,g.nip,jt.nama jenis_tugas
 FROM tugas_guru tg JOIN guru g ON g.id=tg.guru_id JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
 WHERE tg.tahun_pelajaran_id=? AND tg.target_tipe='ekstrakurikuler' AND tg.target_id=? AND tg.aktif=1
 AND jt.kode IN ($extraTaskPh) AND g.status='aktif' ORDER BY g.nama");
$st->execute(array_merge([$tp,$extraId], $extraTaskCodes));$teachers=$st->fetchAll();
$st=$pdo->prepare("SELECT epa.*,g.nama,g.nip FROM ekstra_pembina_absensi epa JOIN guru g ON g.id=epa.guru_id WHERE epa.ekstrakurikuler_id=? AND epa.tahun_pelajaran_id=? AND epa.tanggal BETWEEN ? AND ? ORDER BY epa.tanggal,g.nama");
$st->execute([$extraId,$tp,$from,$to]);$logs=$st->fetchAll();
$map=[];foreach($logs as $x)$map[(int)$x['guru_id'].'|'.(int)($x['sesi_id']??0)]=$x;
$ss=$pdo->prepare("SELECT id,tanggal,jam_mulai,jam_selesai,status FROM ekstra_sesi WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND tanggal BETWEEN ? AND ? AND status<>'dibatalkan' ORDER BY tanggal,id");$ss->execute([$extraId,$tp,$from,$to]);$sessions=$ss->fetchAll();
$q=$pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');$q->execute([$tp]);$tpName=(string)($q->fetchColumn()?:'');
$page_title='Absensi Pembina/Pelatih';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main extra-page"><header class="topbar"><div class="topbar-title">Absensi Pembina/Pelatih</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><?=e($extra['nama'])?> · Absensi Pembina/Pelatih</h1><p>Rekap bulanan · Tapel <?=e($tpName)?></p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/saya.php">← Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="form-card"><form method="get" class="form-actions"><input type="hidden" name="extra_id" value="<?=$extraId?>"><label>Bulan<input class="form-control" type="month" name="month" value="<?=e($month)?>"></label><button class="btn btn-primary">Tampilkan</button></form></div>
<div class="form-card"><h2><i class="bi bi-person-check"></i> Catat Kehadiran</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="extra_id" value="<?=$extraId?>"><div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<label>Pembina/Pelatih<select name="guru_id" class="form-control" required><option value="">Pilih</option><?php foreach($teachers as $g):?><option value="<?=$g['id']?>"><?=e($g['nama'])?> · <?=e($g['jenis_tugas'])?></option><?php endforeach;?></select></label>
<label>Pertemuan<select name="sesi_id" class="form-control" required><option value="">Pilih pertemuan</option><?php foreach($sessions as $ss):?><option value="<?=$ss['id']?>"><?=e(date('d-m-Y',strtotime($ss['tanggal'])))?> · <?=e(substr((string)$ss['jam_mulai'],0,5))?><?=!empty($ss['jam_selesai'])?' - '.e(substr((string)$ss['jam_selesai'],0,5)):''?></option><?php endforeach;?></select></label>
<label>Status<select name="status" class="form-control"><option value="H">Hadir</option><option value="I">Izin</option><option value="S">Sakit</option><option value="A">Alpa</option></select></label>
<label>Jam<input type="time" class="form-control" name="waktu"></label>
<label style="grid-column:1/-1">Keterangan<input class="form-control" name="keterangan"></label></div><button class="btn btn-primary">Simpan Absensi</button></form></div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Rekap Bulanan</h2><p>Ringkasan pertemuan yang benar-benar dilaksanakan · dokumen tanda tangan dibuat per pertemuan · Tapel <?=e($tpName)?></p></div><button type="button" class="btn btn-secondary" onclick="window.print()">🖨 Cetak</button></div>
<?php
$sessionIds=array_map(fn($x)=>(int)$x['id'],$sessions);$logMap=[];foreach($logs as $l)$logMap[(int)$l['guru_id'].'|'.(int)($l['sesi_id']??0)]=$l['status'];
?>
<div class="table-responsive"><table class="data-table meeting-summary"><thead><tr><th>No</th><th>Tanggal</th><th>Jam</th><th>Pertemuan</th><th>H</th><th>I</th><th>S</th><th>A</th><th class="no-print">Dokumen</th></tr></thead><tbody><?php if(!$sessions):?><tr><td colspan="9" class="empty-state">Belum ada pertemuan yang tercatat pada bulan ini.</td></tr><?php else:foreach($sessions as $i=>$ss):$counts=['H'=>0,'I'=>0,'S'=>0,'A'=>0];foreach($teachers as $g){$v=$logMap[(int)$g['id'].'|'.(int)$ss['id']]['status']??'';if(isset($counts[$v]))$counts[$v]++;}?><tr><td><?=$i+1?></td><td><?=e(date('d-m-Y',strtotime($ss['tanggal'])))?></td><td><?=e(substr((string)$ss['jam_mulai'],0,5))?><?=!empty($ss['jam_selesai'])?' - '.e(substr((string)$ss['jam_selesai'],0,5)):''?></td><td><?=e(ucfirst((string)$ss['status']))?></td><td><?=$counts['H']?></td><td><?=$counts['I']?></td><td><?=$counts['S']?></td><td><?=$counts['A']?></td><td class="no-print"><a class="btn btn-sm btn-primary" href="<?=BASE_URL?>ekstrakurikuler/pembina_rekap.php?extra_id=<?=$extraId?>&sesi_id=<?=$ss['id']?>">Lihat / Cetak</a></td></tr><?php endforeach;endif;?></tbody></table></div>
<div class="print-signatures"><div><p>Mengetahui,</p><p>Kepala Sekolah</p><div class="signature-space"></div><strong>____________________________</strong></div><div><p>Tempat, tanggal __________________</p><p>Pembina/Pelatih</p><div class="signature-space"></div><strong>____________________________</strong></div></div>
</div>
</section></main><style>@media print{.sidebar,.topbar,.page-header .btn,.form-card,.section-title button{display:none!important}.main{margin:0!important}.content{padding:0!important}.dashboard-table-card{box-shadow:none!important;border:0!important}.monthly-attendance{font-size:9px;width:100%;border-collapse:collapse}.monthly-attendance th,.monthly-attendance td{padding:3px!important;border:1px solid #555;text-align:center}.monthly-attendance th:nth-child(2),.monthly-attendance td:nth-child(2){text-align:left;white-space:nowrap}.status-cell{font-weight:600}.ttd-cell{min-width:65px;height:45px}.print-signatures{display:flex!important;justify-content:space-between;margin-top:35px;page-break-inside:avoid}.print-signatures>div{width:40%;text-align:center}.signature-space{height:55px}} .print-signatures{display:none}</style><?php require_once __DIR__.'/../layouts/footer.php';?>
