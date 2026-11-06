<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';

$errors=[]; $success='';
$years=$pdo->query('SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC')->fetchAll();
$activeYearId=0;
foreach($years as $y){ if((int)$y['aktif']===1){$activeYearId=(int)$y['id'];break;} }
$yearId=(int)($_POST['tahun_pelajaran_id']??$_GET['tahun_pelajaran_id']??$activeYearId);
$selectedYear=null;
foreach($years as $y){if((int)$y['id']===$yearId){$selectedYear=$y;break;}}
if(!$selectedYear)$yearId=$activeYearId;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_valid_csrf();
    $file=$_FILES['file']??null;
    if(!$file || $file['error']!==UPLOAD_ERR_OK) $errors[]='File XLSX belum dipilih atau gagal diunggah.';
    elseif(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))!=='xlsx') $errors[]='File harus berformat .xlsx.';
    elseif((int)$file['size']>10*1024*1024) $errors[]='Ukuran file maksimal 10 MB.';
    else try {
        $rows=xlsx_read_rows((string)$file['tmp_name']);
        if(!$rows) throw new RuntimeException('Worksheet XLSX tidak berisi data.');
        $header=xlsx_normalize_header($rows[0]);
        $required=['nis','nisn','nama','jk','no_hp','status','kelas'];
        if($header!==$required) {
            $errors[]='Header XLSX tidak sesuai. Yang diharapkan: '.implode(', ',$required).'. Header yang terbaca: '.($header?implode(', ',$header):'(kosong)').'. Pastikan data berada di worksheet pertama dan baris pertama adalah header.';
        } else {
            if(!$selectedYear) {
                $errors[]='Tahun pelajaran belum dipilih atau belum tersedia. Atur tahun pelajaran terlebih dahulu.';
            } else {
                $classRows=$pdo->query("SELECT id,nama FROM master_kelas WHERE aktif=1 ORDER BY tingkat,nama")->fetchAll();
                $classMap=[];
                foreach($classRows as $c) $classMap[mb_strtolower(trim((string)$c['nama']),'UTF-8')]=(int)$c['id'];

                $pdo->beginTransaction();
                try {
                    $insert=$pdo->prepare("INSERT INTO siswa (nis,nisn,nama,jk,no_hp,kode_qr,status) VALUES (?,NULLIF(?,''),?,NULLIF(?,''),NULLIF(?,''),?,?)");
                    $assign=$pdo->prepare("INSERT INTO siswa_kelas (siswa_id,tahun_pelajaran_id,kelas_id,status,keterangan) VALUES (?,?,?,'aktif',NULLIF(?,''))");
                    $checkNis=$pdo->prepare("SELECT id FROM siswa WHERE nis=? LIMIT 1");
                    $checkNisn=$pdo->prepare("SELECT id FROM siswa WHERE nisn=? LIMIT 1");
                    $seenNis=[];$seenNisn=[];$count=0;$placed=0;$unplaced=0;$line=1;
                    foreach(array_slice($rows,1) as $row){
                        $line++;$row=xlsx_flexible_row($row,7); $row=array_pad($row,7,'');
                        [$nis,$nisn,$nama,$jk,$noHp,$status,$kelas]=array_map(fn($v)=>trim((string)$v),array_slice($row,0,7));
                        $jk=strtoupper($jk);$status=strtolower($status);
                        if($nis===''&&$nama===''&&$kelas==='') continue;
                        if($nis===''||$nama==='') throw new RuntimeException("Baris $line: NIS dan nama wajib diisi.");
                        if(isset($seenNis[$nis])) throw new RuntimeException("Baris $line: NIS $nis muncul lebih dari sekali dalam file.");
                        $checkNis->execute([$nis]); if($checkNis->fetchColumn()) throw new RuntimeException("Baris $line: NIS $nis sudah terdaftar.");
                        $seenNis[$nis]=true;
                        if($nisn!==''){
                            if(isset($seenNisn[$nisn])) throw new RuntimeException("Baris $line: NISN $nisn muncul lebih dari sekali dalam file.");
                            $checkNisn->execute([$nisn]); if($checkNisn->fetchColumn()) throw new RuntimeException("Baris $line: NISN $nisn sudah terdaftar.");
                            $seenNisn[$nisn]=true;
                        }
                        if($jk!==''&&!in_array($jk,['L','P'],true)) throw new RuntimeException("Baris $line: JK harus L, P, atau kosong.");
                        if($status==='')$status='aktif';
                        if(!in_array($status,['aktif','lulus','nonaktif'],true)) throw new RuntimeException("Baris $line: status harus aktif, lulus, atau nonaktif.");
                        $classId=0;
                        if($kelas!==''){
                            $key=mb_strtolower($kelas,'UTF-8');
                            $classId=$classMap[$key]??0;
                            if(!$classId) throw new RuntimeException("Baris $line: kelas \"$kelas\" tidak ditemukan pada Master Kelas aktif.");
                        }
                        $qr=siswa_generate_qr($pdo);
                        $insert->execute([$nis,$nisn,$nama,$jk,$noHp,$qr,$status]);
                        $studentId=(int)$pdo->lastInsertId();$count++;
                        if($classId){$assign->execute([$studentId,$yearId,$classId,'Import XLSX']);$placed++;}else{$unplaced++;}
                    }
                    $pdo->commit();
                    siswa_audit($pdo,'IMPORT',0,'Impor '.$count.' siswa dari XLSX; ditempatkan '.$placed.', belum ditempatkan '.$unplaced);
                    $success="Impor berhasil: $count siswa. $placed langsung ditempatkan di kelas dan $unplaced belum ditempatkan.";
                } catch(Throwable $e){
                    if($pdo->inTransaction())$pdo->rollBack();
                    $errors[]=$e->getMessage();
                }
            }
        }
    } catch(Throwable $e){$errors[]=$e->getMessage();}
}
$page_title='Impor Siswa';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main siswa-import-page"><header class="topbar"><div class="topbar-title">Impor Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Impor Siswa dari XLSX</h1><p>Impor dapat sekaligus menempatkan siswa ke kelas pada Tahun Pelajaran yang dipilih.</p></div><a class="btn btn-secondary btn-back" href="<?=BASE_URL?>siswa/baru.php?tahun_pelajaran_id=<?=$yearId?>"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="form-card">
<div class="section-title"><div><h2>Format Excel (XLSX)</h2><p>Header harus berada pada baris pertama dan urutannya jangan diubah.</p></div><a class="btn btn-purple" href="<?=BASE_URL?>siswa/template_import.xlsx"><i class="bi bi-file-earmark-arrow-down"></i> Download Template XLSX</a></div>
<p><strong>Header:</strong> <code>nis, nisn, nama, jk, no_hp, status, kelas</code></p><p class="text-muted">Kolom XLSX normal dipisahkan oleh Excel. Sistem juga menerima hasil konversi yang menyimpan kolom dalam satu sel dengan pemisah <strong>koma (,)</strong> atau <strong>titik koma (;)</strong>.</p>
<div class="alert alert-info"><strong>Petunjuk:</strong>
<ul style="margin:8px 0 0 18px"><li><strong>Wajib:</strong> NIS dan Nama.</li><li><strong>Opsional:</strong> NISN, JK, No. HP, dan Kelas.</li><li>Status boleh kosong; jika kosong otomatis menjadi <code>aktif</code>.</li><li>Jika Kelas diisi, tulis nama kelas yang persis ada di <strong>Master Kelas aktif</strong>; penempatannya masuk ke Tahun Pelajaran yang dipilih.</li><li>Jika Kelas kosong, siswa tetap diimpor dan ditandai <strong>Belum ditempatkan</strong>; kelas dapat diatur kemudian melalui <strong>Kelola Kelas</strong>.</li><li>Jika nama kelas diisi tetapi tidak ditemukan, seluruh import dibatalkan agar tidak ada data setengah masuk.</li><li>QR tidak perlu diisi karena dibuat otomatis dan permanen. Gunakan template XLSX agar format kolom tetap sesuai. Sistem membaca pemisah koma (,) maupun titik koma (;) pada file hasil konversi.</li></ul></div>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<label>Tahun Pelajaran *<select class="form-control" name="tahun_pelajaran_id" required><?php foreach($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $yearId === (int)$y['id'] ? 'selected' : '' ?>><?=e((string)$y['nama'])?><?= (int)$y['aktif']===1 ? ' (aktif)' : '' ?></option><?php endforeach; ?></select></label><label>File Excel (.xlsx)<input class="form-control" type="file" name="file" accept=".xlsx" required></label><div class="form-actions"><button class="btn btn-success" type="submit"><i class="bi bi-upload"></i> Impor XLSX</button></div></form>
</div></section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
