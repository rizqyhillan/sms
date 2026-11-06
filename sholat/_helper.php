<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auto_sessions.php';
require_login();
require_permission('sholat');

function sholat_audit(PDO $pdo, string $aksi, ?int $id, string $keterangan): void {
    $st=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
    $st->execute([user_id(),$aksi,'sholat',$id,$keterangan,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
}
function sholat_session(PDO $pdo,int $id):?array{
    $st=$pdo->prepare("SELECT s.*,ta.nama tahun_nama,se.nama semester_nama,ja.kode jenis_kode,ja.nama jenis_nama,ja.kategori,js.nama sholat_nama FROM sesi_absensi s JOIN tahun_pelajaran ta ON ta.id=s.tahun_pelajaran_id JOIN semester se ON se.id=s.semester_id JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id JOIN jenis_sholat js ON js.id=s.jenis_sholat_id WHERE s.id=? AND ja.kategori='sholat' LIMIT 1");
    $st->execute([$id]);$row=$st->fetch();return $row?:null;
}
function sholat_get_or_create_hari_ini(PDO $pdo):array{return auto_sholat_hari_ini($pdo);}
function sholat_can_manual():bool{return has_role(['admin','guru','kepala_sekolah']);}
function sholat_require_manual():void{require_login();if(!sholat_can_manual())deny_access();}

function sholat_require_editable_session(PDO $pdo, int $sesiId): void
{
    auto_sync_session_lifecycle($pdo, $sesiId);
    $st=$pdo->prepare('SELECT status,status_sesi FROM sesi_absensi WHERE id=? LIMIT 1');
    $st->execute([$sesiId]);
    $row=$st->fetch();
    if(!$row) { http_response_code(404); exit('Sesi sholat tidak ditemukan.'); }
    if(($row['status']??'')==='ditutup' || ($row['status_sesi']??'')==='CLOSED') {
        http_response_code(409);
        exit('Sesi sholat sudah ditutup dan dikunci. Data tidak dapat diedit atau dihapus.');
    }
}
