<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pembinaan_require_access();

$tapel = pembinaan_active_tapel($pdo);
$tapelId = $tapel ? (int)$tapel['id'] : 0;
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10,20,50,100], true)) $perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$stats = ['total'=>0,'proses'=>0,'selesai'=>0,'ortu'=>0];
if ($tapelId) {
    $st=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa WHERE tahun_pelajaran_id=?"); $st->execute([$tapelId]); $stats['total']=(int)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa WHERE tahun_pelajaran_id=? AND status='proses'"); $st->execute([$tapelId]); $stats['proses']=(int)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa WHERE tahun_pelajaran_id=? AND status='selesai'"); $st->execute([$tapelId]); $stats['selesai']=(int)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa WHERE tahun_pelajaran_id=? AND melibatkan_ortu=1"); $st->execute([$tapelId]); $stats['ortu']=(int)$st->fetchColumn();
}

$where=['p.tahun_pelajaran_id=?']; $params=[$tapelId];
if($q!==''){ $where[]='(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR p.masalah LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like); }
if(in_array($status,['direncanakan','proses','selesai'],true)){ $where[]='p.status=?'; $params[]=$status; }

$count=$pdo->prepare("SELECT COUNT(*) FROM pembinaan_siswa p JOIN siswa s ON s.id=p.siswa_id WHERE ".implode(' AND ',$where));
$count->execute($params); $total=(int)$count->fetchColumn();
$pages=max(1,(int)ceil($total/$perPage)); $page=min($page,$pages); $offset=($page-1)*$perPage;
$sql="SELECT p.*,s.nis,s.nisn,s.nama,mk.nama kelas,g.nama petugas,jp.nama pelanggaran_nama,jp.kode pelanggaran_kode
FROM pembinaan_siswa p JOIN siswa s ON s.id=p.siswa_id
LEFT JOIN guru g ON g.id=p.petugas_id
LEFT JOIN pelanggaran_siswa ps ON ps.id=p.pelanggaran_id
LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id
LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=p.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
WHERE ".implode(' AND ',$where)." ORDER BY p.tanggal DESC,p.id DESC LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();

$page_title='Pembinaan'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Pembinaan Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>
.pb-hero{background:linear-gradient(135deg,#172033,#273449);color:#fff;border-radius:18px;padding:22px;margin-bottom:18px}.pb-hero h1{margin:0;font-size:25px;font-weight:850}.pb-hero p{margin:6px 0 0;color:#cbd5e1}.pb-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.pb-card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:16px}.pb-num{font-size:25px;font-weight:900}.pb-label{font-size:11px;font-weight:800;color:#64748b}.pb-table{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px}.pb-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:15px}.pb-filter label{margin:0}.pb-filter .form-control{min-width:150px}.pb-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:850;padding:5px 8px;border-radius:999px}.pb-status-plan{background:#e0e7ff;color:#3730a3}.pb-status-proses{background:#ffedd5;color:#9a3412}.pb-status-selesai{background:#dcfce7;color:#166534}.pb-status-batal{background:#f1f5f9;color:#475569}.pb-meta{font-size:11px;color:#64748b}.pb-student{font-weight:800}.pb-type{font-size:11px;color:#475569;font-weight:750}.pb-callout{display:flex;gap:10px;padding:12px 14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;border-radius:12px;margin-bottom:16px}
@media(max-width:900px){.pb-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.pb-grid{grid-template-columns:1fr}.pb-filter>*{width:100%}.pb-filter .form-control{width:100%}}
</style>
<div class="pb-hero"><div style="display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap"><div><h1><i class="bi bi-person-check-fill"></i> Pembinaan Siswa</h1><p>Siswa yang mencapai 25+ poin akan otomatis masuk ke pembinaan.</p></div></div></div>
<div class="pb-callout"><i class="bi bi-info-circle-fill"></i><div><strong>Alur sederhana:</strong> 25+ poin → Perlu Ditangani → BK menangani → Selesai. Riwayat pelanggaran tetap tersimpan.</div></div>
<?php if(!$tapel):?><div class="alert alert-warning">Belum ada Tahun Pelajaran aktif.</div><?php endif;?>
<div class="pb-grid"><div class="pb-card"><div class="pb-label">TOTAL PEMBINAAN</div><div class="pb-num"><?=number_format($stats['total'])?></div><small>Tapel aktif</small></div><div class="pb-card"><div class="pb-label">DALAM PROSES</div><div class="pb-num"><?=number_format($stats['proses'])?></div><small>Perlu tindak lanjut</small></div><div class="pb-card"><div class="pb-label">SELESAI</div><div class="pb-num"><?=number_format($stats['selesai'])?></div><small>Pembinaan selesai</small></div><div class="pb-card"><div class="pb-label">MELIBATKAN ORANG TUA</div><div class="pb-num"><?=number_format($stats['ortu'])?></div><small>Komunikasi/pendampingan</small></div></div>
<div class="pb-table"><div class="section-title"><div><h2>Riwayat Pembinaan</h2><p><?=number_format($total)?> data pada Tahun Pelajaran aktif.</p></div></div>
<form method="get" class="pb-filter"><label><span class="form-label">Cari</span><input class="form-control" type="search" name="q" value="<?=e($q)?>" placeholder="NIS / NISN / Nama / masalah"></label><label><span class="form-label">Status</span><select class="form-control" name="status"><option value="">Semua status</option><?php foreach(['direncanakan'=>'Perlu Ditangani','proses'=>'Sedang Dibina','selesai'=>'Selesai'] as $v=>$l):?><option value="<?=$v?>" <?=$status===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></label><label><span class="form-label">Per halaman</span><select class="form-control" name="per_page"><?php foreach([10,20,50,100] as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></label><button class="btn btn-secondary"><i class="bi bi-search"></i> Terapkan</button><a class="btn btn-secondary" href="<?=BASE_URL?>pembinaan/index.php">Reset</a></form>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Jenis</th><th>Status</th><th>Petugas</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada data pembinaan.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?><br><span class="pb-meta"><?=e(substr((string)($r['waktu']??''),0,5))?></span></td><td><div class="pb-student"><?=e($r['nama'])?></div><div class="pb-meta"><?=e($r['nis'])?> · <?=e((string)($r['kelas']??'Belum ada kelas'))?></div></td><td><span class="pb-type">Pembinaan</span><?php if(!empty($r['pelanggaran_nama'])):?><br><span class="pb-meta">Terkait: <?=e($r['pelanggaran_nama'])?></span><?php endif;?></td><td><span class="pb-status <?=e(pembinaan_status_class($r['status']))?>"><i class="bi bi-circle-fill" style="font-size:6px"></i> <?=e(pembinaan_status_label($r['status']))?></span><?php if((int)$r['melibatkan_ortu']===1):?><br><span class="pb-meta"><i class="bi bi-people-fill"></i> Orang tua terlibat</span><?php endif;?></td><td><?=e((string)($r['petugas']??'Sistem'))?></td><td><a class="btn btn-secondary btn-sm btn-icon-sm" title="Detail pembinaan" aria-label="Detail pembinaan" href="<?=BASE_URL?>pembinaan/detail.php?id=<?=(int)$r['id']?>"><i class="bi bi-eye" aria-hidden="true"></i></a></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php if($pages>1):?><div class="pagination"><span>Menampilkan <?=$total?($offset+1):0?>–<?=min($offset+$perPage,$total)?> dari <?=$total?></span><span>Halaman <?=$page?>/<?=$pages?></span><div><?php if($page>1):?><a class="btn btn-secondary btn-sm" href="?<?=http_build_query(array_merge($_GET,['page'=>$page-1]))?>">‹</a><?php endif;?> <?php if($page<$pages):?><a class="btn btn-secondary btn-sm" href="?<?=http_build_query(array_merge($_GET,['page'=>$page+1]))?>">›</a><?php endif;?></div></div><?php endif;?></div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
