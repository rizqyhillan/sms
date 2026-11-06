<?php
declare(strict_types=1);
require_once __DIR__.'/_helper.php';
$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0);ekstra_require($extraId);$extra=ekstra_detail($pdo,$extraId);if(!$extra)deny_access();$tp=active_tapel_id($pdo);if(!$tp)exit('Tahun Pelajaran aktif belum tersedia.');
$month=(string)($_GET['month']??$_POST['month']??date('Y-m'));if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');$from=$month.'-01';$to=date('Y-m-t',strtotime($from));$selectedSessionId=(int)($_GET['sesi_id']??$_POST['sesi_id']??0);$error='';$success='';
function foto_kompres_ke_jpg(string $tmpPath, string $destPath, int $maxSide = 1600, int $quality = 80): bool
{
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
        return false;
    }
    $info = @getimagesize($tmpPath);
    if (!$info || empty($info['mime'])) return false;

    $src = null;
    switch ($info['mime']) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            if (!function_exists('imagecreatefrompng')) return false;
            $src = @imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) return false;
            $src = @imagecreatefromwebp($tmpPath);
            break;
        default:
            return false;
    }
    if (!$src) return false;

    $width = imagesx($src);
    $height = imagesy($src);
    if ($width < 1 || $height < 1) {
        imagedestroy($src);
        return false;
    }

    // Batasi sisi terpanjang agar foto tetap tajam untuk cetak A4 4 foto,
    // tetapi ukuran penyimpanan jauh lebih kecil.
    $scale = min(1, $maxSide / max($width, $height));
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // JPEG tidak mendukung transparansi. Gunakan latar putih agar PNG/WEBP
    // transparan tidak menjadi hitam setelah dikonversi.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $ok = @imagejpeg($dst, $destPath, $quality);
    imagedestroy($dst);
    imagedestroy($src);
    return $ok && is_file($destPath) && filesize($destPath) > 0;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $selectedSessionId=(int)($_POST['sesi_id']??0);
    $caption=trim((string)($_POST['keterangan']??''));
    if($selectedSessionId<1||empty($_FILES['foto']['tmp_name'])) {
        $error='Tanggal dan foto wajib diisi.';
    } else {
        $vq=$pdo->prepare("SELECT id,tanggal FROM ekstra_sesi WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND tanggal BETWEEN ? AND ? AND status<>'dibatalkan' LIMIT 1");
        $vq->execute([$selectedSessionId,$extraId,$tp,$from,$to]);
        $s=$vq->fetch();
        if(!$s) {
            $error='Pertemuan yang dipilih tidak valid. Silakan pilih kembali pertemuan.';
        } else {
            $f=$_FILES['foto'];
            $allowed=['image/jpeg','image/png','image/webp'];
            $mime='';
            if(!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            }
            if($f['error']!==UPLOAD_ERR_OK || !in_array($mime,$allowed,true)) {
                $error='Format foto harus JPG, PNG, atau WEBP.';
            } elseif((int)$f['size']>5*1024*1024) {
                $error='Ukuran foto asli maksimal 5 MB.';
            } elseif(!function_exists('imagejpeg')) {
                $error='Fitur kompresi gambar PHP (GD) belum aktif di server.';
            } else {
                $dir=__DIR__.'/../uploads/ekstra';
                if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) {
                    $error='Folder penyimpanan foto tidak dapat dibuat.';
                } else {
                    $name='ekstra_'.$extraId.'_'.$date.'_'.bin2hex(random_bytes(5)).'.jpg';
                    $path=$dir.'/'.$name;
                    if(!foto_kompres_ke_jpg($f['tmp_name'],$path,1600,80)) {
                        $error='Foto gagal dikompres. Pastikan file gambar valid dan GD PHP aktif.';
                    } else {
                        $rel='uploads/ekstra/'.$name;
                        $pdo->beginTransaction();
                        try {
                            $st=$pdo->prepare('SELECT id,file_path FROM ekstra_dokumentasi WHERE sesi_id=? LIMIT 1');
                            $st->execute([(int)$s['id']]);
                            $old=$st->fetch();

                            if($old) {
                                $q=$pdo->prepare('UPDATE ekstra_dokumentasi SET file_path=?,keterangan=?,uploaded_by=?,created_at=CURRENT_TIMESTAMP WHERE id=?');
                                $q->execute([$rel,$caption!==''?$caption:null,user_id(),(int)$old['id']]);
                            } else {
                                $q=$pdo->prepare('INSERT INTO ekstra_dokumentasi(sesi_id,file_path,keterangan,uploaded_by) VALUES(?,?,?,?)');
                                $q->execute([(int)$s['id'],$rel,$caption!==''?$caption:null,user_id()]);
                            }
                            $pdo->commit();

                            // Hapus file lama setelah transaksi berhasil agar tidak ada
                            // file lama yang hilang jika penyimpanan database gagal.
                            if($old && !empty($old['file_path'])) {
                                $oldPath=__DIR__.'/../'.ltrim((string)$old['file_path'],'/');
                                if(is_file($oldPath)) @unlink($oldPath);
                            }
                            $kb=round(((int)filesize($path))/1024,1);
                            $success=$old
                                ? 'Foto pertemuan berhasil diganti dan dikompres menjadi '.$kb.' KB.'
                                : 'Foto kegiatan berhasil disimpan dan dikompres menjadi '.$kb.' KB.';
                        } catch(Throwable $ex) {
                            if($pdo->inTransaction()) $pdo->rollBack();
                            if(is_file($path)) @unlink($path);
                            $error='Foto gagal disimpan. Silakan coba lagi.';
                        }
                    }
                }
            }
        }
    }
}

$st=$pdo->prepare("SELECT es.id,es.tanggal,es.jam_mulai,es.jam_selesai,ed.file_path,ed.keterangan FROM ekstra_sesi es JOIN ekstra_dokumentasi ed ON ed.sesi_id=es.id WHERE es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=? AND es.tanggal BETWEEN ? AND ? ORDER BY es.tanggal,es.id");$st->execute([$extraId,$tp,$from,$to]);$rows=$st->fetchAll();
$page_title='Dokumentasi Foto Ekstra';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?><main class="main extra-page"><header class="topbar"><div class="topbar-title">Dokumentasi Foto</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content"><div class="page-header"><div><h1><?=e($extra['nama'])?> · Dokumentasi Foto</h1><p>1 foto setiap pertemuan · cetak A4 berisi 4 foto.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/saya.php">← Kembali</a></div><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="form-card"><form method="get" class="form-actions"><input type="hidden" name="extra_id" value="<?=$extraId?>"><label>Bulan<input class="form-control" type="month" name="month" value="<?=e($month)?>"></label><button class="btn btn-primary">Tampilkan</button></form></div>
<div class="form-card"><h2><i class="bi bi-camera"></i> Tambah Foto Pertemuan</h2><form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><div class="dashboard-grid" style="grid-template-columns:1fr 1fr"><label style="grid-column:1/-1">Pertemuan<select name="sesi_id" class="form-control" required><option value="">Pilih pertemuan</option><?php $allSessions=$pdo->prepare("SELECT id,tanggal,jam_mulai,jam_selesai FROM ekstra_sesi WHERE ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND tanggal BETWEEN ? AND ? AND status<>'dibatalkan' ORDER BY tanggal DESC,id DESC");$allSessions->execute([$extraId,$tp,$from,$to]);foreach($allSessions->fetchAll() as $ss): ?><option value="<?=((int)$ss['id'])?>" <?=$selectedSessionId===(int)$ss['id']?'selected':''?>><?=e(date('d/m/Y',strtotime($ss['tanggal'])))?> · <?=e(substr((string)$ss['jam_mulai'],0,5))?><?=!empty($ss['jam_selesai'])?' - '.e(substr((string)$ss['jam_selesai'],0,5)):''?></option><?php endforeach; ?></select></label><label>Foto<input type="file" class="form-control" name="foto" accept="image/jpeg,image/png,image/webp" required></label><label>Keterangan<input class="form-control" name="keterangan" placeholder="Contoh: Praktik simpul dan ikatan"></label></div><button class="btn btn-primary">Simpan Foto</button></form></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Dokumentasi <?=e(date('F Y',strtotime($from)))?></h2></div><button class="btn btn-secondary" onclick="window.print()">🖨 Cetak A4</button></div><?php if(!$rows):?><div class="empty-state">Belum ada foto pada bulan ini.</div><?php else:$chunks=array_chunk($rows,4);foreach($chunks as $pageNo=>$pageRows):?><div class="photo-page"><div class="photo-page-title"><?=e($extra['nama'])?> · <?=e(date('F Y',strtotime($from)))?> · Halaman <?=($pageNo+1)?></div><div class="photo-grid"><?php foreach($pageRows as $r):?><figure><img src="<?=BASE_URL.e($r['file_path'])?>" alt="Dokumentasi"><figcaption><strong><?=e($r['tanggal'])?></strong><br><?=e(substr((string)$r['jam_mulai'],0,5))?><?=!empty($r['jam_selesai'])?' - '.e(substr((string)$r['jam_selesai'],0,5)):''?><?=!empty($r['keterangan'])?'<br>'.e($r['keterangan']):''?></figcaption></figure><?php endforeach;for($i=count($pageRows);$i<4;$i++):?><div class="photo-empty"></div><?php endfor;?></div></div><?php endforeach;endif;?></div></section></main>
<style>.photo-page{padding:12px}.photo-page-title{font-weight:700;text-align:center;margin-bottom:12px}.photo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.photo-grid figure,.photo-empty{margin:0;border:1px solid #e2e8f0;padding:8px;background:#fff}.photo-grid img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}.photo-grid figcaption{padding:8px 2px;font-size:.85rem}.photo-empty{visibility:hidden}@media print{@page{size:A4 portrait;margin:12mm}.sidebar,.topbar,.page-header,.form-card,.section-title,.alert{display:none!important}.main{margin:0!important}.content{padding:0!important}.dashboard-table-card{border:0;box-shadow:none}.photo-page{height:calc(297mm - 24mm);padding:0;break-after:page;box-sizing:border-box}.photo-page:last-child{break-after:auto}.photo-page-title{font-size:12pt;margin:0 0 6mm}.photo-grid{gap:6mm}.photo-grid figure,.photo-empty{padding:5px}.photo-grid img{aspect-ratio:4/3}.photo-grid figcaption{font-size:9pt;padding:5px 2px}.photo-empty{visibility:hidden}}</style><?php require_once __DIR__.'/../layouts/footer.php';?>