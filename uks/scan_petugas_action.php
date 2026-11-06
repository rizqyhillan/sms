<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
header('Content-Type: application/json; charset=utf-8');

function sp_json(array $d,int $s=200):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

try{
    uks_require_access($pdo);
    if(!uks_can_manage($pdo))throw new RuntimeException('Anda tidak memiliki akses absensi petugas UKS.');

    $action=(string)($_POST['action']??$_GET['action']??'');
    $tp=active_tapel_id($pdo);$sem=active_semester_id($pdo,$tp);$today=date('Y-m-d');$hari=(int)date('N');$now=date('H:i:s');

    if($action==='lookup'){
        $q=trim((string)($_GET['q']??''));
        if(mb_strlen($q)<2)sp_json(['ok'=>true,'data'=>[]]);
        $like='%'.$q.'%';$data=[];
        if($tp && $sem){
            $st=$pdo->prepare("SELECT 'guru' tipe,g.id,g.nama,g.nip AS identitas,'Petugas UKS Guru' AS jadwal
                FROM guru g JOIN tugas_guru tg ON tg.guru_id=g.id JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
                WHERE g.status='aktif' AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND jt.kode='PETUGAS_UKS'
                  AND (g.nama LIKE ? OR g.nip LIKE ? OR g.kode_qr LIKE ?)
                GROUP BY g.id ORDER BY g.nama LIMIT 20");
            $st->execute([$tp,$sem,$like,$like,$like]);$data=array_merge($data,$st->fetchAll());
            $st=$pdo->prepare("SELECT 'siswa' tipe,s.id,s.nama,s.nis AS identitas,CONCAT('Petugas UKS Siswa · ',COALESCE(mk.nama,'-')) AS jadwal
                FROM siswa s JOIN siswa_kelas sk ON sk.siswa_id=s.id JOIN uks_petugas_jadwal uj ON uj.siswa_kelas_id=sk.id
                LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
                WHERE s.status='aktif' AND uj.semester_id=? AND uj.hari=? AND uj.aktif=1
                  AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.kode_qr LIKE ?)
                ORDER BY s.nama LIMIT 20");
            $st->execute([$sem,$hari,$like,$like,$like,$like]);$data=array_merge($data,$st->fetchAll());
        }
        sp_json(['ok'=>true,'data'=>array_slice($data,0,30)]);
    }

    require_valid_csrf();
    $qr=trim((string)($_POST['kode_qr']??''));
    $metode=strtolower((string)($_POST['metode']??'qr'));
    if(!in_array($metode,['qr','usb','manual'],true))$metode='qr';
    if($qr==='')throw new RuntimeException('QR kosong.');

    $tipe=null;$ref=0;$nama='';$jadwalId=null;$jamMulai=null;
    $st=$pdo->prepare("SELECT g.id,g.nama FROM guru g JOIN tugas_guru tg ON tg.guru_id=g.id JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        WHERE g.kode_qr=? AND g.status='aktif' AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND jt.kode='PETUGAS_UKS' LIMIT 1");
    $st->execute([$qr,$tp,$sem]);
    if($r=$st->fetch()){
        $tipe='guru';$ref=(int)$r['id'];$nama=$r['nama'];
    }else{
        $st=$pdo->prepare("SELECT uj.id jadwal_id,uj.jam_mulai,s.id,s.nama,s.nis
            FROM uks_petugas_jadwal uj JOIN siswa_kelas sk ON sk.id=uj.siswa_kelas_id JOIN siswa s ON s.id=sk.siswa_id
            WHERE s.kode_qr=? AND uj.hari=? AND uj.aktif=1 AND s.status='aktif' AND uj.semester_id=? LIMIT 1");
        $st->execute([$qr,$hari,$sem]);
        if($r=$st->fetch()){
            $tipe='siswa';$ref=(int)$r['id'];$nama=$r['nama'];$jadwalId=(int)$r['jadwal_id'];$jamMulai=$r['jam_mulai'];
        }
    }
    if(!$tipe)throw new RuntimeException('QR dikenali, tetapi bukan Petugas UKS aktif hari ini.');

    $col=$tipe==='guru'?'guru_id':'siswa_id';
    $st=$pdo->prepare("SELECT id,waktu_masuk,status FROM uks_petugas_absensi WHERE petugas_tipe=? AND {$col}=? AND tanggal=? LIMIT 1");
    $st->execute([$tipe,$ref,$today]);if($ex=$st->fetch())throw new RuntimeException($nama.' sudah absen hari ini pada pukul '.($ex['waktu_masuk']?substr($ex['waktu_masuk'],0,8):'-').'.');

    $status='hadir';
    if($jamMulai && $now>$jamMulai)$status='terlambat';

    $st=$pdo->prepare("INSERT INTO uks_petugas_absensi
        (petugas_jadwal_id,petugas_tipe,guru_id,siswa_id,tanggal,waktu_masuk,status,metode,verified_by,verified_at)
        VALUES(?,?,?,?,?,?,?,?,?,NOW())");
    $st->execute([$jadwalId,$tipe,$tipe==='guru'?$ref:null,$tipe==='siswa'?$ref:null,$today,$now,$status,$metode,user_id()]);
    $id=(int)$pdo->lastInsertId();
    uks_audit($pdo,'SCAN_ABSENSI_PETUGAS_UKS',$id,'Scan absensi petugas UKS '.$tipe.' '.$nama.' status '.strtoupper($status).'.');
    sp_json(['ok'=>true,'nama'=>$nama,'tipe'=>strtoupper($tipe),'status'=>strtoupper($status),'waktu'=>$now,'message'=>'Absensi '.$nama.' berhasil dicatat.']);
}catch(Throwable $e){sp_json(['ok'=>false,'message'=>$e->getMessage()],400);}
