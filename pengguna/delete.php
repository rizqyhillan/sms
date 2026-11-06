<?php
declare(strict_types=1);require_once __DIR__.'/_helper.php';pengguna_require_admin();require_valid_csrf();
$id=max(0,(int)($_POST['id']??0));if($id<=0||$id===(int)user_id())exit('Akun sendiri tidak boleh dihapus.');
$st=$pdo->prepare('SELECT id,username,nama,role,status FROM users WHERE id=? LIMIT 1');$st->execute([$id]);$u=$st->fetch();if(!$u)exit('Pengguna tidak ditemukan.');
if($u['role']==='admin'){ $n=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn(); if($u['status']==='aktif'&&$n<=1)exit('Admin aktif terakhir tidak boleh dihapus.'); }
$checks=[
 ['audit_log','user_id'],['absensi','scan_oleh'],['absensi_perubahan','diubah_oleh'],['sesi_absensi','dibuka_oleh'],['sesi_absensi','ditutup_oleh'],['perpustakaan_peminjaman','petugas_id'],['ekstra_jurnal','dibuat_oleh'],['ekstra_penilaian','dinilai_oleh'],['ekstra_sesi','dibuka_oleh'],['ekstra_sesi','ditutup_oleh']
];
$used=false;foreach($checks as [$table,$col]){try{$st=$pdo->prepare("SELECT 1 FROM `$table` WHERE `$col`=? LIMIT 1");$st->execute([$id]);if($st->fetch()){ $used=true;break; }}catch(PDOException $e){}}
if($used){pengguna_flash('Akun tidak dihapus karena sudah memiliki histori/aktivitas. Gunakan Nonaktifkan.');redirect('pengguna/index.php');}
$st=$pdo->prepare('DELETE FROM users WHERE id=?');$st->execute([$id]);pengguna_audit('DELETE_USER',null,'Menghapus akun '.$u['username']);pengguna_flash('Pengguna berhasil dihapus.');redirect('pengguna/index.php');
