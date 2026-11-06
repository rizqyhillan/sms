<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';

$errors=[]; $success='';
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
        $required=['nip','nama','jk','no_hp','mapel','status'];
        if($header!==$required) {
            $errors[]='Header XLSX tidak sesuai. Yang diharapkan: '.implode(', ',$required).'. Header yang terbaca: '.($header?implode(', ',$header):'(kosong)').'. Pastikan data berada di worksheet pertama dan baris pertama adalah header.';
        } else {
            $pdo->beginTransaction();
            try {
                $insert=$pdo->prepare("INSERT INTO guru (nip,nama,jk,no_hp,kode_qr,mapel,status) VALUES (NULLIF(?,''),?,NULLIF(?,''),NULLIF(?,''),?,?,?)");
                $count=0; $line=1;
                foreach(array_slice($rows,1) as $row){
                    $line++; $row=xlsx_flexible_row($row,6); $row=array_pad($row,6,'');
                    [$nip,$nama,$jk,$noHp,$mapel,$status]=array_map(fn($v)=>trim((string)$v),array_slice($row,0,6));
                    $jk=strtoupper($jk); $status=strtolower($status);
                    if($nip===''&&$nama==='') continue;
                    if($nama==='') throw new RuntimeException("Baris $line: nama guru wajib diisi.");
                    if($jk!==''&&!in_array($jk,['L','P'],true)) throw new RuntimeException("Baris $line: JK harus L, P, atau kosong.");
                    if($status==='') $status='aktif';
                    if(!in_array($status,['aktif','nonaktif'],true)) throw new RuntimeException("Baris $line: status harus aktif atau nonaktif.");
                    $qr=guru_generate_qr($pdo); $insert->execute([$nip,$nama,$jk,$noHp,$qr,$mapel,$status]); $count++;
                }
                $pdo->commit(); guru_audit($pdo,'IMPORT',0,'Impor '.$count.' guru dari XLSX'); $success="Impor berhasil: $count guru.";
            } catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                if((int)($e->errorInfo[1]??0)===1062)$errors[]='Import dibatalkan karena ada NIP duplikat dengan data yang sudah ada atau sesama baris import.'; else $errors[]=$e->getMessage();
            }
        }
    } catch(Throwable $e){$errors[]=$e->getMessage();}
}
$page_title='Impor Guru'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main guru-subpage guru-import-page"><header class="topbar"><div class="topbar-title">Impor Guru</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content"><div class="page-header"><div><h1>Impor Guru dari XLSX</h1><p>Gunakan file <strong>.xlsx</strong>. QR guru dibuat otomatis dan tetap permanen.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>guru/index.php">Kembali</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="form-card"><div class="section-title"><div><h2>Format Excel (XLSX)</h2><p>Header harus berada pada baris pertama dan urutannya jangan diubah.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>guru/template_import.xlsx">Download Template XLSX</a></div>
<p><strong>Header:</strong> <code>nip, nama, jk, no_hp, mapel, status</code></p><p class="text-muted">Sistem membaca XLSX normal dan hasil konversi yang menyimpan kolom dengan pemisah <strong>koma (,)</strong> atau <strong>titik koma (;)</strong>.</p>
<p><strong>Wajib:</strong> Nama. <strong>Opsional:</strong> NIP, JK, No. HP, Mapel. <strong>Status:</strong> boleh kosong, otomatis <code>aktif</code>. JK jika diisi: L/P.</p>
<div class="alert alert-info"><strong>Petunjuk:</strong> Kolom opsional boleh kosong. Jangan menghapus atau mengganti nama/urutan header. Gunakan template XLSX dan jangan mengubah nama atau urutan header. QR tidak perlu diisi karena dibuat otomatis oleh sistem.</div>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><label>File Excel (.xlsx)<input class="form-control" type="file" name="file" accept=".xlsx" required></label><div class="form-actions"><button class="btn btn-primary" type="submit">Impor XLSX</button></div></form></div></section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
