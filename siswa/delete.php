<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

require_valid_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
$ids = [];
if (isset($_POST['id'])) {
    $singleId = (int)$_POST['id'];
    if ($singleId > 0) $ids[$singleId] = $singleId;
}
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $id) { $id=(int)$id; if($id>0)$ids[$id]=$id; }
}
$ids=array_values($ids);
if(!$ids){header('Location: '.BASE_URL.'siswa/index.php');exit;}

$deleted=0;$blocked=[];
$pdo->beginTransaction();
try{
    $find=$pdo->prepare('SELECT nama,nis FROM siswa WHERE id=? LIMIT 1');
    $del=$pdo->prepare('DELETE FROM siswa WHERE id=?');
    foreach($ids as $id){
        $find->execute([$id]);$row=$find->fetch();
        if(!$row)continue;
        $deps=siswa_delete_dependencies($pdo,$id);
        if($deps){
            $labels=[];
            foreach($deps as $d)$labels[]=$d['table'].' ('.$d['count'].')';
            $blocked[]=$row['nama'].' — masih memiliki data: '.implode(', ',$labels);
            continue;
        }
        $del->execute([$id]);
        if($del->rowCount()===1){$deleted++;siswa_audit($pdo,'DELETE',$id,'Menghapus siswa '.$row['nama'].' (NIS '.$row['nis'].')');}
    }
    $pdo->commit();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    http_response_code(500);exit('Penghapusan gagal: '.e($e->getMessage()));
}
$parts=[];
if($deleted)$parts[]=$deleted.' siswa berhasil dihapus.';
if($blocked)$parts[]='Tidak dihapus karena memiliki riwayat/data terkait: '.implode(' | ',$blocked);
if(!$parts)$parts[]='Tidak ada data yang dihapus.';
$_SESSION['flash_message']=implode(' ',$parts);
header('Location: '.BASE_URL.'siswa/index.php');exit;
