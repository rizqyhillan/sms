<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
$errors=[]; $success='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_valid_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if ($action==='create') {
            $nama=trim((string)($_POST['nama']??''));
            $mulai=(string)($_POST['tanggal_mulai']??'');
            $selesai=(string)($_POST['tanggal_selesai']??'');
            if ($nama==='') $errors[]='Nama tahun pelajaran wajib diisi.';
            if ($mulai!=='' && $selesai!=='' && $mulai>$selesai) $errors[]='Tanggal selesai tidak boleh sebelum tanggal mulai.';
            if (!$errors) {
                $pdo->beginTransaction();
                $st=$pdo->prepare('INSERT INTO tahun_pelajaran (nama,aktif,tanggal_mulai,tanggal_selesai) VALUES (?,0,?,?)');
                $st->execute([$nama,$mulai!==''?$mulai:null,$selesai!==''?$selesai:null]);
                $id=(int)$pdo->lastInsertId();
                // Setiap Tahun Pelajaran selalu mempunyai dua semester: Ganjil dan Genap.
                $firstYear = null; $secondYear = null;
                if (preg_match('/^(\d{4})\/(\d{4})$/', $nama, $m)) { $firstYear=(int)$m[1]; $secondYear=(int)$m[2]; }
                $ganjilMulai = $mulai !== '' ? $mulai : ($firstYear ? sprintf('%04d-07-01',$firstYear) : null);
                $ganjilSelesai = $firstYear ? sprintf('%04d-12-31',$firstYear) : null;
                $genapMulai = $secondYear ? sprintf('%04d-01-01',$secondYear) : null;
                $genapSelesai = $selesai !== '' ? $selesai : ($secondYear ? sprintf('%04d-06-30',$secondYear) : null);
                $si=$pdo->prepare('INSERT INTO semester (tahun_pelajaran_id,nama,aktif,tanggal_mulai,tanggal_selesai) VALUES (?,?,?,?,?)');
                $si->execute([$id,'Ganjil',0,$ganjilMulai,$ganjilSelesai]);
                $si->execute([$id,'Genap',0,$genapMulai,$genapSelesai]);
                $pdo->commit();
                tp_audit($pdo,'CREATE',$id,'Menambahkan tahun pelajaran '.$nama.' beserta Semester Ganjil dan Genap.');
                $success='Tahun pelajaran berhasil dibuat beserta Semester Ganjil dan Genap.';
            }
        } elseif ($action==='update') {
            $id=(int)($_POST['id']??0);
            $row=tp_find($pdo,$id);
            $nama=trim((string)($_POST['nama']??''));
            $mulai=trim((string)($_POST['tanggal_mulai']??''));
            $selesai=trim((string)($_POST['tanggal_selesai']??''));
            if (!$row) $errors[]='Tahun pelajaran tidak ditemukan.';
            if ($nama==='') $errors[]='Nama tahun pelajaran wajib diisi.';
            if ($mulai!=='' && $selesai!=='' && $mulai>$selesai) $errors[]='Tanggal selesai tidak boleh sebelum tanggal mulai.';
            if (!$errors) {
                $pdo->beginTransaction();
                $st=$pdo->prepare('UPDATE tahun_pelajaran SET nama=?, tanggal_mulai=?, tanggal_selesai=? WHERE id=?');
                $st->execute([$nama,$mulai!==''?$mulai:null,$selesai!==''?$selesai:null,$id]);

                // Keep the two generated semester periods consistent with the Tapel.
                $firstYear=null; $secondYear=null;
                if (preg_match('/^(\d{4})\/(\d{4})$/',$nama,$m)) {
                    $firstYear=(int)$m[1]; $secondYear=(int)$m[2];
                }
                $ganjilMulai=$mulai!==''?$mulai:($firstYear?sprintf('%04d-07-01',$firstYear):null);
                $ganjilSelesai=$firstYear?sprintf('%04d-12-31',$firstYear):null;
                $genapMulai=$secondYear?sprintf('%04d-01-01',$secondYear):null;
                $genapSelesai=$selesai!==''?$selesai:($secondYear?sprintf('%04d-06-30',$secondYear):null);
                $st=$pdo->prepare("UPDATE semester SET tanggal_mulai=?, tanggal_selesai=? WHERE tahun_pelajaran_id=? AND nama='Ganjil'");
                $st->execute([$ganjilMulai,$ganjilSelesai,$id]);
                $st=$pdo->prepare("UPDATE semester SET tanggal_mulai=?, tanggal_selesai=? WHERE tahun_pelajaran_id=? AND nama='Genap'");
                $st->execute([$genapMulai,$genapSelesai,$id]);
                $pdo->commit();
                tp_audit($pdo,'UPDATE',$id,'Mengubah tahun pelajaran '.$row['nama'].' menjadi '.$nama.'.');
                $success='Tahun pelajaran berhasil diperbarui.';
            }
        } elseif ($action==='delete') {
            $id=(int)($_POST['id']??0);
            $row=tp_find($pdo,$id);
            if (!$row) $errors[]='Tahun pelajaran tidak ditemukan.';
            elseif ((int)$row['aktif']===1) $errors[]='Tahun pelajaran aktif tidak boleh dihapus. Aktifkan tahun pengganti terlebih dahulu.';
            else {
                $blockers=tp_reference_blockers($pdo,$id);
                if ($blockers) {
                    $parts=[];
                    foreach ($blockers as $b) $parts[]=$b['table'].' ('.$b['count'].' data)';
                    $errors[]='Tahun pelajaran tidak dapat dihapus karena masih memiliki transaksi/riwayat: '.implode(', ',$parts).'.';
                } else {
                    $pdo->beginTransaction();
                    $st=$pdo->prepare('DELETE FROM semester WHERE tahun_pelajaran_id=?');
                    $st->execute([$id]);
                    $st=$pdo->prepare('DELETE FROM tahun_pelajaran WHERE id=? AND aktif=0');
                    $st->execute([$id]);
                    if ($st->rowCount()!==1) {
                        $pdo->rollBack();
                        $errors[]='Penghapusan tahun pelajaran gagal.';
                    } else {
                        $pdo->commit();
                        tp_audit($pdo,'DELETE',$id,'Menghapus tahun pelajaran '.$row['nama'].' yang belum memiliki transaksi/riwayat.');
                        $success='Tahun pelajaran berhasil dihapus.';
                    }
                }
            }
        } elseif ($action==='activate') {
            $id=(int)($_POST['id']??0); $row=tp_find($pdo,$id);
            if (!$row) $errors[]='Tahun pelajaran tidak ditemukan.';
            else {
                $oldActive=$pdo->query("SELECT s.id,s.nama,tp.nama AS tapel_nama FROM semester s JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id WHERE tp.aktif=1 AND s.aktif=1 ORDER BY s.id DESC LIMIT 1")->fetch();
                $fotoReset=['count'=>0,'bytes'=>0,'files'=>[]];
                $pdo->beginTransaction();
                try {
                    $pdo->exec('UPDATE tahun_pelajaran SET aktif=0 WHERE aktif=1');
                    $st=$pdo->prepare('UPDATE tahun_pelajaran SET aktif=1 WHERE id=?'); $st->execute([$id]);
                    $st=$pdo->prepare('UPDATE semester SET aktif=0 WHERE tahun_pelajaran_id=?'); $st->execute([$id]);
                    $st=$pdo->prepare("UPDATE semester SET aktif=1 WHERE tahun_pelajaran_id=? AND (CURRENT_DATE BETWEEN COALESCE(tanggal_mulai,'1000-01-01') AND COALESCE(tanggal_selesai,'9999-12-31')) ORDER BY id LIMIT 1"); $st->execute([$id]);
                    if ($st->rowCount()===0) { $st=$pdo->prepare("UPDATE semester SET aktif=1 WHERE tahun_pelajaran_id=? AND nama='Ganjil' LIMIT 1"); $st->execute([$id]); }
                    if($oldActive && (int)$oldActive['id']>0) $fotoReset=tp_reset_ekstra_foto_semester($pdo,(int)$oldActive['id']);
                    $pdo->commit();
                } catch(Throwable $ex){ if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
                if(!empty($fotoReset['files'])) tp_remove_ekstra_foto_files($fotoReset['files']);
                tp_audit($pdo,'ACTIVATE',$id,'Mengaktifkan tahun pelajaran '.$row['nama'].' beserta semester aktif.');
                if((int)$fotoReset['count']>0){ $kb=round((int)$fotoReset['bytes']/1024,1); tp_audit($pdo,'RESET_EKSTRA_FOTO',(int)$oldActive['id'],'Reset otomatis foto dokumentasi '.$oldActive['nama'].' pada '.$oldActive['tapel_nama'].'; '.(int)$fotoReset['count'].' foto, '.$kb.' KB.'); }
                $success='Tahun pelajaran '.$row['nama'].' sekarang aktif.'.((int)$fotoReset['count']>0?' Foto dokumentasi semester sebelumnya otomatis direset.':'');
            }
        } elseif ($action==='activate_semester') {
            $sid=(int)($_POST['semester_id']??0);
            $st=$pdo->prepare('SELECT s.*,tp.nama AS tapel_nama,tp.aktif AS tapel_aktif FROM semester s JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id WHERE s.id=? LIMIT 1');
            $st->execute([$sid]); $semRow=$st->fetch();
            if (!$semRow) $errors[]='Semester tidak ditemukan.';
            elseif (!(int)$semRow['tapel_aktif']) $errors[]='Semester hanya dapat diaktifkan pada Tahun Pelajaran yang sedang aktif.';
            else {
                $oldSt=$pdo->prepare('SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? AND aktif=1 AND id<>? ORDER BY id DESC LIMIT 1');
                $oldSt->execute([(int)$semRow['tahun_pelajaran_id'],$sid]); $oldSem=$oldSt->fetch();
                $fotoReset=['count'=>0,'bytes'=>0,'files'=>[]];
                $pdo->beginTransaction();
                try {
                    $st=$pdo->prepare('UPDATE semester SET aktif=0 WHERE tahun_pelajaran_id=?'); $st->execute([(int)$semRow['tahun_pelajaran_id']]);
                    $st=$pdo->prepare('UPDATE semester SET aktif=1 WHERE id=?'); $st->execute([$sid]);
                    if($oldSem && (int)$oldSem['id']>0) $fotoReset=tp_reset_ekstra_foto_semester($pdo,(int)$oldSem['id']);
                    $pdo->commit();
                } catch(Throwable $ex){ if($pdo->inTransaction())$pdo->rollBack(); throw $ex; }
                if(!empty($fotoReset['files'])) tp_remove_ekstra_foto_files($fotoReset['files']);
                tp_audit($pdo,'ACTIVATE_SEMESTER',$sid,'Mengaktifkan Semester '.$semRow['nama'].' pada '.$semRow['tapel_nama']);
                if((int)$fotoReset['count']>0){ $kb=round((int)$fotoReset['bytes']/1024,1); tp_audit($pdo,'RESET_EKSTRA_FOTO',(int)$oldSem['id'],'Reset otomatis foto dokumentasi Semester '.$oldSem['nama'].' pada '.$semRow['tapel_nama'].'; '.(int)$fotoReset['count'].' foto, '.$kb.' KB.'); }
                $success='Semester '.$semRow['nama'].' sekarang aktif pada '.$semRow['tapel_nama'].'.'.((int)$fotoReset['count']>0?' Foto dokumentasi semester sebelumnya otomatis direset.':'');
            }
        } elseif ($action==='deactivate') {
            $id=(int)($_POST['id']??0); $row=tp_find($pdo,$id);
            if (!$row) $errors[]='Tahun pelajaran tidak ditemukan.';
            elseif (!(int)$row['aktif']) $errors[]='Tahun pelajaran sudah tidak aktif.';
            else $errors[]='Tahun pelajaran aktif tidak boleh dikosongkan. Aktifkan tahun pelajaran pengganti terlebih dahulu.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$rows=$pdo->query('SELECT tp.*, (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.tahun_pelajaran_id=tp.id AND sk.status="aktif") AS siswa_aktif, (SELECT COUNT(DISTINCT sk.kelas_id) FROM siswa_kelas sk WHERE sk.tahun_pelajaran_id=tp.id AND sk.status="aktif") AS jumlah_kelas, (SELECT GROUP_CONCAT(CONCAT(s.nama,IF(s.aktif=1," · aktif","")) ORDER BY s.id SEPARATOR " | ") FROM semester s WHERE s.tahun_pelajaran_id=tp.id) AS semester_info FROM tahun_pelajaran tp ORDER BY tp.id DESC')->fetchAll();
foreach ($rows as &$__tpRow) { $__tpRow['_blockers']=tp_reference_blockers($pdo,(int)$__tpRow['id']); } unset($__tpRow);
$page_title='Tahun Pelajaran'; require_once __DIR__.'/../../layouts/header.php'; require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Tahun Pelajaran</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-calendar3-range"></i> Tahun Pelajaran</h1><p>Kelola periode akademik dan siapkan proses kenaikan kelas tanpa menghapus riwayat lama.</p></div><a class="btn btn-primary" href="<?=BASE_URL?>pengaturan/tahun_pelajaran/kenaikan.php"><i class="bi bi-arrow-up-circle"></i> Proses Kenaikan Kelas</a></div>
<?php foreach($errors as $err):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<div class="dashboard-grid"><div class="dashboard-card"><div class="card-label">Total Tahun</div><div class="card-number"><?=count($rows)?></div><div class="card-description">Riwayat periode akademik</div></div><div class="dashboard-card"><div class="card-label">Tahun Aktif</div><div class="card-number"><?=count(array_filter($rows,fn($r)=>(int)$r['aktif']===1))?></div><div class="card-description">Harus satu periode</div></div></div>
<div class="dashboard-grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start">
<div class="form-card"><div class="section-title"><div><h2><i class="bi bi-plus-circle"></i> Tambah Tahun Pelajaran</h2><p>Buat periode baru terlebih dahulu, lalu aktifkan setelah siap.</p></div></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create"><div class="form-grid"><label>Nama Tahun Pelajaran *<input class="form-control" name="nama" required maxlength="20" placeholder="Contoh: 2027/2028"></label><label>Tanggal Mulai<input type="date" class="form-control" name="tanggal_mulai"></label><label>Tanggal Selesai<input type="date" class="form-control" name="tanggal_selesai"></label></div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button></div></form></div>
<div class="form-card"><div class="section-title"><div><h2><i class="bi bi-shield-check"></i> Aturan</h2><p>Riwayat lama tidak dihapus ketika tahun aktif diganti.</p></div></div><ul class="mb-0"><li>Satu tahun pelajaran aktif pada satu waktu.</li><li>Mengaktifkan tahun baru otomatis menonaktifkan tahun sebelumnya.</li><li>Data <strong>siswa_kelas</strong> tetap tersimpan.</li><li>Kenaikan kelas memakai preview sebelum disimpan.</li></ul></div></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Riwayat Tahun Pelajaran</h2><p>Jangan menghapus periode yang sudah memiliki transaksi.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Nama</th><th>Periode</th><th>Semester</th><th>Siswa Aktif</th><th>Kelas Terisi</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Belum ada tahun pelajaran.</td></tr><?php else:foreach($rows as $r):?><tr><td><strong><?=e($r['nama'])?></strong></td><td><?=e((string)($r['tanggal_mulai']??'-'))?> s/d <?=e((string)($r['tanggal_selesai']??'-'))?></td><td><?=e((string)($r['semester_info']??'-'))?></td><td><?=number_format((int)$r['siswa_aktif'])?></td><td><?=number_format((int)$r['jumlah_kelas'])?></td><td><span class="status-badge <?=$r['aktif']?'status-aktif':'status-nonaktif'?>"><?=$r['aktif']?'Aktif':'Arsip'?></span></td><td><div class="table-actions">
<?php if(!(int)$r['aktif']):?>
<form method="post" style="display:inline" onsubmit="return confirm('Aktifkan tahun pelajaran ini? Tahun aktif saat ini akan menjadi arsip.')"><?=csrf_field()?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?=intval($r['id'])?>"><button class="btn btn-sm btn-primary btn-icon-sm" title="Aktifkan" aria-label="Aktifkan"><i class="bi bi-check-circle" aria-hidden="true"></i></button></form>
<?php else:?><span class="text-muted small"><i class="bi bi-check-circle"></i> Sedang aktif</span><?php endif;?>
<a class="btn btn-sm btn-secondary btn-icon-sm" href="<?=BASE_URL?>pengaturan/tahun_pelajaran/edit.php?id=<?=intval($r['id'])?>" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square"></i></a>
<?php if(!(int)$r['aktif'] && empty($r['_blockers'])):?>
<form method="post" style="display:inline" onsubmit="return confirm('Hapus Tahun Pelajaran ini beserta Semester Ganjil dan Genap? Tindakan ini tidak dapat dibatalkan.')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=intval($r['id'])?>"><button class="btn btn-sm btn-danger btn-icon-sm" title="Hapus" aria-label="Hapus"><i class="bi bi-trash3"></i></button></form>
<?php elseif(!(int)$r['aktif']):?><span class="text-muted small" title="Tidak dapat dihapus karena sudah memiliki transaksi/riwayat"><i class="bi bi-lock"></i> Terkunci</span><?php endif;?>
</div></td></tr><?php endforeach;endif;?></tbody></table></div></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Semester Tahun Pelajaran</h2><p>Setiap Tahun Pelajaran mempunyai Semester Ganjil dan Genap. Hanya satu semester yang aktif pada Tahun Pelajaran aktif.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tahun Pelajaran</th><th>Semester</th><th>Periode Semester</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php $semRows=$pdo->query("SELECT s.*,tp.nama AS tapel_nama,tp.aktif AS tapel_aktif FROM semester s JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id ORDER BY tp.id DESC,s.id")->fetchAll(); if(!$semRows):?><tr><td colspan="5" class="empty-state">Belum ada semester.</td></tr><?php else:foreach($semRows as $s):?><tr><td><?=e($s['tapel_nama'])?></td><td><strong><?=e($s['nama'])?></strong></td><td><?=e((string)($s['tanggal_mulai']??'-'))?> s/d <?=e((string)($s['tanggal_selesai']??'-'))?></td><td><span class="status-badge <?=$s['aktif']?'status-aktif':'status-nonaktif'?>"><?=$s['aktif']?'Aktif':'Arsip'?></span></td><td><?php if((int)$s['tapel_aktif'] && !(int)$s['aktif']):?><form method="post" style="display:inline" onsubmit="return confirm('Aktifkan semester ini?')"><?=csrf_field()?><input type="hidden" name="action" value="activate_semester"><input type="hidden" name="semester_id" value="<?=$s['id']?>"><button class="btn btn-sm btn-primary btn-icon-sm" title="Aktifkan" aria-label="Aktifkan"><i class="bi bi-check-circle" aria-hidden="true"></i></button></form><?php elseif((int)$s['aktif']):?><span class="text-muted small"><i class="bi bi-check-circle"></i> Semester aktif</span><?php else:?><span class="text-muted small">Aktifkan Tapel terlebih dahulu</span><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main><?php require_once __DIR__.'/../../layouts/footer.php'; ?>
