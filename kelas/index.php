<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$cari = trim((string)($_GET['cari'] ?? ''));
$status = (string)($_GET['status'] ?? 'aktif');
if (!in_array($status, ['aktif','nonaktif','semua'], true)) $status = 'aktif';
$perPage=(int)($_GET['per_page']??20); if(!in_array($perPage,[5,10,20,50,100],true))$perPage=20;
$page=max(1,(int)($_GET['page']??1));

$years = $pdo->query('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
$activeYearId = 0;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $activeYearId = (int)$y['id']; break; } }
$selectedYearId = (int)($_GET['tahun_pelajaran_id'] ?? $activeYearId);
$selectedYear = null;
foreach ($years as $y) { if ((int)$y['id'] === $selectedYearId) { $selectedYear = $y; break; } }
if (!$selectedYear && $activeYearId > 0) { $selectedYearId = $activeYearId; foreach ($years as $y) { if ((int)$y['id'] === $selectedYearId) { $selectedYear = $y; break; } } }

$where = [];
$params = [];
if ($status !== 'semua') { $where[] = 'mk.aktif = ?'; $params[] = $status === 'aktif' ? 1 : 0; }
if ($cari !== '') { $where[] = '(mk.nama LIKE ? OR CAST(mk.tingkat AS CHAR) LIKE ?)'; $q = '%' . $cari . '%'; array_push($params, $q, $q); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSt=$pdo->prepare("SELECT COUNT(*) FROM master_kelas mk $whereSql"); $countSt->execute($params); $total=(int)$countSt->fetchColumn(); $totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages)$page=$totalPages; $offset=($page-1)*$perPage;
$sql = "SELECT mk.*,
        (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.kelas_id = mk.id AND sk.tahun_pelajaran_id = ? AND sk.status = 'aktif') AS jumlah_siswa,
        (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.kelas_id = mk.id AND sk.tahun_pelajaran_id = ?) AS jumlah_riwayat
        FROM master_kelas mk $whereSql
        ORDER BY mk.tingkat ASC, mk.urutan ASC, mk.nama ASC LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql); $st->execute(array_merge([$selectedYearId, $selectedYearId], $params)); $rows = $st->fetchAll();
$summary = $pdo->query("SELECT COUNT(*) semua, SUM(aktif=1) aktif, SUM(aktif=0) nonaktif FROM master_kelas")->fetch();
$page_title = 'Data Kelas';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main kelas-page">
<header class="topbar"><div class="topbar-title">Data Kelas</div><div class="topbar-user"><?= e(user_name()) ?></div></header>
<section class="content">
<div class="page-header">
    <div><h1>Kelas &amp; Rombel</h1><p>Struktur rombel tetap sebagai master. Jumlah dan anggota siswa mengikuti Tahun Pelajaran yang dipilih.</p></div>
    <div class="page-header-actions"><a href="<?= BASE_URL ?>kelas/tambah.php" class="btn btn-primary"><i class="bi bi-building-add"></i> Tambah Kelas</a></div>
</div>
<div class="kelas-summary-grid">
    <div class="dashboard-alert alert-info kelas-summary-card"><div class="alert-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="alert-label">Total Kelas</div><div class="alert-value"><?= (int)$summary['semua'] ?></div><div class="alert-text">Semua status</div></div></div>
    <div class="dashboard-alert alert-success kelas-summary-card"><div class="alert-icon"><i class="bi bi-check-circle-fill"></i></div><div><div class="alert-label">Kelas Aktif</div><div class="alert-value"><?= (int)$summary['aktif'] ?></div><div class="alert-text">Digunakan</div></div></div>
    <div class="dashboard-alert alert-warning kelas-summary-card"><div class="alert-icon"><i class="bi bi-pause-circle-fill"></i></div><div><div class="alert-label">Nonaktif</div><div class="alert-value"><?= (int)$summary['nonaktif'] ?></div><div class="alert-text">Tidak digunakan</div></div></div>
</div>
<div class="dashboard-table-card">
<div class="section-title"><div><h2>Daftar Kelas</h2><p><?= $selectedYear ? 'Menampilkan anggota kelas pada Tapel '.e((string)$selectedYear['nama']) : 'Belum ada Tahun Pelajaran.' ?></p></div></div>
<form method="get" class="filter-bar">
<select name="tahun_pelajaran_id" class="form-control" aria-label="Tahun Pelajaran"><?php foreach($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $selectedYearId === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif'] === 1 ? ' (aktif)' : '' ?></option><?php endforeach; ?></select>
<input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama atau tingkat..." class="form-control">
<select name="status" class="form-control"><option value="aktif" <?= $status==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= $status==='nonaktif'?'selected':'' ?>>Nonaktif</option><option value="semua" <?= $status==='semua'?'selected':'' ?>>Semua</option></select>
<select name="per_page" class="form-control" title="Jumlah per halaman"><option value="5" <?= $perPage===5?'selected':'' ?>>5</option><option value="10" <?= $perPage===10?'selected':'' ?>>10</option><option value="20" <?= $perPage===20?'selected':'' ?>>20</option><option value="50" <?= $perPage===50?'selected':'' ?>>50</option><option value="100" <?= $perPage===100?'selected':'' ?>>100</option></select><button class="btn btn-secondary" type="submit"><i class="bi bi-funnel"></i> Terapkan</button><a class="btn btn-secondary" href="<?= BASE_URL ?>kelas/index.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
</form>
<div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>Nama Kelas</th><th>Tingkat</th><th>Siswa Aktif</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="empty-state">Belum ada data kelas.</td></tr>
<?php else: foreach ($rows as $i => $r): ?><tr>
<td><?= $i + 1 ?></td><td><strong><?= e($r['nama']) ?></strong></td><td><?= (int)$r['tingkat'] ?></td><td><strong><?= (int)$r['jumlah_siswa'] ?></strong><br><small class="text-muted">aktif pada Tapel</small></td>
<td><span class="status-badge <?= $r['aktif'] ? 'status-aktif' : 'status-nonaktif' ?>"><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
<td><div class="table-actions"><a class="btn btn-warning btn-sm btn-icon-sm" title="Edit kelas" aria-label="Edit kelas" href="<?= BASE_URL ?>kelas/edit.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a><a class="btn btn-info btn-sm btn-icon-sm" title="Detail kelas" aria-label="Detail kelas" href="<?= BASE_URL ?>kelas/detail.php?id=<?= (int)$r['id'] ?>&tahun_pelajaran_id=<?= $selectedYearId ?>"><i class="bi bi-eye" aria-hidden="true"></i></a><form method="post" action="<?= BASE_URL ?>kelas/toggle.php" style="display:inline" onsubmit="return confirm('<?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> kelas ini?')"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-icon-sm <?= $r['aktif'] ? 'btn-warning' : 'btn-success' ?>" type="submit" title="<?= $r['aktif'] ? 'Nonaktifkan kelas' : 'Aktifkan kelas' ?>" aria-label="<?= $r['aktif'] ? 'Nonaktifkan kelas' : 'Aktifkan kelas' ?>"><i class="bi <?= $r['aktif'] ? 'bi-pause-circle' : 'bi-check-circle' ?>" aria-hidden="true"></i></button></form><?php if((int)$r['jumlah_riwayat']===0):?><form method="post" action="<?= BASE_URL ?>kelas/delete.php" style="display:inline" onsubmit="return confirm('Hapus kelas ini?')"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus kelas" aria-label="Hapus"><i class="bi bi-trash3" aria-hidden="true"></i></button></form><?php else: ?><span class="text-muted small d-inline-flex align-items-center gap-1" title="Kelas tidak dapat dihapus karena masih memiliki siswa aktif. Nonaktifkan jika kelas tidak digunakan lagi."><i class="bi bi-lock"></i> Masih memiliki riwayat siswa</span><?php endif;?></div></td>
</tr><?php endforeach; endif; ?></tbody></table></div>

<div class="pagination-bar"><div class="text-muted">Menampilkan <?= $total ? ($offset+1).'–'.min($offset+$perPage,$total) : '0' ?> dari <?= $total ?> kelas</div><div class="pagination"><?php for($pg=1;$pg<=$totalPages;$pg++):?><a class="btn btn-sm <?= $pg===$page?'btn-primary':'btn-secondary' ?>" href="?tahun_pelajaran_id=<?=$selectedYearId?>&cari=<?=rawurlencode($cari)?>&status=<?=rawurlencode($status)?>&per_page=<?=$perPage?>&page=<?=$pg?>"><?=$pg?></a><?php endfor;?></div></div>
</div></section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
