<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pelanggaran_require_access();

$tapel = pelanggaran_active_tapel($pdo);
$tapelId = $tapel ? (int)$tapel['id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));
$kelasId = max(0,(int)($_GET['kelas_id'] ?? 0));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage,[10,20,50,100],true)) $perPage=20;
$page=max(1,(int)($_GET['page']??1));

$kelasRows=$pdo->query("SELECT id,nama FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama")->fetchAll();
$stats=['kasus'=>0,'poin'=>0,'p1'=>0,'p2'=>0,'p3'=>0,'p4'=>0];
if($tapelId){
    $st=$pdo->prepare("SELECT COUNT(*) FROM pelanggaran_siswa WHERE tahun_pelajaran_id=?");$st->execute([$tapelId]);$stats['kasus']=(int)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COALESCE(SUM(poin),0) FROM pelanggaran_siswa WHERE tahun_pelajaran_id=? AND status<>'selesai'");$st->execute([$tapelId]);$stats['poin']=(int)$st->fetchColumn();
}

$where=['ps.tahun_pelajaran_id=?'];$params=[$tapelId];
if($q!==''){ $where[]='(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR jp.nama LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like); }
if($kelasId>0){$where[]='EXISTS(SELECT 1 FROM siswa_kelas skf WHERE skf.siswa_id=s.id AND skf.tahun_pelajaran_id=? AND skf.kelas_id=? AND skf.status="aktif")';$params[]=$tapelId;$params[]=$kelasId;}
$count=$pdo->prepare("SELECT COUNT(*) FROM pelanggaran_siswa ps JOIN siswa s ON s.id=ps.siswa_id JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id WHERE ".implode(' AND ',$where));$count->execute($params);$total=(int)$count->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
$sql="SELECT ps.*,s.nis,s.nisn,s.nama,jp.kode jp_kode,jp.nama jp_nama,mk.nama kelas,g.nama petugas
FROM pelanggaran_siswa ps JOIN siswa s ON s.id=ps.siswa_id JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id
LEFT JOIN guru g ON g.id=ps.petugas_id
LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=ps.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
WHERE ".implode(' AND ',$where)." ORDER BY ps.tanggal DESC,ps.id DESC LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();

// Hitung status peringatan berdasarkan total bersih per siswa pada tapel aktif.
$studentTotals=[];
if($rows){$ids=array_values(array_unique(array_map(fn($r)=>(int)$r['siswa_id'],$rows)));$ph=implode(',',array_fill(0,count($ids),'?'));
    $p=$ids;array_unshift($p,$tapelId);$st=$pdo->prepare("SELECT siswa_id,COALESCE(SUM(poin),0) total FROM pelanggaran_siswa WHERE tahun_pelajaran_id=? AND siswa_id IN ($ph) GROUP BY siswa_id");$st->execute($p);foreach($st->fetchAll() as $r)$studentTotals[(int)$r['siswa_id']]=(int)$r['total'];
    $st=$pdo->prepare("SELECT siswa_id,COALESCE(SUM(delta_poin),0) delta FROM pelanggaran_poin_penyesuaian WHERE tahun_pelajaran_id=? AND siswa_id IN ($ph) GROUP BY siswa_id");$st->execute($p);foreach($st->fetchAll() as $r)$studentTotals[(int)$r['siswa_id']]=max(0,($studentTotals[(int)$r['siswa_id']]??0)+(int)$r['delta']);
}
$attentionRows=[];
if($tapelId){
    $sqlAttention="SELECT s.id,s.nis,s.nama,mk.nama kelas,
        COALESCE(v.gross,0)+COALESCE(a.delta,0) poin_efektif,
        CASE WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=100 THEN 'P4' WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=75 THEN 'P3' WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=50 THEN 'P2' WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=25 THEN 'P1' ELSE 'Normal' END tahap,
        (SELECT p.status FROM pembinaan_siswa p WHERE p.siswa_id=s.id AND p.tahun_pelajaran_id=? ORDER BY p.tanggal DESC,p.id DESC LIMIT 1) pb_status
        FROM siswa s
        LEFT JOIN (SELECT siswa_id,tahun_pelajaran_id,SUM(poin) gross FROM pelanggaran_siswa WHERE tahun_pelajaran_id=? GROUP BY siswa_id,tahun_pelajaran_id) v ON v.siswa_id=s.id AND v.tahun_pelajaran_id=?
        LEFT JOIN (SELECT siswa_id,tahun_pelajaran_id,SUM(delta_poin) delta FROM pelanggaran_poin_penyesuaian WHERE tahun_pelajaran_id=? GROUP BY siswa_id,tahun_pelajaran_id) a ON a.siswa_id=s.id AND a.tahun_pelajaran_id=?
        LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.status='aktif' AND COALESCE(v.gross,0)+COALESCE(a.delta,0)>=25
        ORDER BY poin_efektif DESC,s.nama ASC LIMIT 20";
    $qA=$pdo->prepare($sqlAttention);$qA->execute([$tapelId,$tapelId,$tapelId,$tapelId,$tapelId,$tapelId]);$attentionRows=$qA->fetchAll();
}
$page_title='Pelanggaran';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pelanggaran Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>
.pel-hero{background:linear-gradient(135deg,#172033,#273449);color:#fff;border-radius:18px;padding:22px;margin-bottom:18px}.pel-hero h1{margin:0;font-size:25px;font-weight:850}.pel-hero p{margin:6px 0 0;color:#cbd5e1}.pel-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.pel-card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:16px}.pel-num{font-size:25px;font-weight:900}.pel-label{font-size:11px;font-weight:800;color:#64748b}.pel-card small{color:#64748b}.pel-table{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px}.warn{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:850;padding:4px 7px;border-radius:999px}.warn-p1{background:#fef3c7;color:#92400e}.warn-p2{background:#ffedd5;color:#9a3412}.warn-p3{background:#fee2e2;color:#b91c1c}.warn-p4{background:#fce7f3;color:#9d174d}.warn-normal{background:#dcfce7;color:#166534}.muted{color:#64748b;font-size:12px}.filter-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:15px}.filter-row label{margin:0}.filter-row .form-control{min-width:150px}@media(max-width:900px){.pel-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.pel-grid{grid-template-columns:1fr}.filter-row>*{width:100%}}
</style>
<div class="pel-hero"><div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap"><div><h1><i class="bi bi-exclamation-triangle-fill"></i> Pelanggaran & Sistem Poin</h1><p>Tahun Pelajaran aktif: <strong><?=e($tapel['nama']??'Belum tersedia')?></strong>. Riwayat tetap dipisahkan per tahun pelajaran.</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><?php if(pelanggaran_can_record()):?><a class="btn btn-primary" href="<?=BASE_URL?>pelanggaran/tambah.php"><i class="bi bi-plus-circle"></i> Tambah Pelanggaran</a><?php endif;?><?php if(pelanggaran_can_manage_master()):?><a class="btn btn-secondary" href="<?=BASE_URL?>pelanggaran/jenis.php"><i class="bi bi-list-check"></i> Master Jenis & Poin</a><?php endif;?></div></div></div>
<?php if(!$tapel):?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Belum ada Tahun Pelajaran aktif. Transaksi pelanggaran belum dapat dicatat.</div><?php endif;?>
<div class="pel-grid"><div class="pel-card"><div class="pel-label">KASUS TAHUN INI</div><div class="pel-num"><?=number_format($stats['kasus'])?></div><small>Semua catatan</small></div><div class="pel-card"><div class="pel-label">POIN TERCATAT</div><div class="pel-num"><?=number_format($stats['poin'])?></div><small>Sebelum penyesuaian</small></div><div class="pel-card"><div class="pel-label">AMBANG PERINGATAN</div><div class="pel-num">25 / 50 / 75 / 100</div><small>P1 · P2 · P3 · P4</small></div><div class="pel-card"><div class="pel-label">PRINSIP</div><div class="pel-num"><i class="bi bi-arrow-repeat"></i></div><small>Poin dapat diperbaiki setelah pembinaan</small></div></div><div class="pel-table" style="margin-bottom:18px"><div class="section-title"><div><h2><i class="bi bi-bell-fill"></i> Peringatan & Tindak Lanjut</h2><p>Siswa dengan poin efektif 25+ perlu mendapat perhatian sesuai tahapnya.</p></div></div><?php if(!$attentionRows):?><div class="empty-state">Belum ada siswa yang mencapai ambang peringatan.</div><?php else:?><div class="table-responsive"><table class="data-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>Poin Efektif</th><th>Tahap</th><th>Status Pembinaan</th><th>Aksi</th></tr></thead><tbody><?php foreach($attentionRows as $ar):$ap=pelanggaran_status_poin((int)$ar['poin_efektif']);?><tr><td><strong><?=e($ar['nama'])?></strong><br><span class="muted"><?=e($ar['nis'])?></span></td><td><?=e((string)($ar['kelas']??'Belum ada kelas'))?></td><td><strong><?=number_format((int)$ar['poin_efektif'])?></strong></td><td><span class="warn <?=e('warn-'.$ap['class'])?>"><i class="bi <?=e($ap['icon'])?>"></i> <?=e($ap['label'])?></span></td><td><?= $ar['pb_status'] ? '<span class="muted">'.e(pembinaan_status_label((string)$ar['pb_status'])).'</span>' : '<span class="warn warn-p4"><i class="bi bi-exclamation-circle"></i> Belum ada pembinaan</span>' ?></td><td><a class="btn btn-secondary btn-sm" href="<?=BASE_URL?>pembinaan/index.php?q=<?=urlencode($ar['nama'])?>"><i class="bi bi-person-check"></i> Pembinaan</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<div class="pel-table"><div class="section-title"><div><h2>Riwayat Pelanggaran</h2><p><?=$total?> data pada tahun pelajaran aktif.</p></div></div>
<form method="get" class="filter-row"><label><span class="form-label">Cari</span><input class="form-control" type="search" name="q" value="<?=e($q)?>" placeholder="NIS / NISN / Nama / Pelanggaran"></label><label><span class="form-label">Kelas</span><select class="form-control" name="kelas_id"><option value="0">Semua kelas</option><?php foreach($kelasRows as $k):?><option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option><?php endforeach;?></select></label><label><span class="form-label">Per halaman</span><select class="form-control" name="per_page"><?php foreach([10,20,50,100] as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></label><button class="btn btn-secondary"><i class="bi bi-search"></i> Terapkan</button><a class="btn btn-secondary" href="<?=BASE_URL?>pelanggaran/index.php">Reset</a></form>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Pelanggaran</th><th>Poin</th><th>Status Peringatan</th><th>Petugas</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Belum ada catatan pelanggaran pada tahun pelajaran aktif.</td></tr><?php else:foreach($rows as $r):$stP=pelanggaran_status_poin((int)($studentTotals[(int)$r['siswa_id']]??0));?><tr><td><?=e($r['tanggal'])?><br><span class="muted"><?=e(substr((string)($r['waktu']??''),0,5))?></span></td><td><strong><?=e($r['nama'])?></strong><br><span class="muted"><?=e($r['nis'])?> · <?=e((string)($r['kelas']??'Belum ada kelas'))?></span></td><td><strong><?=e($r['jp_nama'])?></strong><br><span class="muted"><?=e((string)($r['kronologi']??''))?></span></td><td><strong>+<?=number_format((int)$r['poin'])?></strong></td><td><span class="warn warn-<?=$stP['class']?>"><i class="bi <?=$stP['icon']?>"></i> <?=$stP['label']?></span><br><span class="muted">Total <?=number_format((int)($studentTotals[(int)$r['siswa_id']]??0))?> poin</span></td><td><?=e((string)($r['petugas']??'Sistem'))?></td><td><a class="btn btn-secondary btn-sm btn-icon-sm" title="Riwayat pelanggaran siswa" aria-label="Riwayat pelanggaran siswa" href="<?=BASE_URL?>pelanggaran/detail.php?id=<?=(int)$r['siswa_id']?>"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></a></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php if($pages>1):?><div class="pagination"><span>Menampilkan <?=$total?($offset+1):0?>–<?=min($offset+$perPage,$total)?> dari <?=$total?></span><span>Halaman <?=$page?>/<?=$pages?></span><div><?php if($page>1):?><a class="btn btn-secondary btn-sm" href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>">‹</a><?php endif;?> <?php if($page<$pages):?><a class="btn btn-secondary btn-sm" href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>">›</a><?php endif;?></div></div><?php endif;?></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
