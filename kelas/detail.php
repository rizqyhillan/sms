<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$id = (int)($_GET['id'] ?? 0);
$kelas = kelas_find($pdo, $id);
if (!$kelas) { http_response_code(404); exit('Kelas tidak ditemukan.'); }

$years = $pdo->query('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
$activeYearId = 0;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $activeYearId = (int)$y['id']; break; } }
$selectedYearId = (int)($_GET['tahun_pelajaran_id'] ?? $activeYearId);
$selectedYear = null;
foreach ($years as $y) { if ((int)$y['id'] === $selectedYearId) { $selectedYear = $y; break; } }
if (!$selectedYear && $activeYearId > 0) {
    $selectedYearId = $activeYearId;
    foreach ($years as $y) { if ((int)$y['id'] === $selectedYearId) { $selectedYear = $y; break; } }
}

$st = $pdo->prepare(
    "SELECT sk.id, sk.nomor_urut, sk.status, sk.keterangan,
            s.id AS siswa_id, s.nis, s.nisn, s.nama, s.jk
     FROM siswa_kelas sk
     JOIN siswa s ON s.id = sk.siswa_id
     WHERE sk.kelas_id = ? AND sk.tahun_pelajaran_id = ?
     ORDER BY (sk.status = 'aktif') DESC, COALESCE(sk.nomor_urut, 999999) ASC, s.nama ASC"
);
$st->execute([$id, $selectedYearId]);
$students = $st->fetchAll();

$jumlahAktif = 0;
foreach ($students as $student) { if ($student['status'] === 'aktif') $jumlahAktif++; }

$page_title = 'Detail Kelas';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main kelas-page">
<header class="topbar"><div class="topbar-title">Detail Kelas</div><div class="topbar-user"><?= e(user_name()) ?></div></header>
<section class="content">
<div class="page-header">
    <div>
        <h1><?=e($kelas['nama'])?></h1>
        <p><?= $selectedYear ? 'Anggota rombel pada Tapel '.e((string)$selectedYear['nama']) : 'Belum ada Tahun Pelajaran.' ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary btn-back" href="<?=BASE_URL?>kelas/index.php?tahun_pelajaran_id=<?=$selectedYearId?>"><i class="bi bi-arrow-left"></i> Kembali</a>
        <a class="btn btn-warning" href="<?=BASE_URL?>kelas/edit.php?id=<?=$id?>"><i class="bi bi-pencil-square"></i> Edit Rombel</a>
    </div>
</div>

<div class="form-card">
    <div class="detail-grid">
        <div><small>Tahun Pelajaran</small><strong><?=e((string)($selectedYear['nama'] ?? '-'))?></strong></div>
        <div><small>Nama Rombel</small><strong><?=e($kelas['nama'])?></strong></div>
        <div><small>Tingkat</small><strong><?= (int)$kelas['tingkat'] ?></strong></div>
        <div><small>Siswa Aktif</small><strong><?= $jumlahAktif ?></strong></div>
        <div><small>Total Riwayat pada Tapel</small><strong><?=count($students)?></strong></div>
        <div><small>Status Master</small><strong><?= $kelas['aktif'] ? 'Aktif' : 'Nonaktif' ?></strong></div>
    </div>
</div>

<div class="dashboard-table-card">
    <div class="section-title">
        <div><h2>Anggota Kelas</h2><p>Daftar siswa tidak tercampur dengan Tapel lain.</p></div>
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="id" value="<?=$id?>">
            <select name="tahun_pelajaran_id" class="form-control" onchange="this.form.submit()" aria-label="Tahun Pelajaran">
                <?php foreach($years as $y): ?>
                    <option value="<?=(int)$y['id']?>" <?=$selectedYearId===(int)$y['id']?'selected':''?>><?=e((string)$y['nama'])?><?= (int)$y['aktif']===1?' (aktif)':''?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>No.</th><th>NIS</th><th>NISN</th><th>Nama</th><th>JK</th><th>Status Tapel</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if(!$students): ?>
                <tr><td colspan="7" class="empty-state">Belum ada siswa pada kelas ini di Tapel tersebut.</td></tr>
            <?php else: foreach($students as $i=>$student): ?>
                <tr>
                    <td><?= $student['nomor_urut'] !== null ? (int)$student['nomor_urut'] : ($i+1) ?></td>
                    <td><?=e($student['nis'])?></td>
                    <td><?=e((string)($student['nisn'] ?? '-'))?></td>
                    <td><strong><?=e($student['nama'])?></strong></td>
                    <td><?=e((string)($student['jk'] ?? '-'))?></td>
                    <td><span class="status-badge status-<?=e($student['status'])?>"><?=e(ucfirst($student['status']))?></span></td>
                    <td><a class="btn btn-info btn-sm btn-icon-sm" title="Detail siswa" aria-label="Detail siswa" href="<?=BASE_URL?>siswa/detail.php?id=<?=(int)$student['siswa_id']?>&tahun_pelajaran_id=<?=$selectedYearId?>"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
