<?php
declare(strict_types=1);
require_once __DIR__.'/../absensi/_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    require_valid_csrf();
    $sesiId=(int)($_POST['sesi_id']??0);
    $qr=trim((string)($_POST['kode_qr']??''));
    $mode=(string)($_POST['mode']??'masuk');
    if(!in_array($mode,['masuk','pulang'],true)) $mode='masuk';
    if($sesiId<1||$qr==='') throw new RuntimeException('Sesi dan QR wajib diisi.');
    $s=absensi_find_session($pdo,$sesiId);
    if(!$s) throw new RuntimeException('Sesi tidak ditemukan.');
    if((string)($s['status_sesi']??'')==='CANCELLED') throw new RuntimeException('Sesi dibatalkan dan tidak menerima absensi.');
    // Segarkan lifecycle berdasarkan waktu server terlebih dahulu.
    auto_sync_session_lifecycle($pdo,$sesiId);
    $s=absensi_find_session($pdo,$sesiId);
    if(!$s) throw new RuntimeException('Sesi tidak ditemukan.');
    if($s['kategori']==='kehadiran') absensi_sync_finalisasi_otomatis($pdo,$sesiId,new DateTimeImmutable('now'));
    if($s['kategori']==='kehadiran' && absensi_is_finalized($pdo,$sesiId)) throw new RuntimeException('KEHADIRAN HARI INI SUDAH DIFINALISASI.');
    $offline=((string)($_POST['offline']??'')==='1');
    if($mode==='pulang' && !absensi_pulang_aktif($pdo)) throw new RuntimeException('Absen pulang belum diaktifkan.');
    if($mode==='pulang' && $offline) throw new RuntimeException('Absen pulang membutuhkan koneksi server aktif.');
    $waktuInput=trim((string)($_POST['waktu_scan']??''));
    $serverNow=new DateTimeImmutable('now');
    $now=$serverNow;
    if($offline&&$waktuInput!==''){
        try{
            $clientTime=(new DateTimeImmutable($waktuInput))->setTimezone(new DateTimeZone(date_default_timezone_get()));
            // Offline timestamp boleh berasal dari perangkat, tetapi tidak boleh
            // dibuat jauh di masa depan dibanding waktu server saat sinkronisasi.
            if($clientTime > $serverNow->modify('+5 minutes')){
                throw new RuntimeException('Waktu scan offline tidak valid: melebihi waktu server.');
            }
            $now=$clientTime;
        }catch(RuntimeException $e){throw $e;}
        catch(Throwable $e){throw new RuntimeException('Waktu scan offline tidak valid.');}
    }
    $today=$now->format('Y-m-d');
    $nowServer=$now->format('H:i:s');
    if((string)$s['tanggal']!==$today) throw new RuntimeException('Tanggal transaksi tidak sesuai dengan sesi.');

    // Untuk online, waktu server adalah sumber kebenaran. Untuk offline, waktu scan
    // perangkat dipakai agar transaksi yang benar-benar terjadi saat sesi OPEN masih
    // dapat disinkronkan beberapa menit kemudian. Keduanya tetap harus berada tepat
    // di dalam jendela sesi.
    $effectiveState=auto_session_state($s,$now);
    if($mode==='masuk' && $effectiveState!=='OPEN') {
        if($effectiveState==='DRAFT') throw new RuntimeException('Kegiatan belum dimulai. Scanner belum menerima scan untuk sesi ini.');
        throw new RuntimeException('ABSENSI MASUK DITUTUP. Sesi berakhir pukul '.substr((string)$s['jam_selesai'],0,5).' WIB.');
    }
    if($mode==='pulang') {
        $mulaiPulang = absensi_setting_value($pdo, 'jam_mulai_pulang', '13:00') ?? '13:00';
        $selesaiPulang = absensi_setting_value($pdo, 'jam_selesai_pulang', '16:30') ?? '16:30';
        $batasFinal=absensi_batas_finalisasi($pdo);
        if($now->format('H:i') < $mulaiPulang) throw new RuntimeException('Absen pulang belum dibuka. Mulai pukul '.$mulaiPulang.' WIB.');
        if($now->format('H:i') >= $selesaiPulang) throw new RuntimeException('ABSEN PULANG DITUTUP. Batas penerimaan sampai pukul '.$selesaiPulang.' WIB.');
        if($now->format('H:i') >= $batasFinal) throw new RuntimeException('Kehadiran sudah memasuki batas finalisasi otomatis pukul '.$batasFinal.' WIB.');
    }
    $st=$pdo->prepare('SELECT id,nis,nama,status,kode_qr,jk FROM siswa WHERE kode_qr=? LIMIT 1');
    $st->execute([$qr]);$siswa=$st->fetch();
    if(!$siswa) throw new RuntimeException('QR tidak dikenali sebagai siswa.');
    if($siswa['status']!=='aktif') throw new RuntimeException('Siswa berstatus '.ucfirst($siswa['status']).' dan tidak dapat melakukan scan.');
    $waktu=$now->format('Y-m-d H:i:s');

    if($s['kategori']==='sholat') {
        $st=$pdo->prepare('SELECT id,status,waktu_scan FROM absensi_sholat WHERE sesi_id=? AND siswa_id=? LIMIT 1');
        $st->execute([$sesiId,$siswa['id']]);$existing=$st->fetch();
        if($existing){echo json_encode(['ok'=>false,'duplicate'=>true,'message'=>'Siswa sudah tercatat pada sesi sholat ini.','nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'waktu'=>$existing['waktu_scan']]);exit;}
        try{
            $st=$pdo->prepare('INSERT INTO absensi_sholat (sesi_id,siswa_id,status,waktu_scan,dicatat_oleh,metode,keterangan) VALUES (?,?,\'SUDAH\',?,?,\'QR\',NULL)');
            $st->execute([$sesiId,$siswa['id'],$waktu,user_id()]);
        }catch(PDOException $e){
            if((string)$e->getCode()==='23000'){
                $dup=$pdo->prepare('SELECT id,status,waktu_scan FROM absensi_sholat WHERE sesi_id=? AND siswa_id=? LIMIT 1');
                $dup->execute([$sesiId,$siswa['id']]);
                $row=$dup->fetch();
                if($row){
                    echo json_encode(['ok'=>false,'duplicate'=>true,'message'=>'Siswa sudah tercatat pada sesi sholat ini.','nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'waktu'=>$row['waktu_scan']]);exit;
                }
            }
            throw $e;
        }
        $id=(int)$pdo->lastInsertId();
        absensi_audit($pdo,$offline?'OFFLINE_SYNC':'SCAN_SHOLAT',$id,($offline?'Sinkronisasi offline: ':'').'Scan sholat '.$siswa['nis'].' pada sesi '.$s['nama_sesi']);
        echo json_encode(['ok'=>true,'nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'status'=>'SUDAH','waktu'=>$waktu,'sholat'=>true]);exit;
    }

    if ($mode === 'pulang') {
        $st=$pdo->prepare('SELECT id,status,waktu_scan,waktu_pulang FROM absensi WHERE sesi_id=? AND siswa_id=? LIMIT 1');
        $st->execute([$sesiId,$siswa['id']]);
        $existing=$st->fetch();
        if (!$existing) throw new RuntimeException('Data Absen Masuk siswa belum ditemukan. Pulang tidak dapat dicatat tanpa data kehadiran hari ini.');
        if (!empty($existing['waktu_pulang'])) {
            echo json_encode(['ok'=>false,'duplicate'=>true,'message'=>'Absen pulang sudah tercatat.','nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'waktu'=>$existing['waktu_pulang']]); exit;
        }
        $up=$pdo->prepare('UPDATE absensi SET waktu_pulang=?,pulang_oleh=?,pulang_at=NOW() WHERE id=? AND waktu_pulang IS NULL');
        $up->execute([$waktu,user_id(),(int)$existing['id']]);
        if ($up->rowCount() < 1) throw new RuntimeException('Absen pulang sudah tercatat atau data berubah. Silakan muat ulang.');
        absensi_audit($pdo,'SCAN_PULANG',(int)$existing['id'],'Scan pulang siswa '.$siswa['nis'].' pada '.$waktu);
        echo json_encode(['ok'=>true,'nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'status'=>'PULANG','waktu'=>$waktu,'sholat'=>false]); exit;
    }
    $st=$pdo->prepare('SELECT id,status,waktu_scan FROM absensi WHERE sesi_id=? AND siswa_id=? LIMIT 1');
    $st->execute([$sesiId,$siswa['id']]);$existing=$st->fetch();
    if($existing){echo json_encode(['ok'=>false,'duplicate'=>true,'message'=>'Siswa sudah tercatat pada sesi ini.','nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'waktu'=>$existing['waktu_scan']]);exit;}
    $status='H';
    if($s['batas_terlambat']&&$now->format('H:i:s')>$s['batas_terlambat'])$status='T';
    try{
        $st=$pdo->prepare('INSERT INTO absensi (sesi_id,siswa_id,status,waktu_scan,scan_oleh,metode_awal,sumber_terakhir,status_verifikasi,keterangan) VALUES (?,?,?,?,?,\'QR\',\'QR\',\'belum\',?)');
        $st->execute([$sesiId,$siswa['id'],$status,$waktu,user_id(),$status==='T'?'Scan melewati batas terlambat':null]);
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000'){
            $dup=$pdo->prepare('SELECT id,status,waktu_scan FROM absensi WHERE sesi_id=? AND siswa_id=? LIMIT 1');
            $dup->execute([$sesiId,$siswa['id']]);
            $row=$dup->fetch();
            if($row){
                echo json_encode(['ok'=>false,'duplicate'=>true,'message'=>'Siswa sudah tercatat pada sesi ini.','nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'waktu'=>$row['waktu_scan']]);exit;
            }
        }
        throw $e;
    }
    $id=(int)$pdo->lastInsertId();
    absensi_audit($pdo,$offline?'OFFLINE_SYNC':'SCAN',$id,($offline?'Sinkronisasi offline: ':'').'Scan siswa '.$siswa['nis'].' pada sesi '.$s['nama_sesi']);
    echo json_encode(['ok'=>true,'nama'=>$siswa['nama'],'nis'=>$siswa['nis'],'status'=>$status,'waktu'=>$waktu,'sholat'=>false]);
} catch(Throwable $e) {http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
