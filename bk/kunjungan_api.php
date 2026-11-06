<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/operational_hours.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);
if (user_role() === 'kepala_sekolah') {
    http_response_code(403); exit('Mode monitoring tidak dapat mencatat kunjungan BK.');
}
header('Content-Type: application/json; charset=utf-8');

function bk_json(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function bk_find_student_by_qr(PDO $pdo, string $qr): ?array {
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,s.status,COALESCE(mk.nama,'-') kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.kode_qr=? LIMIT 1");
    $st->execute([$qr]); $r=$st->fetch(); return $r?:null;
}
function bk_context(PDO $pdo, int $sid): ?array {
    $st=$pdo->prepare("SELECT sk.tahun_pelajaran_id,sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id WHERE sk.siswa_id=? AND sk.status='aktif' AND tp.aktif=1 ORDER BY sk.id DESC LIMIT 1");
    $st->execute([$sid]); $r=$st->fetch(); return $r?:null;
}
$action=(string)($_POST['action']??$_GET['action']??'');
if($action==='lookup'){
    $q=trim((string)($_GET['q']??''));
    if(mb_strlen($q)<2) bk_json(['ok'=>true,'data'=>[]]);
    $like='%'.$q.'%';
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,COALESCE(mk.nama,'-') kelas FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.status='aktif' AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.kode_qr LIKE ?) ORDER BY s.nama LIMIT 20");
    $st->execute([$like,$like,$like,$like]); bk_json(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($action==='scan'){
    if($_SERVER['REQUEST_METHOD']!=='POST') bk_json(['ok'=>false,'message'=>'Metode tidak valid.'],405);
    require_valid_csrf();
    $qr=trim((string)($_POST['kode_qr']??''));
    $metode=strtoupper((string)($_POST['metode']??'QR'));
    if(!in_array($metode,['QR','USB','MANUAL'],true))$metode='QR';
    if($qr==='') bk_json(['ok'=>false,'message'=>'QR siswa kosong.'],422);
    try { sekolah_require_operasional_aktif($pdo); } catch (RuntimeException $e) { bk_json(['ok'=>false,'message'=>$e->getMessage()],422); }
    $s=bk_find_student_by_qr($pdo,$qr);
    if(!$s || $s['status']!=='aktif') bk_json(['ok'=>false,'message'=>'QR tidak ditemukan atau siswa tidak aktif.'],404);
    $ctx=bk_context($pdo,(int)$s['id']);
    if(!$ctx) bk_json(['ok'=>false,'message'=>'Riwayat kelas/tahun pelajaran siswa tidak ditemukan.'],422);
    $dup=$pdo->prepare("SELECT id,waktu_kunjungan FROM bk_kunjungan WHERE siswa_id=? AND tanggal=CURDATE() AND waktu_kunjungan>=TIME(DATE_SUB(NOW(),INTERVAL 60 SECOND)) ORDER BY id DESC LIMIT 1");
    $dup->execute([(int)$s['id']]); $d=$dup->fetch();
    if($d) bk_json(['ok'=>false,'duplicate'=>true,'message'=>'Kunjungan siswa sudah tercatat dalam 60 detik terakhir.','nama'=>$s['nama'],'nis'=>$s['nis'],'kelas'=>$s['kelas'],'waktu'=>$d['waktu_kunjungan']]);
    $jenis=(string)($_POST['jenis_kunjungan']??'konseling');
    if(!in_array($jenis,['konseling','konsultasi','rujukan','administratif','lainnya'],true))$jenis='konseling';
    $ket=trim((string)($_POST['keterangan_singkat']??''));
    if(mb_strlen($ket)>255)$ket=mb_substr($ket,0,255);
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta'));
    $st=$pdo->prepare('INSERT INTO bk_kunjungan(siswa_id,tahun_pelajaran_id,kelas_id,tanggal,waktu_kunjungan,jenis_kunjungan,keterangan_singkat,metode,petugas_id,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $gid=bk_current_guru_id($pdo);
    $st->execute([(int)$s['id'],(int)$ctx['tahun_pelajaran_id'],(int)$ctx['kelas_id'],$now->format('Y-m-d'),$now->format('H:i:s'),$jenis,$ket!==''?$ket:null,$metode,$gid?:null,(int)($_SESSION['user_id']??0)?:null]);
    $id=(int)$pdo->lastInsertId(); bk_audit($pdo,'visit_create',$id,'Mencatat kunjungan BK siswa '.$s['nis']);
    bk_json(['ok'=>true,'id'=>$id,'nama'=>$s['nama'],'nis'=>$s['nis'],'nisn'=>$s['nisn'],'kelas'=>$s['kelas'],'waktu'=>$now->format('Y-m-d H:i:s'),'jenis'=>$jenis]);
}
bk_json(['ok'=>false,'message'=>'Aksi tidak valid.'],400);
