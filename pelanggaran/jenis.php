<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pelanggaran_require_access();
if(!pelanggaran_can_manage_master()) deny_access();
$errors=[];$success='';$editId=(int)($_GET['edit']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();$action=(string)($_POST['action']??'');
    try{
        if($action==='save'){
            $id=(int)($_POST['id']??0);$kode=strtoupper(trim((string)($_POST['kode']??'')));$nama=trim((string)($_POST['nama']??''));$kategori=trim((string)($_POST['kategori']??''));$poin=(int)($_POST['poin']??0);$ket=trim((string)($_POST['keterangan']??''));
            if($kode===''||$nama==='')$errors[]='Kode dan nama pelanggaran wajib diisi.';
            if($poin<0)$errors[]='Poin tidak boleh negatif.';
            if(!$errors){
                if($id>0){$st=$pdo->prepare('UPDATE jenis_pelanggaran SET kode=?,nama=?,kategori=?,poin=?,keterangan=? WHERE id=?');$st->execute([$kode,$nama,$kategori!==''?$kategori:null,$poin,$ket!==''?$ket:null,$id]);pelanggaran_audit($pdo,'UPDATE_JENIS',$id,'Memperbarui jenis pelanggaran '.$nama);$success='Jenis pelanggaran diperbarui.';}
                else{$st=$pdo->prepare('INSERT INTO jenis_pelanggaran(kode,nama,kategori,poin,aktif,keterangan) VALUES(?,?,?,?,1,?)');$st->execute([$kode,$nama,$kategori!==''?$kategori:null,$poin,$ket!==''?$ket:null]);$new=(int)$pdo->lastInsertId();pelanggaran_audit($pdo,'CREATE_JENIS',$new,'Menambahkan jenis pelanggaran '.$nama);$success='Jenis pelanggaran berhasil ditambahkan.';}
                $editId=0;
            }
        }elseif($action==='toggle'){$id=(int)($_POST['id']??0);$st=$pdo->prepare('UPDATE jenis_pelanggaran SET aktif=IF(aktif=1,0,1) WHERE id=?');$st->execute([$id]);pelanggaran_audit($pdo,'TOGGLE_JENIS',$id,'Mengubah status jenis pelanggaran');$success='Status jenis pelanggaran diperbarui.';}
    }catch(PDOException $e){$errors[]=$e->getCode()==='23000'?'Kode atau nama pelanggaran sudah digunakan.':'Gagal menyimpan data.';}
}
$edit=null;if($editId){$st=$pdo->prepare('SELECT * FROM jenis_pelanggaran WHERE id=?');$st->execute([$editId]);$edit=$st->fetch()?:null;}
$perPageOptions=[5,10,15,25,50];
$perPage=(int)($_GET['per_page']??10); if(!in_array($perPage,$perPageOptions,true))$perPage=10;
$page=max(1,(int)($_GET['page']??1));
$total=(int)$pdo->query('SELECT COUNT(*) FROM jenis_pelanggaran')->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages)$page=$totalPages;
$offset=($page-1)*$perPage;
$stRows=$pdo->prepare("SELECT * FROM jenis_pelanggaran ORDER BY CASE WHEN kode REGEXP '^[0-9]+$' THEN 0 ELSE 1 END, CAST(kode AS UNSIGNED), kode, id LIMIT ? OFFSET ?");
$stRows->bindValue(1,$perPage,PDO::PARAM_INT);$stRows->bindValue(2,$offset,PDO::PARAM_INT);$stRows->execute();$rows=$stRows->fetchAll();
$page_title='Jenis Pelanggaran & Poin';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Jenis Pelanggaran & Poin</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-list-check"></i> Jenis Pelanggaran & Poin</h1><p>Master poin menjadi sumber otomatis saat pelanggaran dicatat.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pelanggaran/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
<?php foreach($errors as $x):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($x)?></div><?php endforeach;?><?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif;?>
<style>
.pelanggaran-master-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.pelanggaran-master-table{min-width:0}
.pelanggaran-master-table .dashboard-table-card{overflow:hidden}
.pelanggaran-master-table .table-responsive{overflow-x:auto}
.pelanggaran-master-table .data-table{width:100%;min-width:0;table-layout:auto}
.pelanggaran-master-table .data-table th,.pelanggaran-master-table .data-table td{vertical-align:middle}
.pelanggaran-master-table .col-kode{width:72px;white-space:nowrap;text-align:center}
.pelanggaran-master-table .col-nama{width:42%;}
.pelanggaran-master-table .col-kategori{width:24%;}
.pelanggaran-master-table .col-poin{width:72px;white-space:nowrap;text-align:center}
.pelanggaran-master-table .col-status{width:92px;white-space:nowrap;text-align:center}
.pelanggaran-master-table .col-aksi{width:92px;white-space:nowrap;text-align:center}
.pelanggaran-master-table code{display:inline-block;white-space:nowrap;min-width:38px;text-align:center}
.pelanggaran-master-table .nama-pelanggaran,.pelanggaran-master-table .kategori-pelanggaran{overflow-wrap:anywhere;word-break:normal;line-height:1.45}
.pelanggaran-master-table .keterangan-pelanggaran{display:block;margin-top:4px;line-height:1.35;overflow-wrap:anywhere}
.pelanggaran-master-table .aksi-wrap{display:flex;justify-content:center;gap:6px;align-items:center}
.pelanggaran-pagination{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:14px 0 0}
.pelanggaran-pagination .page-info{color:#64748b;font-size:.92rem}
.pelanggaran-pagination .page-links{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.pelanggaran-pagination a,.pelanggaran-pagination span{min-width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dbe3ef;border-radius:9px;text-decoration:none;background:#fff;color:#334155;padding:0 10px}
.pelanggaran-pagination a.active{background:#315be8;color:#fff;border-color:#315be8;font-weight:700}
.pelanggaran-pagination a.disabled{pointer-events:none;opacity:.45}
.pelanggaran-modal{width:min(680px,calc(100vw - 28px));border:0;border-radius:18px;padding:0;box-shadow:0 24px 80px rgba(15,23,42,.28)}
.pelanggaran-modal::backdrop{background:rgba(15,23,42,.5)}
.pelanggaran-modal .modal-head{display:flex;justify-content:space-between;align-items:center;padding:20px 22px;border-bottom:1px solid #e5e7eb}
.pelanggaran-modal .modal-body{padding:22px}
.pelanggaran-modal .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}
@media (max-width:900px){.pelanggaran-master-table .data-table{min-width:0}.pelanggaran-master-table .col-nama{width:38%}.pelanggaran-master-table .col-kategori{width:22%}}
@media (max-width:640px){.pelanggaran-master-table .table-responsive{overflow-x:auto}.pelanggaran-master-table .data-table{min-width:760px}.pelanggaran-master-toolbar{align-items:flex-start}.pelanggaran-pagination{align-items:flex-start}}
</style>
<div class="pelanggaran-master-table"><div class="dashboard-table-card">
<div class="pelanggaran-master-toolbar"><div class="section-title" style="margin:0"><div><h2>Daftar Jenis Pelanggaran</h2><p><?=$total?> jenis tersimpan · diurutkan berdasarkan kode terkecil.</p></div></div><button type="button" class="btn btn-primary" onclick="document.getElementById('tambahJenisModal').showModal()"><i class="bi bi-plus-circle"></i> Tambah Jenis Pelanggaran</button></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th class="col-kode">Kode</th><th class="col-nama">Nama Pelanggaran</th><th class="col-kategori">Kategori</th><th class="col-poin">Poin</th><th class="col-status">Status</th><th class="col-aksi">Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada jenis pelanggaran.</td></tr><?php else:foreach($rows as $r):?><tr><td class="col-kode"><code><?=e($r['kode'])?></code></td><td class="nama-pelanggaran"><strong><?=e($r['nama'])?></strong><?php if((string)($r['keterangan']??'')!==''):?><span class="text-muted small keterangan-pelanggaran"><?=e((string)$r['keterangan'])?></span><?php endif;?></td><td class="kategori-pelanggaran"><?=e((string)($r['kategori']??'-'))?></td><td class="col-poin"><strong><?=number_format((int)$r['poin'])?></strong></td><td class="col-status"><span class="status-badge <?=$r['aktif']?'status-aktif':'status-nonaktif'?>"><?=$r['aktif']?'Aktif':'Nonaktif'?></span></td><td class="col-aksi"><div class="aksi-wrap"><a class="btn btn-secondary btn-sm" href="?edit=<?=(int)$r['id']?>&per_page=<?=$perPage?>&page=<?=$page?>" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a><form method="post" style="display:inline" onsubmit="return confirm('Ubah status jenis ini?')"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-warning btn-sm" title="Aktif/nonaktif jenis pelanggaran" aria-label="Aktif/nonaktif jenis pelanggaran"><i class="bi bi-power"></i></button></form></div></td></tr><?php endforeach;endif;?></tbody></table></div>
<div class="pelanggaran-pagination"><div class="page-info">Menampilkan <?= $total?($offset+1):0 ?>–<?=min($offset+$perPage,$total)?> dari <?=$total?> data</div><div class="page-links"><span style="border:0;background:transparent;padding:0 4px;min-width:auto">Tampil</span><?php foreach($perPageOptions as $opt):?><a class="<?=($opt===$perPage?'active':'')?>" href="?page=1&per_page=<?=$opt?>"><?=$opt?></a><?php endforeach;?><?php if($page>1):?><a href="?page=<?=$page-1?>&per_page=<?=$perPage?>" aria-label="Sebelumnya">‹</a><?php endif;?><?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++):?><a class="<?=($i===$page?'active':'')?>" href="?page=<?=$i?>&per_page=<?=$perPage?>"><?=$i?></a><?php endfor;?><?php if($page<$totalPages):?><a href="?page=<?=$page+1?>&per_page=<?=$perPage?>" aria-label="Berikutnya">›</a><?php endif;?></div></div>
</div></div>
<dialog id="tambahJenisModal" class="pelanggaran-modal"><div class="modal-head"><div><h2 style="margin:0"><?= $edit?'Edit':'Tambah' ?> Jenis Pelanggaran</h2><p style="margin:4px 0 0;color:#64748b">Poin diambil otomatis dari master saat pelanggaran dicatat.</p></div><button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('tambahJenisModal').close()" aria-label="Tutup">×</button></div><div class="modal-body"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>"><div class="form-grid" style="grid-template-columns:1fr 1fr;gap:14px"><label>Kode *<input class="form-control" name="kode" maxlength="50" required value="<?=e((string)($edit['kode']??''))?>" placeholder="Contoh: 101"></label><label>Poin *<input class="form-control" type="number" min="0" max="999" name="poin" required value="<?=e((string)($edit['poin']??0))?>"></label><label style="grid-column:1/-1">Nama Pelanggaran *<input class="form-control" name="nama" maxlength="150" required value="<?=e((string)($edit['nama']??''))?>"></label><label style="grid-column:1/-1">Kategori<input class="form-control" name="kategori" value="<?=e((string)($edit['kategori']??''))?>" placeholder="Kedisiplinan"></label><label style="grid-column:1/-1">Keterangan<textarea class="form-control" name="keterangan" rows="4"><?=e((string)($edit['keterangan']??''))?></textarea></label></div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="document.getElementById('tambahJenisModal').close()">Batal</button><button class="btn btn-primary"><i class="bi bi-save"></i> <?= $edit?'Simpan Perubahan':'Simpan' ?></button></div></form></div></dialog>
<?php if($edit):?><script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('tambahJenisModal').showModal());</script><?php endif;?>
<div class="alert alert-info" style="margin-top:18px"><i class="bi bi-shield-check"></i> Ambang peringatan otomatis: <strong>P1 25+</strong>, <strong>P2 50+</strong>, <strong>P3 75+</strong>, <strong>P4 100+</strong>. Perubahan master poin tidak mengubah nilai poin yang sudah tercatat pada riwayat transaksi.</div>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
