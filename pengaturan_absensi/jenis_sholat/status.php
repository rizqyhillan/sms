<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
pengaturan_sholat_config_admin_only();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method Not Allowed');}
require_valid_csrf();
$id=(int)($_POST['id']??0);$aktif=((int)($_POST['aktif']??0)===1)?1:0;
$st=$pdo->prepare('SELECT id,nama FROM jenis_sholat WHERE id=? LIMIT 1');$st->execute([$id]);$row=$st->fetch();
if(!$row){http_response_code(404);exit('Jenis sholat tidak ditemukan.');}
$st=$pdo->prepare('UPDATE jenis_sholat SET aktif=? WHERE id=?');$st->execute([$aktif,$id]);
pengaturan_absensi_audit($pdo,$aktif?'ACTIVATE_JENIS_SHOLAT':'DEACTIVATE_JENIS_SHOLAT',$id,($aktif?'Mengaktifkan':'Menonaktifkan').' jenis sholat: '.$row['nama']);
redirect('pengaturan_absensi/jenis_sholat/index.php?saved=1');
