<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);

if (user_role() === 'kepala_sekolah') {
    http_response_code(403);
    exit('Detail BK tidak tersedia untuk role Kepala Sekolah.');
}

$siswaId = max(0, (int)($_GET['siswa_id'] ?? $_POST['siswa_id'] ?? 0));
if ($siswaId > 0 && !bk_can_view_student($pdo, $siswaId)) {
    http_response_code(403);
    exit('Akses siswa BK ditolak.');
}

$siswa = $siswaId > 0 ? bk_get_student($pdo, $siswaId) : null;
if ($siswaId > 0 && !$siswa) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

$activeTapelId = (int)($pdo->query("SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
$studentRows = [];
if ($activeTapelId > 0) {
    $stStudents = $pdo->prepare(<<<'SQL'
        SELECT s.id, s.nis, s.nisn, s.nama,
               sk.kelas_id, mk.nama AS kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id
            FROM siswa_kelas sk2
            WHERE sk2.siswa_id=s.id
              AND sk2.tahun_pelajaran_id=?
              AND sk2.status='aktif'
            ORDER BY sk2.id DESC LIMIT 1
        )
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.status='aktif'
        ORDER BY mk.tingkat, mk.urutan, mk.nama, s.nama
    SQL);
    $stStudents->execute([$activeTapelId]);
    $studentRows = $stStudents->fetchAll();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $tanggal = trim((string)($_POST['tanggal'] ?? date('Y-m-d')));
    $waktu = trim((string)($_POST['waktu'] ?? date('H:i:s')));
    $jenis = trim((string)($_POST['jenis'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
    $hasil = trim((string)($_POST['hasil'] ?? ''));
    $tindakLanjut = trim((string)($_POST['tindak_lanjut'] ?? ''));
    $statusCatatan = trim((string)($_POST['status'] ?? 'baru'));
    $guruBkId = bk_current_guru_id($pdo);

    if ($siswaId <= 0 || !bk_can_view_student($pdo, $siswaId)) {
        $error = 'Pilih kelas terlebih dahulu, kemudian pilih siswa.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $error = 'Tanggal tidak valid.';
    } elseif ($jenis === '') {
        $error = 'Jenis layanan wajib diisi.';
    } elseif ($deskripsi === '') {
        $error = 'Deskripsi wajib diisi.';
    } elseif (!in_array($statusCatatan, ['baru','proses','selesai'], true)) {
        $error = 'Status catatan tidak valid.';
    } elseif ($guruBkId <= 0 && user_role() === 'guru_bk') {
        $error = 'Akun Guru BK belum terhubung ke data guru.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO catatan_bk
                (siswa_id, tanggal, waktu, jenis, deskripsi, hasil, tindak_lanjut, status, guru_bk_id, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $siswaId,
            $tanggal,
            $waktu !== '' ? $waktu : null,
            $jenis,
            $deskripsi,
            $hasil !== '' ? $hasil : null,
            $tindakLanjut !== '' ? $tindakLanjut : null,
            $statusCatatan,
            $guruBkId > 0 ? $guruBkId : null
        ]);

        $id = (int)$pdo->lastInsertId();
        bk_audit($pdo, 'create', $id, 'Membuat catatan BK untuk siswa ID '.$siswaId);

        header('Location: '.BASE_URL.'bk/catatan.php?siswa_id='.$siswaId.'&saved=1');
        exit;
    }
}

if ($siswaId > 0) {
$notesStmt = $pdo->prepare("
    SELECT
        cb.id, cb.tanggal, cb.waktu, cb.jenis, cb.status,
        cb.guru_bk_id, g.nama AS guru_bk
    FROM catatan_bk cb
    LEFT JOIN guru g ON g.id=cb.guru_bk_id
    WHERE cb.siswa_id=?
    ORDER BY cb.tanggal DESC, cb.id DESC
");
$notesStmt->execute([$siswaId]);
$notes = $notesStmt->fetchAll();
} else {
    $notes = [];
}

$page_title = 'Catatan BK';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main">
<header class="topbar">
    <div class="topbar-title">Catatan BK</div>
    <div class="topbar-user"><?=e(user_name())?></div>
</header>

<section class="content">
<style>
.bk-private{padding:13px 15px;border-radius:11px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;margin-bottom:16px;display:flex;gap:10px}
.bk-student-card{display:flex;align-items:center;gap:14px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:13px;margin-bottom:16px}
.bk-student-avatar{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:#ede9fe;color:#6d28d9;font-size:20px}
.bk-student-name{font-size:18px;font-weight:850}.bk-student-meta{font-size:12px;color:#64748b;margin-top:3px}
</style>

<div class="page-header">
    <div><h1><i class="bi bi-journal-text" style="margin-right:8px"></i>Catatan BK</h1><p>Riwayat layanan BK untuk siswa terpilih.</p></div>
    <div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>bk/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
</div>

<div class="bk-private"><i class="bi bi-lock-fill"></i><div><strong>Catatan privat.</strong> Isi konseling hanya dapat diakses melalui modul BK.</div></div>

<form method="get" class="dashboard-table-card" style="margin-bottom:16px">
    <div class="section-title"><div><h2>Pilih Siswa</h2><p>Untuk efisiensi, pilih kelas terlebih dahulu lalu pilih siswa yang akan diberi layanan BK.</p></div></div>
    <div class="student-picker" data-student-picker data-items="<?=e(json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'nis'=>(string)$r['nis'],'nisn'=>(string)($r['nisn']??''),'nama'=>(string)$r['nama'],'kelas_id'=>(int)($r['kelas_id']??0),'kelas'=>(string)($r['kelas']??'')],$studentRows),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
        <input type="hidden" name="siswa_id" data-student-value value="<?=e((string)$siswaId)?>">
        <div class="student-picker-toolbar">
            <select class="form-control" data-student-class><option value="">Pilih Kelas Terlebih Dahulu</option></select>
            <input class="form-control" type="search" data-student-search placeholder="Pilih kelas terlebih dahulu, lalu cari nama/NIS/NISN..." autocomplete="off">
        </div>
        <div data-student-selected></div>
        <div class="student-picker-results" data-student-results></div>
        <div class="student-picker-empty" data-student-empty hidden>Tidak ada siswa yang sesuai.</div>
        <small class="student-picker-help"><i class="bi bi-funnel"></i> Pilih kelas terlebih dahulu, kemudian pilih siswa.</small>
    </div>
    <div class="form-actions" style="margin-top:14px"><button class="btn btn-primary" type="submit"><i class="bi bi-arrow-right-circle"></i> Buka Catatan Siswa</button></div>
</form>

<?php if (!$siswa): ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i> Pilih siswa terlebih dahulu untuk membuka formulir catatan BK.</div>
<?php else: ?>
<div class="bk-student-card">
    <div class="bk-student-avatar"><i class="bi bi-person-fill"></i></div>
    <div>
        <div class="bk-student-name"><?=e($siswa['nama'])?></div>
        <div class="bk-student-meta">NIS <?=e($siswa['nis'])?><?=!empty($siswa['nisn'])?' · NISN '.e($siswa['nisn']):''?> · Kelas <?=e($siswa['kelas'] ?? 'Belum ditempatkan')?></div>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> Catatan BK berhasil disimpan.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div>
<?php endif; ?>

<div class="dashboard-grid" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr)">
    <div class="dashboard-table-card">
        <div class="section-title"><div><h2>Tambah Catatan</h2><p>Gunakan struktur catatan BK yang tersedia pada database.</p></div></div>

        <form method="post">
            <?=csrf_field()?>
            <input type="hidden" name="siswa_id" value="<?=$siswaId?>">

            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="<?=e($_POST['tanggal'] ?? date('Y-m-d'))?>" required>

            <label class="form-label" style="margin-top:12px">Waktu</label>
            <input type="time" name="waktu" class="form-control" value="<?=e($_POST['waktu'] ?? date('H:i'))?>">

            <label class="form-label" style="margin-top:12px">Jenis Layanan</label>
            <input type="text" name="jenis" class="form-control" maxlength="190" value="<?=e($_POST['jenis'] ?? '')?>" placeholder="Contoh: Konseling Individu" required>

            <label class="form-label" style="margin-top:12px">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="5" placeholder="Deskripsi permasalahan/konteks layanan..." required><?=e($_POST['deskripsi'] ?? '')?></textarea>

            <label class="form-label" style="margin-top:12px">Hasil</label>
            <textarea name="hasil" class="form-control" rows="4" placeholder="Hasil layanan/konseling..."><?=e($_POST['hasil'] ?? '')?></textarea>

            <label class="form-label" style="margin-top:12px">Tindak Lanjut</label>
            <textarea name="tindak_lanjut" class="form-control" rows="4" placeholder="Tindak lanjut yang disepakati..."><?=e($_POST['tindak_lanjut'] ?? '')?></textarea>

            <label class="form-label" style="margin-top:12px">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['baru'=>'Baru','proses'=>'Proses','selesai'=>'Selesai'] as $value=>$label): ?>
                    <option value="<?=$value?>" <?=($_POST['status'] ?? 'baru')===$value?'selected':''?>><?=$label?></option>
                <?php endforeach; ?>
            </select>

            <div style="margin-top:14px">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan Catatan</button>
            </div>
        </form>
    </div>

    <div class="dashboard-table-card">
        <div class="section-title"><div><h2>Riwayat Catatan</h2><p><?=number_format(count($notes))?> catatan tersimpan.</p></div></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Tanggal</th><th>Jenis</th><th>Status</th><th>Guru BK</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if (!$notes): ?>
                    <tr><td colspan="5" class="empty-state">Belum ada catatan.</td></tr>
                <?php else: foreach ($notes as $note): ?>
                    <tr>
                        <td><?=e($note['tanggal'])?><?=!empty($note['waktu'])?' '.e($note['waktu']):''?></td>
                        <td><strong><?=e($note['jenis'])?></strong></td>
                        <td><span class="bk-status bk-status-<?=e($note['status'])?>"><?=e(ucfirst($note['status']))?></span></td>
                        <td><?=e($note['guru_bk'] ?? '-')?></td>
                        <td><a class="btn btn-sm btn-secondary" href="<?=BASE_URL?>bk/catatan_detail.php?id=<?=$note['id']?>"><i class="bi bi-eye"></i></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

</section>
</main>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
