<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_registry.php';

require_login();
if (user_role() !== 'admin') {
    deny_access();
}

$protectedCodes = array_keys(task_registry());

$error = '';
$success = '';
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;

function jt_make_code(string $name): string
{
    $code = strtoupper(trim($name));
    $code = preg_replace('/[^A-Z0-9]+/', '_', $code) ?? '';
    $code = trim($code, '_');
    if ($code === '') {
        $code = 'TUGAS_TAMBAHAN';
    }
    return substr($code, 0, 50);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $kode = strtoupper(trim((string)($_POST['kode'] ?? '')));
            $nama = trim((string)($_POST['nama'] ?? ''));
            $kategori = trim((string)($_POST['kategori'] ?? ''));
            $keterangan = trim((string)($_POST['keterangan'] ?? ''));

            if ($nama === '') {
                throw new RuntimeException('Nama jenis tugas wajib diisi.');
            }

            if ($kode === '') {
                $kode = jt_make_code($nama);
            }

            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $kode)) {
                throw new RuntimeException('Kode hanya boleh berisi huruf kapital, angka, dan garis bawah (_).');
            }

            if (in_array($kode, $protectedCodes, true)) {
                if ($id <= 0) {
                    throw new RuntimeException('Kode tersebut merupakan kode sistem dan tidak dapat digunakan untuk jenis tugas manual.');
                }

                $q = $pdo->prepare("SELECT kode FROM jenis_tugas WHERE id=? LIMIT 1");
                $q->execute([$id]);
                $oldCode = (string)($q->fetchColumn() ?: '');
                if ($oldCode !== $kode) {
                    throw new RuntimeException('Kode sistem tidak boleh diubah.');
                }
            }

            if ($id > 0) {
                $q = $pdo->prepare("SELECT kode,nama FROM jenis_tugas WHERE id=? LIMIT 1");
                $q->execute([$id]);
                $old = $q->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new RuntimeException('Jenis tugas tidak ditemukan.');
                }

                if (in_array((string)$old['kode'], $protectedCodes, true)) {
                    // System records may keep their identity; only descriptive fields
                    // are editable, preserving application capabilities.
                    $st = $pdo->prepare("UPDATE jenis_tugas SET nama=?,kategori=?,keterangan=? WHERE id=?");
                    $st->execute([
                        $nama,
                        $kategori !== '' ? $kategori : null,
                        $keterangan !== '' ? $keterangan : null,
                        $id
                    ]);
                    $success = 'Informasi jenis tugas sistem diperbarui. Kode sistem tetap dikunci.';
                } else {
                    $st = $pdo->prepare("UPDATE jenis_tugas SET kode=?,nama=?,kategori=?,keterangan=? WHERE id=?");
                    $st->execute([
                        $kode,
                        $nama,
                        $kategori !== '' ? $kategori : null,
                        $keterangan !== '' ? $keterangan : null,
                        $id
                    ]);
                    $success = 'Jenis tugas berhasil diperbarui.';
                }

                $audit = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
                $audit->execute([
                    user_id(), 'UPDATE_JENIS_TUGAS', 'jenis_tugas', $id,
                    'Memperbarui jenis tugas: ' . $nama,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                $editId = 0;
            } else {
                $st = $pdo->prepare("INSERT INTO jenis_tugas(kode,nama,kategori,aktif,keterangan) VALUES(?,?,?,?,?)");
                $st->execute([
                    $kode,
                    $nama,
                    $kategori !== '' ? $kategori : null,
                    1,
                    $keterangan !== '' ? $keterangan : null
                ]);
                $newId = (int)$pdo->lastInsertId();

                $audit = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
                $audit->execute([
                    user_id(), 'CREATE_JENIS_TUGAS', 'jenis_tugas', $newId,
                    'Menambahkan jenis tugas manual: ' . $nama . ' [' . $kode . ']',
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                $success = 'Jenis tugas tambahan berhasil ditambahkan.';
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare("SELECT id,kode,nama,aktif FROM jenis_tugas WHERE id=? LIMIT 1");
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Jenis tugas tidak ditemukan.');
            }
            if (in_array((string)$row['kode'], $protectedCodes, true)) {
                throw new RuntimeException('Jenis tugas sistem dilindungi dan statusnya tidak boleh diubah dari menu manual.');
            }

            $newStatus = (int)$row['aktif'] === 1 ? 0 : 1;
            $st = $pdo->prepare("UPDATE jenis_tugas SET aktif=? WHERE id=?");
            $st->execute([$newStatus, $id]);

            $audit = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
            $audit->execute([
                user_id(), 'TOGGLE_JENIS_TUGAS', 'jenis_tugas', $id,
                ($newStatus ? 'Mengaktifkan' : 'Menonaktifkan') . ' jenis tugas: ' . (string)$row['nama'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            $success = $newStatus ? 'Jenis tugas diaktifkan.' : 'Jenis tugas dinonaktifkan.';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $q = $pdo->prepare("SELECT id,kode,nama FROM jenis_tugas WHERE id=? LIMIT 1");
            $q->execute([$id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Jenis tugas tidak ditemukan.');
            }
            if (in_array((string)$row['kode'], $protectedCodes, true)) {
                throw new RuntimeException('Jenis tugas sistem tidak boleh dihapus.');
            }

            $use = $pdo->prepare("SELECT COUNT(*) FROM tugas_guru WHERE jenis_tugas_id=?");
            $use->execute([$id]);
            $used = (int)$use->fetchColumn();

            if ($used > 0) {
                throw new RuntimeException('Jenis tugas sudah dipakai pada ' . $used . ' penugasan. Nonaktifkan saja agar histori tetap aman.');
            }

            $st = $pdo->prepare("DELETE FROM jenis_tugas WHERE id=?");
            $st->execute([$id]);

            $audit = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
            $audit->execute([
                user_id(), 'DELETE_JENIS_TUGAS', 'jenis_tugas', $id,
                'Menghapus jenis tugas manual: ' . (string)$row['nama'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            $success = 'Jenis tugas manual berhasil dihapus.';
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $error = 'Kode jenis tugas sudah digunakan. Gunakan kode yang berbeda.';
        } else {
            $error = 'Gagal menyimpan perubahan jenis tugas.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM jenis_tugas WHERE id=? LIMIT 1");
    $st->execute([$editId]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editRow) {
        $editId = 0;
        $error = $error ?: 'Jenis tugas yang akan diedit tidak ditemukan.';
    }
}

$rows = $pdo->query("
    SELECT jt.*,
           (SELECT COUNT(*) FROM tugas_guru tg WHERE tg.jenis_tugas_id=jt.id) AS total_penugasan
    FROM jenis_tugas jt
    ORDER BY jt.aktif DESC, jt.kategori ASC, jt.nama ASC, jt.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Pagination hanya untuk tampilan daftar; tidak mengubah logika CRUD maupun query data.
$allowedPerPage = [5, 10, 15, 20, 25, 50];
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}
$totalRows = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$currentPage = min($currentPage, $totalPages);
$pageRows = array_slice($rows, ($currentPage - 1) * $perPage, $perPage);

function jt_page_url(int $page, int $perPage): string
{
    $params = ['page' => $page, 'per_page' => $perPage];
    if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
        $params['edit'] = (int)$_GET['edit'];
    }
    return '?' . http_build_query($params);
}

$page_title = 'Jenis Tugas Tambahan';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar">
    <div class="topbar-title">Jenis Tugas Tambahan</div>
    <div class="topbar-user"><?=e(user_name())?></div>
</header>
<section class="content">
    <style>
        .jt-layout{display:grid;grid-template-columns:minmax(0,390px) minmax(0,1fr);gap:18px;align-items:start}
        .jt-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
        .jt-card-head{padding:18px 20px 12px}
        .jt-card-head h2{margin:0;font-size:1.12rem}
        .jt-card-head p{margin:5px 0 0;color:#64748b;font-size:.88rem}
        .jt-form{padding:0 20px 20px}
        .jt-form .form-control{min-height:44px}
        .jt-form label{display:block;font-weight:700;margin-top:12px}
        .jt-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
        .jt-table-wrap{padding:0 14px 14px}
        .jt-table{width:100%;border-collapse:separate;border-spacing:0}
        .jt-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;padding:11px 10px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
        .jt-table td{padding:11px 10px;border-bottom:1px solid #edf2f7;vertical-align:middle}
        .jt-table tr:last-child td{border-bottom:0}
        .jt-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.76rem;background:#f1f5f9;padding:4px 7px;border-radius:7px;color:#334155}
        .jt-protected{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;color:#64748b;margin-top:4px}
        .jt-note{margin:0 0 16px;padding:12px 14px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:.88rem}
        .jt-warning{margin-top:16px;padding:12px 14px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:.86rem}
        .jt-actions-cell{display:flex;gap:6px;flex-wrap:wrap}
        .jt-empty{padding:28px!important;text-align:center;color:#64748b}
        .jt-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 14px 14px;border-top:1px solid #edf2f7}
        .jt-page-size{display:flex;align-items:center;gap:8px;color:#64748b;font-size:.84rem;font-weight:600}
        .jt-page-size select{width:auto;min-width:78px;min-height:38px;padding:6px 30px 6px 10px;border:1px solid #dbe3ee;border-radius:9px;background:#fff}
        .jt-page-info{color:#64748b;font-size:.84rem}
        .jt-page-nav{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
        .jt-page-nav a,.jt-page-nav span{min-width:36px;height:36px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dbe3ee;border-radius:9px;background:#fff;color:#334155;text-decoration:none;font-size:.84rem;font-weight:700}
        .jt-page-nav a:hover{background:#f1f5f9}
        .jt-page-nav .active{background:#2563eb;border-color:#2563eb;color:#fff}
        .jt-page-nav .disabled{opacity:.45;pointer-events:none}
        @media(max-width:700px){.jt-pagination{align-items:stretch}.jt-page-size,.jt-page-info{width:100%}.jt-page-nav{width:100%}}
        @media(max-width:1000px){.jt-layout{grid-template-columns:1fr}}
        @media(max-width:700px){.jt-table-wrap{overflow-x:auto}.jt-table{min-width:760px}}
    </style>

    <div class="page-header">
        <div>
            <h1><i class="bi bi-list-task"></i> Jenis Tugas Tambahan</h1>
            <p>Kelola jenis penugasan guru secara manual. Jenis baru dapat ditambah, diedit, diaktifkan/nonaktifkan, atau dihapus jika belum memiliki histori penugasan.</p>
        </div>
        <div class="page-header-actions">
            <a class="btn btn-secondary" href="<?=BASE_URL?>tugas_guru/index.php"><i class="bi bi-person-workspace"></i> Tugas Guru</a>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=e($error)?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?=e($success)?></div>
    <?php endif; ?>

    <div class="jt-note">
        <strong><i class="bi bi-shield-check"></i> Aman untuk pengembangan.</strong>
        Jenis tugas manual yang baru tidak otomatis mendapatkan hak akses khusus. Hak akses aplikasi tetap dikendalikan oleh <code>task_registry.php</code>.
    </div>

    <div class="jt-layout">
        <div class="jt-card" id="editor">
            <div class="jt-card-head">
                <h2><i class="bi bi-plus-circle"></i> <?=$editRow ? 'Edit Jenis Tugas' : 'Tambah Jenis Tugas'?></h2>
                <p><?=$editRow ? 'Perbarui informasi jenis tugas.' : 'Tambahkan jenis tugas sesuai kebutuhan sekolah.'?></p>
            </div>
            <form method="post" class="jt-form">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?=$editRow ? (int)$editRow['id'] : 0?>">

                <label>Nama Jenis Tugas *
                    <input class="form-control" name="nama" maxlength="100" required value="<?=e((string)($editRow['nama'] ?? ''))?>" placeholder="Contoh: Koordinator Literasi">
                </label>

                <label>Kode
                    <input class="form-control" name="kode" maxlength="50" value="<?=e((string)($editRow['kode'] ?? ''))?>" placeholder="Otomatis dari nama jika dikosongkan" <?=($editRow && in_array((string)$editRow['kode'],$protectedCodes,true))?'readonly':''?>>
                </label>

                <label>Kategori
                    <input class="form-control" name="kategori" maxlength="50" value="<?=e((string)($editRow['kategori'] ?? ''))?>" placeholder="Contoh: Kesiswaan, Akademik, Layanan">
                </label>

                <label>Keterangan
                    <textarea class="form-control" name="keterangan" rows="4" maxlength="1000" placeholder="Penjelasan singkat jenis tugas..."><?=e((string)($editRow['keterangan'] ?? ''))?></textarea>
                </label>

                <div class="jt-actions">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?=$editRow ? 'Simpan Perubahan' : 'Tambah Jenis Tugas'?></button>
                    <?php if($editRow): ?>
                        <a class="btn btn-secondary" href="<?=BASE_URL?>jenis_tugas/index.php"><i class="bi bi-x-lg"></i> Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="jt-card">
            <div class="jt-card-head">
                <h2><i class="bi bi-collection"></i> Daftar Jenis Tugas</h2>
                <p><?=$totalRows?> jenis tersedia di master.</p>
            </div>
            <div class="jt-table-wrap">
                <table class="jt-table">
                    <thead>
                        <tr>
                            <th>Jenis Tugas</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Penugasan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(!$rows): ?>
                        <tr><td colspan="5" class="jt-empty">Belum ada jenis tugas.</td></tr>
                    <?php else: foreach($pageRows as $r):
                        $isProtected = in_array((string)$r['kode'], $protectedCodes, true);
                        $used = (int)$r['total_penugasan'];
                    ?>
                        <tr>
                            <td>
                                <strong><?=e($r['nama'])?></strong><br>
                                <span class="jt-code"><?=e($r['kode'])?></span>
                                <?php if($isProtected): ?><div class="jt-protected"><i class="bi bi-lock-fill"></i> Sistem</div><?php endif; ?>
                                <?php if(!empty($r['keterangan'])): ?><div class="text-muted small" style="margin-top:4px"><?=e($r['keterangan'])?></div><?php endif; ?>
                            </td>
                            <td><?=e((string)($r['kategori'] ?: '-'))?></td>
                            <td><span class="status-badge <?=$r['aktif'] ? 'status-aktif' : 'status-nonaktif'?>"><?=$r['aktif'] ? 'Aktif' : 'Nonaktif'?></span></td>
                            <td><strong><?=$used?></strong></td>
                            <td>
                                <div class="jt-actions-cell">
                                    <a class="btn btn-secondary btn-sm btn-icon-sm" href="?edit=<?=(int)$r['id']?>" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square"></i></a>
                                    <?php if(!$isProtected): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Ubah status jenis tugas ini?')">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                                            <button class="btn btn-warning btn-sm btn-icon-sm" type="submit" title="<?=$r['aktif']?'Nonaktifkan':'Aktifkan'?>" aria-label="<?=$r['aktif']?'Nonaktifkan':'Aktifkan'?>">
                                                <i class="bi <?=$r['aktif']?'bi-pause-circle':'bi-play-circle'?>"></i>
                                            </button>
                                        </form>
                                        <?php if($used === 0): ?>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Hapus jenis tugas ini secara permanen?')">
                                                <?=csrf_field()?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                                                <button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus" aria-label="Hapus"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted-text" title="Sudah digunakan pada penugasan"><i class="bi bi-lock"></i> Histori</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted-text" title="Jenis tugas sistem dilindungi"><i class="bi bi-shield-lock"></i> Terkunci</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($totalRows > 0): ?>
                <div class="jt-pagination">
                    <div class="jt-page-size">
                        <label for="jtPerPage">Tampilkan</label>
                        <select id="jtPerPage" onchange="window.location.href='?page=1&per_page='+this.value">
                            <?php foreach($allowedPerPage as $size): ?>
                                <option value="<?=$size?>" <?=$perPage === $size ? 'selected' : ''?>><?=$size?></option>
                            <?php endforeach; ?>
                        </select>
                        <span>data</span>
                    </div>
                    <div class="jt-page-info">
                        Menampilkan <?=($totalRows ? (($currentPage - 1) * $perPage + 1) : 0)?>–<?=min($currentPage * $perPage, $totalRows)?> dari <?=$totalRows?> jenis
                    </div>
                    <nav class="jt-page-nav" aria-label="Navigasi halaman jenis tugas">
                        <a class="<?=$currentPage <= 1 ? 'disabled' : ''?>" href="<?=e(jt_page_url(max(1, $currentPage - 1), $perPage))?>" aria-label="Sebelumnya">&laquo;</a>
                        <?php for($pg = 1; $pg <= $totalPages; $pg++): ?>
                            <a class="<?=$pg === $currentPage ? 'active' : ''?>" href="<?=e(jt_page_url($pg, $perPage))?>" <?=$pg === $currentPage ? 'aria-current="page"' : ''?>><?=$pg?></a>
                        <?php endfor; ?>
                        <a class="<?=$currentPage >= $totalPages ? 'disabled' : ''?>" href="<?=e(jt_page_url(min($totalPages, $currentPage + 1), $perPage))?>" aria-label="Berikutnya">&raquo;</a>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="jt-warning">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Catatan penting:</strong>
        jenis tugas yang sudah digunakan dalam penugasan tidak dihapus agar histori tetap aman. Gunakan <strong>Nonaktifkan</strong> jika jenis tersebut tidak ingin muncul untuk penugasan baru.
    </div>
</section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
