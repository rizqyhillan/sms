<?php
declare(strict_types=1);require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/_helper.php';perpus_require_operate();header('Content-Type: application/json; charset=utf-8');
function jout(array $d,int $s=200):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
perpus_sync_members($pdo);$type=(string)($_GET['type']??'');$q=trim((string)($_GET['q']??''));$kelasId=(int)($_GET['kelas_id']??0);$action=(string)($_GET['action']??'');
if($action==='resolve_qr'){
 $code=trim((string)($_GET['code']??''));if($code==='')jout(['ok'=>false,'message'=>'Kode QR kosong.'],422);
 $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,COALESCE(mk.nama,'-') kelas,a.id anggota_id,a.kode_anggota,a.status anggota_status FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN perpustakaan_anggota a ON a.siswa_id=s.id WHERE s.status='aktif' AND s.kode_qr=? LIMIT 1");$st->execute([$code]);if($s=$st->fetch())jout(['ok'=>true,'type'=>'siswa','data'=>$s]);
 $st=$pdo->prepare("SELECT g.id,g.nip,g.nama,g.kode_qr,a.id anggota_id,a.kode_anggota,a.status anggota_status FROM guru g LEFT JOIN perpustakaan_anggota a ON a.guru_id=g.id WHERE g.status='aktif' AND g.kode_qr=? LIMIT 1");$st->execute([$code]);if($g=$st->fetch())jout(['ok'=>true,'type'=>'guru','data'=>$g]);
 
 $st=$pdo->prepare("SELECT e.id eksemplar_id,e.kode_eksemplar,e.status eksemplar_status,e.kondisi,b.id buku_id,b.kode_buku,b.judul,b.penulis FROM perpustakaan_eksemplar e JOIN perpustakaan_buku b ON b.id=e.buku_id WHERE e.kode_eksemplar=? LIMIT 1");$st->execute([$code]);if($e=$st->fetch())jout(['ok'=>true,'type'=>'eksemplar','data'=>$e]);
 $st=$pdo->prepare("SELECT id,kode_buku,judul,stok,status FROM perpustakaan_buku WHERE kode_buku=? LIMIT 1");$st->execute([$code]);if($b=$st->fetch())jout(['ok'=>true,'type'=>'buku','data'=>$b]);
 jout(['ok'=>false,'message'=>'QR tidak dikenali sebagai siswa, guru, atau eksemplar buku.'],404);
}
if($type==='siswa'){
 if(mb_strlen($q)<1)jout(['ok'=>true,'data'=>[]]);$like='%'.$q.'%';$sql="SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,COALESCE(mk.nama,'-') kelas,a.id anggota_id,a.kode_anggota,a.status anggota_status FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN perpustakaan_anggota a ON a.siswa_id=s.id WHERE s.status='aktif' AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.kode_qr LIKE ?)".($kelasId>0?' AND sk.kelas_id=?':'')." ORDER BY s.nama LIMIT 20";$p=[$like,$like,$like,$like];if($kelasId>0)$p[]=$kelasId;$st=$pdo->prepare($sql);$st->execute($p);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($type==='guru'){
 if(mb_strlen($q)<1)jout(['ok'=>true,'data'=>[]]);$like='%'.$q.'%';$st=$pdo->prepare("SELECT g.id,g.nip,g.nama,g.kode_qr,a.id anggota_id,a.kode_anggota FROM guru g LEFT JOIN perpustakaan_anggota a ON a.guru_id=g.id AND a.status='aktif' WHERE g.status='aktif' AND (g.nama LIKE ? OR g.nip LIKE ? OR g.kode_qr LIKE ?) ORDER BY g.nama LIMIT 20");$st->execute([$like,$like,$like]);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($type==='anggota'){
 if(mb_strlen($q)<2)jout(['ok'=>true,'data'=>[]]);$like='%'.$q.'%';$st=$pdo->prepare("SELECT a.id,a.kode_anggota,COALESCE(s.nama,g.nama) nama,s.nis,s.nisn,g.nip FROM perpustakaan_anggota a LEFT JOIN siswa s ON s.id=a.siswa_id LEFT JOIN guru g ON g.id=a.guru_id WHERE a.status='aktif' AND (a.kode_anggota LIKE ? OR s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR g.nama LIKE ? OR g.nip LIKE ?) ORDER BY nama LIMIT 20");$st->execute([$like,$like,$like,$like,$like,$like]);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($type==='buku'){
 if(mb_strlen($q)<1)jout(['ok'=>true,'data'=>[]]);$like='%'.$q.'%';$st=$pdo->prepare("SELECT b.id,b.kode_buku,b.judul,b.isbn,b.penulis,b.status,COUNT(e.id) total_eksemplar,SUM(e.status='tersedia') tersedia FROM perpustakaan_buku b LEFT JOIN perpustakaan_eksemplar e ON e.buku_id=b.id WHERE b.status='tersedia' AND (b.kode_buku LIKE ? OR b.isbn LIKE ? OR b.judul LIKE ? OR b.penulis LIKE ?) GROUP BY b.id ORDER BY b.judul LIMIT 20");$st->execute([$like,$like,$like,$like]);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($type==='eksemplar'){
 $bukuId=(int)($_GET['buku_id']??0); if($bukuId<=0)jout(['ok'=>true,'data'=>[]]); $st=$pdo->prepare("SELECT e.id eksemplar_id,e.kode_eksemplar,e.status,e.kondisi,b.judul,b.kode_buku FROM perpustakaan_eksemplar e JOIN perpustakaan_buku b ON b.id=e.buku_id WHERE e.buku_id=? AND e.status='tersedia' ORDER BY e.id LIMIT 50");$st->execute([$bukuId]);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($type==='siswa_loans'){
 $studentId=(int)($_GET['student_id']??0);if($studentId<=0)jout(['ok'=>true,'data'=>[]]);$st=$pdo->prepare("SELECT p.id,p.nomor_transaksi,p.tanggal_pinjam,p.tanggal_jatuh_tempo,p.status,COUNT(d.id) jumlah_buku FROM perpustakaan_peminjaman p JOIN perpustakaan_anggota a ON a.id=p.anggota_id JOIN perpustakaan_detail_peminjaman d ON d.peminjaman_id=p.id AND d.status='dipinjam' WHERE a.siswa_id=? GROUP BY p.id ORDER BY p.tanggal_jatuh_tempo ASC,p.id DESC");$st->execute([$studentId]);jout(['ok'=>true,'data'=>$st->fetchAll()]);
}
jout(['ok'=>false,'message'=>'Permintaan tidak valid.'],400);
