<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';

perpus_require_manage();

$errors = [];
$success = '';
$required = ['kode_buku','isbn','judul','penulis','penerbit','tahun_terbit','kategori','jumlah_eksemplar'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $file = $_FILES['file'] ?? null;

    if (!$file || (int)$file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File XLSX belum dipilih atau gagal diunggah.';
    } elseif (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'File harus berformat .xlsx.';
    } elseif ((int)$file['size'] > 10 * 1024 * 1024) {
        $errors[] = 'Ukuran file maksimal 10 MB.';
    } else {
        try {
            $rows = xlsx_read_rows((string)$file['tmp_name']);
            if (!$rows) throw new RuntimeException('Worksheet XLSX tidak berisi data.');

            $header = xlsx_normalize_header($rows[0]);
            if ($header !== $required) {
                $errors[] = 'Header XLSX tidak sesuai. Yang diharapkan: ' . implode(', ', $required) . '. Header yang terbaca: ' . ($header ? implode(', ', $header) : '(kosong)') . '.';
            } else {
                $pdo->beginTransaction();
                try {
                    $categoryStmt = $pdo->prepare('SELECT id FROM perpustakaan_kategori WHERE kode=? AND aktif=1 LIMIT 1');
                    $duplicateStmt = $pdo->prepare('SELECT 1 FROM perpustakaan_buku WHERE kode_buku=? LIMIT 1');
                    $insert = $pdo->prepare("INSERT INTO perpustakaan_buku
                        (kode_buku,isbn,judul,penulis,penerbit,tahun_terbit,kategori_id,stok,lokasi,kondisi,status,keterangan)
                        VALUES(?,?,?,?,?,?,?,? ,NULL,'baik','tersedia',NULL)");
                    $exemplarInsert = $pdo->prepare("INSERT INTO perpustakaan_eksemplar (buku_id,kode_eksemplar,kondisi,status,lokasi) VALUES (?,?,?,?,?)");

                    $count = 0;
                    $line = 1;
                    $seen = [];

                    foreach (array_slice($rows, 1) as $row) {
                        $line++;
                        $row = xlsx_flexible_row($row, 8);
                        $row = array_pad($row, 8, '');
                        [$kode,$isbn,$judul,$penulis,$penerbit,$tahun,$kategori,$stok] = array_map(
                            static fn($v) => trim((string)$v),
                            array_slice($row, 0, 8)
                        );

                        if ($kode === '' && $judul === '') continue;
                        if ($kode === '') throw new RuntimeException("Baris $line: kode_buku wajib diisi.");
                        if ($judul === '') throw new RuntimeException("Baris $line: judul wajib diisi.");
                        if (isset($seen[strtolower($kode)])) throw new RuntimeException("Baris $line: kode_buku '$kode' duplikat di dalam file.");
                        $seen[strtolower($kode)] = true;

                        if ($isbn !== '' && mb_strlen($isbn) > 50) throw new RuntimeException("Baris $line: ISBN terlalu panjang.");
                        if ($tahun !== '') {
                            if (!preg_match('/^\d{4}$/', $tahun) || (int)$tahun < 1900 || (int)$tahun > 2100) {
                                throw new RuntimeException("Baris $line: tahun_terbit harus 4 digit antara 1900-2100.");
                            }
                        }
                        if ($stok === '') $stok = '0';
                        if (!ctype_digit($stok)) throw new RuntimeException("Baris $line: stok harus bilangan bulat 0 atau lebih.");
                        $stokInt = (int)$stok;

                        $kategoriId = null;
                        if ($kategori !== '') {
                            $categoryStmt->execute([$kategori]);
                            $kategoriId = $categoryStmt->fetchColumn();
                            if (!$kategoriId) throw new RuntimeException("Baris $line: kode kategori '$kategori' tidak ditemukan atau tidak aktif.");
                            $kategoriId = (int)$kategoriId;
                        }

                        $duplicateStmt->execute([$kode]);
                        if ($duplicateStmt->fetchColumn()) throw new RuntimeException("Baris $line: kode_buku '$kode' sudah ada di Data Buku. Import dibatalkan agar tidak terjadi duplikasi.");

                        $insert->execute([
                            $kode,
                            $isbn !== '' ? $isbn : null,
                            $judul,
                            $penulis !== '' ? $penulis : null,
                            $penerbit !== '' ? $penerbit : null,
                            $tahun !== '' ? (int)$tahun : null,
                            $kategoriId,
                            $stokInt
                        ]);
                        $newBookId=(int)$pdo->lastInsertId();
                        for($n=1;$n<=$stokInt;$n++){
                            $ekKode=$kode.'-'.str_pad((string)$n,4,'0',STR_PAD_LEFT);
                            $exemplarInsert->execute([$newBookId,$ekKode,'baik','tersedia',null]);
                        }
                        $count++;
                    }

                    perpus_sync_all_stock($pdo);
                    perpus_audit_detail($pdo, 'IMPORT', 0, 'Impor ' . $count . ' buku dari XLSX', [], ['jumlah_buku'=>$count]);
                    $pdo->commit();
                    $success = "Import berhasil: $count buku.";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$page_title = 'Import Data Buku';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Import Data Buku</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><i class="bi bi-file-earmark-spreadsheet"></i> Import Data Buku</h1><p>Masukkan banyak buku sekaligus menggunakan file XLSX.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>perpustakaan/buku.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php foreach($errors as $error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach; ?>
<?php if($success): ?><div class="alert alert-success"><?=e($success)?></div><?php endif; ?>
<div class="form-card">
<div class="section-title"><div><h2>Format Excel (XLSX)</h2><p>Header harus berada pada baris pertama dan urutannya jangan diubah.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>perpustakaan/template_buku.xlsx"><i class="bi bi-download"></i> Download Template XLSX</a></div>
<p><strong>Header:</strong> <code>kode_buku, isbn, judul, penulis, penerbit, tahun_terbit, kategori, jumlah_eksemplar</code></p>
<div class="alert alert-info"><strong>Catatan:</strong> <code>kategori</code> diisi dengan <strong>kode kategori</strong> dari menu Kategori Buku. Boleh kosong. Jika diisi, kode harus sudah ada dan aktif.</div>
<ul class="text-muted"><li><strong>Wajib:</strong> kode_buku dan judul.</li><li><strong>Opsional:</strong> ISBN, penulis, penerbit, tahun terbit, kategori.</li><li><strong>Jumlah eksemplar:</strong> angka 0 atau lebih. Sistem membuat QR unik untuk setiap eksemplar, misalnya <code>BK001-0001</code>, <code>BK001-0002</code>.</li><li>Kondisi otomatis <strong>baik</strong> dan status otomatis <strong>tersedia</strong>.</li><li>Jika ada kode buku yang sudah ada atau duplikat dalam file, seluruh import dibatalkan agar data tidak setengah masuk.</li></ul>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><label>File Excel (.xlsx)<input class="form-control" type="file" name="file" accept=".xlsx" required></label><div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Import XLSX</button></div></form>
</div>
</section></main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
