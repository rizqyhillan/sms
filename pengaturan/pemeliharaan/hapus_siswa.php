<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();

/**
 * SATU PINTU PENGHAPUSAN SISWA
 *
 * Mode:
 * - individu : pemeriksaan relasi lengkap, boleh menghapus siswa yang memiliki histori
 * - kelas    : memilih satu kelas, hanya siswa yang aman untuk bulk delete
 * - massal   : memilih beberapa siswa / seluruh hasil filter, hanya siswa yang aman
 *
 * Logika penghapusan existing dipertahankan. Perubahan utama adalah penggabungan UI
 * dan routing agar admin tidak perlu berpindah-pindah halaman.
 */

$relationTables = [
    'absensi','absensi_sholat','catatan_bk','ekstra_absensi','ekstra_anggota',
    'home_visit','izin_siswa','pelanggaran_siswa','pelanggaran_poin_penyesuaian',
    'pembinaan_siswa','konsultasi_bk','perpustakaan_anggota','perpustakaan_kunjungan',
    'siswa_kelas','siswa_status_riwayat','uks_kunjungan','uks_rujukan','orang_tua_siswa'
];

function hs_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return $cache[$table] = ((int)$st->fetchColumn() > 0);
}

function hs_delete_rows_by_student(PDO $pdo, string $table, int $id): int
{
    if (!hs_table_exists($pdo, $table)) return 0;
    $st = $pdo->prepare("DELETE FROM `{$table}` WHERE `siswa_id`=?");
    $st->execute([$id]);
    return $st->rowCount();
}

/** Penghapusan lengkap satu siswa. Ini mempertahankan mekanisme halaman individu lama. */
function hs_delete_student(PDO $pdo, int $id): void
{
    foreach ([
        ['absensi_perubahan','absensi'],
        ['absensi_sholat_perubahan','absensi_sholat'],
        ['ekstra_penilaian','ekstra_anggota']
    ] as [$child,$parent]) {
        if (!hs_table_exists($pdo,$child) || !hs_table_exists($pdo,$parent)) continue;
        if ($child === 'absensi_perubahan') {
            $st = $pdo->prepare('DELETE ap FROM absensi_perubahan ap JOIN absensi a ON a.id=ap.absensi_id WHERE a.siswa_id=?');
        } elseif ($child === 'absensi_sholat_perubahan') {
            $st = $pdo->prepare('DELETE ap FROM absensi_sholat_perubahan ap JOIN absensi_sholat a ON a.id=ap.absensi_sholat_id WHERE a.siswa_id=?');
        } else {
            $st = $pdo->prepare('DELETE ep FROM ekstra_penilaian ep JOIN ekstra_anggota ea ON ea.id=ep.ekstra_anggota_id WHERE ea.siswa_id=?');
        }
        $st->execute([$id]);
    }

    foreach ([
        'absensi','absensi_sholat','catatan_bk','ekstra_absensi','ekstra_anggota','home_visit',
        'izin_siswa','pelanggaran_siswa','pelanggaran_poin_penyesuaian','pembinaan_siswa',
        'konsultasi_bk','perpustakaan_kunjungan','siswa_kelas','siswa_status_riwayat',
        'uks_rujukan','uks_kunjungan','orang_tua_siswa'
    ] as $table) {
        hs_delete_rows_by_student($pdo,$table,$id);
    }

    if (hs_table_exists($pdo,'perpustakaan_detail_peminjaman') && hs_table_exists($pdo,'perpustakaan_peminjaman') && hs_table_exists($pdo,'perpustakaan_anggota')) {
        // Jangan pernah menghapus siswa yang masih memiliki buku fisik yang belum kembali.
        // Jika transaksi aktif dihapus lebih dulu, eksemplar dapat tertinggal berstatus "dipinjam"
        // tanpa pemilik transaksi sehingga integritas stok/riwayat rusak.
        $activeLoan = $pdo->prepare("SELECT COUNT(*) FROM perpustakaan_detail_peminjaman d
            JOIN perpustakaan_peminjaman p ON p.id=d.peminjaman_id
            JOIN perpustakaan_anggota a ON a.id=p.anggota_id
            WHERE a.siswa_id=? AND d.status='dipinjam'");
        $activeLoan->execute([$id]);
        if ((int)$activeLoan->fetchColumn() > 0) {
            throw new RuntimeException('Siswa masih memiliki buku perpustakaan yang belum dikembalikan. Selesaikan pengembalian terlebih dahulu sebelum siswa dihapus.');
        }
        $st = $pdo->prepare('DELETE pd FROM perpustakaan_detail_peminjaman pd JOIN perpustakaan_peminjaman pp ON pp.id=pd.peminjaman_id JOIN perpustakaan_anggota pa ON pa.id=pp.anggota_id WHERE pa.siswa_id=?');
        $st->execute([$id]);
        $st = $pdo->prepare('DELETE pp FROM perpustakaan_peminjaman pp JOIN perpustakaan_anggota pa ON pa.id=pp.anggota_id WHERE pa.siswa_id=?');
        $st->execute([$id]);
        $st = $pdo->prepare('DELETE FROM perpustakaan_anggota WHERE siswa_id=?');
        $st->execute([$id]);
    }

    $st = $pdo->prepare('DELETE FROM siswa WHERE id=?');
    $st->execute([$id]);
    if ($st->rowCount() !== 1) throw new RuntimeException('Gagal menghapus siswa ID '.$id.'.');
}

function hs_student_is_protected(PDO $pdo, int $id, array $relationTables): bool
{
    foreach ($relationTables as $table) {
        if (!hs_table_exists($pdo,$table)) continue;
        $st = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE siswa_id=? LIMIT 1");
        $st->execute([$id]);
        if ($st->fetchColumn() !== false) return true;
    }
    return false;
}

function hs_protected_ids(PDO $pdo, array $ids, array $relationTables): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0)));
    if (!$ids) return [];
    $place = implode(',',array_fill(0,count($ids),'?'));
    $protected = [];
    foreach ($relationTables as $table) {
        if (!hs_table_exists($pdo,$table)) continue;
        $st = $pdo->prepare("SELECT DISTINCT siswa_id FROM `{$table}` WHERE siswa_id IN ($place)");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $protected[(int)$id] = true;
    }
    return $protected;
}

function hs_audit_delete(PDO $pdo, array $students, string $action, string $path): void
{
    $stmt = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
    foreach ($students as $s) {
        $stmt->execute([
            user_id(), $action, 'pemeliharaan', (int)$s['id'],
            'Penghapusan siswa '.$s['nama'].' (NIS '.$s['nis'].'); safety backup: '.basename($path),
            $_SERVER['REMOTE_ADDR']??null, $_SERVER['HTTP_USER_AGENT']??null
        ]);
    }
}

function hs_url(array $changes=[]): string
{
    $base = [
        'mode'=>(string)($_GET['mode']??'individu'),
        'q'=>(string)($_GET['q']??''),
        'status'=>(string)($_GET['status']??'aktif'),
        'kelas_id'=>(int)($_GET['kelas_id']??0),
        'page'=>(int)($_GET['page']??1),
        'id'=>(int)($_GET['id']??0)
    ];
    return '?'.http_build_query(array_merge($base,$changes));
}

$mode = (string)($_GET['mode'] ?? 'individu');
if (!in_array($mode,['individu','kelas','massal'],true)) $mode='individu';
$success=''; $error='';

$q = trim((string)($_GET['q']??''));
$status = (string)($_GET['status']??'aktif');
if (!in_array($status,['aktif','nonaktif','semua'],true)) $status='aktif';
$kelasId = (int)($_GET['kelas_id']??0);
$page = max(1,(int)($_GET['page']??1));
$perPage = 20;
$selectedId = (int)($_GET['id']??$_POST['siswa_id']??0);

/* ============================================================
 * POST: VERIFIKASI OPERASI DATA SENSITIF
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action']??'')==='unlock_destructive') {
    require_valid_csrf();
    $result = maintenance_try_destructive_unlock((string)($_POST['admin_password'] ?? ''));
    if($result['ok']) $success = $result['message'];
    else $error = $result['message'];
}

/* ============================================================
 * POST: INDIVIDU
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action']??'')==='delete_individual') {
    require_valid_csrf();
    maintenance_require_destructive_unlock();
    require_valid_csrf();
    $selectedId=(int)($_POST['siswa_id']??0);
    if (strtoupper(trim((string)($_POST['confirm_text']??'')))!=='HAPUS') {
        $error='Ketik HAPUS untuk konfirmasi.';
        $mode='individu';
    } else try {
        $st=$pdo->prepare('SELECT id,nis,nisn,nama,status FROM siswa WHERE id=? LIMIT 1');
        $st->execute([$selectedId]);
        $s=$st->fetch();
        if(!$s) throw new RuntimeException('Siswa tidak ditemukan.');
        $path=maintenance_create_backup(true);
        maintenance_verify_backup_file($path);
        $pdo->beginTransaction();
        hs_delete_student($pdo,$selectedId);
        // Audit dicatat sebelum COMMIT agar penghapusan tidak dapat selesai
        // tanpa jejak audit bila pencatatan gagal.
        hs_audit_delete($pdo,[$s],'DELETE_STUDENT',$path);
        $pdo->commit();
        $success='Siswa '.($s['nama']??'').' dan data relasi operasionalnya berhasil dihapus. Safety backup: '.basename($path).'.';
        $selectedId=0;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error=$e->getMessage();
    }
}

/* ============================================================
 * POST: BULK / KELAS / SEMUA HASIL FILTER
 * ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && (string)($_POST['action']??'')==='delete_bulk') {
    require_valid_csrf();
    maintenance_require_destructive_unlock();
    $scope=(string)($_POST['scope']??'selected');
    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['siswa_ids']??[])),fn($v)=>$v>0)));
    $confirm=strtoupper(trim((string)($_POST['confirm_text']??'')));
    $postKelasId=(int)($_POST['kelas_id']??0);
    $postStatus=(string)($_POST['status']??'aktif');
    $postQ=trim((string)($_POST['q']??''));
    $mode=(string)($_POST['mode']??'massal');
    if(!in_array($mode,['kelas','massal'],true)) $mode='massal';

    try {
        /* Untuk mode kelas, server mengambil seluruh siswa dari kelas yang dipilih.
           Checkbox browser tidak dijadikan sumber kebenaran. */
        if($mode==='kelas') {
            if($postKelasId<=0) throw new RuntimeException('Pilih kelas terlebih dahulu.');
            $classWhere = 'sk.kelas_id=? AND sk.status="aktif"';
            $classParams = [$postKelasId];
            if (in_array($postStatus,['aktif','nonaktif'],true)) { $classWhere .= ' AND s.status=?'; $classParams[] = $postStatus; }
            $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.status FROM siswa s JOIN siswa_kelas sk ON sk.siswa_id=s.id WHERE {$classWhere} ORDER BY s.nama,s.id");
            $st->execute($classParams);
            $students=$st->fetchAll();
            $ids=array_map(fn($r)=>(int)$r['id'],$students);
            if(!$ids) throw new RuntimeException('Tidak ada siswa aktif pada kelas yang dipilih.');
        } elseif($scope==='all_filtered') {
            $where=[];$params=[];
            if($postQ!=='') { $where[]='(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)'; $like='%'.$postQ.'%'; array_push($params,$like,$like,$like); }
            if(in_array($postStatus,['aktif','nonaktif'],true)) { $where[]='s.status=?'; $params[]=$postStatus; }
            if($postKelasId>0) { $where[]='EXISTS (SELECT 1 FROM siswa_kelas skf WHERE skf.siswa_id=s.id AND skf.kelas_id=? AND skf.status="aktif")'; $params[]=$postKelasId; }
            $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
            $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.status FROM siswa s {$whereSql} ORDER BY s.nama,s.id LIMIT 101");
            $st->execute($params); $students=$st->fetchAll();
            if(count($students)>100) throw new RuntimeException('Maksimal 100 siswa per proses. Gunakan filter kelas/pencarian atau lakukan beberapa tahap.');
            $ids=array_map(fn($r)=>(int)$r['id'],$students);
            if(!$ids) throw new RuntimeException('Tidak ada siswa yang sesuai filter.');
        }

        if(!$ids) throw new RuntimeException('Belum ada siswa yang dipilih.');
        if(count($ids)>100) throw new RuntimeException('Maksimal 100 siswa per proses.');
        if($confirm!=='HAPUS') throw new RuntimeException('Ketik HAPUS untuk konfirmasi penghapusan.');

        $place=implode(',',array_fill(0,count($ids),'?'));
        $st=$pdo->prepare("SELECT id,nis,nisn,nama,status FROM siswa WHERE id IN ($place) ORDER BY nama");
        $st->execute($ids); $students=$st->fetchAll();
        $found=array_map(fn($r)=>(int)$r['id'],$students);
        if(array_diff($ids,$found)) throw new RuntimeException('Sebagian siswa tidak ditemukan. Muat ulang daftar lalu coba lagi.');

        $protected=hs_protected_ids($pdo,$ids,$relationTables);
        if($protected) {
            $names=[];
            foreach($students as $s) if(isset($protected[(int)$s['id']])) $names[]=$s['nama'].' (NIS '.$s['nis'].')';
            $label=$mode==='kelas'?'kelas':'massal';
            throw new RuntimeException('Penghapusan '.$label.' dibatalkan. Ada siswa yang memiliki riwayat/transaksi: '.implode(', ',array_slice($names,0,8)).(count($names)>8?' dan lainnya.':'').' Gunakan mode Individu untuk pemeriksaan relasi satu per satu.');
        }

        $path=maintenance_create_backup(true);
        maintenance_verify_backup_file($path);
        $pdo->beginTransaction();
        foreach($ids as $id) hs_delete_student($pdo,$id);
        // Audit dicatat di dalam transaksi agar kegagalan audit membatalkan
        // seluruh penghapusan siswa.
        hs_audit_delete($pdo,$students,$mode==='kelas'?'DELETE_STUDENT_CLASS':'DELETE_STUDENT_BULK',$path);
        $pdo->commit();
        $success=count($ids).' siswa berhasil dihapus. Safety backup: '.basename($path).'.';
        $page=1;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error=$e->getMessage();
    }
}

/* ============================================================
 * DATA MODE INDIVIDU
 * ============================================================ */
$rows=[];$selected=null;$relations=[];
if($mode==='individu') {
    $rows=$pdo->query("SELECT s.id,s.nis,s.nisn,s.nama,s.status,(SELECT mk.nama FROM siswa_kelas sk JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE sk.siswa_id=s.id ORDER BY sk.id DESC LIMIT 1) AS kelas FROM siswa s ORDER BY s.nama LIMIT 500")->fetchAll();
    if($selectedId>0) {
        $st=$pdo->prepare('SELECT id,nis,nisn,nama,status FROM siswa WHERE id=?');
        $st->execute([$selectedId]); $selected=$st->fetch();
        if($selected) {
            foreach($relationTables as $table) {
                if(!hs_table_exists($pdo,$table)) continue;
                $st=$pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE siswa_id=?");$st->execute([$selectedId]);$n=(int)$st->fetchColumn();
                if($n>0) $relations[$table]=$n;
            }
            if (hs_table_exists($pdo,'perpustakaan_detail_peminjaman') && hs_table_exists($pdo,'perpustakaan_peminjaman') && hs_table_exists($pdo,'perpustakaan_anggota')) {
                $st=$pdo->prepare("SELECT COUNT(*) FROM perpustakaan_detail_peminjaman d
                    JOIN perpustakaan_peminjaman p ON p.id=d.peminjaman_id
                    JOIN perpustakaan_anggota a ON a.id=p.anggota_id
                    WHERE a.siswa_id=? AND d.status='dipinjam'");
                $st->execute([$selectedId]);
                $n=(int)$st->fetchColumn();
                if($n>0) $relations['perpustakaan_buku_belum_kembali']=$n;
            }
        }
    }
}

/* ============================================================
 * DATA MODE KELAS / MASSAL
 * ============================================================ */
$kelas=$pdo->query("SELECT id,nama FROM master_kelas ORDER BY urutan,nama")->fetchAll();
$total=0;$pages=1;$bulkRows=[];$protectedIds=[];$safeCount=0;
if(in_array($mode,['kelas','massal'],true)) {
    $where=[];$params=[];
    if($q!=='') { $where[]='(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like); }
    if($status!=='semua') { $where[]='s.status=?';$params[]=$status; }
    if($kelasId>0) { $where[]='EXISTS (SELECT 1 FROM siswa_kelas skf WHERE skf.siswa_id=s.id AND skf.kelas_id=? AND skf.status="aktif")';$params[]=$kelasId; }
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $st=$pdo->prepare("SELECT COUNT(*) FROM siswa s {$whereSql}");$st->execute($params);$total=(int)$st->fetchColumn();
    $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.status,(SELECT mk.nama FROM siswa_kelas sk JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE sk.siswa_id=s.id ORDER BY sk.id DESC LIMIT 1) kelas FROM siswa s {$whereSql} ORDER BY s.nama,s.id LIMIT {$perPage} OFFSET {$offset}");
    $st->execute($params);$bulkRows=$st->fetchAll();
    $ids=array_map(fn($r)=>(int)$r['id'],$bulkRows);$protectedIds=hs_protected_ids($pdo,$ids,$relationTables);
    foreach($bulkRows as $r) if(!isset($protectedIds[(int)$r['id']])) $safeCount++;
}

$page_title='Hapus Siswa';
require_once __DIR__.'/../../layouts/header.php';
require_once __DIR__.'/../../layouts/sidebar.php';
?>
<style>
.hs-head-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.hs-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 18px}.hs-tab{display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid #d9e0ec;border-radius:14px;background:#fff;color:#18233b;text-decoration:none;box-shadow:0 5px 18px rgba(31,41,55,.05);transition:.18s}.hs-tab:hover{transform:translateY(-1px);border-color:#c5d1e5}.hs-tab.active{border-color:#4f46e5;box-shadow:0 8px 24px rgba(79,70,229,.12);background:linear-gradient(135deg,#eef2ff,#fff)}.hs-tab-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;font-size:19px;background:#f1f5f9;color:#334155}.hs-tab-danger .hs-tab-icon{background:#fff1f2;color:#dc2626}.hs-tab-class .hs-tab-icon{background:#fff7ed;color:#ea580c}.hs-tab-bulk .hs-tab-icon{background:#fef2f2;color:#b91c1c}.hs-tab strong{display:block;font-size:15px}.hs-tab small{display:block;color:#71809a;margin-top:2px}.hs-panel{border-radius:18px}.hs-risk{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border:1px solid #fecaca;background:#fff7f7;color:#7f1d1d;border-radius:14px;margin-bottom:16px}.hs-risk i{font-size:20px}.hs-mode-title{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.hs-mode-title h2{margin:0}.hs-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}.hs-info{padding:14px;border:1px solid #e3e8f1;border-radius:14px;background:#fff}.hs-info .label{font-size:12px;color:#71809a}.hs-info .value{font-size:23px;font-weight:800;margin-top:3px}.hs-filter-grid{display:grid;grid-template-columns:minmax(240px,2fr) minmax(160px,1fr) minmax(160px,1fr) auto;gap:12px;align-items:end}.hs-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.hs-bulkbar{display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;padding:14px 0 0}.hs-danger-zone{padding:14px;border:1px solid #fecaca;background:#fffafa;border-radius:14px}.hs-pagination{display:flex;justify-content:center;gap:8px;align-items:center;margin-top:16px}.hs-pagination a{padding:8px 12px;border:1px solid #dbe2ec;border-radius:9px;background:#fff;text-decoration:none;color:#25324b}.hs-pagination .current{font-weight:700}.hs-empty{text-align:center;padding:28px;color:#71809a}.hs-check{width:18px;height:18px}.hs-table-note{font-size:13px;color:#71809a}.hs-class-danger{border-left:4px solid #ea580c}.hs-bulk-danger{border-left:4px solid #dc2626}@media(max-width:900px){.hs-tabs,.hs-info-grid{grid-template-columns:1fr}.hs-filter-grid{grid-template-columns:1fr}.hs-head-actions{width:100%}}
</style>
<main class="main">
<header class="topbar"><div class="topbar-title">Hapus Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header">
  <div><h1><i class="bi bi-person-x-fill"></i> Hapus Siswa</h1><p>Satu halaman untuk penghapusan individu, per kelas, maupun massal. Mekanisme keamanan dan safety backup tetap dipertahankan.</p></div>
  <div class="hs-head-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/pemeliharaan/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
</div>
<?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif;?>

<?php if(!maintenance_destructive_is_unlocked()): ?>
<div class="hs-risk" style="border-color:rgba(220,53,69,.35);background:rgba(220,53,69,.06)"><i class="bi bi-shield-lock"></i><div><strong>Operasi Penghapusan Terkunci</strong><br>Login Administrator saja tidak cukup. Verifikasi ulang password Administrator diperlukan sebelum tombol penghapusan dibuka. Setelah berhasil, akses berlaku maksimal 5 menit.</div></div>
<div class="form-card hs-panel" style="max-width:680px">
<h2><i class="bi bi-shield-lock"></i> Verifikasi Administrator</h2>
<p>Verifikasi ini mencegah penghapusan terjadi hanya karena browser masih memiliki sesi Administrator yang aktif.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="unlock_destructive">
<label>Password Administrator<input class="form-control" type="password" name="admin_password" autocomplete="current-password" required></label>
<div class="form-actions"><button class="btn btn-danger"><i class="bi bi-unlock-fill"></i> Verifikasi &amp; Buka Penghapusan</button></div>
</form>
</div>
<?php else: ?>
<div class="alert alert-warning"><strong><i class="bi bi-unlock-fill"></i> Operasi Penghapusan Terbuka Sementara</strong><br>Verifikasi Administrator berhasil. Akses penghapusan berlaku maksimal 5 menit dan tetap memerlukan CSRF, konfirmasi, pemeriksaan relasi, serta safety backup.</div>
<?php endif; ?>

<nav class="hs-tabs" aria-label="Mode penghapusan">
 <a class="hs-tab hs-tab-danger <?=$mode==='individu'?'active':''?>" href="<?=e(hs_url(['mode'=>'individu','id'=>0,'page'=>1]))?>"><span class="hs-tab-icon"><i class="bi bi-person-x"></i></span><span><strong>Hapus per Siswa</strong><small>Periksa relasi lengkap</small></span></a>
 <a class="hs-tab hs-tab-class <?=$mode==='kelas'?'active':''?>" href="<?=e(hs_url(['mode'=>'kelas','id'=>0,'page'=>1]))?>"><span class="hs-tab-icon"><i class="bi bi-people"></i></span><span><strong>Hapus per Kelas</strong><small>Pilih satu kelas</small></span></a>
 <a class="hs-tab hs-tab-bulk <?=$mode==='massal'?'active':''?>" href="<?=e(hs_url(['mode'=>'massal','id'=>0,'page'=>1]))?>"><span class="hs-tab-icon"><i class="bi bi-trash3"></i></span><span><strong>Hapus Massal / Semua</strong><small>Pilih siswa atau seluruh hasil</small></span></a>
</nav>

<?php if($mode==='individu'):?>
<div class="hs-risk"><i class="bi bi-shield-exclamation"></i><div><strong>Mode Individu</strong><br>Mode ini boleh menghapus siswa yang sudah memiliki riwayat/transaksi, tetapi sistem akan memeriksa dan menghapus relasinya dalam satu transaksi setelah safety backup.</div></div>
<div class="form-card hs-panel"><div class="hs-mode-title"><h2><i class="bi bi-person-vcard"></i> Pilih Siswa</h2><span class="hs-table-note">Maksimal 500 pilihan ditampilkan</span></div><form method="get" style="margin-top:14px"><input type="hidden" name="mode" value="individu"><label>Siswa<select class="form-control" name="id" onchange="this.form.submit()"><option value="0">-- pilih siswa --</option><?php foreach($rows as $r):?><option value="<?=$r['id']?>" <?=$selectedId===(int)$r['id']?'selected':''?>><?=e($r['nama'])?> · NIS <?=e((string)$r['nis'])?><?=!empty($r['kelas'])?' · '.e($r['kelas']):''?></option><?php endforeach;?></select></label></form></div>
<?php if($selected):?>
<div class="dashboard-table-card hs-panel" style="margin-top:16px"><div class="section-title"><div><h2><?=e($selected['nama'])?></h2><p>NIS <?=e((string)$selected['nis'])?> · NISN <?=e((string)$selected['nisn'])?> · Status <?=e((string)$selected['status'])?></p></div><span class="badge bg-danger">Penghapusan permanen</span></div>
<div class="hs-risk"><i class="bi bi-hdd-fill"></i><div>Data operasional yang terhubung akan ikut dihapus. <strong>Audit Log tidak ikut dihapus.</strong> Safety backup dibuat otomatis sebelum penghapusan.</div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Relasi</th><th>Jumlah</th></tr></thead><tbody><?php if(!$relations):?><tr><td colspan="2">Tidak ada data relasi yang terdeteksi.</td></tr><?php else:foreach($relations as $table=>$n):?><tr><td><?=e($table)?></td><td><?=number_format($n)?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php if(maintenance_destructive_is_unlocked()): ?>
<form method="post" class="hs-danger-zone" style="margin-top:16px" onsubmit="return confirm('PERMANEN: hapus siswa beserta data relasinya?')"><?=csrf_field()?><input type="hidden" name="action" value="delete_individual"><input type="hidden" name="siswa_id" value="<?=$selected['id']?>"><label>Ketik <strong>HAPUS</strong> untuk konfirmasi<input class="form-control" name="confirm_text" required autocomplete="off" placeholder="HAPUS" style="max-width:320px"></label><button class="btn btn-danger mt-3"><i class="bi bi-trash3"></i> Hapus Permanen Siswa</button></form>
<?php else: ?>
<div class="alert alert-secondary mt-3"><i class="bi bi-lock"></i> Verifikasi Administrator diperlukan sebelum penghapusan dapat dijalankan.</div>
<?php endif; ?>
</div>
<?php endif;?>

<?php else:?>
<div class="hs-risk <?=$mode==='kelas'?'hs-class-danger':'hs-bulk-danger'?>"><i class="bi bi-shield-lock"></i><div><strong><?= $mode==='kelas'?'Mode Hapus per Kelas':'Mode Hapus Massal / Semua' ?></strong><br>Hanya siswa <strong>tanpa riwayat/transaksi</strong> yang boleh dihapus melalui mode ini. Siswa terlindungi tidak dapat dipaksa melalui bulk dan harus diperiksa lewat mode Individu.</div></div>

<?php if($mode==='kelas'):?>
<div class="form-card hs-panel"><div class="hs-mode-title"><h2><i class="bi bi-people"></i> Pilih Kelas</h2><span class="hs-table-note">Penghapusan maksimal 100 siswa per proses</span></div><form method="get" class="hs-filter-grid" style="margin-top:14px"><input type="hidden" name="mode" value="kelas"><label>Kelas<select class="form-control" name="kelas_id" required><option value="0">-- pilih kelas --</option><?php foreach($kelas as $k):?><option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option><?php endforeach;?></select></label><label>Status<select class="form-control" name="status"><option value="aktif" <?=$status==='aktif'?'selected':''?>>Aktif</option><option value="nonaktif" <?=$status==='nonaktif'?'selected':''?>>Nonaktif</option><option value="semua" <?=$status==='semua'?'selected':''?>>Semua</option></select></label><div class="hs-actions"><button class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan Kelas</button><a class="btn btn-light" href="<?=BASE_URL?>pengaturan/pemeliharaan/hapus_siswa.php?mode=kelas"><i class="bi bi-arrow-counterclockwise"></i> Reset</a></div></form></div>
<?php else:?>
<div class="form-card hs-panel"><div class="hs-mode-title"><h2><i class="bi bi-funnel"></i> Filter Siswa</h2><span class="hs-table-note">Default 20 siswa per halaman</span></div><form method="get" class="hs-filter-grid" style="margin-top:14px"><input type="hidden" name="mode" value="massal"><label>Cari NIS / NISN / Nama<input class="form-control" type="search" name="q" value="<?=e($q)?>" placeholder="Ketik pencarian..."></label><label>Kelas<select class="form-control" name="kelas_id"><option value="0">Semua Kelas</option><?php foreach($kelas as $k):?><option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option><?php endforeach;?></select></label><label>Status<select class="form-control" name="status"><option value="aktif" <?=$status==='aktif'?'selected':''?>>Aktif</option><option value="nonaktif" <?=$status==='nonaktif'?'selected':''?>>Nonaktif</option><option value="semua" <?=$status==='semua'?'selected':''?>>Semua</option></select></label><div class="hs-actions"><button class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan</button><a class="btn btn-light" href="<?=BASE_URL?>pengaturan/pemeliharaan/hapus_siswa.php?mode=massal"><i class="bi bi-arrow-counterclockwise"></i> Reset</a></div></form></div>
<?php endif;?>

<div class="dashboard-table-card hs-panel" style="margin-top:16px"><div class="section-title"><div><h2>Daftar Siswa</h2><p><?=number_format($total)?> siswa ditemukan · halaman <?=$page?> dari <?=$pages?></p></div><div class="hs-info-grid" style="margin:0;min-width:280px"><div class="hs-info"><div class="label">Aman di halaman</div><div class="value"><?=number_format($safeCount)?></div></div><div class="hs-info"><div class="label">Terlindungi</div><div class="value"><?=number_format(count($protectedIds))?></div></div><div class="hs-info"><div class="label">Total hasil</div><div class="value"><?=number_format($total)?></div></div></div></div>
<?php if(maintenance_destructive_is_unlocked()): ?>
<form method="post" id="hsBulkForm" onsubmit="return hsConfirmBulk()">
<?=csrf_field()?><input type="hidden" name="action" value="delete_bulk"><input type="hidden" name="mode" value="<?=$mode?>"><input type="hidden" name="kelas_id" value="<?=$kelasId?>"><input type="hidden" name="status" value="<?=e($status)?>"><input type="hidden" name="q" value="<?=e($q)?>"><input type="hidden" name="scope" id="hsScope" value="selected">
<div class="table-responsive"><table class="data-table"><thead><tr><?php if($mode==='massal'):?><th style="width:48px"><input class="hs-check" type="checkbox" id="hsCheckAll" title="Pilih semua yang aman di halaman ini"></th><?php endif;?><th>Siswa</th><th>NIS / NISN</th><th>Kelas</th><th>Status</th><th>Keamanan</th></tr></thead><tbody>
<?php if(!$bulkRows):?><tr><td colspan="<?=$mode==='massal'?6:5?>" class="hs-empty">Tidak ada siswa yang sesuai filter.</td></tr><?php else:foreach($bulkRows as $r):$rid=(int)$r['id'];$protected=isset($protectedIds[$rid]);?><tr><?php if($mode==='massal'):?><td><input class="hs-check hsStudentCheck" type="checkbox" name="siswa_ids[]" value="<?=$rid?>" <?=$protected?'disabled':''?>></td><?php endif;?><td><strong><?=e($r['nama'])?></strong></td><td><?=e((string)$r['nis'])?><br><small><?=e((string)($r['nisn']??''))?></small></td><td><?=e((string)($r['kelas']??'-'))?></td><td><span class="badge <?=$r['status']==='aktif'?'bg-success':'bg-secondary'?>"><?=e($r['status'])?></span></td><td><?php if($protected):?><span class="badge bg-warning text-dark"><i class="bi bi-shield-lock"></i> Ada riwayat</span><?php else:?><span class="badge bg-success"><i class="bi bi-check2-circle"></i> Aman</span><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div>
<div class="hs-bulkbar"><div><?php if($mode==='kelas'):?><strong><?=number_format($total)?></strong> siswa pada kelas ini<?php else:?><strong><span id="hsSelectedCount">0</span></strong> siswa dipilih<?php endif;?></div><div class="hs-actions"><?php if($mode==='massal'):?><button type="button" class="btn btn-outline-danger" onclick="hsUseAll()"><i class="bi bi-check2-square"></i> Pilih Semua Hasil</button><?php endif;?><label class="mb-0">Ketik <strong>HAPUS</strong></label><input class="form-control" style="width:150px" name="confirm_text" autocomplete="off" placeholder="HAPUS" required><button class="btn btn-danger" type="submit"><i class="bi bi-trash3"></i> <?=$mode==='kelas'?'Hapus Siswa di Kelas':'Hapus Terpilih'?></button></div></div>
</form>
<?php else: ?>
<div class="alert alert-secondary mt-3"><i class="bi bi-lock"></i> Verifikasi Administrator diperlukan sebelum penghapusan massal/per kelas dapat dijalankan.</div>
<?php endif; ?>
<?php if($pages>1):?><nav class="hs-pagination" aria-label="Paginasi siswa"><a href="<?=e(hs_url(['page'=>max(1,$page-1)]))?>">‹ Sebelumnya</a><span class="current">Halaman <?=$page?> / <?=$pages?></span><a href="<?=e(hs_url(['page'=>min($pages,$page+1)]))?>">Berikutnya ›</a></nav><?php endif;?>
</div>
<?php if($mode==='massal'):?><div class="hs-risk" style="margin-top:16px"><i class="bi bi-exclamation-octagon"></i><div><strong>Pilih Semua Hasil</strong> berarti seluruh hasil filter akan dihapus dalam satu proses. Sistem tetap membatasi maksimal <strong>100 siswa</strong> per proses dan akan membatalkan seluruh proses bila ada satu saja siswa yang memiliki riwayat/transaksi.</div></div><?php endif;?>
<?php endif;?>
</section></main>
<script>
(function(){
 const boxes=[...document.querySelectorAll('.hsStudentCheck')], all=document.getElementById('hsCheckAll'), count=document.getElementById('hsSelectedCount');
 function update(){if(!count)return;const n=boxes.filter(x=>x.checked).length;count.textContent=n;if(all){const enabled=boxes.filter(x=>!x.disabled);all.checked=enabled.length>0&&enabled.every(x=>x.checked);all.indeterminate=enabled.some(x=>x.checked)&&!all.checked;}}
 all?.addEventListener('change',()=>{boxes.forEach(x=>{if(!x.disabled)x.checked=all.checked});update();});boxes.forEach(x=>x.addEventListener('change',update));update();
 window.hsUseAll=function(){
   const scope=document.getElementById('hsScope'); if(!scope)return;
   const n=<?=json_encode($total)?>;
   if(n>100){alert('Pilih Semua Hasil dibatasi maksimal 100 siswa per proses. Gunakan filter terlebih dahulu.');return;}
   if(n<1){alert('Tidak ada siswa pada hasil filter.');return;}
   scope.value='all_filtered';boxes.forEach(x=>{if(!x.disabled)x.checked=true});update();
   alert('Semua siswa pada hasil filter dipilih. Ketik HAPUS lalu klik tombol hapus untuk melanjutkan.');
 };
 window.hsConfirmBulk=function(){
   const mode=<?=json_encode($mode)?>, scope=document.getElementById('hsScope')?.value||'selected';
   const n=scope==='all_filtered'?<?=json_encode($total)?>:document.querySelectorAll('.hsStudentCheck:checked').length;
   const c=(document.querySelector('input[name="confirm_text"]')?.value||'').trim().toUpperCase();
   if(!n){alert('Pilih minimal satu siswa yang aman untuk dihapus.');return false;}
   if(n>100){alert('Maksimal 100 siswa per proses.');return false;}
   if(c!=='HAPUS'){alert('Ketik HAPUS untuk konfirmasi.');return false;}
   return confirm('PERMANEN: hapus '+n+' siswa? Safety backup akan dibuat otomatis. Siswa yang memiliki riwayat/transaksi akan membuat seluruh proses dibatalkan.');
 };
})();
</script>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
