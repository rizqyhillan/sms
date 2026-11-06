<?php
declare(strict_types=1);require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/_helper.php';perpus_require_access();
$eid=(int)($_GET['eksemplar_id']??0);$bid=(int)($_GET['buku_id']??0);
$single=false;$rows=[];$title='';$kodeMaster='';
if($eid){
 $st=$pdo->prepare('SELECT e.*,b.judul,b.kode_buku,b.penulis FROM perpustakaan_eksemplar e JOIN perpustakaan_buku b ON b.id=e.buku_id WHERE e.id=?');$st->execute([$eid]);$r=$st->fetch();
 if(!$r)exit('Data tidak ditemukan.');$rows=[$r];$title=(string)$r['judul'];$kodeMaster=(string)$r['kode_buku'];$single=true;$back=BASE_URL.'perpustakaan/eksemplar.php?buku_id='.(int)$r['buku_id'];
}else{
 $st=$pdo->prepare('SELECT id,kode_buku,judul,penulis FROM perpustakaan_buku WHERE id=?');$st->execute([$bid]);$master=$st->fetch();
 if(!$master)exit('Data buku tidak ditemukan.');
 $st=$pdo->prepare('SELECT e.*,b.judul,b.kode_buku,b.penulis FROM perpustakaan_eksemplar e JOIN perpustakaan_buku b ON b.id=e.buku_id WHERE e.buku_id=? ORDER BY e.id');$st->execute([$bid]);$rows=$st->fetchAll();
 $title=(string)$master['judul'];$kodeMaster=(string)$master['kode_buku'];$back=BASE_URL.'perpustakaan/buku.php';
}
$page_title=$single?'QR Eksemplar':'Cetak Semua QR Eksemplar';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';?>
<main class="main"><header class="topbar"><div class="topbar-title">QR Eksemplar</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-qr-code"></i> <?= $single?'QR Eksemplar':'Cetak Semua QR' ?></h1><p><strong><?=e($title)?></strong> · <?=e($kodeMaster) ?> · <?=count($rows)?> eksemplar</p></div><div class="page-header-actions"><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak <?= $single?'QR':'Semua QR' ?></button><a class="btn btn-secondary" href="<?=$back?>"><i class="bi bi-arrow-left"></i> Kembali</a></div></div>
<?php if(!$rows):?><div class="alert alert-warning">Belum ada eksemplar untuk buku ini.</div><?php else:?>
<div class="qr-sheet"><div class="qr-grid">
<?php foreach($rows as $i=>$r):?><article class="qr-card"><div class="qr-box" id="qr<?=$i?>"></div><div class="qr-title"><?=e($r['judul'])?></div><div class="qr-code"><?=e($r['kode_eksemplar'])?></div><?php if(!empty($r['penulis'])):?><div class="qr-author"><?=e($r['penulis'])?></div><?php endif;?></article><?php endforeach;?>
</div></div><?php endif;?></section></main>
<style>
.qr-sheet{background:#fff;border-radius:16px;padding:24px}.qr-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;max-width:1000px;margin:0 auto}.qr-card{border:1px solid #d8dee9;border-radius:12px;padding:16px;text-align:center;background:#fff;break-inside:avoid;page-break-inside:avoid}.qr-box{display:flex;justify-content:center;align-items:center;min-height:170px;margin-bottom:8px}.qr-title{font-weight:800;font-size:15px;line-height:1.25}.qr-code{font-weight:700;font-size:14px;margin-top:5px;letter-spacing:.4px}.qr-author{font-size:11px;color:#667085;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}@media(max-width:900px){.qr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.qr-grid{grid-template-columns:1fr}}@media print{body *{visibility:hidden!important}.qr-sheet,.qr-sheet *{visibility:visible!important}.qr-sheet{position:absolute;left:0;top:0;width:100%;padding:8mm;background:#fff;border:0}.qr-grid{grid-template-columns:repeat(3,1fr);gap:8mm}.qr-card{border:1px solid #999;padding:5mm;border-radius:0}.qr-box{min-height:42mm}.qr-title{font-size:11pt}.qr-code{font-size:10pt}.qr-author{font-size:8pt}.page-header{display:none!important}@page{size:A4 portrait;margin:8mm}}
</style>
<script src="<?=BASE_URL?>assets/js/vendor/qrcode.min.js?v=92"></script><script>
<?php foreach($rows as $i=>$r):?>new QRCode(document.getElementById('qr<?=$i?>'),{text:<?=json_encode((string)$r['kode_eksemplar'])?>,width:160,height:160});<?php endforeach;?>
</script><?php require_once __DIR__.'/../layouts/footer.php';
