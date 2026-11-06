<?php
declare(strict_types=1);
require_once __DIR__.'/_helper.php';
pengguna_require_admin();
$id=max(0,(int)($_GET['id']??$_POST['id']??0));
$st=$pdo->prepare('SELECT id,username,nama,role,status FROM users WHERE id=? LIMIT 1');$st->execute([$id]);$u=$st->fetch();if(!$u)exit('Pengguna tidak ditemukan.');
$rights=$pdo->query("SELECT id,kode,nama,modul,aktif FROM hak_akses WHERE aktif=1 ORDER BY modul,nama,id")->fetchAll();
$st=$pdo->prepare('SELECT hak_akses_id FROM user_hak_akses WHERE user_id=?');$st->execute([$id]);$selected=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));$error='';

// Akses dasar berdasarkan role: otomatis, tidak dapat dicabut dari halaman ini.
$roleDefaults=[
 'admin'=>['*'],
 'kepala_sekolah'=>['laporan'],
 'guru_bk'=>['siswa','bk','pelanggaran','pembinaan','home_visit','laporan','izin_siswa'],
 'guru'=>['absensi','sholat','laporan','izin_siswa'],
 'tu'=>['siswa','guru','kelas','absensi','sholat','ekstrakurikuler','perpustakaan','uks','laporan','izin_siswa'],
];
$autoCodes=$roleDefaults[$u['role']]??[];
if(in_array('*',$autoCodes,true))$autoCodes=array_column($rights,'kode');

// Tugas tambahan aktif pengguna: menjadi sumber akses otomatis.
$taskRows=[];
if(in_array($u['role'],['guru','guru_bk','tu'],true)){
  $st=$pdo->prepare('SELECT jt.kode,jt.nama,jt.kategori,tg.target_tipe,tg.target_id,
      CASE
        WHEN UPPER(COALESCE(tg.target_tipe,\'\'))=\'KELAS\' THEN (SELECT mk.nama FROM master_kelas mk WHERE mk.id=tg.target_id LIMIT 1)
        WHEN UPPER(COALESCE(tg.target_tipe,\'\'))=\'EKSTRAKURIKULER\' THEN (SELECT e.nama FROM ekstrakurikuler e WHERE e.id=tg.target_id LIMIT 1)
        ELSE NULL END AS target_nama
      FROM tugas_guru tg JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
      WHERE tg.guru_id=(SELECT u.guru_id FROM users u WHERE u.id=? LIMIT 1) AND tg.aktif=1 AND jt.aktif=1
        AND tg.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1)
        AND tg.semester_id=(SELECT s.id FROM semester s JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id WHERE tp.aktif=1 AND s.aktif=1 ORDER BY s.id DESC LIMIT 1)
      ORDER BY jt.nama,tg.id');
  $st->execute([$id]);$taskRows=$st->fetchAll()?:[];
}

$taskAutoLabels=[];
$taskCapMap=[
 'WALI_KELAS'=>'Wali Kelas','GURU_PIKET'=>'Guru Piket','PETUGAS_PIKET'=>'Guru Piket',
 'PEMBINA_EKSTRA'=>'Ekstrakurikuler Saya','KOORDINATOR_EKSTRA'=>'Ekstrakurikuler Saya',
 'PEMBINA_PRAMUKA'=>'Ekstrakurikuler Saya','PEMBINA_PMR'=>'Ekstrakurikuler Saya','PEMBINA_BASKET'=>'Ekstrakurikuler Saya','PEMBINA_FUTSAL'=>'Ekstrakurikuler Saya',
 'PETUGAS_UKS'=>'UKS','PETUGAS_PERPUSTAKAAN'=>'Perpustakaan','PEMBINA_OSIS'=>'Pembina OSIS'
];
foreach($taskRows as $t){$label=$taskCapMap[strtoupper($t['kode'])]??null;if(!$label)continue;$target=$t['target_nama']?(' · '.$t['target_nama']):'';$taskAutoLabels[$label][$t['kode']]=['nama'=>$t['nama'],'target'=>$target];}

$autoIds=[];foreach($rights as $r){if(in_array($r['kode'],$autoCodes,true))$autoIds[]=(int)$r['id'];}
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_valid_csrf();
 $ids=array_values(array_unique(array_map('intval',(array)($_POST['hak_akses']??[]))));
 $valid=array_map('intval',array_column($rights,'id'));$ids=array_values(array_intersect($ids,$valid));
 // Akses otomatis tidak disimpan sebagai hak manual dan tidak dapat dicabut dari sini.
 $ids=array_values(array_diff($ids,$autoIds));
 try{$pdo->beginTransaction();
   if($autoIds){$ph=implode(',',array_fill(0,count($autoIds),'?'));$pdo->prepare("DELETE FROM user_hak_akses WHERE user_id=? AND hak_akses_id IN ($ph)")->execute(array_merge([$id],$autoIds));}
   $pdo->prepare('DELETE FROM user_hak_akses WHERE user_id=?')->execute([$id]);
   $ins=$pdo->prepare('INSERT INTO user_hak_akses(user_id,hak_akses_id) VALUES(?,?)');foreach($ids as $rid)$ins->execute([$id,$rid]);
   pengguna_audit('UPDATE_PERMISSION',$id,'Memperbarui hak akses manual akun '.$u['username'].' ('.count($ids).' hak manual; akses role/tugas otomatis tidak dihitung)');$pdo->commit();pengguna_flash('Hak akses manual berhasil diperbarui. Akses dari role dan tugas tetap otomatis.');redirect('pengguna/index.php');
 }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();$error='Gagal menyimpan hak akses.';}
}
$page_title='Hak Akses Pengguna';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
$grouped=[];foreach($rights as $r)$grouped[$r['modul']??'Lainnya'][]=$r;
?>
<main class="main"><header class="topbar"><div class="topbar-title">Hak Akses</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Hak Akses Pengguna</h1><p><?=e($u['nama'])?> · <?=e(pengguna_roles()[$u['role']]??$u['role'])?></p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pengguna/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div></div>
<div class="alert alert-info"><strong>Akses otomatis.</strong> Role dan tugas tambahan menentukan akses utama. Akses otomatis tidak dapat dicabut dengan checkbox di halaman ini. Untuk mengubahnya, ubah <strong>Role</strong> atau <strong>Tugas Tambahan</strong>.</div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if($autoCodes || $taskAutoLabels):?><div class="form-card"><h3 style="margin-top:0"><i class="bi bi-lightning-charge-fill"></i> Akses Otomatis</h3><p class="text-muted">Akses berikut aktif otomatis dan tidak perlu dicentang.</p><div style="display:flex;flex-wrap:wrap;gap:10px"><?php foreach($rights as $r): if(in_array($r['kode'],$autoCodes,true)):?><span style="display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#ecfdf5;color:#166534;border:1px solid #bbf7d0"><i class="bi bi-lock-fill"></i><?=e($r['nama'])?> <small>(Role)</small></span><?php endif;endforeach;foreach($taskAutoLabels as $label=>$codes):?><span style="display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe"><i class="bi bi-briefcase-fill"></i><?=e($label)?> <small>(Tugas)</small><?php foreach($codes as $c):?><?=e($c['target'])?><?php endforeach;?></span><?php endforeach;?></div></div><?php endif;?>
<form method="post"><input type="hidden" name="id" value="<?=$id?>"><?=csrf_field()?><div class="form-card"><h3 style="margin-top:0"><i class="bi bi-shield-check"></i> Akses Manual Tambahan</h3><p class="text-muted">Gunakan hanya jika pengguna membutuhkan akses tambahan yang tidak berasal dari Role atau Tugas Tambahan.</p><div class="dashboard-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
<?php foreach($grouped as $modul=>$items):?><div class="form-card" style="margin-top:0"><h4 style="margin-top:0"><?=e($modul)?></h4><?php $hasManual=false;foreach($items as $r):if(in_array($r['kode'],$autoCodes,true))continue;$hasManual=true;?><label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f1f5f9"><input type="checkbox" name="hak_akses[]" value="<?=$r['id']?>" <?=in_array((int)$r['id'],$selected,true)?'checked':''?>><span><strong><?=e($r['nama'])?></strong><br><small><?=e($r['kode'])?></small></span></label><?php endforeach;if(!$hasManual):?><div class="text-muted">Tidak ada akses manual tambahan.</div><?php endif;?></div><?php endforeach;?></div></div><div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Simpan Akses Manual</button><a class="btn btn-secondary" href="<?=BASE_URL?>pengguna/index.php">Batal</a></div></form></section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
