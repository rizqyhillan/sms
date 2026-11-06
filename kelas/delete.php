<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
if (user_role() !== 'admin') deny_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
require_valid_csrf();
$id=(int)($_POST['id']??0);
$st=$pdo->prepare('SELECT id,nama,aktif FROM master_kelas WHERE id=? LIMIT 1');$st->execute([$id]);$row=$st->fetch();
if(!$row){redirect('kelas/index.php?error='.rawurlencode('Kelas tidak ditemukan.'));}
$st=$pdo->prepare('SELECT COUNT(*) FROM siswa_kelas WHERE kelas_id=?');$st->execute([$id]);$used=(int)$st->fetchColumn();
if($used>0){redirect('kelas/index.php?error='.rawurlencode('Kelas sudah memiliki histori penempatan siswa, sehingga tidak dapat dihapus. Gunakan Nonaktifkan.'));}
try{$pdo->beginTransaction();$st=$pdo->prepare('DELETE FROM master_kelas WHERE id=?');$st->execute([$id]);$st=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');$st->execute([user_id(),'DELETE_KELAS','kelas',$id,'Menghapus kelas '.$row['nama'],$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);$pdo->commit();redirect('kelas/index.php?saved='.rawurlencode('Kelas berhasil dihapus.'));}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();redirect('kelas/index.php?error='.rawurlencode('Kelas tidak dapat dihapus: '.$e->getMessage()));}
