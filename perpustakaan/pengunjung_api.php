<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';
perpus_require_access();
header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function find_student(PDO $pdo, string $qr): ?array {
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,s.status,COALESCE(mk.nama,'-') AS kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.kode_qr=? LIMIT 1");
    $st->execute([$qr]); $r=$st->fetch(); return $r?:null;
}
$action=(string)($_POST['action']??$_GET['action']??'');
if($action==='recent'){
    $q=trim((string)($_GET['q']??''));
    $kelasId=(int)($_GET['kelas_id']??0);
    $where=['DATE(k.waktu_kunjungan)=CURDATE()']; $params=[];
    if($q!==''){$where[]='(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
    if($kelasId>0){$where[]='k.kelas_id=?';$params[]=$kelasId;}
    $st=$pdo->prepare("SELECT k.id,k.waktu_kunjungan,k.metode,s.nama,s.nis,s.nisn,COALESCE(mk.nama,'-') kelas,COALESCE(u.nama,'-') petugas FROM perpustakaan_kunjungan k JOIN siswa s ON s.id=k.siswa_id LEFT JOIN master_kelas mk ON mk.id=k.kelas_id LEFT JOIN users u ON u.id=k.petugas_id WHERE ".implode(' AND ',$where)." ORDER BY k.id DESC LIMIT 200");
    $st->execute($params); $rows=$st->fetchAll();
    $today=(int)$pdo->query('SELECT COUNT(*) FROM perpustakaan_kunjungan WHERE DATE(waktu_kunjungan)=CURDATE()')->fetchColumn();
    json_out(['ok'=>true,'today'=>$today,'data'=>$rows]);
}
if($action==='lookup'){
    $q=trim((string)($_GET['q']??''));
    $kelasId=(int)($_GET['kelas_id']??0);
    if(mb_strlen($q)<2) json_out(['ok'=>true,'data'=>[]]);
    $like='%'.$q.'%';
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,COALESCE(mk.nama,'-') AS kelas
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.status='aktif' AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.kode_qr LIKE ?)" . ($kelasId>0 ? " AND sk.kelas_id=?" : "") . "
        ORDER BY s.nama LIMIT 20");
    $lookupParams=[$like,$like,$like,$like]; if($kelasId>0)$lookupParams[]=$kelasId;
    $st->execute($lookupParams); json_out(['ok'=>true,'data'=>$st->fetchAll()]);
}
if($action==='scan'){
    if($_SERVER['REQUEST_METHOD']!=='POST'){json_out(['ok'=>false,'message'=>'Metode tidak valid.'],405);}
    require_valid_csrf();
    $qr=trim((string)($_POST['kode_qr']??''));
    $metode=(string)($_POST['metode']??'manual');
    if($qr==='') json_out(['ok'=>false,'message'=>'QR siswa kosong.'],422);
    if(!in_array($metode,['kamera','usb','manual'],true))$metode='manual';
    $s=find_student($pdo,$qr);
    if(!$s || $s['status']!=='aktif') json_out(['ok'=>false,'message'=>'QR tidak ditemukan atau siswa tidak aktif.'],404);
    // Seluruh aktivitas perpustakaan mengikuti waktu pembelajaran aktif sekolah.
    try { perpus_require_waktu_pembelajaran($pdo); } catch (RuntimeException $e) { json_out(['ok'=>false,'message'=>$e->getMessage()],422); }

    // Satu siswa hanya boleh memiliki satu kunjungan per hari.
    // Lock baris siswa terlebih dahulu agar dua scan bersamaan tidak dapat membuat
    // dua record untuk siswa yang sama (race condition).
    try {
        $pdo->beginTransaction();

        $lock=$pdo->prepare('SELECT id FROM siswa WHERE id=? FOR UPDATE');
        $lock->execute([(int)$s['id']]);
        if(!$lock->fetchColumn()){
            $pdo->rollBack();
            json_out(['ok'=>false,'message'=>'Data siswa tidak ditemukan.'],404);
        }

        $dup=$pdo->prepare("SELECT id,waktu_kunjungan FROM perpustakaan_kunjungan WHERE siswa_id=? AND DATE(waktu_kunjungan)=CURDATE() ORDER BY waktu_kunjungan ASC,id ASC LIMIT 1");
        $dup->execute([(int)$s['id']]);
        $d=$dup->fetch();
        if($d){
            $pdo->rollBack();
            json_out([
                'ok'=>false,
                'duplicate'=>true,
                'message'=>'Siswa sudah tercatat sebagai pengunjung hari ini.',
                'nama'=>$s['nama'],
                'nis'=>$s['nis'],
                'kelas'=>$s['kelas'],
                'waktu'=>$d['waktu_kunjungan']
            ]);
        }

        $ctx=$pdo->prepare("SELECT sk.tahun_pelajaran_id,sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id WHERE sk.siswa_id=? AND NOW() BETWEEN COALESCE(tp.tanggal_mulai,'1000-01-01') AND COALESCE(tp.tanggal_selesai,'9999-12-31') ORDER BY tp.id DESC LIMIT 1");
        $ctx->execute([(int)$s['id']]); $ctxRow=$ctx->fetch();
        if(!$ctxRow){
            $ctx=$pdo->prepare("SELECT sk.tahun_pelajaran_id,sk.kelas_id FROM siswa_kelas sk JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id WHERE sk.siswa_id=? AND tp.aktif=1 ORDER BY tp.id DESC LIMIT 1");
            $ctx->execute([(int)$s['id']]); $ctxRow=$ctx->fetch();
        }
        if(!$ctxRow){
            $pdo->rollBack();
            json_out(['ok'=>false,'message'=>'Riwayat kelas siswa tidak ditemukan.'],422);
        }

        $st=$pdo->prepare('INSERT INTO perpustakaan_kunjungan (siswa_id,tahun_pelajaran_id,kelas_id,waktu_kunjungan,metode,petugas_id,ip_address,user_agent) VALUES (?,?,?,NOW(),?,?,?,?)');
        $st->execute([(int)$s['id'],(int)$ctxRow['tahun_pelajaran_id'],(int)$ctxRow['kelas_id'],$metode,user_id(),$_SERVER['REMOTE_ADDR']??null,substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)]);
        $id=(int)$pdo->lastInsertId();
        perpus_audit($pdo,'CREATE',$id,'Mencatat kunjungan perpustakaan: '.$s['nama']);
        $pdo->commit();
    } catch (Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        error_log('perpustakaan/pengunjung_api scan: '.$e->getMessage());
        json_out(['ok'=>false,'message'=>'Kunjungan gagal disimpan. Silakan coba lagi.'],500);
    }

    json_out(['ok'=>true,'id'=>$id,'nama'=>$s['nama'],'nis'=>$s['nis'],'nisn'=>$s['nisn'],'kelas'=>$s['kelas'],'waktu'=>date('Y-m-d H:i:s'),'metode'=>$metode]);
}
json_out(['ok'=>false,'message'=>'Aksi tidak valid.'],400);
