<?php
declare(strict_types=1);
require_once __DIR__ . '/../_helper.php';
require_role('admin');

$years=$pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY id DESC")->fetchAll();
$activeYear=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetch() ?: null;
$yearId=(int)($_GET['tahun_pelajaran_id']??$_POST['tahun_pelajaran_id']??($activeYear['id']??0));
$yearIds=array_map('intval',array_column($years,'id')); if(!in_array($yearId,$yearIds,true))$yearId=(int)($activeYear['id']??0);
$studentId=(int)($_GET['siswa_id']??$_POST['siswa_id']??0);
$search=trim((string)($_GET['cari']??''));
$errors=[];$success='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $studentId=(int)($_POST['siswa_id']??0);$tanggal=(string)($_POST['tanggal_status']??date('Y-m-d'));$tujuan=trim((string)($_POST['tujuan']??''));$ket=trim((string)($_POST['keterangan']??''));
    if($studentId<=0)$errors[]='Siswa wajib dipilih.';
    if($yearId<=0)$errors[]='Tahun pelajaran wajib dipilih.';
    if($tanggal==='')$errors[]='Tanggal wajib diisi.';
    if($tujuan==='')$errors[]='Sekolah tujuan wajib diisi.';
    if(!$errors){
        $pdo->beginTransaction();
        try{
            $lock=$pdo->prepare('SELECT * FROM siswa WHERE id=? FOR UPDATE');$lock->execute([$studentId]);$s=$lock->fetch();
            if(!$s)throw new RuntimeException('Siswa tidak ditemukan.');
            if($s['status']!=='aktif')throw new RuntimeException('Siswa sudah berstatus '.strtoupper((string)$s['status']).' dan tidak dapat dimutasi lagi.');
            $placement=$pdo->prepare("SELECT id,kelas_id,status FROM siswa_kelas WHERE siswa_id=? AND tahun_pelajaran_id=? AND status='aktif' ORDER BY id DESC LIMIT 1 FOR UPDATE");$placement->execute([$studentId,$yearId]);$sk=$placement->fetch();
            if(!$sk)throw new RuntimeException('Siswa tidak memiliki penempatan aktif pada Tahun Pelajaran yang dipilih.');
            $ins=$pdo->prepare('INSERT INTO siswa_status_riwayat (siswa_id,tahun_pelajaran_id,status,tanggal_status,tujuan,keterangan,created_by) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$studentId,$yearId,'pindah',$tanggal,$tujuan!==''?$tujuan:null,$ket!==''?$ket:null,user_id()]);$rid=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE siswa_kelas SET status='pindah', keterangan=CASE WHEN ?='' THEN keterangan ELSE ? END WHERE id=?")->execute([$ket,$ket,(int)$sk['id']]);
            $pdo->prepare("UPDATE siswa SET status='pindah' WHERE id=?")->execute([$studentId]);
            siswa_audit($pdo,'MUTASI_PINDAH',$studentId,'Siswa diproses Pindah pada Tapel #'.$yearId.'; tujuan: '.$tujuan.'; riwayat #'.$rid);
            $pdo->commit();
            $success='Mutasi Pindah berhasil dicatat. Data siswa tetap tersimpan.';$studentId=0;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]=$e->getMessage();}
    }
}

$selected=null;
if($studentId>0){
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,s.jk,s.status,mk.nama nama_kelas,tp.nama tahun_nama
        FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN tahun_pelajaran tp ON tp.id=sk.tahun_pelajaran_id WHERE s.id=? LIMIT 1");$st->execute([$yearId,$studentId]);$selected=$st->fetch()?:null;
}
$list=[];
if($selected===null){
    $sql="SELECT s.id,s.nis,s.nisn,s.nama,mk.nama nama_kelas FROM siswa s JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif' LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.status='aktif'";$params=[$yearId];
    if($search!==''){ $sql.=" AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ?)";$k='%'.$search.'%';array_push($params,$k,$k,$k); }
    $sql.=" ORDER BY s.nama LIMIT 100";$st=$pdo->prepare($sql);$st->execute($params);$list=$st->fetchAll();
}
$page_title='Mutasi Pindah';require_once __DIR__.'/../../layouts/header.php';require_once __DIR__.'/../../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Mutasi Pindah</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content mutasi-subpage">
<div class="page-header"><div><h1><i class="bi bi-box-arrow-right"></i> Pindah Sekolah</h1><p>Catat siswa yang pindah tanpa menghapus identitas dan histori.</p></div></div>
<?php foreach($errors as $err):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<div class="form-card"><form method="get" class="form-grid"><label>Tahun Pelajaran<select class="form-control" name="tahun_pelajaran_id"><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=((int)$y['id']===$yearId?'selected':'')?>><?=e($y['nama'])?><?=((int)$y['aktif']===1?' · Aktif':'')?></option><?php endforeach;?></select></label><label>Cari NIS / NISN / Nama<input class="form-control" name="cari" value="<?=e($search)?>" placeholder="Ketik untuk mencari..."></label><div class="form-actions" style="align-self:end"><button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button></div></form></div>
<?php if($selected): ?><div class="form-card"><div class="section-title"><div><h2>Konfirmasi Siswa</h2><p><?=e($selected['nama'])?> · NIS <?=e($selected['nis'])?></p></div></div><div class="detail-grid"><div><small>Nama</small><strong><?=e($selected['nama'])?></strong></div><div><small>NISN</small><strong><?=e((string)($selected['nisn']??'-'))?></strong></div><div><small>Kelas</small><strong><?=e((string)($selected['nama_kelas']??'-'))?></strong></div><div><small>Tapel</small><strong><?=e((string)($selected['tahun_nama']??'-'))?></strong></div></div><form method="post" onsubmit="return confirm('Proses siswa ini sebagai PINDAH? Data tidak akan dihapus.')" style="margin-top:20px"><?=csrf_field()?><input type="hidden" name="siswa_id" value="<?=$selected['id']?>"><input type="hidden" name="tahun_pelajaran_id" value="<?=$yearId?>"><div class="form-grid"><label>Tanggal Pindah *<input class="form-control" type="date" name="tanggal_status" value="<?=e(date('Y-m-d'))?>" required></label><label>Sekolah Tujuan *<input class="form-control" name="tujuan" required placeholder="Contoh: SMP Negeri 2 Plaosan"></label><label style="grid-column:1/-1">Keterangan<textarea class="form-control" name="keterangan" rows="3" placeholder="Alasan atau catatan tambahan..."></textarea></label></div><div class="form-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>siswa/mutasi/pindah.php?tahun_pelajaran_id=<?=$yearId?>">Batal</a><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Proses Pindah</button></div></form></div><?php else: ?><div class="dashboard-table-card"><div class="section-title"><div><h2>Pilih Siswa</h2><p>Hanya siswa yang masih aktif pada Tapel yang dipilih yang ditampilkan.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>NIS</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Aksi</th></tr></thead><tbody><?php if(!$list):?><tr><td colspan="5" class="empty-state">Siswa aktif tidak ditemukan.</td></tr><?php else:foreach($list as $r):?><tr><td><?=e($r['nis'])?></td><td><?=e((string)($r['nisn']??'-'))?></td><td><?=e($r['nama'])?></td><td><?=e((string)($r['nama_kelas']??'-'))?></td><td><a class="btn btn-primary btn-sm" href="<?=BASE_URL?>siswa/mutasi/pindah.php?tahun_pelajaran_id=<?=$yearId?>&siswa_id=<?=$r['id']?>"><i class="bi bi-check2"></i> Pilih</a></td></tr><?php endforeach;endif;?></tbody></table></div></div><?php endif; ?>
</section></main><?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
