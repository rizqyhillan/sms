<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
pengaturan_sholat_config_admin_only();

$perPage=(int)($_GET['per_page']??20);
if(!in_array($perPage,[5,10,20,50,100],true))$perPage=20;
$page=max(1,(int)($_GET['page']??1));
$total=(int)$pdo->query("SELECT COUNT(*) FROM jenis_sholat")->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;
$offset=($page-1)*$perPage;
$st=$pdo->prepare("SELECT js.*, (SELECT COUNT(*) FROM sesi_absensi s WHERE s.jenis_sholat_id=js.id) AS total_sesi FROM jenis_sholat js ORDER BY js.urutan ASC, js.id ASC LIMIT $perPage OFFSET $offset");
$st->execute();$rows=$st->fetchAll();

$page_title='Jenis Sholat';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Pengaturan Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content sholat-settings-module"><?php if(isset($_GET['saved'])):?><div class="alert alert-success"><?=e((string)$_GET['saved'])?></div><?php endif;?><?php if(isset($_GET['error'])):?><div class="alert alert-danger"><?=e((string)$_GET['error'])?></div><?php endif;?>
<div class="page-header">
  <div><h1>Jenis Sholat</h1><p>Kelola jenis sholat yang tersedia untuk pembuatan sesi absensi.</p></div>
  <div class="page-header-actions"><a class="btn btn-primary" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/tambah.php"><i class="bi bi-plus-circle"></i> Tambah Jenis Sholat</a><a class="btn btn-secondary" href="<?=BASE_URL?>sholat/index.php"><i class="bi bi-moon-stars"></i> Absensi Sholat</a></div>
</div>
<div class="info-box"><strong>Catatan:</strong><p>Menonaktifkan jenis sholat hanya membuatnya tidak tersedia untuk sesi baru. Data dan histori sesi yang sudah ada tetap dipertahankan.</p></div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Daftar Jenis Sholat</h2><p>Urutan menentukan posisi pilihan saat membuat sesi.</p></div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Urutan</th><th>Nama</th><th>Status</th><th>Sesi Tercatat</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada jenis sholat.</td></tr>
<?php else: foreach($rows as $r): ?>
<tr>
<td><strong><?= (int)$r['urutan'] ?></strong></td>
<td><?=e($r['nama'])?></td>
<td><span class="status-badge status-<?=((int)$r['aktif']===1?'aktif':'nonaktif')?>"><?=((int)$r['aktif']===1?'Aktif':'Nonaktif')?></span></td>
<td><?= (int)$r['total_sesi'] ?></td>
<td><?=e($r['created_at'])?></td>
<td><div class="table-actions"><a class="btn btn-secondary btn-sm btn-icon-sm" title="Edit jenis sholat" aria-label="Edit jenis sholat" href="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/edit.php?id=<?=(int)$r['id']?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a><?php if((int)$r['aktif']===1):?><form method="post" action="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/status.php" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="aktif" value="0"><button class="btn btn-secondary btn-sm btn-icon-sm" type="submit" onclick="return confirm('Nonaktifkan jenis sholat ini?')" title="Nonaktifkan" aria-label="Nonaktifkan"><i class="bi bi-pause-circle" aria-hidden="true"></i></button></form><?php else:?><form method="post" action="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/status.php" style="display:inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="aktif" value="1"><button class="btn btn-primary btn-sm btn-icon-sm" type="submit" title="Aktifkan" aria-label="Aktifkan"><i class="bi bi-check-circle" aria-hidden="true"></i></button></form><?php endif;?><?php if((int)$r['total_sesi']===0):?><form method="post" action="<?=BASE_URL?>pengaturan_absensi/jenis_sholat/delete.php" style="display:inline" onsubmit="return confirm('Hapus jenis sholat ini?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus" aria-label="Hapus"><i class="bi bi-trash3" aria-hidden="true"></i></button></form><?php else:?><span class="muted-text" title="Sudah memiliki histori sesi"><i class="bi bi-lock"></i> Histori tersimpan</span><?php endif;?></div></td>
</tr>
<?php endforeach; endif; ?></tbody></table></div>
<div class="list-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;flex-wrap:wrap">
<div class="muted-text">Menampilkan <?= $total?($offset+1):0 ?>–<?= min($offset+$perPage,$total) ?> dari <?= $total ?> jenis sholat</div>
<form method="get" style="display:flex;align-items:center;gap:8px"><label class="muted-text">Tampil <select class="form-control" name="per_page" onchange="this.form.submit()"><?php foreach([5,10,20,50,100] as $n):?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach;?></select></label><input type="hidden" name="page" value="1"></form>
</div>
<?php if($totalPages>1):?><div class="pagination" style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap"><?php for($pg=1;$pg<=$totalPages;$pg++):?><a class="btn <?=$pg===$page?'btn-primary':'btn-secondary'?> btn-sm" href="?page=<?=$pg?>&per_page=<?=$perPage?>"><?=$pg?></a><?php endfor;?></div><?php endif;?>
</div>
</section></main>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
