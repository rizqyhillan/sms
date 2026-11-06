<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pengguna_require_admin();

$cari = trim((string)($_GET['cari'] ?? ''));
$status = (string)($_GET['status'] ?? 'aktif');
if (!in_array($status, ['aktif','nonaktif','semua'], true)) $status = 'aktif';
$role = (string)($_GET['role'] ?? 'semua');
if (!array_key_exists($role, pengguna_roles()) && $role !== 'semua') $role = 'semua';
$allowedPerPage=[5,10,20,50,100];
$perPage=(int)($_GET['per_page']??20); if(!in_array($perPage,$allowedPerPage,true))$perPage=20;
$page=max(1,(int)($_GET['page']??1));

$where=[];$params=[];
if($status!=='semua'){ $where[]='u.status=?';$params[]=$status; }
if($role!=='semua'){ $where[]='u.role=?';$params[]=$role; }
if($cari!==''){ $where[]='(u.username LIKE ? OR u.nama LIKE ? OR g.nama LIKE ? OR s.nama LIKE ?)';$q='%'.$cari.'%';array_push($params,$q,$q,$q,$q); }
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';

$st=$pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id $whereSql");
$st->execute($params);$total=(int)$st->fetchColumn();$totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;

$sql="SELECT u.id,u.username,u.nama,u.role,u.status,u.last_login,u.created_at,u.guru_id,u.siswa_id,
       g.nama AS guru_nama,s.nama AS siswa_nama,
       (SELECT COUNT(*) FROM user_hak_akses uha WHERE uha.user_id=u.id) AS jumlah_hak
       FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id
       $whereSql ORDER BY u.nama ASC,u.id ASC LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
$summary=$pdo->query("SELECT COUNT(*) semua,SUM(status='aktif') aktif,SUM(status='nonaktif') nonaktif FROM users")->fetch();
$flash=pengguna_flash();

$page_title='Pengguna & Hak Akses';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Pengguna</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<style>
.user-security-note{display:flex;gap:12px;align-items:flex-start;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:14px 16px;border-radius:12px;margin-bottom:18px}.user-security-note i{font-size:18px}.role-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:7px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:800}.link-chip{font-size:12px;color:#64748b}.action-icon{margin-right:5px}.table-actions form{display:inline}.table-actions{align-items:center}
</style>
<?php if($flash):?><div class="alert alert-info"><?=e($flash)?></div><?php endif;?>
<div class="page-header"><div><h1>Pengguna & Hak Akses</h1><p>Kelola akun login, status, role, dan hak akses. Role tidak menggantikan tugas guru.</p></div><div class="page-header-actions"><a href="<?=BASE_URL?>pengguna/akun_kepala_sekolah.php" class="btn btn-secondary"><i class="bi bi-person-vcard"></i> Akun Kepala Sekolah</a><a href="<?=BASE_URL?>pengguna/tambah.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Tambah Pengguna</a></div></div>
<div class="user-security-note"><i class="bi bi-shield-lock-fill"></i><div><strong>Keamanan akun</strong><div style="margin-top:3px">Password disimpan sebagai hash. Akun nonaktif tidak dapat login. Perubahan role dan hak akses dicatat ke audit log.</div></div></div>
<div class="dashboard-grid">
<div class="dashboard-card"><div class="card-label">Total Pengguna</div><div class="card-number"><?= (int)$summary['semua']?></div><div class="card-description">Semua akun</div></div>
<div class="dashboard-card"><div class="card-label">Aktif</div><div class="card-number"><?= (int)$summary['aktif']?></div><div class="card-description">Dapat login</div></div>
<div class="dashboard-card"><div class="card-label">Nonaktif</div><div class="card-number"><?= (int)$summary['nonaktif']?></div><div class="card-description">Login diblokir</div></div>
</div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Daftar Pengguna</h2><p>Hak akses dikelola per akun.</p></div></div>
<form method="get" class="filter-bar">
<input type="search" name="cari" value="<?=e($cari)?>" placeholder="Cari username, nama, guru, atau siswa..." class="form-control">
<select name="role" class="form-control"><option value="semua">Semua Role</option><?php foreach(pengguna_roles() as $k=>$v):?><option value="<?=$k?>" <?=$role===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select>
<select name="status" class="form-control"><option value="aktif" <?=$status==='aktif'?'selected':''?>>Aktif</option><option value="nonaktif" <?=$status==='nonaktif'?'selected':''?>>Nonaktif</option><option value="semua" <?=$status==='semua'?'selected':''?>>Semua</option></select>
<select name="per_page" class="form-control"><?php foreach($allowedPerPage as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select>
<button class="btn btn-secondary" type="submit"><i class="bi bi-funnel"></i> Terapkan</button><a class="btn btn-secondary" href="<?=BASE_URL?>pengguna/index.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
</form>
<div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>Pengguna</th><th>Role</th><th>Relasi</th><th>Hak Akses</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8" class="empty-state">Belum ada pengguna sesuai filter.</td></tr><?php else: foreach($rows as $i=>$r):?><tr>
<td><?=$offset+$i+1?></td><td><strong><?=e($r['nama'])?></strong><br><code><?=e($r['username'])?></code></td>
<td><span class="role-chip"><i class="bi bi-<?= $r['role']==='admin'?'shield-fill':($r['role']==='kepala_sekolah'?'person-vcard':($r['role']==='guru_bk'?'heart-pulse-fill':($r['role']==='guru'?'person-badge':'mortarboard')))?>"></i><?=e(pengguna_roles()[$r['role']]??$r['role'])?></span></td>
<td><span class="link-chip"><?php if($r['guru_id']):?>Guru: <?=e($r['guru_nama']??'-')?><?php elseif($r['siswa_id']):?>Siswa: <?=e($r['siswa_nama']??'-')?><?php else:?>Tidak terhubung<?php endif;?></span></td>
<td><span class="status-badge status-aktif"><?= (int)$r['jumlah_hak']?> hak</span></td>
<td><span class="status-badge <?=$r['status']==='aktif'?'status-aktif':'status-nonaktif'?>"><i class="bi bi-<?=$r['status']==='aktif'?'check-circle':'pause-circle'?>"></i> <?=ucfirst($r['status'])?></span></td>
<td><?= $r['last_login'] ? e(date('d-m-Y H:i',strtotime((string)$r['last_login']))) : '<span class="link-chip">Belum login</span>'?></td>
<td><div class="table-actions">
<a class="btn btn-secondary btn-sm btn-icon-sm" title="Edit pengguna" aria-label="Edit pengguna" href="<?=BASE_URL?>pengguna/edit.php?id=<?=(int)$r['id']?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
<a class="btn btn-secondary btn-sm btn-icon-sm" title="Hak akses" aria-label="Hak akses" href="<?=BASE_URL?>pengguna/hak_akses.php?id=<?=(int)$r['id']?>"><i class="bi bi-shield-check" aria-hidden="true"></i></a>
<?php if((int)$r['id']!==(int)user_id()):?><form method="post" action="<?=BASE_URL?>pengguna/toggle.php" onsubmit="return confirm('Ubah status akun ini?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-sm btn-icon-sm <?=$r['status']==='aktif'?'btn-secondary':'btn-primary'?>" type="submit" title="<?=$r['status']==='aktif'?'Nonaktifkan':'Aktifkan'?>" aria-label="<?=$r['status']==='aktif'?'Nonaktifkan':'Aktifkan'?>"><i class="bi bi-<?=$r['status']==='aktif'?'pause':'play'?>-circle" aria-hidden="true"></i></button></form><?php endif;?>
<?php if((int)$r['id']!==(int)user_id()):?><form method="post" action="<?=BASE_URL?>pengguna/delete.php" onsubmit="return confirm('Hapus akun ini? Akun yang sudah memiliki histori tidak boleh dihapus.')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus" aria-label="Hapus"><i class="bi bi-trash3" aria-hidden="true"></i></button></form><?php endif;?>
</div></td></tr><?php endforeach; endif;?></tbody></table></div>
<div class="pagination-bar"><div class="text-muted">Menampilkan <?=$total?($offset+1).'–'.min($offset+$perPage,$total):'0'?> dari <?=$total?> pengguna</div><div class="pagination"><?php for($pg=1;$pg<=$totalPages;$pg++):?><a class="btn btn-sm <?=$pg===$page?'btn-primary':'btn-secondary'?>" href="?<?=http_build_query(array_merge($_GET,['page'=>$pg]))?>"><?=$pg?></a><?php endfor;?></div></div>
</div></section></main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
