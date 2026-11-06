<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';

header('Content-Type: application/json; charset=utf-8');

function uks_json(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function uks_find_student_by_qr(PDO $pdo, string $qr): ?array {
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,s.status,
        COALESCE(mk.nama,'-') AS kelas, sk.kelas_id, sk.tahun_pelajaran_id
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id FROM siswa_kelas sk2
            JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id
            WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1
            ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.kode_qr=? LIMIT 1");
    $st->execute([$qr]);
    $r=$st->fetch();
    return $r?:null;
}

function uks_visit_context(PDO $pdo, int $sid): ?array {
    $st=$pdo->prepare("SELECT sk.tahun_pelajaran_id,sk.kelas_id
        FROM siswa_kelas sk
        JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id
        WHERE sk.siswa_id=? AND sk.status='aktif' AND tp.aktif=1
        ORDER BY sk.id DESC LIMIT 1");
    $st->execute([$sid]);
    $r=$st->fetch();
    return $r?:null;
}

function uks_current_queue_number(PDO $pdo, string $date): int {
    $st=$pdo->prepare("SELECT COALESCE(MAX(nomor_antrian),0)+1 FROM uks_kunjungan WHERE tanggal=?");
    $st->execute([$date]);
    return (int)$st->fetchColumn();
}

try {
    uks_require_access($pdo);
    if (!uks_can_manage($pdo)) {
        throw new RuntimeException('Anda tidak memiliki akses layanan UKS.');
    }

    $action=(string)($_POST['action']??$_GET['action']??'');

    if ($action==='lookup') {
        $q=trim((string)($_GET['q']??''));
        if (mb_strlen($q)<2) uks_json(['ok'=>true,'data'=>[]]);
        $like='%'.$q.'%';
        $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.kode_qr,
            COALESCE(mk.nama,'-') AS kelas
            FROM siswa s
            LEFT JOIN siswa_kelas sk ON sk.id=(
                SELECT sk2.id FROM siswa_kelas sk2
                JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id
                WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1
                ORDER BY sk2.id DESC LIMIT 1)
            LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
            WHERE s.status='aktif'
              AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.kode_qr LIKE ?)
            ORDER BY s.nama LIMIT 20");
        $st->execute([$like,$like,$like,$like]);
        uks_json(['ok'=>true,'data'=>$st->fetchAll()]);
    }

    if ($action==='queue') {
        $st=$pdo->prepare("SELECT uk.id,uk.nomor_antrian,uk.status,uk.tanggal,
            uk.waktu,uk.waktu_mulai,uk.waktu_selesai,uk.jenis_layanan,
            s.nis,s.nisn,s.nama,mk.nama AS kelas,g.nama AS petugas
            FROM uks_kunjungan uk
            JOIN siswa s ON s.id=uk.siswa_id
            LEFT JOIN master_kelas mk ON mk.id=uk.kelas_id
            LEFT JOIN guru g ON g.id=uk.petugas_id
            WHERE uk.tanggal=CURDATE()
            ORDER BY uk.nomor_antrian ASC, uk.id ASC");
        $st->execute();
        $rows=$st->fetchAll();

        // Data sensitif tidak dikirim ke layar monitoring umum.
        if (user_role()==='kepala_sekolah') {
            $rows=array_map(static function(array $r): array {
                unset($r['jenis_layanan'],$r['waktu_mulai'],$r['waktu_selesai'],$r['petugas']);
                return $r;
            },$rows);
        }
        uks_json(['ok'=>true,'data'=>$rows]);
    }

    if ($action==='scan' || $action==='enqueue') {
        if ($_SERVER['REQUEST_METHOD']!=='POST') uks_json(['ok'=>false,'message'=>'Metode tidak valid.'],405);
        require_valid_csrf();
        $qr=trim((string)($_POST['kode_qr']??''));
        $metode=strtoupper((string)($_POST['metode']??'QR'));
        if (!in_array($metode,['QR','USB','MANUAL'],true)) $metode='QR';
        if ($qr==='') uks_json(['ok'=>false,'message'=>'QR siswa kosong.'],422);

        try { sekolah_require_operasional_aktif($pdo); } catch (RuntimeException $e) { uks_json(['ok'=>false,'message'=>$e->getMessage()],422); }
        $s=uks_find_student_by_qr($pdo,$qr);
        if (!$s || $s['status']!=='aktif') {
            uks_json(['ok'=>false,'message'=>'QR tidak ditemukan atau siswa tidak aktif.'],404);
        }
        $ctx=uks_visit_context($pdo,(int)$s['id']);
        if (!$ctx) uks_json(['ok'=>false,'message'=>'Kelas/tahun pelajaran aktif siswa tidak ditemukan.'],422);
        if (!uks_can_manage_student($pdo,(int)$s['id'])) {
            uks_json(['ok'=>false,'message'=>'Anda tidak berwenang mencatat layanan untuk siswa ini.'],403);
        }

        $dup=$pdo->prepare("SELECT id,nomor_antrian,status,waktu
            FROM uks_kunjungan
            WHERE siswa_id=? AND tanggal=CURDATE()
              AND status IN ('baru','ditangani')
            ORDER BY id DESC LIMIT 1");
        $dup->execute([(int)$s['id']]);
        if ($d=$dup->fetch()) {
            uks_json(['ok'=>true,'duplicate'=>true,'id'=>(int)$d['id'],
                'nomor'=>(int)$d['nomor_antrian'],'status'=>$d['status'],
                'waktu'=>$d['waktu'],'student'=>$s,
                'message'=>'Siswa sudah berada dalam layanan aktif.']);
        }

        $date=date('Y-m-d');
        $time=date('H:i:s');
        $petugas=uks_current_guru_id($pdo);
        $pdo->beginTransaction();
        try {
            $n=uks_current_queue_number($pdo,$date);
            $st=$pdo->prepare("INSERT INTO uks_kunjungan
                (siswa_id,tahun_pelajaran_id,kelas_id,tanggal,waktu,nomor_antrian,metode,petugas_id,status)
                VALUES(?,?,?,?,?,?,?,?,?)");
            $st->execute([
                (int)$s['id'],(int)$ctx['tahun_pelajaran_id'],(int)$ctx['kelas_id'],
                $date,$time,$n,$metode,$petugas,'baru'
            ]);
            $id=(int)$pdo->lastInsertId();
            uks_audit($pdo,'QUEUE_KUNJUNGAN_UKS',$id,
                'Siswa masuk antrian layanan UKS '.$s['nis'].' melalui '.$metode.'.');
            $pdo->commit();
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }

        uks_json(['ok'=>true,'duplicate'=>false,'id'=>$id,'nomor'=>$n,
            'nama'=>$s['nama'],'nis'=>$s['nis'],'kelas'=>$s['kelas'],
            'waktu'=>$date.' '.$time,'status'=>'BARU','metode'=>$metode,
            'student'=>$s]);
    }

    if (in_array($action,['start','finish','cancel'],true)) {
        if ($_SERVER['REQUEST_METHOD']!=='POST') uks_json(['ok'=>false,'message'=>'Metode tidak valid.'],405);
        require_valid_csrf();
        $id=(int)($_POST['id']??0);
        if($id<=0) uks_json(['ok'=>false,'message'=>'ID layanan tidak valid.'],422);

        $st=$pdo->prepare("SELECT uk.*,s.nama,s.nis FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id WHERE uk.id=? LIMIT 1");
        $st->execute([$id]);
        $row=$st->fetch();
        if(!$row) uks_json(['ok'=>false,'message'=>'Antrian tidak ditemukan.'],404);
        if(!uks_can_manage_student($pdo,(int)$row['siswa_id'])) uks_json(['ok'=>false,'message'=>'Anda tidak berwenang menangani siswa ini.'],403);

        $gid=uks_current_guru_id($pdo);

        if($action==='start'){
            if($row['status']!=='baru') uks_json(['ok'=>false,'message'=>'Antrian tidak berada pada status BARU.'],409);
            $jenis=trim((string)($_POST['jenis_layanan']??''));
            $allowed=['pemeriksaan','konsultasi','pertolongan','obat','rujukan','lainnya'];
            if(!in_array($jenis,$allowed,true)) uks_json(['ok'=>false,'message'=>'Keperluan layanan belum dipilih.'],422);
            $up=$pdo->prepare("UPDATE uks_kunjungan SET status='ditangani',jenis_layanan=?,petugas_id=?,waktu_mulai=? WHERE id=? AND status='baru'");
            $up->execute([$jenis,$gid,date('H:i:s'),$id]);
            if($up->rowCount()!==1) uks_json(['ok'=>false,'message'=>'Layanan gagal dimulai. Muat ulang halaman.'],409);
            uks_audit($pdo,'START_LAYANAN_UKS',$id,'Memulai layanan UKS untuk '.$row['nis'].'.');
            uks_json(['ok'=>true,'message'=>'Layanan dimulai.']);
        }

        if($action==='finish'){
            if($row['status']!=='ditangani') uks_json(['ok'=>false,'message'=>'Layanan belum berada pada status DILAYANI.'],409);
            $keluhan=trim((string)($_POST['keluhan']??''));
            $hasil=trim((string)($_POST['hasil_pemeriksaan']??''));
            $tindakan=trim((string)($_POST['tindakan']??''));
            $obat=trim((string)($_POST['obat']??''));
            $catatan=trim((string)($_POST['catatan']??''));
            $up=$pdo->prepare("UPDATE uks_kunjungan
                SET status='selesai',keluhan=?,hasil_pemeriksaan=?,tindakan=?,obat=?,catatan=?,waktu_selesai=?
                WHERE id=? AND status='ditangani'");
            $up->execute([$keluhan,$hasil,$tindakan,$obat,$catatan,date('H:i:s'),$id]);
            if($up->rowCount()!==1) uks_json(['ok'=>false,'message'=>'Penyelesaian layanan gagal.'],409);
            uks_audit($pdo,'FINISH_LAYANAN_UKS',$id,'Menyelesaikan layanan UKS untuk '.$row['nis'].'.');
            uks_json(['ok'=>true,'message'=>'Layanan berhasil diselesaikan.']);
        }

        if($action==='cancel'){
            if(!in_array($row['status'],['baru','ditangani'],true)) uks_json(['ok'=>false,'message'=>'Layanan tidak dapat dibatalkan pada status ini.'],409);
            $up=$pdo->prepare("UPDATE uks_kunjungan SET status='batal',waktu_selesai=? WHERE id=? AND status IN ('baru','ditangani')");
            $up->execute([date('H:i:s'),$id]);
            uks_audit($pdo,'CANCEL_LAYANAN_UKS',$id,'Membatalkan antrian layanan UKS untuk '.$row['nis'].'.');
            uks_json(['ok'=>true,'message'=>'Antrian dibatalkan.']);
        }
    }

    uks_json(['ok'=>false,'message'=>'Aksi tidak valid.'],400);
} catch (Throwable $e) {
    uks_json(['ok'=>false,'message'=>$e->getMessage()],400);
}
