<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('absensi');

function pengaturan_kehadiran_admin_only(): void {
    if (user_role() !== 'admin') deny_access();
}

function pengaturan_sholat_config_admin_only(): void {
    if (user_role() !== 'admin') deny_access();
}
function pengaturan_kehadiran_audit(PDO $pdo,string $aksi,?int $id,string $ket):void{
    $st=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
    $st->execute([user_id(),$aksi,'pengaturan_absensi',$id,$ket,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
}
function nama_hari_id(int $hari): string { return [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'][$hari] ?? '-'; }

function pengaturan_absensi_audit(PDO $pdo,string $aksi,?int $id,string $ket):void { pengaturan_kehadiran_audit($pdo,$aksi,$id,$ket); }
