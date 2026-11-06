<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);

$q=trim((string)($_GET['q']??''));
$status=(string)($_GET['status']??'');
$perPageInput=(int)($_GET['per_page']??20);
$perPage=in_array($perPageInput,[10,20,50,100],true)?$perPageInput:20;
$page=max(1,(int)($_GET['page']??1));
$scope=uks_teacher_scope($pdo,'s');
$where=["1=1"];$params=[];
if($scope['sql']!=='1=1'){ $where[]=$scope['sql'];$params=array_merge($params,$scope['params']); }
if($q!==''){ $like='%'.$q.'%';$where[]='(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';array_push($params,$like,$like,$like); }
if(in_array($status,['baru','ditangani','selesai','batal'],true)){ $where[]='uk.status=?';$params[]=$status; }
$count=$pdo->prepare("SELECT COUNT(*) FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id WHERE ".implode(' AND ',$where));$count->execute($params);
$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$st=$pdo->prepare("SELECT uk.id,uk.tanggal,uk.waktu,uk.waktu_mulai,uk.waktu_selesai,uk.nomor_antrian,uk.status,uk.jenis_layanan,s.nis,s.nisn,s.nama,mk.nama AS kelas,g.nama AS petugas
 FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id LEFT JOIN master_kelas mk ON mk.id=uk.kelas_id LEFT JOIN guru g ON g.id=uk.petugas_id
 WHERE ".implode(' AND ',$where)." ORDER BY uk.tanggal DESC,uk.id DESC LIMIT ".$perPage." OFFSET ".$offset);
$st->execute($params);$rows=$st->fetchAll();
function uks_riw_q(array $c=[]):string{$b=$_GET??[];unset($b['page']);foreach($c as $k=>$v){if($v===null)unset($b[$k]);else$b[$k]=$v;}return http_build_query($b);}
$page_title='Riwayat Layanan UKS';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main uks-module-page"><header class="topbar"><div class="topbar-title">Riwayat Layanan UKS</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-clock-history"></i> Riwayat Layanan UKS</h1><p>Riwayat kunjungan dan pelayanan UKS. Detail kesehatan mengikuti hak akses.</p></div><div class="page-header-actions"><a class="btn btn-primary" href="<?=BASE_URL?>uks/kunjungan.php"><i class="bi bi-people-fill"></i> Layanan Hari Ini</a></div></div>
<div class="form-card"><form method="get" class="filter-row"><div><label class="form-label">Cari siswa</label><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Nama / NIS / NISN"></div><div><label class="form-label">Status</label><select class="form-control" name="status"><option value="">Semua</option><option value="baru" <?=$status==='baru'?'selected':''?>>Baru</option><option value="ditangani" <?=$status==='ditangani'?'selected':''?>>Dilayani</option><option value="selesai" <?=$status==='selesai'?'selected':''?>>Selesai</option><option value="batal" <?=$status==='batal'?'selected':''?>>Batal</option></select></div><div><label class="form-label">Baris</label><select class="form-control" name="per_page"><?php foreach([10,20,50,100] as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></div><div><button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button></div></form></div>
<div class="form-card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Antrian</th><th>Siswa</th><th>Kelas</th><th>Layanan</th><th>Petugas</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8" class="empty-state">Belum ada riwayat layanan.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?><div style="font-size:11px;color:#64748b"><?=e(substr((string)($r['waktu']??''),0,5))?> WIB</div></td><td>#<?=e((string)($r['nomor_antrian']??'-'))?></td><td><strong><?=e($r['nama'])?></strong><div style="font-size:11px;color:#64748b"><?=e($r['nis'])?><?=!empty($r['nisn'])?' · '.e($r['nisn']):''?></div></td><td><?=e($r['kelas']??'-')?></td><td><?=e($r['jenis_layanan']??'-')?></td><td><?=e($r['petugas']??'-')?></td><td><span class="badge <?=e($r['status'])?>"><?=e(['baru'=>'BARU','ditangani'=>'DILAYANI','selesai'=>'SELESAI','batal'=>'BATAL'][$r['status']]??$r['status'])?></span></td><td><a class="btn btn-sm btn-secondary" href="<?=BASE_URL?>uks/kunjungan_detail.php?id=<?=$r['id']?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach;endif;?>
</tbody></table></div>
<?php if($pages>1):?><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:14px"><?php for($i=1;$i<=$pages;$i++):?><a class="btn btn-sm <?=$i===$page?'btn-primary':'btn-secondary'?>" href="?<?=e(uks_riw_q(['page'=>$i]))?>"><?=$i?></a><?php endfor;?></div><?php endif;?>
</div></section></main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
