<?php
declare(strict_types=1);
require_once __DIR__.'/../absensi/_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    require_login();
    $sesiId=(int)($_GET['sesi_id']??0);
    if($sesiId<1) throw new RuntimeException('Sesi tidak valid.');
    $s=absensi_find_session($pdo,$sesiId);
    if(!$s) throw new RuntimeException('Sesi tidak ditemukan.');
    if($s['kategori']==='sholat'){
        $q=$pdo->prepare("SELECT s.nama, COALESCE(mk.nama,'Belum Ditempatkan') kelas, ash.status, DATE_FORMAT(ash.waktu_scan,'%H:%i:%s') waktu FROM absensi_sholat ash JOIN siswa s ON s.id=ash.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE ash.sesi_id=? ORDER BY ash.waktu_scan DESC LIMIT 10");
    } else {
        $q=$pdo->prepare("SELECT s.nama, COALESCE(mk.nama,'Belum Ditempatkan') kelas, a.status, DATE_FORMAT(a.waktu_scan,'%H:%i:%s') waktu FROM absensi a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE a.sesi_id=? ORDER BY a.waktu_scan DESC LIMIT 10");
    }
    $q->execute([$sesiId]);
    echo json_encode(['ok'=>true,'rows'=>$q->fetchAll()],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
