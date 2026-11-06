<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
pengaturan_sholat_config_admin_only();
pengaturan_kehadiran_admin_only();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Metode tidak diizinkan.');}
require_valid_csrf();$id=(int)($_POST['id']??0);
$st=$pdo->prepare('SELECT id,nama,aktif FROM jenis_sholat WHERE id=? LIMIT 1');$st->execute([$id]);$row=$st->fetch();
if(!$row)redirect('pengaturan_absensi/jenis_sholat/index.php?error='.rawurlencode('Jenis sholat tidak ditemukan.'));
$st=$pdo->prepare('SELECT COUNT(*) FROM sesi_absensi WHERE jenis_sholat_id=?');$st->execute([$id]);$used=(int)$st->fetchColumn();
$st=$pdo->prepare('SELECT COUNT(*) FROM jadwal_sholat WHERE jenis_sholat_id=?');$st->execute([$id]);$scheduled=(int)$st->fetchColumn();
if($used>0||$scheduled>0)redirect('pengaturan_absensi/jenis_sholat/index.php?error='.rawurlencode('Jenis sholat sudah digunakan pada histori sesi atau jadwal, sehingga tidak dapat dihapus. Nonaktifkan jika tidak ingin digunakan lagi.'));
try{$pdo->beginTransaction();$st=$pdo->prepare('DELETE FROM jenis_sholat WHERE id=?');$st->execute([$id]);pengaturan_kehadiran_audit($pdo,'DELETE_JENIS_SHOLAT',$id,'Menghapus jenis sholat '.$row['nama']);$pdo->commit();redirect('pengaturan_absensi/jenis_sholat/index.php?saved='.rawurlencode('Jenis sholat berhasil dihapus.'));}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();redirect('pengaturan_absensi/jenis_sholat/index.php?error='.rawurlencode('Jenis sholat tidak dapat dihapus: '.$e->getMessage()));}
