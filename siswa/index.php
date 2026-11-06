<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$cari = trim((string)($_GET['cari'] ?? ''));
$status = (string)($_GET['status'] ?? 'aktif');
if (!in_array($status, ['aktif','lulus','pindah','keluar','nonaktif','semua'], true)) $status = 'aktif';

$kelas_id = (int)($_GET['kelas_id'] ?? 0);

$allowedPerPage = [5, 10, 20, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $allowedPerPage, true)) $per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$years = $pdo->query("SELECT id, nama, aktif, tanggal_mulai, tanggal_selesai FROM tahun_pelajaran ORDER BY tanggal_mulai DESC, id DESC")->fetchAll();
$active_year = null;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $active_year = $y; break; } }
$active_year_id = $active_year ? (int)$active_year['id'] : 0;
$selected_year_id = (int)($_GET['tahun_pelajaran_id'] ?? $active_year_id);
$selected_year = null;
foreach ($years as $y) { if ((int)$y['id'] === $selected_year_id) { $selected_year = $y; break; } }
if (!$selected_year) { $selected_year_id = $active_year_id; $selected_year = $active_year; }


$where = [];
$params = [];
if ($status !== 'semua') {
    if ($status === 'nonaktif') { $where[] = 's.status = ?'; $params[] = $status; }
    else { $where[] = 'sk.status = ?'; $params[] = $status; }
}
if ($kelas_id > 0) { $where[] = 'mk.id = ?'; $params[] = $kelas_id; }
if ($cari !== '') {
    $where[] = '(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR s.kode_qr LIKE ?)';
    $keyword = '%' . $cari . '%';
    array_push($params, $keyword, $keyword, $keyword, $keyword);
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(DISTINCT s.id)
             FROM siswa s
             LEFT JOIN siswa_kelas sk ON sk.id = (
                 SELECT sk2.id FROM siswa_kelas sk2
                 WHERE sk2.siswa_id = s.id AND sk2.tahun_pelajaran_id = ?
                 ORDER BY (sk2.status = 'aktif') DESC, sk2.id DESC LIMIT 1
             )
             LEFT JOIN master_kelas mk ON mk.id = sk.kelas_id
             $where_sql";
$stmt = $pdo->prepare($countSql);
$stmt->execute(array_merge([$selected_year_id], $params));
$total_data = (int)$stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_data / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$kelas_stmt = $pdo->prepare("SELECT DISTINCT mk.id, mk.nama, mk.tingkat, mk.urutan
    FROM master_kelas mk
    JOIN siswa_kelas skc ON skc.kelas_id = mk.id AND skc.tahun_pelajaran_id = ?
    WHERE mk.aktif = 1
    ORDER BY mk.tingkat ASC, mk.urutan ASC, mk.nama ASC");
$kelas_stmt->execute([$selected_year_id]);
$kelas_list = $kelas_stmt->fetchAll();

$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.jk, s.no_hp, s.kode_qr, s.status AS status_identitas,
               sk.status AS status_tapel, s.created_at,
               mk.nama AS nama_kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id = (
            SELECT sk2.id FROM siswa_kelas sk2
            WHERE sk2.siswa_id = s.id AND sk2.tahun_pelajaran_id = ?
            ORDER BY (sk2.status = 'aktif') DESC, sk2.id DESC LIMIT 1
        )
        LEFT JOIN master_kelas mk ON mk.id = sk.kelas_id
        $where_sql
        ORDER BY s.nama ASC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$selected_year_id], $params));
$siswa_list = $stmt->fetchAll();

$summaryStmt = $pdo->prepare("SELECT
    COUNT(*) AS semua,
    SUM(sk.status='aktif') AS aktif,
    SUM(sk.status='lulus') AS lulus,
    SUM(sk.status='pindah') AS pindah,
    SUM(sk.status='keluar') AS keluar
    FROM siswa_kelas sk
    WHERE sk.tahun_pelajaran_id = ?");
$summaryStmt->execute([$selected_year_id]);
$summary = $summaryStmt->fetch() ?: [];
$total_semua = (int)($summary['semua'] ?? 0);
$total_aktif = (int)($summary['aktif'] ?? 0);
$total_lulus = (int)($summary['lulus'] ?? 0);
$total_pindah = (int)($summary['pindah'] ?? 0);
$total_keluar = (int)($summary['keluar'] ?? 0);
$identitasStmt = $pdo->query("SELECT COUNT(*) FROM siswa");
$total_identitas = (int)$identitasStmt->fetchColumn();
$total_tanpa_penempatan = max(0, $total_identitas - $total_semua);

function siswa_page_url(int $page_number): string {
    $query = $_GET;
    $query['page'] = $page_number;
    return '?' . http_build_query($query);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main siswa-page">
<header class="topbar"><div class="topbar-title">Data Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<?php if (!empty($_SESSION['flash_message'])): ?><div class="alert alert-info"><?=e((string)$_SESSION['flash_message'])?></div><?php unset($_SESSION['flash_message']); endif; ?>
<div class="page-header">
    <div><h1>Master Data Siswa</h1><p>Identitas siswa bersifat permanen. Daftar di bawah mengikuti <strong>Tahun Pelajaran</strong> yang dipilih.</p></div>
    <div class="page-header-actions">
        <a href="<?=BASE_URL?>siswa/tambah.php" class="btn btn-primary btn-fn-add"><i class="bi bi-person-plus"></i> Tambah Siswa</a>
        <a href="<?=BASE_URL?>siswa/import.php" class="btn btn-success btn-fn-import"><i class="bi bi-file-earmark-spreadsheet"></i> Impor XLSX</a>
        <a href="<?=BASE_URL?>siswa/kartu_cetak.php" class="btn btn-info btn-fn-card"><i class="bi bi-person-vcard"></i> Kartu Absensi</a>
    </div>
</div>

<div class="dashboard-grid siswa-master-stat-grid">
    <div class="dashboard-alert alert-info siswa-master-stat"><div class="alert-icon"><i class="bi bi-people-fill"></i></div><div><div class="alert-label">Siswa di Tapel</div><div class="alert-value"><?=$total_semua?></div><div class="alert-text"><?=e((string)($selected_year['nama'] ?? '-'))?></div></div></div>
    <div class="dashboard-alert alert-success siswa-master-stat"><div class="alert-icon"><i class="bi bi-person-check-fill"></i></div><div><div class="alert-label">Aktif di Tapel</div><div class="alert-value"><?=$total_aktif?></div><div class="alert-text">Penempatan aktif</div></div></div>
    <div class="dashboard-alert alert-purple siswa-master-stat"><div class="alert-icon"><i class="bi bi-mortarboard-fill"></i></div><div><div class="alert-label">Lulus di Tapel</div><div class="alert-value"><?=$total_lulus?></div><div class="alert-text">Histori Tapel</div></div></div>
    <div class="dashboard-alert alert-warning siswa-master-stat"><div class="alert-icon"><i class="bi bi-truck"></i></div><div><div class="alert-label">Pindah di Tapel</div><div class="alert-value"><?=$total_pindah?></div><div class="alert-text">Histori Tapel</div></div></div>
    <div class="dashboard-alert alert-danger siswa-master-stat"><div class="alert-icon"><i class="bi bi-box-arrow-right"></i></div><div><div class="alert-label">Keluar di Tapel</div><div class="alert-value"><?=$total_keluar?></div><div class="alert-text">Histori Tapel</div></div></div>
    <div class="dashboard-alert alert-teal siswa-master-stat"><div class="alert-icon"><i class="bi bi-person-dash-fill"></i></div><div><div class="alert-label">Belum Ada Penempatan</div><div class="alert-value"><?=$total_tanpa_penempatan?></div><div class="alert-text">Dari <?=number_format($total_identitas)?> identitas</div></div></div>
</div>


<div class="dashboard-table-card">
<div class="section-title"><div><h2>Daftar Siswa</h2><p><?= $selected_year ? 'Menampilkan penempatan siswa pada Tapel '.e((string)$selected_year['nama']) : 'Belum ada tahun pelajaran.' ?></p></div></div>

<div class="alert alert-info" style="margin-bottom:16px"><i class="bi bi-info-circle"></i> <strong>Aturan tampilan:</strong> mengganti Tapel tidak menghapus siswa. Sistem hanya menampilkan siswa yang mempunyai riwayat penempatan pada Tapel tersebut. Data lama tetap tersimpan.</div>

<form method="get" class="filter-bar student-filter-bar">
    <select name="tahun_pelajaran_id" class="form-control" aria-label="Tahun Pelajaran">
        <?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $selected_year_id === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif'] === 1 ? ' (aktif)' : '' ?></option><?php endforeach; ?>
    </select>
    <input type="search" name="cari" value="<?=e($cari)?>" placeholder="Cari NIS, NISN, nama, atau QR..." class="form-control">
    <select name="kelas_id" class="form-control">
        <option value="0">Semua kelas</option>
        <?php foreach ($kelas_list as $k): ?><option value="<?=(int)$k['id']?>" <?=$kelas_id===(int)$k['id']?'selected':''?>><?=e((string)$k['nama'])?></option><?php endforeach; ?>
    </select>
    <select name="status" class="form-control">
        <option value="aktif" <?=$status==='aktif'?'selected':''?>>Aktif pada Tapel</option>
        <option value="lulus" <?=$status==='lulus'?'selected':''?>>Lulus</option><option value="pindah" <?=$status==='pindah'?'selected':''?>>Pindah</option><option value="keluar" <?=$status==='keluar'?'selected':''?>>Keluar</option>
        <option value="nonaktif" <?=$status==='nonaktif'?'selected':''?>>Nonaktif</option>
        <option value="semua" <?=$status==='semua'?'selected':''?>>Semua</option>
    </select>
    <label class="student-per-page">Tampilkan
        <select name="per_page" class="form-control" onchange="this.form.submit()">
            <?php foreach ($allowedPerPage as $n): ?><option value="<?=$n?>" <?=$per_page===$n?'selected':''?>><?=$n?></option><?php endforeach; ?>
        </select> data
    </label>
    <button class="btn btn-secondary student-filter-apply" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
    <a class="btn btn-secondary student-filter-reset" href="<?=BASE_URL?>siswa/index.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
</form>

<form method="post" action="<?=BASE_URL?>siswa/delete.php" id="bulkSiswaForm">
<?=csrf_field()?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin:12px 0">
    <label><input type="checkbox" id="checkAllSiswa"> Pilih semua pada halaman ini</label>
    <button class="btn btn-danger btn-sm" type="submit" name="bulk_delete" value="1" onclick="return confirm('Hapus siswa yang dipilih? Data yang memiliki riwayat/transaksi tidak akan dihapus.')"><i class="bi bi-trash3"></i> Hapus Terpilih</button>
</div>
<div class="table-responsive">
<table class="data-table student-master-table">
<thead><tr><th style="width:40px"></th><th>NIS</th><th>Nama</th><th>JK</th><th>Kelas</th><th>Status</th><th>QR</th><th>Aksi</th></tr></thead>
<tbody>
<?php if (!$siswa_list): ?>
<tr><td colspan="8" class="empty-state">Belum ada data siswa.</td></tr>
<?php else: foreach ($siswa_list as $row): ?>
<tr>
<td><input type="checkbox" name="ids[]" value="<?=(int)$row['id']?>"></td>
<td><?=e((string)$row['nis'])?></td>
<td><strong><?=e((string)$row['nama'])?></strong><br><small><?=e((string)($row['nisn'] ?? ''))?></small></td>
<td><?=e((string)($row['jk'] ?? '-'))?></td>
<td><?=e((string)($row['nama_kelas'] ?? 'Belum ditempatkan'))?></td>
<td><?php $displayStatus = $row['status_tapel'] ?: $row['status_identitas']; $statusLabel = $row['status_tapel'] ? ucfirst((string)$row['status_tapel']) : ucfirst((string)$row['status_identitas']); ?><span class="status-badge status-<?=e((string)$displayStatus)?>"><?=e($statusLabel)?></span></td>
<td><code><?=e((string)$row['kode_qr'])?></code></td>
<td><div class="table-actions">
<a class="btn btn-info btn-sm btn-icon-sm btn-fn-detail" title="Detail siswa" aria-label="Detail siswa" href="<?=BASE_URL?>siswa/detail.php?id=<?=(int)$row['id']?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
<a class="btn btn-warning btn-sm btn-icon-sm btn-fn-edit" title="Edit siswa" aria-label="Edit siswa" href="<?=BASE_URL?>siswa/edit.php?id=<?=(int)$row['id']?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
<a class="btn btn-primary btn-sm btn-icon-sm btn-fn-qr" title="Kartu / QR siswa" aria-label="Kartu / QR siswa" href="<?=BASE_URL?>siswa/kartu.php?id=<?=(int)$row['id']?>"><i class="bi bi-qr-code" aria-hidden="true"></i></a>
<form method="post" action="<?=BASE_URL?>siswa/delete.php" style="display:inline" onsubmit="return confirm('Hapus siswa ini? Jika memiliki riwayat/transaksi, penghapusan akan ditolak.')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus" aria-label="Hapus"><i class="bi bi-trash3" aria-hidden="true"></i></button></form>
</div></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</form>

<div class="pagination">
    <span>Menampilkan <?=$total_data ? $offset+1 : 0?>–<?=min($offset+$per_page,$total_data)?> dari <?=$total_data?> data</span>
    <span>Halaman <?=$page?> dari <?=$total_pages?></span>
    <div>
        <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="<?=e(siswa_page_url($page-1))?>">‹</a><?php endif; ?>
        <?php if ($page < $total_pages): ?><a class="btn btn-secondary btn-sm" href="<?=e(siswa_page_url($page+1))?>">›</a><?php endif; ?>
    </div>
</div>
</div>
</section>
</main>
<script>
document.getElementById('checkAllSiswa')?.addEventListener('change', function () {
    document.querySelectorAll('#bulkSiswaForm input[name="ids[]"]').forEach(function (el) { el.checked = this.checked; }, this);
});
</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
