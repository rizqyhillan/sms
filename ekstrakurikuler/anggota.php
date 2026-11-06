<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$extraId=(int)($_GET['extra_id']??$_POST['extra_id']??0);
ekstra_require($extraId);
$extra=ekstra_detail($pdo,$extraId);
if(!$extra || !$extra['aktif']) deny_access();
$tp=active_tapel_id($pdo);
if(!$tp) exit('Tahun Pelajaran aktif belum tersedia.');
$success='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 require_valid_csrf();
 $action=(string)($_POST['action']??'');
 if($action==='bulk_add'){
  $students=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['siswa_ids']??[])),fn($v)=>$v>0)));
  if(!$students) $error='Belum ada siswa yang dicentang.';
  else {
   $valid=$pdo->prepare("SELECT s.id FROM siswa s JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif' WHERE s.status='aktif' AND s.id=? LIMIT 1");
   $chk=$pdo->prepare('SELECT id,status FROM ekstra_anggota WHERE ekstrakurikuler_id=? AND siswa_id=? AND tahun_pelajaran_id=? LIMIT 1');
   $ins=$pdo->prepare("INSERT INTO ekstra_anggota(ekstrakurikuler_id,siswa_id,tahun_pelajaran_id,tanggal_masuk,status) VALUES(?,?,?,CURDATE(),'aktif')");
   $up=$pdo->prepare("UPDATE ekstra_anggota SET status='aktif',tanggal_keluar=NULL,keterangan=NULL WHERE id=?");
   $added=0;
   foreach($students as $student){
    $valid->execute([$tp,$student]);
    if(!$valid->fetchColumn()) continue;
    $chk->execute([$extraId,$student,$tp]);$old=$chk->fetch();
    if($old){ if((string)$old['status']!=='aktif'){$up->execute([(int)$old['id']]);$added++;} }
    else{$ins->execute([$extraId,$student,$tp]);$added++;}
   }
   $success=$added>0 ? $added.' siswa berhasil ditetapkan sebagai anggota aktif.' : 'Tidak ada perubahan. Siswa yang dipilih sudah menjadi anggota aktif atau tidak valid.';
  }
 }elseif($action==='status'){
  $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
  if(!in_array($status,['aktif','nonaktif','keluar'],true)) $error='Status anggota tidak valid.';
  else{
   $st=$pdo->prepare('UPDATE ekstra_anggota SET status=?,tanggal_keluar=CASE WHEN ?="keluar" THEN CURDATE() ELSE tanggal_keluar END WHERE id=? AND ekstrakurikuler_id=? AND tahun_pelajaran_id=?');
   $st->execute([$status,$status,$id,$extraId,$tp]);$success='Status anggota diperbarui.';
  }
 }
}

$st=$pdo->prepare("SELECT ea.id,ea.siswa_id,ea.tanggal_masuk,ea.tanggal_keluar,ea.status,ea.keterangan,s.nis,s.nisn,s.nama,mk.nama kelas_nama
 FROM ekstra_anggota ea JOIN siswa s ON s.id=ea.siswa_id
 LEFT JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=ea.tahun_pelajaran_id AND sk.status='aktif'
 LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
 WHERE ea.ekstrakurikuler_id=? AND ea.tahun_pelajaran_id=? ORDER BY CASE ea.status WHEN 'aktif' THEN 0 ELSE 1 END,s.nama");
$st->execute([$extraId,$tp]);$members=$st->fetchAll();

$st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,sk.kelas_id,mk.nama kelas_nama,COALESCE(ea.status,'') member_status
 FROM siswa s JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif'
 LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
 LEFT JOIN ekstra_anggota ea ON ea.ekstrakurikuler_id=? AND ea.siswa_id=s.id AND ea.tahun_pelajaran_id=?
 WHERE s.status='aktif' ORDER BY mk.nama,s.nama");
$st->execute([$tp,$extraId,$tp]);$available=$st->fetchAll();
$tpRow=$pdo->prepare('SELECT nama FROM tahun_pelajaran WHERE id=?');$tpRow->execute([$tp]);$tpName=(string)($tpRow->fetchColumn()?:'');

$page_title='Anggota Ekstrakurikuler';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main extra-page"><header class="topbar"><div class="topbar-title">Anggota Ekstrakurikuler</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><?=e($extra['nama'])?> · Anggota</h1><p>Tapel <strong><?=e($tpName)?></strong>. Pembina memilih peserta berdasarkan kelas; histori anggota tetap disimpan per Tapel.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>ekstrakurikuler/saya.php">← Kembali</a></div>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="form-card"><h2><i class="bi bi-people-fill"></i> Pilih Peserta Ekstrakurikuler</h2><p class="text-muted">Pilih kelas, kemudian centang siswa yang mengikuti kegiatan. Siswa yang sudah aktif akan tampil tercentang.</p>
<form method="post" id="bulkMemberForm"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="bulk_add">
<div class="member-toolbar"><div><label class="form-label fw-bold">Pilih Kelas</label><select id="memberClass" class="form-control"><option value="">Pilih kelas terlebih dahulu</option></select></div><div><label class="form-label fw-bold">Cari Siswa</label><input id="memberSearch" class="form-control" type="search" placeholder="Nama, NIS, atau NISN..." autocomplete="off" disabled></div></div>
<div class="member-actions"><label><input type="checkbox" id="selectAllMembers" disabled> Centang semua siswa di kelas</label><span id="memberCount" class="text-muted">Belum memilih kelas.</span></div>
<div id="memberList" class="member-list"><div class="member-empty">Pilih kelas untuk menampilkan siswa.</div></div>
<div class="form-actions"><button class="btn btn-primary" type="submit" id="saveMembers" disabled><i class="bi bi-save"></i> Simpan Peserta Terpilih</button></div>
</form></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Daftar Anggota</h2><p><?=count(array_filter($members,fn($m)=>$m['status']==='aktif'))?> anggota aktif.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>No</th><th>Nama</th><th>NIS/NISN</th><th>Kelas</th><th>Masuk</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$members):?><tr><td colspan="7" class="empty-state">Belum ada anggota.</td></tr><?php else:$no=1;foreach($members as $m):?><tr><td><?=$no++?></td><td><strong><?=e($m['nama'])?></strong></td><td><?=e($m['nis'])?><br><small><?=e($m['nisn']??'-')?></small></td><td><?=e($m['kelas_nama']??'-')?></td><td><?=e($m['tanggal_masuk']??'-')?></td><td><span class="status-badge <?=$m['status']==='aktif'?'status-aktif':'status-nonaktif'?>"><?=e(ucfirst($m['status']))?></span></td><td><?php if($m['status']==='aktif'):?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$m['id']?>"><input type="hidden" name="status" value="nonaktif"><button class="btn btn-sm btn-warning btn-icon-sm" title="Nonaktif" aria-label="Nonaktif"><i class="bi bi-pause-circle" aria-hidden="true"></i></button></form><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$m['id']?>"><input type="hidden" name="status" value="keluar"><button class="btn btn-sm btn-danger btn-icon-sm" title="Keluar" aria-label="Keluar"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button></form><?php else:?><form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="extra_id" value="<?=$extraId?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$m['id']?>"><input type="hidden" name="status" value="aktif"><button class="btn btn-sm btn-success btn-icon-sm" title="Aktifkan" aria-label="Aktifkan"><i class="bi bi-check-circle" aria-hidden="true"></i></button></form><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main>
<style>.member-toolbar{display:grid;grid-template-columns:1fr 1fr;gap:12px}.member-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:14px 0 10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.member-list{border:1px solid #e2e8f0;border-radius:12px;max-height:430px;overflow:auto;background:#fff}.member-row{display:flex;align-items:center;gap:12px;padding:10px 13px;border-bottom:1px solid #eef2f7;cursor:pointer}.member-row:last-child{border-bottom:0}.member-row input{width:18px;height:18px}.member-row-main{min-width:0;flex:1}.member-row-main strong,.member-row-main small{display:block}.member-row-main small{color:#64748b}.member-row.already{background:#f0fdf4}.member-row.already .member-tag{color:#166534;background:#dcfce7}.member-tag{font-size:11px;font-weight:700;padding:4px 7px;border-radius:999px;background:#e2e8f0;color:#475569}.member-empty{padding:24px;text-align:center;color:#64748b}@media(max-width:700px){.member-toolbar{grid-template-columns:1fr}}</style>
<script>
(function(){const items=<?=json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'nis'=>(string)$r['nis'],'nisn'=>(string)($r['nisn']??''),'nama'=>(string)$r['nama'],'kelas_id'=>(int)$r['kelas_id'],'kelas'=>(string)($r['kelas_nama']??''),'status'=>(string)($r['member_status']??'')],$available),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;const cls=document.getElementById('memberClass'),search=document.getElementById('memberSearch'),list=document.getElementById('memberList'),all=document.getElementById('selectAllMembers'),count=document.getElementById('memberCount'),save=document.getElementById('saveMembers');const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));const classes=[...new Map(items.map(x=>[String(x.kelas_id),x.kelas]).filter(x=>x[0]&&x[1])).entries()].sort((a,b)=>a[1].localeCompare(b[1],'id',{numeric:true}));classes.forEach(([id,nama])=>{const o=document.createElement('option');o.value=id;o.textContent=nama;cls.appendChild(o)});function rows(){const c=cls.value,q=search.value.trim().toLowerCase();if(!c)return [];return items.filter(x=>String(x.kelas_id)===c&&(!q||[x.nama,x.nis,x.nisn].some(v=>String(v||'').toLowerCase().includes(q))));}function refresh(){const boxes=[...list.querySelectorAll('input[type=checkbox]')];const checked=boxes.filter(x=>x.checked).length;all.checked=boxes.length>0&&checked===boxes.length;all.indeterminate=checked>0&&checked<boxes.length;count.textContent=boxes.length+' siswa ditampilkan · '+checked+' dicentang';save.disabled=boxes.length===0||checked===0;}function update(){const found=rows();list.innerHTML='';if(!cls.value){list.innerHTML='<div class="member-empty">Pilih kelas untuk menampilkan siswa.</div>';all.disabled=true;search.disabled=true;save.disabled=true;count.textContent='Belum memilih kelas.';return}search.disabled=false;all.disabled=!found.length;found.forEach(x=>{const row=document.createElement('label');row.className='member-row '+(x.status==='aktif'?'already':'');const cb=document.createElement('input');cb.type='checkbox';cb.name='siswa_ids[]';cb.value=x.id;cb.checked=x.status==='aktif';cb.addEventListener('change',refresh);const main=document.createElement('span');main.className='member-row-main';main.innerHTML='<strong>'+esc(x.nama)+'</strong><small>'+esc(x.nis||'-')+' · '+esc(x.nisn||'-')+'</small>';const tag=document.createElement('span');tag.className='member-tag';tag.textContent=x.status==='aktif'?'Sudah aktif':'Belum terdaftar';row.append(cb,main,tag);list.appendChild(row)});all.disabled=!found.length;save.disabled=!found.length;refresh()}all.addEventListener('change',()=>{list.querySelectorAll('input[type=checkbox]').forEach(x=>x.checked=all.checked);refresh()});cls.addEventListener('change',update);search.addEventListener('input',update);update();})();
</script>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
