<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
if(user_role()!=='admin')deny_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak diizinkan.');}
require_valid_csrf();$id=(int)($_POST['id']??0);
$s=absensi_find_session($pdo,$id);if(!$s)redirect('absensi/index.php?error='.rawurlencode('Sesi tidak ditemukan.'));
auto_sync_session_lifecycle($pdo,$id);$s=absensi_find_session($pdo,$id);
if($s['kategori']!=='kehadiran')redirect('absensi/index.php?error='.rawurlencode('Delete dari halaman ini hanya untuk sesi kehadiran.'));
if(($s['status']??'')==='ditutup' || ($s['status_sesi']??'')==='CLOSED')redirect('absensi/index.php?error='.rawurlencode('Sesi sudah ditutup dan dikunci. Data historis tidak dapat dihapus.'));
try{$pdo->beginTransaction();$st=$pdo->prepare('DELETE FROM sesi_absensi WHERE id=? AND jenis_sholat_id IS NULL');$st->execute([$id]);if($st->rowCount()!==1)throw new RuntimeException('Sesi tidak dapat dihapus.');$st=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');$st->execute([user_id(),'DELETE_SESSION','absensi',$id,'Menghapus sesi kehadiran '.$s['nama_sesi'].' pada '.$s['tanggal'].' beserta transaksi terkait',$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);$pdo->commit();redirect('absensi/index.php?saved='.rawurlencode('Sesi kehadiran berhasil dihapus beserta transaksi terkait.'));}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();redirect('absensi/index.php?error='.rawurlencode('Sesi tidak dapat dihapus: '.$e->getMessage()));}
