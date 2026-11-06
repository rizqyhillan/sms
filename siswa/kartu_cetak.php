<?php
declare(strict_types=1);require_once __DIR__.'/_helper.php';
require_once __DIR__.'/../includes/school_profile.php';
$school_profile = school_profile($pdo);
$cari=trim((string)($_GET['cari']??''));$kelas_id=max(0,(int)($_GET['kelas_id']??0));$where=["s.status='aktif'"];$params=[];
if($cari!==''){ $where[]='(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR s.kode_qr LIKE ?)';$q='%'.$cari.'%';array_push($params,$q,$q,$q,$q); }
if($kelas_id>0){$where[]='mk.id=?';$params[]=$kelas_id;}$w=implode(' AND ',$where);
$sql="SELECT s.id,s.nis,s.nama,s.kode_qr,mk.nama nama_kelas,sk.nomor_urut FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE $w ORDER BY COALESCE(mk.urutan,999),s.nama";$st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();$classes=$pdo->query("SELECT id,nama FROM master_kelas WHERE aktif=1 ORDER BY urutan,nama")->fetchAll();
$page_title='Cetak Kartu Absensi';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?><main class="main"><header class="topbar"><div class="topbar-title">Cetak Kartu Absensi</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content siswa-kartu-cetak-page">
<div class="page-header no-print"><div><h1>Cetak Kartu Absensi Siswa</h1><p><?=count($rows)?> kartu · satu sisi · desain sama dengan kartu guru, warna biru.</p></div><div class="page-header-actions"><button class="btn btn-success" type="button" onclick="prepareCardsPrint()"><i class="bi bi-printer" aria-hidden="true"></i> Cetak <?=count($rows)?> Kartu</button><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/index.php">Kembali</a></div></div>
<div class="dashboard-table-card no-print"><form method="get" class="filter-bar"><input class="form-control" name="cari" value="<?=e($cari)?>" placeholder="Cari nama, NIS, NISN, atau QR..."><select class="form-control" name="kelas_id"><option value="0">Semua Kelas</option><?php foreach($classes as $c):?><option value="<?= (int)$c['id']?>" <?=$kelas_id===(int)$c['id']?'selected':''?>><?=e($c['nama'])?></option><?php endforeach;?></select><button class="btn btn-secondary">Terapkan</button></form></div>
<?php if(!$rows):?><div class="empty-state">Tidak ada siswa yang sesuai.</div><?php else:?><div class="card-print-toolbar no-print"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> Format kartu vertikal 54 × 86 mm · 6 kartu per A4 · QR permanen.</div><div class="simple-id-sheet">
<?php foreach($rows as $r):?><article class="simple-id-card student-id-card"><div class="id-slot" aria-hidden="true"></div><div class="id-logo"><?php if(!empty($school_profile['logo'])):?><img src="<?=e(school_logo_url($pdo))?>" alt="Logo Sekolah"><?php else:?><i class="bi bi-building" aria-hidden="true"></i><?php endif;?></div><div class="id-school"><?=e($school_profile['nama_sekolah'])?></div><div class="id-name"><?=e($r['nama'])?></div><div class="id-role">SISWA</div><div class="id-qr"><div class="qr" id="qr-<?= (int)$r['id']?>"></div><small>SCAN QR</small></div><div class="id-footer">SPLASMA</div></article><?php endforeach;for($i=count($rows)%6;$i>0&&$i<6;$i--):?><article class="simple-id-card empty"></article><?php endfor;?></div><?php endif;?></section></main>
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

.simple-id-sheet{width:184mm;min-height:268mm;margin:0 auto;display:grid;grid-template-columns:repeat(2,54mm);grid-template-rows:repeat(3,86mm);gap:5mm 20mm;justify-content:center;align-content:start}
.simple-id-sheet .simple-id-card{box-shadow:none!important}
.card-print-toolbar{display:flex;align-items:center;gap:8px;margin:0 0 14px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:700}
.guru-card-print-page .card-print-toolbar{border:1px solid #fed7aa;background:#fff7ed;color:#9a3412}
.siswa-kartu-cetak-page .card-print-toolbar{border:1px solid #c7d2fe;background:#edf3ff;color:#173b7a}
.empty{visibility:hidden}
@page{size:A4 portrait;margin:10mm}
@media print{html,body{margin:0!important;padding:0!important;background:#fff!important}.no-print,.sidebar,.topbar,.page-header{display:none!important}.main{margin:0!important;padding:0!important}.content{padding:0!important}.simple-id-sheet{width:184mm;min-height:268mm;margin:0;gap:5mm 20mm}.simple-id-sheet,.simple-id-sheet *{visibility:visible!important}}
@media(max-width:800px){.simple-id-sheet{width:auto;display:flex;flex-wrap:wrap;justify-content:center;gap:18px}.simple-id-card{flex:0 0 54mm}}
</style>><script src="<?= BASE_URL ?>assets/js/vendor/qrcode.min.js?v=91"></script><script><?php foreach($rows as $r):?>new QRCode(document.getElementById('qr-<?= (int)$r['id']?>'),{text:<?=json_encode((string)$r['kode_qr'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,width:300,height:300,correctLevel:QRCode.CorrectLevel.M});<?php endforeach;?>function prepareCardsPrint(){document.querySelectorAll('.qr canvas').forEach(function(c){try{var img=document.createElement('img');img.src=c.toDataURL('image/png');img.alt='QR Absensi Siswa';c.replaceWith(img);}catch(e){}});setTimeout(function(){window.print();},300)}window.addEventListener('afterprint',function(){window.location.reload()});</script><?php require_once __DIR__.'/../layouts/footer.php'; ?>
