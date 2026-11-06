<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/school_profile.php';
$school_profile = school_profile($pdo);
$id=max(0,(int)($_GET['id']??0));
$siswa=siswa_find($pdo,$id);
if(!$siswa){http_response_code(404);exit('Siswa tidak ditemukan.');}
$st=$pdo->prepare("SELECT mk.nama,sk.nomor_urut FROM siswa_kelas sk JOIN master_kelas mk ON mk.id=sk.kelas_id JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id WHERE sk.siswa_id=? AND sk.status='aktif' AND tp.aktif=1 ORDER BY sk.id DESC LIMIT 1");$st->execute([$id]);$kelasRow=$st->fetch()?:[];$kelas=$kelasRow['nama']??'Belum ditempatkan';
$page_title='Kartu Absensi Siswa';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Kartu Absensi Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content siswa-kartu-page">
<div class="page-header"><div><h1>Kartu Absensi Siswa</h1><p>Kartu satu sisi dengan logo sekolah, nama, peran, dan QR permanen.</p></div><div class="page-header-actions"><button class="btn btn-success" type="button" onclick="prepareCardPrint()"><i class="bi bi-printer" aria-hidden="true"></i> Cetak Kartu</button><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/detail.php?id=<?=$id?>">Kembali</a></div></div>
<div class="simple-id-preview">
<article class="simple-id-card student-id-card">
  <div class="id-slot" aria-hidden="true"></div>
  <div class="id-logo"><?php if(!empty($school_profile['logo'])):?><img src="<?=e(school_logo_url($pdo))?>" alt="Logo Sekolah"><?php else:?><i class="bi bi-building" aria-hidden="true"></i><?php endif;?></div>
  <div class="id-school"><?=e($school_profile['nama_sekolah'])?></div>
  <div class="id-name"><?=e($siswa['nama'])?></div>
  <div class="id-role">SISWA</div>
  <div class="id-qr"><div id="qrcode"></div><small>SCAN QR</small></div>
  <div class="id-footer">SPLASMA</div>
</article>
</div>
</section></main>
<style>
.simple-id-preview{display:flex;justify-content:center;padding:24px 16px 36px}
.simple-id-card{width:54mm;height:86mm;box-sizing:border-box;position:relative;overflow:hidden;background:#fff;border:1px solid #d8dee8;border-radius:5mm;box-shadow:0 18px 40px rgba(15,23,42,.12);text-align:center;padding:8.5mm 5mm 0;color:#0f172a}
.simple-id-card:after{content:'';position:absolute;left:0;right:0;bottom:0;height:12mm;background:linear-gradient(135deg,#173b7a,#2458a6);border-radius:0 0 4.5mm 4.5mm}
.id-slot{width:20mm;height:4mm;position:absolute;top:3.5mm;left:50%;transform:translateX(-50%);border-radius:99px;background:#e7ebf0;box-shadow:inset 0 1px 3px rgba(15,23,42,.16)}
.id-logo{width:11mm;height:11mm;margin:1.5mm auto 1mm;display:flex;align-items:center;justify-content:center}
.id-logo img{width:100%;height:100%;object-fit:contain}
.id-logo i{font-size:23pt;color:#173b7a}
.id-school{font-size:6.6pt;font-weight:900;line-height:1.05;text-transform:uppercase;max-width:44mm;margin:auto;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-height:4mm}
.id-name{font-size:11pt;font-weight:900;line-height:1.05;margin-top:3mm;min-height:6mm;max-width:44mm;margin-left:auto;margin-right:auto;word-break:break-word}
.id-role{display:inline-flex;margin-top:1.5mm;min-width:20mm;justify-content:center;padding:1.2mm 4mm;border-radius:99px;background:#eff6ff;color:#173b7a;font-size:6.2pt;font-weight:900;letter-spacing:.08em}
.id-qr{position:relative;z-index:2;margin:2.5mm auto 0}
.id-qr #qrcode,.id-qr .qr{width:24mm;height:24mm;margin:auto;background:#fff}
.id-qr canvas,.id-qr img{width:100%!important;height:100%!important;display:block}
.id-qr small{display:none}
.id-footer{position:absolute;z-index:3;left:0;right:0;bottom:3.2mm;color:#fff;font-size:11pt;font-weight:900;letter-spacing:.16em}

@media print{html,body{margin:0!important;padding:0!important;background:#fff!important}.sidebar,.topbar,.page-header{display:none!important}.main{margin:0!important;padding:0!important}.content{padding:0!important}.simple-id-preview{padding:0!important}.simple-id-card{box-shadow:none!important;position:absolute;left:50%;top:8mm;transform:translateX(-50%);break-inside:avoid;page-break-inside:avoid}.simple-id-card,.simple-id-card *{visibility:visible!important}}
@media(max-width:600px){.simple-id-preview{padding:18px 0}.simple-id-card{transform:scale(.96);transform-origin:top center;margin-bottom:-4mm}}
</style>>
<script src="<?= BASE_URL ?>assets/js/vendor/qrcode.min.js?v=91"></script><script>new QRCode(document.getElementById('qrcode'),{text:<?=json_encode((string)$siswa['kode_qr'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,width:300,height:300,correctLevel:QRCode.CorrectLevel.M});function prepareCardPrint(){document.querySelectorAll('#qrcode canvas').forEach(function(c){try{var img=document.createElement('img');img.src=c.toDataURL('image/png');img.alt='QR Absensi Siswa';c.replaceWith(img);}catch(e){}});setTimeout(function(){window.print();},250)}window.addEventListener('afterprint',function(){window.location.reload()});</script>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
