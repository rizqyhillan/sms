<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/school_profile.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);
$id=max(0,(int)($_GET['id']??0));
if($id<=0) exit('Rujukan tidak ditemukan.');
$st=$pdo->prepare("SELECT r.*, uk.tanggal AS kunjungan_tanggal, uk.waktu AS kunjungan_waktu,
 s.nis,s.nisn,s.nama,s.jk, mk.nama AS kelas, g.nama AS pendamping_guru,
 tp.nama AS tahun_pelajaran
 FROM uks_rujukan r
 JOIN uks_kunjungan uk ON uk.id=r.kunjungan_id
 JOIN siswa s ON s.id=r.siswa_id
 LEFT JOIN guru g ON g.id=r.pendamping_guru_id
 LEFT JOIN tahun_pelajaran tp ON tp.id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1)
 LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1) AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
 LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
 WHERE r.id=? LIMIT 1");
$st->execute([$id]);$r=$st->fetch();
if(!$r) exit('Rujukan tidak ditemukan.');
if(!uks_teacher_can_view_student($pdo,(int)$r['siswa_id'])) deny_access();
$p=school_profile($pdo);$logo=school_logo_url($pdo);
function cetak_jenis(string $v): string { return ['puskesmas'=>'Puskesmas','rumah_sakit'=>'Rumah Sakit','klinik'=>'Klinik','lainnya'=>'Fasilitas Kesehatan'][$v]??$v; }
function cetak_cara(string $v): string { return ['diantar_sekolah'=>'Diantar sekolah','diantar_orangtua'=>'Diantar orang tua/wali','datang_sendiri_dengan_persetujuan'=>'Datang sendiri dengan persetujuan','lainnya'=>'Lainnya'][$v]??$v; }
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Surat Rujukan UKS - <?=e($r['nama'])?></title><style>
@page{size:A4 portrait;margin:18mm 18mm 18mm 20mm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:13px;line-height:1.5;margin:0}.sheet{width:100%}.kop{display:flex;align-items:center;border-bottom:3px solid #111;padding-bottom:9px;margin-bottom:16px}.logo{width:72px;height:72px;object-fit:contain;margin-right:16px}.koptext{flex:1;text-align:center}.koptext h1{font-size:18px;margin:0;font-weight:700}.koptext h2{font-size:15px;margin:2px 0}.koptext p{font-size:11px;margin:2px 0}.title{text-align:center;margin:8px 0 16px}.title h2{font-size:16px;text-decoration:underline;margin:0}.title p{margin:2px 0}.intro{margin-bottom:12px}.info{width:100%;border-collapse:collapse;margin:8px 0 14px}.info td{padding:5px 7px;border:1px solid #bbb;vertical-align:top}.info td:first-child{width:31%;font-weight:700}.section{font-weight:700;margin:13px 0 5px}.box{border:1px solid #aaa;padding:9px;min-height:52px}.sign{display:grid;grid-template-columns:1fr 1fr;gap:35px;margin-top:35px;text-align:center}.sign .space{height:62px}.small{font-size:11px;color:#444}.actions{position:fixed;right:20px;top:18px;display:flex;gap:8px}.btn{border:0;padding:9px 14px;border-radius:6px;background:#111;color:#fff;cursor:pointer;font-size:12px}.btn.secondary{background:#666}@media print{.actions{display:none}.kop{break-inside:avoid}.info,.box,.sign{break-inside:avoid}}
</style></head><body><div class="actions"><button class="btn" onclick="window.print()">Cetak</button><button class="btn secondary" onclick="window.close()">Tutup</button></div><div class="sheet">
<div class="kop"> <?php if($logo): ?><img class="logo" src="<?=e($logo)?>" alt="Logo"> <?php endif; ?><div class="koptext"><h1><?=e($p['nama_sekolah']??'Sekolah')?></h1><h2>UNIT KESEHATAN SEKOLAH (UKS)</h2><p><?=e($p['alamat']??($p['alamat_sekolah']??''))?></p><?php if(!empty($p['telepon'])):?><p>Telp. <?=e($p['telepon'])?></p><?php endif;?></div></div>
<div class="title"><h2>SURAT RUJUKAN UKS</h2><p class="small">Nomor rujukan: UKS/<?=date('Y',strtotime((string)$r['tanggal']))?>/<?=str_pad((string)$id,4,'0',STR_PAD_LEFT)?></p></div>
<div class="intro">Dengan ini menerangkan bahwa siswa berikut mendapatkan rujukan dari Unit Kesehatan Sekolah untuk mendapatkan pemeriksaan/tindakan lebih lanjut.</div>
<table class="info"><tr><td>Nama Siswa</td><td><?=e($r['nama'])?></td></tr><tr><td>NIS / NISN</td><td><?=e($r['nis']??'-')?> / <?=e($r['nisn']??'-')?></td></tr><tr><td>Kelas</td><td><?=e($r['kelas']??'-')?></td></tr><tr><td>Hari/Tanggal</td><td><?=e($r['tanggal'])?><?=!empty($r['waktu'])?' · '.e($r['waktu']):''?></td></tr><tr><td>Tujuan Rujukan</td><td><?=e(cetak_jenis($r['tujuan_jenis']))?> — <strong><?=e($r['fasilitas_nama'])?></strong></td></tr><tr><td>Pendamping</td><td><?=e($r['pendamping_guru']??($r['pendamping_nama']??'-'))?></td></tr><tr><td>Cara Berangkat</td><td><?=e(cetak_cara($r['cara_berangkat']))?></td></tr></table>
<div class="section">Alasan Rujukan</div><div class="box"><?=nl2br(e($r['alasan']))?></div>
<div class="section">Hasil / Tindak Lanjut</div><div class="box"><?=nl2br(e($r['hasil_tindak_lanjut']??'-'))?></div>
<div class="section">Catatan</div><div class="box"><?=nl2br(e($r['catatan']??'-'))?></div>
<div class="sign"><div>Petugas UKS,<div class="space"></div><strong>(........................................)</strong></div><div>Orang Tua/Wali,<div class="space"></div><strong>(........................................)</strong></div></div>
<p class="small" style="margin-top:22px">Dokumen ini merupakan catatan administrasi rujukan UKS. Informasi pelayanan kesehatan digunakan sesuai kewenangan dan kebutuhan penanganan siswa.</p>
</div></body></html>
