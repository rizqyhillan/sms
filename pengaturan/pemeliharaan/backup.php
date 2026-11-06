<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();

$success=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_valid_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if ($action==='create') {
            $path=maintenance_create_backup(true);
            $name=basename($path);
            $st=$pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
            $st->execute([user_id(),'CREATE_BACKUP','pemeliharaan',null,'Membuat backup '.$name,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
            $success='Backup berhasil dibuat: '.$name;
        } elseif ($action==='delete_backup') {
            $name=basename((string)($_POST['name']??''));
            if (!preg_match('/^backup_[0-9]{8}_[0-9]{6}\.zip$/',$name)) throw new RuntimeException('Nama backup tidak valid.');
            $path=maintenance_backup_dir().DIRECTORY_SEPARATOR.$name;
            if (!is_file($path)) throw new RuntimeException('Backup tidak ditemukan.');
            @unlink($path);
            $st=$pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
            $st->execute([user_id(),'DELETE_BACKUP','pemeliharaan',null,'Menghapus file backup '.$name,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
            $success='Backup dihapus.';
        } elseif ($action==='restore') {
            if (empty($_FILES['backup']['tmp_name']) || ($_FILES['backup']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Pilih file backup ZIP yang valid.');
            $f=$_FILES['backup'];
            if ((int)$f['size'] > 1024*1024*1024) throw new RuntimeException('File backup maksimal 1 GB.');
            if (strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION))!=='zip') throw new RuntimeException('File restore harus ZIP.');
            $zip=new ZipArchive();
            if ($zip->open($f['tmp_name'])!==true) throw new RuntimeException('File ZIP tidak dapat dibuka.');
            $hasSql=false; for($i=0;$i<$zip->numFiles;$i++){if($zip->getNameIndex($i)==='database.sql'){$hasSql=true;break;}}
            if (!$hasSql) {$zip->close();throw new RuntimeException('Backup tidak memiliki database.sql.');}
            $zip->close();
            // Safety backup sebelum restore.
            $safety=maintenance_create_backup(true);
            $tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'absensi_restore_'.bin2hex(random_bytes(6));
            @mkdir($tmp,0700,true);
            try {
                $zip=new ZipArchive();$zip->open($f['tmp_name']);maintenance_safe_extract($zip,$tmp);$zip->close();
                maintenance_restore_database($tmp.DIRECTORY_SEPARATOR.'database.sql');
                $srcUploads=$tmp.DIRECTORY_SEPARATOR.'uploads';
                if(is_dir($srcUploads)) maintenance_copy_tree($srcUploads, dirname(__DIR__,2).DIRECTORY_SEPARATOR.'uploads');
            } finally {
                if(is_dir($tmp)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $p){$p->isDir()?@rmdir($p->getPathname()):@unlink($p->getPathname());} @rmdir($tmp); }
            }
            $st=$pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
            $st->execute([user_id(),'RESTORE_BACKUP','pemeliharaan',null,'Restore backup '.$f['name'].'; safety backup: '.basename($safety),$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
            $success='Restore berhasil. Safety backup otomatis dibuat: '.basename($safety).'. Silakan login ulang bila sesi berubah.';
        }
    } catch(Throwable $e) { $error=$e->getMessage(); }
}
$rows=maintenance_backup_files();
$page_title='Backup & Restore';require_once __DIR__.'/../../layouts/header.php';require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Backup & Restore</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-cloud-arrow-down"></i> Backup & Restore</h1><p>Backup mencakup database dan folder uploads. Restore selalu membuat safety backup terlebih dahulu.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/pemeliharaan/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif;?>
<div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
<div class="form-card"><h2><i class="bi bi-hdd-fill"></i> Buat Backup</h2><p>Database, routines, triggers, events, dan file uploads disimpan dalam satu ZIP.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create"><button class="btn btn-primary" onclick="return confirm('Buat backup sekarang?')"><i class="bi bi-download"></i> Buat Backup Sekarang</button></form></div>
<div class="form-card"><h2><i class="bi bi-hdd-fill"></i> Restore</h2><p class="text-danger">Restore mengganti isi database. Sistem akan membuat safety backup otomatis sebelum proses.</p><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="restore"><input type="file" class="form-control mb-3" name="backup" accept=".zip,application/zip" required><label class="d-flex gap-2 align-items-center mb-3"><input type="checkbox" required> Saya memahami bahwa restore akan mengganti data database.</label><button class="btn btn-danger" onclick="return confirm('Lanjutkan RESTORE? Pastikan file backup benar.')"><i class="bi bi-arrow-counterclockwise"></i> Restore Backup</button></form></div>
</div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Riwayat Backup Lokal</h2><p>Backup tersimpan di storage khusus di luar folder aplikasi sehingga tetap ada saat aplikasi diperbarui/diganti.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Nama</th><th>Dibuat</th><th>Ukuran</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="4" class="empty-state">Belum ada backup.</td></tr><?php else:foreach($rows as $r):?><tr><td><strong><?=e($r['name'])?></strong></td><td><?=e($r['created'])?></td><td><?=e(maintenance_format_bytes($r['size']))?></td><td><div class="table-actions"><a class="btn btn-secondary btn-sm btn-icon-sm" title="Download backup" aria-label="Download backup" href="<?=BASE_URL?>pengaturan/pemeliharaan/download.php?name=<?=rawurlencode($r['name'])?>"><i class="bi bi-download"></i></a><form method="post" style="display:inline" onsubmit="return confirm('Hapus file backup ini?')"><?=csrf_field()?><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="name" value="<?=e($r['name'])?>"><button class="btn btn-danger btn-sm btn-icon-sm" title="Hapus backup" aria-label="Hapus backup"><i class="bi bi-trash3"></i></button></form></div></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main><?php require_once __DIR__.'/../../layouts/footer.php'; ?>
