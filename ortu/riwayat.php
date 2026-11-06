<?php
declare(strict_types=1);
require_once __DIR__.'/_helper.php';
ortu_require_access();
$oid=ortu_get_id($pdo);
$children=ortu_children($pdo,$oid);
$sid=(int)($_GET['siswa_id']??0);
$allowed=array_map('intval',array_column($children,'id'));
if($sid<=0 && $children)$sid=(int)$children[0]['id'];
if(!in_array($sid,$allowed,true)) $sid=0;
$child=null; foreach($children as $c){if((int)$c['id']===$sid){$child=$c;break;}}
$tpRows=$pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY id DESC")->fetchAll();
$activeTp=null; foreach($tpRows as $t){if((int)$t['aktif']===1){$activeTp=$t;break;}}
$tpId=(int)($_GET['tapel_id']??0); $validTp=array_map('intval',array_column($tpRows,'id'));
if(!in_array($tpId,$validTp,true)) $tpId=(int)($activeTp['id']??($tpRows[0]['id']??0));
$tpName='-'; foreach($tpRows as $t){if((int)$t['id']===$tpId)$tpName=(string)$t['nama'];}
$stats=['pelanggaran'=>0,'pembinaan'=>0,'konsultasi'=>0,'home_visit'=>0,'izin'=>0,'hadir'=>0,'terlambat'=>0,'izin_absen'=>0,'sakit'=>0,'alpa'=>0];
$rows=$pembinaan=$konsultasi=$homeVisit=$izin=[]; $totalPoin=0; $adjustment=0;
if($sid && $tpId){
 $st=$pdo->prepare("SELECT COALESCE(SUM(a.status='H'),0) hadir,COALESCE(SUM(a.status='T'),0) terlambat,COALESCE(SUM(a.status='I'),0) izin_absen,COALESCE(SUM(a.status='S'),0) sakit,COALESCE(SUM(a.status='A'),0) alpa FROM absensi a JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE a.siswa_id=? AND sa.tahun_pelajaran_id=? AND sa.jenis_sholat_id IS NULL");$st->execute([$sid,$tpId]);$a=$st->fetch()?:[]; foreach(['hadir','terlambat','izin_absen','sakit','alpa'] as $k)$stats[$k]=(int)($a[$k]??0);
 $st=$pdo->prepare("SELECT COUNT(*),COALESCE(SUM(poin),0) FROM pelanggaran_siswa WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$sid,$tpId]);$x=$st->fetch(PDO::FETCH_NUM);$stats['pelanggaran']=(int)($x[0]??0);$totalPoin=(int)($x[1]??0);
 $st=$pdo->prepare("SELECT COALESCE(SUM(delta_poin),0) FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=?");$st->execute([$sid,$tpId]);$adjustment=(int)$st->fetchColumn();$totalPoin+=$adjustment;
 $queries=[
  'pembinaan'=>["SELECT tanggal,jenis,status,hasil,tindak_lanjut FROM pembinaan_siswa WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50"],
  'konsultasi'=>["SELECT tanggal,jenis,topik,hasil,kesepakatan,tindak_lanjut,status FROM konsultasi_bk WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50"],
  'home_visit'=>["SELECT tanggal_rencana,tanggal_pelaksanaan,status,tujuan,hasil,tindak_lanjut FROM home_visit WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY COALESCE(tanggal_pelaksanaan,tanggal_rencana) DESC,id DESC LIMIT 50"],
  'izin'=>["SELECT tanggal,jenis,alasan,keterangan,status FROM izin_siswa WHERE siswa_id=? AND tahun_pelajaran_id=? ORDER BY tanggal DESC,id DESC LIMIT 50"]
 ];
 foreach($queries as $k=>$q){$st=$pdo->prepare($q[0]);$st->execute([$sid,$tpId]);$$k=$st->fetchAll();$stats[$k]=count($$k);}
 $st=$pdo->prepare("SELECT p.tanggal,j.nama jenis,p.poin,p.status,p.tindakan FROM pelanggaran_siswa p JOIN jenis_pelanggaran j ON j.id=p.jenis_pelanggaran_id WHERE p.siswa_id=? AND p.tahun_pelajaran_id=? ORDER BY p.tanggal DESC,p.id DESC LIMIT 50");$st->execute([$sid,$tpId]);$rows=$st->fetchAll();
}
$level=$totalPoin>=100?'P4':($totalPoin>=75?'P3':($totalPoin>=50?'P2':($totalPoin>=25?'P1':'Normal')));
function parent_table(string $title,array $headers,array $rows,callable $render):void{echo '<div class="dashboard-table-card portal-card"><h2>'.e($title).'</h2>';if(!$rows){echo '<div class="portal-empty">Belum ada data pada Tahun Pelajaran ini.</div></div>';return;}echo '<div class="table-responsive"><table class="data-table"><thead><tr>';foreach($headers as $h)echo '<th>'.e($h).'</th>';echo '</tr></thead><tbody>';foreach($rows as $r)echo '<tr>'.$render($r).'</tr>';echo '</tbody></table></div></div>';}
$page_title='Riwayat Anak'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Riwayat Anak</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-people-fill"></i> Riwayat Anak</h1><p>Data yang diperuntukkan bagi orang tua/wali · <?=e($tpName)?></p></div><form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><?php if($children):?><select class="form-control" name="siswa_id"><?php foreach($children as $c):?><option value="<?=$c['id']?>" <?=$sid===(int)$c['id']?'selected':''?>><?=e($c['nama'])?> · <?=e($c['kelas']??'-')?></option><?php endforeach;?></select><?php endif;?><select class="form-control" name="tapel_id"><?php foreach($tpRows as $t):?><option value="<?=$t['id']?>" <?=$tpId===(int)$t['id']?'selected':''?>><?=e($t['nama'])?><?=$t['aktif']?' (Aktif)':''?></option><?php endforeach;?></select><button class="btn btn-primary">Tampilkan</button></form></div>
<?php if(!$oid):?><div class="alert alert-warning">Akun orang tua belum terhubung. Silakan hubungi administrator.</div><?php elseif(!$children):?><div class="alert alert-warning">Belum ada siswa yang terhubung ke akun ini.</div><?php elseif(!$child):?><div class="alert alert-danger">Siswa yang diminta tidak terhubung ke akun orang tua ini.</div><?php else:?>
<div class="dashboard-card"><h2><?=e($child['nama'])?></h2><p>NIS <?=e($child['nis'])?><?=!empty($child['nisn'])?' · NISN '.e($child['nisn']):''?> · Kelas <?=e($child['kelas']??'-')?> · Tapel <?=e($tpName)?></p></div>
<div class="dashboard-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))"><div class="dashboard-card"><div class="card-label">PELANGGARAN</div><div class="card-number"><?=number_format($stats['pelanggaran'])?></div></div><div class="dashboard-card"><div class="card-label">STATUS POIN</div><div class="card-number"><?=e($level)?></div><div class="card-description">Total <?=number_format($totalPoin)?> poin<?php if($adjustment!==0):?> · Penyesuaian <?=($adjustment>0?'+':'').number_format($adjustment)?><?php endif;?></div></div><div class="dashboard-card"><div class="card-label">PEMBINAAN</div><div class="card-number"><?=number_format($stats['pembinaan'])?></div></div><div class="dashboard-card"><div class="card-label">KONSULTASI</div><div class="card-number"><?=number_format($stats['konsultasi'])?></div></div><div class="dashboard-card"><div class="card-label">HOME VISIT</div><div class="card-number"><?=number_format($stats['home_visit'])?></div></div></div>
<div class="dashboard-table-card portal-card"><h2>Ringkasan Kehadiran</h2><div class="portal-badges"><span>Hadir: <?=number_format($stats['hadir'])?></span><span>Terlambat: <?=number_format($stats['terlambat'])?></span><span>Izin: <?=number_format($stats['izin_absen'])?></span><span>Sakit: <?=number_format($stats['sakit'])?></span><span>Alpa: <?=number_format($stats['alpa'])?></span></div></div>
<?php
parent_table('Pelanggaran',['Tanggal','Jenis','Poin','Status','Tindakan'],$rows,fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e($r['jenis']).'</td><td>'.e($r['poin']).'</td><td>'.e($r['status']).'</td><td>'.e($r['tindakan']??'-').'</td>');
parent_table('Pembinaan',['Tanggal','Jenis','Status','Hasil','Tindak Lanjut'],$pembinaan,fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e(ucwords(str_replace('_',' ',$r['jenis']))).'</td><td>'.e($r['status']).'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
parent_table('Konsultasi',['Tanggal','Jenis','Topik','Hasil','Tindak Lanjut'],$konsultasi,fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e(ucwords(str_replace('_',' ',$r['jenis']))).'</td><td>'.e($r['topik']).'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
parent_table('Home Visit',['Tanggal','Status','Tujuan','Hasil','Tindak Lanjut'],$homeVisit,fn($r)=>'<td>'.e($r['tanggal_pelaksanaan']?:($r['tanggal_rencana']??'-')).'</td><td>'.e($r['status']).'</td><td>'.e($r['tujuan']??'-').'</td><td>'.e($r['hasil']??'-').'</td><td>'.e($r['tindak_lanjut']??'-').'</td>');
parent_table('Izin Siswa',['Tanggal','Jenis','Alasan','Keterangan','Status'],$izin,fn($r)=>'<td>'.e($r['tanggal']).'</td><td>'.e($r['jenis']).'</td><td>'.e($r['alasan']).'</td><td>'.e($r['keterangan']??'-').'</td><td>'.e($r['status']).'</td>');
?>
<div class="portal-note"><i class="bi bi-shield-lock-fill"></i> Data dibatasi pada anak yang terhubung ke akun ini. Catatan internal BK yang bersifat privat tidak ditampilkan.</div>
<?php endif;?></section></main><style>.portal-card{margin-top:18px}.portal-card h2{font-size:1.05rem;margin:0 0 14px}.portal-empty{padding:14px;border-radius:10px;background:#f8fafc;color:#64748b}.portal-badges{display:flex;gap:10px;flex-wrap:wrap}.portal-badges span{padding:9px 12px;border-radius:10px;background:#f8fafc;color:#334155;font-size:.86rem}.portal-note{margin-top:18px;padding:13px 15px;border:1px solid #dbeafe;background:#eff6ff;border-radius:12px;color:#475569}@media(max-width:900px){.dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}@media(max-width:600px){.dashboard-grid{grid-template-columns:1fr!important}}</style>
<?php require_once __DIR__.'/../layouts/footer.php';
