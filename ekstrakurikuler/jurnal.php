<?php
declare(strict_types=1);
require_once __DIR__.'/_helper.php';

$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0);
ekstra_require($extraId);
$extra=ekstra_detail($pdo,$extraId);
if(!$extra) deny_access();
$tp=active_tapel_id($pdo);
if(!$tp) exit('Tahun Pelajaran aktif belum tersedia.');

$month=(string)($_GET['month']??$_POST['month']??date('Y-m'));
if(!preg_match('/^\d{4}-\d{2}$/',$month)) $month=date('Y-m');
$from=$month.'-01'; $to=date('Y-m-t',strtotime($from));
$selectedSessionId=(int)($_GET['sesi_id']??$_POST['sesi_id']??0);
$selectedDate=(string)($_GET['tanggal']??$_POST['tanggal']??'');
if($selectedDate && ($selectedDate<$from || $selectedDate>$to)) $selectedDate='';
$error=''; $success='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $action=(string)($_POST['action']??'');
    if($action==='save'){
        $date=(string)$_POST['tanggal'];
        $selectedSessionId=(int)($_POST['sesi_id']??0);
        $tujuan=trim((string)$_POST['tujuan']);
        $materi=trim((string)$_POST['materi']);
        $kegiatan=trim((string)$_POST['kegiatan']);
        $hasil=trim((string)$_POST['hasil']);
        $catatan=trim((string)$_POST['catatan']);
        $selectedDate=$date;
        if($date<$from || $date>$to || $kegiatan===''){
            $error='Tanggal dan uraian kegiatan wajib diisi.';
        }else{
            $validSession=null;
            $vq=$pdo->prepare("SELECT id,tanggal,jam_mulai,jam_selesai,status FROM ekstra_sesi WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=? AND tanggal BETWEEN ? AND ? AND status<>'dibatalkan' LIMIT 1");
            $vq->execute([$selectedSessionId,$extraId,$tp,$from,$to]);
            $validSession=$vq->fetch() ?: null;
            if(!$validSession){
                $error='Pertemuan yang dipilih tidak valid. Silakan pilih kembali pertemuan.';
            }else{
                $q=$pdo->prepare("INSERT INTO ekstra_jurnal(sesi_id,tujuan,materi,kegiatan,hasil,catatan,dibuat_oleh) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tujuan=VALUES(tujuan),materi=VALUES(materi),kegiatan=VALUES(kegiatan),hasil=VALUES(hasil),catatan=VALUES(catatan),dibuat_oleh=VALUES(dibuat_oleh)");
                $q->execute([(int)$validSession['id'],$tujuan,$materi,$kegiatan,$hasil,$catatan,user_id()]);
                $success='Jurnal kegiatan pertemuan disimpan.';
            }
        }
    }
}

$st=$pdo->prepare("SELECT es.id,es.tanggal,es.jam_mulai,es.jam_selesai,es.status,
    ej.tujuan,ej.materi,ej.kegiatan,ej.hasil,ej.catatan
    FROM ekstra_sesi es LEFT JOIN ekstra_jurnal ej ON ej.sesi_id=es.id
    WHERE es.ekstrakurikuler_id=? AND es.tahun_pelajaran_id=? AND es.tanggal BETWEEN ? AND ?
      AND es.status<>'dibatalkan' ORDER BY es.tanggal ASC,es.id ASC");
$st->execute([$extraId,$tp,$from,$to]); $rows=$st->fetchAll();

$selected=null;
if($selectedSessionId>0){ foreach($rows as $r){ if((int)$r['id']===$selectedSessionId){$selected=$r;break;} } }
if(!$selected && $selectedDate!==''){ foreach($rows as $r){ if($r['tanggal']===$selectedDate){$selected=$r;break;} } }
if(!$selected && $rows){ $selected=$rows[0]; }
if($selected){ $selectedSessionId=(int)$selected['id']; $selectedDate=(string)$selected['tanggal']; }

$q=$pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');$q->execute([$tp]);$tpName=(string)($q->fetchColumn()?:'');
$page_title='Jurnal Kegiatan Ekstra';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main extra-page">
<header class="topbar"><div class="topbar-title">Jurnal Kegiatan</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><?=e($extra['nama'])?> · Jurnal Kegiatan</h1><p>Jurnal dibuat <strong>per pertemuan</strong> · Tapel <?=e($tpName)?></p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/saya.php">← Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>

<div class="form-card">
<form method="get" class="form-actions" style="align-items:end;flex-wrap:wrap">
<input type="hidden" name="extra_id" value="<?=$extraId?>">
<label>Bulan<input class="form-control" type="month" name="month" value="<?=e($month)?>"></label>
<label>Pertemuan
<select class="form-control" name="sesi_id">
<option value="">Pilih pertemuan</option>
<?php foreach($rows as $r): ?><option value="<?=((int)$r['id'])?>" <?=$selectedSessionId===(int)$r['id']?'selected':''?>><?=e(date('d/m/Y',strtotime($r['tanggal'])))?><?=!empty($r['jam_mulai'])?' · '.e(substr((string)$r['jam_mulai'],0,5)):''?></option><?php endforeach; ?>
</select></label>
<button class="btn btn-primary">Tampilkan</button>
</form>
</div>

<div class="form-card">
<h2><i class="bi bi-journal-text"></i> Jurnal Pertemuan</h2>
<?php if(!$selected): ?>
<div class="empty-state">Belum ada pertemuan pada bulan ini. Buat/aktifkan jadwal terlebih dahulu.</div>
<?php else: ?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save"><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="sesi_id" value="<?=$selectedSessionId?>">
<div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<label>Tanggal<input type="date" name="tanggal" class="form-control" value="<?=e($selected['tanggal'])?>" readonly></label>
<label>Waktu<input class="form-control" value="<?=e(($selected['jam_mulai']??'-').' - '.($selected['jam_selesai']??'-'))?>" readonly></label>
<label>Tujuan<textarea name="tujuan" class="form-control" rows="2"><?=e($selected['tujuan']??'')?></textarea></label>
<label>Materi<textarea name="materi" class="form-control" rows="2"><?=e($selected['materi']??'')?></textarea></label>
<label style="grid-column:1/-1">Kegiatan / Uraian<textarea name="kegiatan" class="form-control" rows="4" required><?=e($selected['kegiatan']??'')?></textarea></label>
<label>Hasil / Capaian<textarea name="hasil" class="form-control" rows="3"><?=e($selected['hasil']??'')?></textarea></label>
<label>Catatan<textarea name="catatan" class="form-control" rows="3"><?=e($selected['catatan']??'')?></textarea></label>
</div>
<div class="form-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Jurnal Pertemuan</button><a class="btn btn-secondary" target="_blank" href="<?=BASE_URL?>ekstrakurikuler/jurnal.php?extra_id=<?=$extraId?>&month=<?=e($month)?>&sesi_id=<?=$selectedSessionId?>&print=1"><i class="bi bi-printer"></i> Cetak Pertemuan</a></div>
</form>
<?php endif; ?>
</div>

<div class="dashboard-table-card">
<div class="section-title"><div><h2>Daftar Pertemuan <?=e(date('F Y',strtotime($from)))?></h2><p class="text-muted">Hanya tanggal kegiatan yang tercatat sebagai pertemuan yang ditampilkan.</p></div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Waktu</th><th>Jurnal</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="4" class="empty-state">Belum ada pertemuan.</td></tr><?php else: foreach($rows as $r): ?><tr><td><?=e(date('d/m/Y',strtotime($r['tanggal'])))?></td><td><?=e(($r['jam_mulai']??'-').' - '.($r['jam_selesai']??'-'))?></td><td><?=!empty($r['kegiatan'])?'<span class="badge bg-success">Terisi</span>':'<span class="badge bg-secondary">Belum diisi</span>'?></td><td><a class="btn btn-secondary btn-sm btn-icon-sm" title="Buka/edit jurnal" aria-label="Buka/edit jurnal" href="<?=BASE_URL?>ekstrakurikuler/jurnal.php?extra_id=<?=$extraId?>&month=<?=e($month)?>&sesi_id=<?=((int)$r['id'])?>"><i class="bi bi-pencil-square" aria-hidden="true"></i></a></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div>
</section></main>
<style>
@media print{.sidebar,.topbar,.page-header,.form-card,.dashboard-table-card{display:none!important}.main{margin:0!important}.content{padding:0!important}.jurnal-print{display:block!important}}
.jurnal-print{display:none}
</style>
<?php
if(isset($_GET['print']) && $_GET['print']==='1' && $selected):
?>
<div class="jurnal-print" style="position:fixed;inset:0;background:#fff;padding:24px;font-family:Arial,sans-serif">
<div style="text-align:center"><h2 style="margin:0">JURNAL KEGIATAN EKSTRAKURIKULER</h2><h3 style="margin:6px 0"><?=e($extra['nama'])?></h3><div>Tapel <?=e($tpName)?> · Tanggal <?=e(date('d/m/Y',strtotime($selected['tanggal'])))?></div></div>
<table style="width:100%;border-collapse:collapse;margin-top:20px" border="1" cellpadding="8">
<tr><th style="width:22%;text-align:left">Waktu</th><td><?=e(($selected['jam_mulai']??'-').' - '.($selected['jam_selesai']??'-'))?></td></tr>
<tr><th style="text-align:left">Tujuan</th><td><?=nl2br(e($selected['tujuan']??'-'))?></td></tr>
<tr><th style="text-align:left">Materi</th><td><?=nl2br(e($selected['materi']??'-'))?></td></tr>
<tr><th style="text-align:left">Kegiatan / Uraian</th><td><?=nl2br(e($selected['kegiatan']??'-'))?></td></tr>
<tr><th style="text-align:left">Hasil / Capaian</th><td><?=nl2br(e($selected['hasil']??'-'))?></td></tr>
<tr><th style="text-align:left">Catatan</th><td><?=nl2br(e($selected['catatan']??'-'))?></td></tr>
</table>
<div style="margin-top:60px;display:flex;justify-content:flex-end"><div style="width:260px;text-align:center">Pembina/Pelatih<br><br><br><br><u><?=e(user_name())?></u></div></div>
<script>window.addEventListener('load',()=>window.print());</script>
</div>
<?php endif; ?>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
