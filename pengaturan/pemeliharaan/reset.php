<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();

/**
 * Reset lingkungan pengujian.
 *
 * Prinsip:
 * - Hanya tabel TRANSAKSI/OPERASIONAL yang dibersihkan.
 * - Master sekolah, konfigurasi, pengguna, siswa, kelas, tapel, jadwal,
 *   jenis data, dan Audit Log TIDAK dihapus.
 * - Tidak melakukan reset AUTO_INCREMENT.
 * - Safety backup dibuat sebelum penghapusan.
 * - Urutan DELETE dihitung dari FK aktif agar child dihapus lebih dahulu.
 * - File dokumentasi ekstra yang terkait transaksi ikut dibersihkan setelah
 *   transaksi database berhasil commit.
 */
$groups = [
    'absensi' => [
        'label' => 'Absensi Kehadiran & Sholat',
        'tables' => ['absensi_perubahan','absensi_sholat_perubahan','absensi','absensi_sholat','sesi_absensi'],
    ],
    'izin' => [
        'label' => 'Izin Siswa',
        'tables' => ['izin_siswa'],
    ],
    'bk' => [
        'label' => 'BK, Layanan, Pelanggaran, Pembinaan & Home Visit',
        'tables' => ['bk_layanan','bk_kunjungan','konsultasi_bk','catatan_bk','home_visit','pembinaan_siswa','pelanggaran_poin_penyesuaian','pelanggaran_siswa'],
    ],
    'ekstra' => [
        'label' => 'Transaksi Ekstrakurikuler',
        'tables' => ['ekstra_penilaian','ekstra_pembina_absensi','ekstra_absensi','ekstra_jurnal_kendala','ekstra_jurnal','ekstra_dokumentasi','ekstra_sesi','ekstra_anggota'],
    ],
    'perpus' => [
        'label' => 'Transaksi Perpustakaan',
        'tables' => ['perpustakaan_detail_peminjaman','perpustakaan_peminjaman','perpustakaan_kunjungan','perpustakaan_anggota'],
    ],
    'uks' => [
        'label' => 'Transaksi UKS',
        'tables' => ['uks_kunjungan_obat','uks_rujukan','uks_stok_transaksi','uks_kunjungan'],
    ],
];

function reset_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $st->execute([$table]);
    return $cache[$table] = ((int)$st->fetchColumn() > 0);
}

function reset_existing_tables(PDO $pdo, array $tables): array
{
    $out = [];
    foreach (array_values(array_unique($tables)) as $table) {
        if (reset_table_exists($pdo, $table)) $out[] = $table;
    }
    return $out;
}

/** Child first berdasarkan FK aktif antar tabel yang akan di-reset. */
function reset_delete_order(PDO $pdo, array $tables): array
{
    $tables = array_values(array_unique($tables));
    if (count($tables) < 2) return $tables;

    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $place = implode(',', array_fill(0, count($tables), '?'));
    $params = array_merge([$db], $tables, $tables);
    $sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=?
              AND TABLE_NAME IN ($place)
              AND REFERENCED_TABLE_NAME IN ($place)
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    // edge child -> parent
    $children = [];
    $indegree = array_fill_keys($tables, 0);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $child = (string)$r['TABLE_NAME'];
        $parent = (string)$r['REFERENCED_TABLE_NAME'];
        if ($child === $parent) continue;
        $children[$child][$parent] = true;
    }

    // Kahn: node dengan child-edge 0 (tidak punya parent di dalam set) dulu? 
    // Untuk DELETE kita justru perlu child dulu. Topological graph child->parent,
    // sehingga node dengan indegree 0 pada edge child->parent adalah child paling luar.
    foreach ($children as $child => $parents) {
        foreach (array_keys($parents) as $parent) {
            $indegree[$parent]++;
        }
    }

    $queue = [];
    foreach ($indegree as $t => $deg) if ($deg === 0) $queue[] = $t;
    sort($queue, SORT_STRING);
    $order = [];
    while ($queue) {
        $node = array_shift($queue);
        $order[] = $node;
        foreach (array_keys($children[$node] ?? []) as $parent) {
            $indegree[$parent]--;
            if ($indegree[$parent] === 0) {
                $queue[] = $parent;
                sort($queue, SORT_STRING);
            }
        }
    }

    // Jika ada cycle/metadata aneh, jangan menebak urutan. Gunakan urutan
    // eksplisit dari grup sebagai fallback; FK error akan menggagalkan transaksi.
    if (count($order) !== count($tables)) return $tables;
    return $order;
}

function reset_collect_ekstra_files(PDO $pdo, array $tables): array
{
    if (!in_array('ekstra_dokumentasi', $tables, true) || !reset_table_exists($pdo, 'ekstra_dokumentasi')) return [];
    $st = $pdo->query("SELECT file_path FROM ekstra_dokumentasi WHERE file_path IS NOT NULL AND file_path<>''");
    return array_values(array_filter(array_map(static fn($r) => (string)($r['file_path'] ?? ''), $st->fetchAll(PDO::FETCH_ASSOC))));
}

function reset_remove_ekstra_files(array $files): array
{
    $removed = 0; $failed = 0; $base = dirname(__DIR__, 2);
    foreach ($files as $rel) {
        $rel = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, (string)$rel), DIRECTORY_SEPARATOR);
        // Hanya file yang memang berada di area dokumentasi ekstra.
        if (stripos(str_replace('\\','/',$rel), 'uploads/ekstra/') !== 0) continue;
        $full = $base . DIRECTORY_SEPARATOR . $rel;
        if (is_file($full)) {
            if (@unlink($full)) $removed++; else $failed++;
        }
    }
    return ['removed'=>$removed,'failed'=>$failed];
}

function reset_audit(PDO $pdo, string $action, string $description): void
{
    $st = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
    $st->execute([user_id(),$action,'pemeliharaan',null,$description,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
}

$success=''; $error=''; $warning='';
$deletedSummary=[]; $photoSummary=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $action = (string)($_POST['action'] ?? 'manual');

    if($action === 'unlock_destructive') {
        $result = maintenance_try_destructive_unlock((string)($_POST['admin_password'] ?? ''));
        if($result['ok']) $success = $result['message'];
        else $error = $result['message'];
        $action = '';
    }

    if($action === '') {
        // Hanya membuka halaman; tidak ada operasi data pada POST ini.
    } else {
        if(!maintenance_destructive_is_unlocked()) {
            $error='Operasi Reset Data masih terkunci. Verifikasi ulang Administrator terlebih dahulu.';
            $action='';
        }
    }

    if($action === '') {
        // lanjut ke render halaman
    } else {
    $keys = $_POST['groups'] ?? [];
    if(!is_array($keys)) $keys=[];
    $keys=array_values(array_intersect(array_keys($groups),array_map('strval',$keys)));

    if($action === 'testing_clean') {
        $keys = array_keys($groups);
        $confirm = strtoupper(trim((string)($_POST['confirm_text'] ?? '')));
        if($confirm !== 'RESET TESTING') $error='Ketik RESET TESTING untuk memulai lingkungan pengujian baru.';
    } elseif(!$keys) {
        $error='Pilih minimal satu kelompok data.';
    } elseif(strtoupper(trim((string)($_POST['confirm_text']??'')))!=='RESET') {
        $error='Ketik RESET untuk mengonfirmasi pembersihan data.';
    }

    if($error==='') {
        try {
            // SAFETY GATE: setiap reset WAJIB memiliki backup baru yang
            // berhasil dibuat DAN diverifikasi sebelum satu baris data pun dihapus.
            // Jika operator lupa membuat backup, sistem membuatnya otomatis.
            // Jika pembuatan/verifikasi backup gagal, reset DIBATALKAN.
            $path=maintenance_create_backup(true);
            maintenance_verify_backup_file($path);
            $allTables=[];
            foreach($keys as $key) $allTables=array_merge($allTables,$groups[$key]['tables']);
            $allTables=reset_existing_tables($pdo,$allTables);
            $deleteOrder=reset_delete_order($pdo,$allTables);
            $extraFiles=reset_collect_ekstra_files($pdo,$allTables);

            $pdo->beginTransaction();
            foreach($deleteOrder as $table){
                $n=(int)$pdo->exec('DELETE FROM `'.$table.'`');
                $deletedSummary[$table]=$n;
            }

            $totalRows=array_sum($deletedSummary);
            $label = $action==='testing_clean'
                ? 'MULAI_SESI_TESTING'
                : 'RESET_DATA';
            $detail = ($action==='testing_clean' ? 'Lingkungan pengujian dibersihkan' : 'Reset kelompok: '.implode(', ',$keys))
                .'; tabel: '.count($allTables).'; baris dihapus: '.$totalRows
                .'; safety backup: '.basename($path);
            if($extraFiles) $detail .= '; file dokumentasi ekstra yang akan dihapus: '.count($extraFiles);

            // Audit reset dicatat DI DALAM transaksi agar kegagalan pencatatan
            // tidak meninggalkan database sudah terhapus tetapi audit gagal.
            reset_audit($pdo,$label,$detail);
            $pdo->commit();

            if($extraFiles){
                $photoSummary=reset_remove_ekstra_files($extraFiles);
                if(($photoSummary['failed']??0)>0){
                    $warning='Data database sudah dibersihkan, tetapi '.$photoSummary['failed'].' file dokumentasi ekstra gagal dihapus. Periksa folder uploads/ekstra.';
                }
            }

            if($photoSummary) $detail .= '; foto ekstra dihapus: '.$photoSummary['removed'];

            if($action==='testing_clean') {
                $success='Lingkungan pengujian baru siap. Seluruh transaksi/aktivitas operasional pada kelompok testing sudah dibersihkan. Master, konfigurasi, pengguna, siswa, jadwal, dan Audit Log tetap dipertahankan. Safety backup: '.basename($path).'.';
            } else {
                $success='Reset selesai. Safety backup: '.basename($path).'.';
            }
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $error=$e->getMessage();
        }
    }
    }
}

$page_title='Reset Data';require_once __DIR__ . '/../../layouts/header.php';require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Reset Data</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-arrow-counterclockwise"></i> Reset Data</h1><p>Membersihkan data transaksi terpilih. Master sekolah, pengguna, siswa, tahun pelajaran, semester, kelas, jadwal, jenis data, konfigurasi, dan Audit Log tidak disentuh.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/pemeliharaan/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<?php if($warning):?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?=e($warning)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif;?>

<?php if(!maintenance_destructive_is_unlocked()): ?>
<div class="alert alert-danger"><strong><i class="bi bi-shield-lock"></i> Operasi Data Sensitif Terkunci</strong><br>Reset Data adalah operasi permanen. Sebelum tombol penghapusan dibuka, Administrator wajib melakukan verifikasi ulang password. Setelah berhasil, akses operasi destruktif berlaku sementara selama 5 menit.</div>
<div class="form-card" style="max-width:680px;border:1px solid rgba(220,53,69,.25);background:rgba(220,53,69,.025)">
<h2><i class="bi bi-shield-lock"></i> Verifikasi Administrator</h2>
<p>Ini adalah lapisan pengaman tambahan. Login Administrator yang masih aktif saja tidak cukup untuk melakukan Reset Data.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="unlock_destructive">
<label>Password Administrator<input class="form-control" type="password" name="admin_password" autocomplete="current-password" required></label>
<div class="form-actions"><button class="btn btn-danger"><i class="bi bi-unlock-fill"></i> Verifikasi &amp; Buka Operasi</button></div>
</form>
</div>
<?php else: ?>
<div class="alert alert-warning"><strong><i class="bi bi-unlock-fill"></i> Operasi Data Sensitif Terbuka Sementara</strong><br>Verifikasi Administrator berhasil. Akses reset terbuka maksimal 5 menit dan tetap mewajibkan CSRF, konfirmasi teks, serta safety backup yang valid.</div>

<div class="alert alert-info"><strong><i class="bi bi-clipboard-check"></i> Persiapan Functional Test</strong><br>Gunakan <strong>Bersihkan Lingkungan Pengujian</strong> jika ingin memulai dari kondisi transaksi kosong. <strong>Safety backup wajib dibuat dan diverifikasi otomatis sebelum penghapusan.</strong> Jika operator lupa membuat backup, sistem membuat backup baru secara otomatis. Jika backup gagal dibuat atau gagal diverifikasi, <strong>reset tidak akan dijalankan</strong>. Master dan konfigurasi tetap ada. Audit Log tidak dihapus; sistem hanya mencatat batas bahwa sesi pengujian baru dimulai.</div>

<div class="form-card" style="border:1px solid rgba(220,53,69,.25);background:rgba(220,53,69,.025)">
<h2><i class="bi bi-trash3"></i> Bersihkan Lingkungan Pengujian</h2>
<p>Ini adalah preset untuk memulai Functional Test. Sistem membersihkan seluruh kelompok transaksi di bawah, termasuk sesi, antrian layanan, jurnal, absensi, riwayat operasional, dan transaksi lainnya. <strong>Master sekolah, pengguna, siswa, kelas, Tapel, jadwal, konfigurasi, dan Audit Log tetap dipertahankan.</strong></p>
<form method="post" onsubmit="return confirm('Yakin memulai lingkungan pengujian baru? Semua data transaksi/testing pada seluruh kelompok akan dihapus setelah safety backup dibuat.')">
<?=csrf_field()?>
<input type="hidden" name="action" value="testing_clean">
<label>Konfirmasi <strong>RESET TESTING</strong><input class="form-control" name="confirm_text" placeholder="Ketik RESET TESTING" autocomplete="off" required></label>
<div class="form-actions"><button class="btn btn-danger"><i class="bi bi-trash3"></i> Bersihkan & Mulai Sesi Testing</button></div>
</form>
</div>

<div class="alert alert-warning"><strong><i class="bi bi-shield-exclamation"></i> Perhatian:</strong> Semua reset, termasuk reset manual, memiliki <strong>gerbang safety backup</strong>. Sistem selalu membuat dan memverifikasi backup baru tepat sebelum penghapusan. Bila backup gagal, data <strong>tidak akan dihapus</strong>. Tindakan reset tetap permanen untuk kelompok yang dipilih. AUTO_INCREMENT tidak di-reset.</div>
<div class="form-card"><h2><i class="bi bi-sliders"></i> Reset Manual per Kelompok</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="manual"><div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
<?php foreach($groups as $key=>$g):?><label class="border rounded p-3 d-flex gap-3 align-items-start"><input type="checkbox" name="groups[]" value="<?=e($key)?>" style="margin-top:4px"><span><strong><?=e($g['label'])?></strong><small class="d-block text-muted mt-1"><?=e(implode(', ',$g['tables']))?></small></span></label><?php endforeach;?>
</div><hr><label>Konfirmasi <strong>RESET</strong><input class="form-control" name="confirm_text" placeholder="Ketik RESET" autocomplete="off" required></label><div class="form-actions"><button class="btn btn-danger" onclick="return confirm('Yakin menghapus kelompok data yang dipilih?')"><i class="bi bi-trash3"></i> Reset Data Terpilih</button></div></form></div>
<?php endif; ?>
</section></main><?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
