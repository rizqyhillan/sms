<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
$errors = [];
$nama = trim((string)($_POST['nama'] ?? ''));
$tingkat = (string)($_POST['tingkat'] ?? '7');
$aktif = (string)($_POST['aktif'] ?? '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $tingkatInt = (int)$tingkat;
    if ($nama === '') $errors[] = 'Nama kelas wajib diisi.';
    if (!in_array($tingkatInt, [7,8,9], true)) $errors[] = 'Tingkat harus 7, 8, atau 9.';
    if (!in_array($aktif, ['0','1'], true)) $errors[] = 'Status tidak valid.';
    if (!$errors) {
        try {
            // Urutan dikelola otomatis agar tidak menjadi field yang perlu diisi pengguna.
            $stMax = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM master_kelas WHERE tingkat=?');
            $stMax->execute([$tingkatInt]);
            $urutanInt = (int)$stMax->fetchColumn();
            $st = $pdo->prepare('INSERT INTO master_kelas (nama, tingkat, urutan, aktif) VALUES (?, ?, ?, ?)');
            $st->execute([$nama, $tingkatInt, $urutanInt, (int)$aktif]);
            $id = (int)$pdo->lastInsertId();
            kelas_audit($pdo, 'CREATE', $id, 'Menambahkan kelas ' . $nama);
            redirect('kelas/detail.php?id=' . $id . '&saved=1');
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) $errors[] = 'Nama kelas sudah digunakan.'; else throw $e;
        }
    }
}
$page_title = 'Tambah Kelas'; require_once __DIR__ . '/../layouts/header.php'; require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main kelas-page"><header class="topbar"><div class="topbar-title">Tambah Kelas</div><div class="topbar-user"><?= e(user_name()) ?></div></header><section class="content">
<div class="page-header"><div><h1>Tambah Kelas</h1><p>Buat master kelas yang dapat digunakan untuk penempatan siswa.</p></div><a class="btn btn-secondary" href="<?= BASE_URL ?>kelas/index.php">Kembali</a></div>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="form-card"><form method="post"><?= csrf_field() ?><div class="form-grid">
<label>Nama Kelas *<input class="form-control" name="nama" required maxlength="20" value="<?= e($nama) ?>" placeholder="Contoh: 7A"></label>
<label>Tingkat *<select class="form-control" name="tingkat"><option value="7" <?= $tingkat==='7'?'selected':'' ?>>7</option><option value="8" <?= $tingkat==='8'?'selected':'' ?>>8</option><option value="9" <?= $tingkat==='9'?'selected':'' ?>>9</option></select></label>
<label>Status<select class="form-control" name="aktif"><option value="1" <?= $aktif==='1'?'selected':'' ?>>Aktif</option><option value="0" <?= $aktif==='0'?'selected':'' ?>>Nonaktif</option></select></label>
</div><div class="form-actions"><button class="btn btn-success" type="submit"><i class="bi bi-check2-circle"></i> Simpan Kelas</button><a class="btn btn-secondary btn-back" href="<?= BASE_URL ?>kelas/index.php"><i class="bi bi-arrow-left"></i> Batal</a></div></form></div>
</section></main><?php require_once __DIR__ . '/../layouts/footer.php'; ?>
