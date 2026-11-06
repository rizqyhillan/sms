<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
require_role('admin');

$years = $pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC")->fetchAll();
$activeYear = null;
foreach ($years as $y) {
    if ((int)$y['aktif'] === 1) { $activeYear = $y; break; }
}
$activeYearId = (int)($activeYear['id'] ?? 0);
$yearId = (int)($_GET['tahun_pelajaran_id'] ?? $_POST['tahun_pelajaran_id'] ?? $activeYearId);
$yearIds = array_map('intval', array_column($years, 'id'));
if (!in_array($yearId, $yearIds, true)) $yearId = $activeYearId;

$classId = (int)($_GET['kelas_id'] ?? $_POST['kelas_id'] ?? 0);
$cari = trim((string)($_GET['cari'] ?? ''));
$errors = [];
$success = '';

$classesStmt = $pdo->query("SELECT id,nama,tingkat,urutan FROM master_kelas WHERE aktif=1 AND tingkat=9 ORDER BY urutan ASC,nama ASC");
$classes = $classesStmt->fetchAll();
$classIds = array_map('intval', array_column($classes, 'id'));
if ($classId !== 0 && !in_array($classId, $classIds, true)) $classId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
    $tanggal = trim((string)($_POST['tanggal_kelulusan'] ?? date('Y-m-d')));
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));
    $yearId = (int)($_POST['tahun_pelajaran_id'] ?? 0);
    $classId = (int)($_POST['kelas_id'] ?? 0);

    if ($yearId <= 0 || !in_array($yearId, $yearIds, true)) $errors[] = 'Tahun pelajaran tidak valid.';
    if ($classId <= 0 || !in_array($classId, $classIds, true)) $errors[] = 'Kelas 9 wajib dipilih.';
    if ($tanggal === '') $errors[] = 'Tanggal kelulusan wajib diisi.';
    if (!$ids) $errors[] = 'Pilih minimal satu siswa untuk diproses.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $lockStudent = $pdo->prepare('SELECT id,nama,status FROM siswa WHERE id=? FOR UPDATE');
            $lockPlacement = $pdo->prepare("SELECT sk.id,sk.status,mk.nama AS kelas_nama,mk.tingkat
                FROM siswa_kelas sk
                JOIN master_kelas mk ON mk.id=sk.kelas_id
                WHERE sk.siswa_id=? AND sk.tahun_pelajaran_id=? AND sk.kelas_id=?
                ORDER BY (sk.status='aktif') DESC, sk.id DESC LIMIT 1 FOR UPDATE");
            $checkHistory = $pdo->prepare("SELECT COUNT(*) FROM siswa_status_riwayat
                WHERE siswa_id=? AND tahun_pelajaran_id=? AND status='lulus'");
            $insertHistory = $pdo->prepare('INSERT INTO siswa_status_riwayat (siswa_id,tahun_pelajaran_id,status,tanggal_status,tujuan,keterangan,created_by) VALUES (?,?,?,?,?,?,?)');
            $updatePlacement = $pdo->prepare("UPDATE siswa_kelas SET status='lulus', keterangan=CASE WHEN ?='' THEN keterangan ELSE ? END WHERE id=? AND status='aktif'");
            $updateStudent = $pdo->prepare("UPDATE siswa SET status='lulus' WHERE id=? AND status='aktif'");

            $processed = [];
            foreach ($ids as $id) {
                $lockStudent->execute([$id]);
                $student = $lockStudent->fetch();
                if (!$student) throw new RuntimeException('Salah satu siswa tidak ditemukan. Proses dibatalkan.');
                if ((string)$student['status'] !== 'aktif') {
                    throw new RuntimeException('Siswa '.(string)$student['nama'].' tidak berstatus aktif. Proses dibatalkan agar data tidak sebagian berubah.');
                }

                $lockPlacement->execute([$id, $yearId, $classId]);
                $placement = $lockPlacement->fetch();
                if (!$placement || (int)$placement['tingkat'] !== 9 || (string)$placement['status'] !== 'aktif') {
                    throw new RuntimeException('Siswa '.(string)$student['nama'].' tidak memiliki penempatan aktif di kelas 9 pada Tapel yang dipilih. Proses dibatalkan.');
                }

                $checkHistory->execute([$id, $yearId]);
                if ((int)$checkHistory->fetchColumn() > 0) {
                    throw new RuntimeException('Riwayat kelulusan siswa '.(string)$student['nama'].' sudah ada pada Tapel tersebut. Proses dibatalkan.');
                }

                $insertHistory->execute([$id, $yearId, 'lulus', $tanggal, null, $keterangan !== '' ? $keterangan : null, user_id()]);
                $historyId = (int)$pdo->lastInsertId();
                $updatePlacement->execute([$keterangan, $keterangan, (int)$placement['id']]);
                if ($updatePlacement->rowCount() !== 1) {
                    throw new RuntimeException('Penempatan siswa '.(string)$student['nama'].' gagal diperbarui. Proses dibatalkan.');
                }
                $updateStudent->execute([$id]);
                if ($updateStudent->rowCount() !== 1) {
                    throw new RuntimeException('Status induk siswa '.(string)$student['nama'].' gagal diperbarui. Proses dibatalkan.');
                }
                siswa_audit($pdo, 'KELULUSAN', $id, 'Siswa dinyatakan LULUS pada Tapel #'.$yearId.' dari kelas 9; riwayat #'.$historyId);
                $processed[] = (string)$student['nama'];
            }

            $pdo->commit();
            $success = count($processed).' siswa berhasil diproses sebagai LULUS.';
            $cari = '';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$sql = "SELECT s.id,s.nis,s.nisn,s.nama,s.jk,sk.id AS siswa_kelas_id,mk.nama AS kelas_nama,
               tp.nama AS tahun_nama,
               EXISTS(SELECT 1 FROM siswa_status_riwayat r WHERE r.siswa_id=s.id AND r.tahun_pelajaran_id=sk.tahun_pelajaran_id AND r.status='lulus') AS sudah_lulus
        FROM siswa s
        JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif' AND sk.kelas_id=?
        JOIN master_kelas mk ON mk.id=sk.kelas_id AND mk.tingkat=9
        JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id
        WHERE s.status='aktif'";
$params = [$yearId, $classId];
if ($cari !== '') {
    $sql .= " AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ?)";
    $q = '%'.$cari.'%';
    array_push($params, $q, $q, $q);
}
$sql .= ' ORDER BY s.nama ASC';
$list = [];
if ($classId > 0) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $list = $st->fetchAll();
}

$selectedClassName = '-';
foreach ($classes as $c) {
    if ((int)$c['id'] === $classId) { $selectedClassName = (string)$c['nama']; break; }
}
$selectedYearName = '-';
foreach ($years as $y) {
    if ((int)$y['id'] === $yearId) { $selectedYearName = (string)$y['nama']; break; }
}

$page_title = 'Kelulusan';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Kelulusan</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content kelulusan-page">
<div class="page-header">
    <div><h1><i class="bi bi-mortarboard-fill"></i> Kelulusan Siswa</h1><p>Khusus siswa kelas 9. Kelulusan tidak menghapus identitas maupun histori pendidikan, kehadiran, dan sholat.</p></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<div class="form-card">
    <div class="section-title"><div><h2>Langkah 1 — Tentukan Tapel & Kelas</h2><p>Hanya kelas dengan tingkat 9 yang tersedia untuk proses kelulusan.</p></div></div>
    <form method="get" class="form-grid">
        <label>Tahun Pelajaran<select class="form-control" name="tahun_pelajaran_id" required><?php foreach($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $yearId === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif']===1 ? ' · Aktif' : '' ?></option><?php endforeach; ?></select></label>
        <label>Kelas 9<select class="form-control" name="kelas_id" required><option value="0">- Pilih Kelas 9 -</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?=e((string)$c['nama'])?></option><?php endforeach; ?></select></label>
        <label>Cari NIS / NISN / Nama<input class="form-control" type="search" name="cari" value="<?=e($cari)?>" placeholder="Opsional"></label>
        <div class="form-actions" style="align-self:end"><button class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan Siswa</button></div>
    </form>
</div>

<?php if ($classId > 0): ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i> Menampilkan siswa <strong>aktif</strong> pada <strong><?=e($selectedClassName)?></strong>, Tapel <strong><?=e($selectedYearName)?></strong>. Pilih siswa untuk membuat preview sebelum proses kelulusan.</div>
<div class="dashboard-table-card">
<form method="post" id="kelulusanForm" onsubmit="return confirmKelulusan();">
<?=csrf_field()?>
<input type="hidden" name="tahun_pelajaran_id" value="<?=$yearId?>">
<input type="hidden" name="kelas_id" value="<?=$classId?>">
<div class="section-title"><div><h2>Langkah 2 — Pilih Siswa</h2><p><?=count($list)?> siswa aktif ditemukan.</p></div><label style="display:flex;align-items:center;gap:8px;font-weight:600"><input type="checkbox" id="checkAll"> Pilih semua</label></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th style="width:50px">Pilih</th><th>NIS</th><th>NISN</th><th>Nama</th><th>JK</th><th>Kelas</th><th>Status</th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="7" class="empty-state">Tidak ada siswa aktif pada kelas 9 dan Tapel yang dipilih.</td></tr>
<?php else: foreach($list as $r): ?><tr><td><input class="student-check" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td><td><?=e((string)$r['nis'])?></td><td><?=e((string)($r['nisn']??'-'))?></td><td><strong><?=e((string)$r['nama'])?></strong></td><td><?=e((string)($r['jk']??'-'))?></td><td><?=e((string)$r['kelas_nama'])?></td><td><span class="status-badge status-aktif">AKTIF</span></td></tr><?php endforeach; endif; ?>
</tbody></table></div>
<div class="form-card" style="margin-top:20px"><div class="section-title"><div><h2>Langkah 3 — Preview & Konfirmasi</h2><p>Data yang dipilih akan menjadi LULUS. Histori kelas 9 tetap disimpan.</p></div></div><div class="form-grid"><label>Tanggal Kelulusan *<input class="form-control" type="date" name="tanggal_kelulusan" value="<?=e(date('Y-m-d'))?>" required></label><label>Keterangan<textarea class="form-control" name="keterangan" rows="2" placeholder="Opsional, misalnya: Lulus Tahun Pelajaran 2026/2027"></textarea></label></div><div class="alert alert-warning" style="margin-top:16px"><i class="bi bi-shield-check"></i> <strong>Perhatian:</strong> proses ini mengubah status siswa menjadi <strong>LULUS</strong> dan penempatan Tapel menjadi <strong>LULUS</strong>. Proses hanya dijalankan setelah semua siswa yang dipilih lolos pemeriksaan integritas.</div><div class="form-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/kelulusan/index.php?tahun_pelajaran_id=<?=$yearId?>&kelas_id=<?=$classId?>"><i class="bi bi-arrow-counterclockwise"></i> Reset</a><button class="btn btn-primary" type="submit"><i class="bi bi-mortarboard-fill"></i> Proses Kelulusan (<span id="selectedCount">0</span>)</button></div></div>
</form></div>
<?php endif; ?>
</section></main>
<script>
(function(){
 const all=document.getElementById('checkAll');
 const boxes=()=>Array.from(document.querySelectorAll('.student-check'));
 const count=()=>{const n=boxes().filter(x=>x.checked).length; const el=document.getElementById('selectedCount'); if(el) el.textContent=n; if(all) all.checked=boxes().length>0&&n===boxes().length;};
 all?.addEventListener('change',()=>{boxes().forEach(x=>x.checked=all.checked);count();});
 boxes().forEach(x=>x.addEventListener('change',count));
 window.confirmKelulusan=function(){
   const n=boxes().filter(x=>x.checked).length;
   if(n<1){alert('Pilih minimal satu siswa.');return false;}
   return confirm('Proses '+n+' siswa menjadi LULUS? Data identitas, kelas, kehadiran, dan sholat tidak dihapus.');
 };
 count();
})();
</script>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
