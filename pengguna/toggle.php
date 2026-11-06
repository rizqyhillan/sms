<?php
declare(strict_types=1);require_once __DIR__.'/_helper.php';pengguna_require_admin();require_valid_csrf();
$id=max(0,(int)($_POST['id']??0));if($id<=0||$id===(int)user_id())exit('Akun ini tidak dapat diubah dari sesi sendiri.');
$st=$pdo->prepare('SELECT id,username,nama,role,status FROM users WHERE id=? LIMIT 1');$st->execute([$id]);$u=$st->fetch();if(!$u)exit('Pengguna tidak ditemukan.');
$new=$u['status']==='aktif'?'nonaktif':'aktif';
if($new==='nonaktif'&&$u['role']==='admin'){$n=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn();if($n<=1)exit('Admin aktif terakhir tidak boleh dinonaktifkan.');}
$st=$pdo->prepare('UPDATE users SET status=? WHERE id=?');$st->execute([$new,$id]);pengguna_audit($new==='aktif'?'ACTIVATE_USER':'DEACTIVATE_USER',$id,'Mengubah status akun '.$u['username'].' menjadi '.$new);pengguna_flash('Status pengguna berhasil diubah.');redirect('pengguna/index.php');
