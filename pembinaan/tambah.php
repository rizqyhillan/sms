<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pembinaan_require_access();
if (!pembinaan_can_create()) deny_access();

$tapel=pembinaan_active_tapel($pdo); $tapelId=$tapel?(int)$tapel['id']:0;
if(!$tapelId) { $error='Belum ada Tahun Pelajaran aktif.'; }
$error=$error??'';
$students=$tapelId ? (function() use($pdo,$tapelId){
    $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,mk.nama kelas FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.status='aktif' ORDER BY mk.urutan,mk.nama,s.nama"); $st->execute([$tapelId]); return $st->fetchAll();
})() : [];
$violations=$tapelId ? (function() use($pdo,$tapelId){
    $st=$pdo->prepare("SELECT ps.id,ps.siswa_id,s.nama,jp.kode,jp.nama jenis,ps.tanggal,ps.poin FROM pelanggaran_siswa ps JOIN siswa s ON s.id=ps.siswa_id JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id WHERE ps.tahun_pelajaran_id=? ORDER BY ps.tanggal DESC,ps.id DESC"); $st->execute([$tapelId]); return $st->fetchAll();
})() : [];
$bkNotes=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_valid_csrf();
    $siswaId=(int)($_POST['siswa_id']??0); $pelanggaranId=(int)($_POST['pelanggaran_id']??0);
    $tanggal=trim((string)($_POST['tanggal']??date('Y-m-d'))); $waktu=trim((string)($_POST['waktu']??''));
    $jenis=(string)($_POST['jenis']??'pembinaan_individu'); $masalah=trim((string)($_POST['masalah']??'')); $tindakan=trim((string)($_POST['tindakan']??'')); $hasil=trim((string)($_POST['hasil']??'')); $tindakLanjut=trim((string)($_POST['tindak_lanjut']??'')); $status=(string)($_POST['status']??'proses'); $melibatkanOrtu=isset($_POST['melibatkan_ortu'])?1:0; $rekomendasi=max(0,(int)($_POST['rekomendasi_pengurangan_poin']??0)); $rujukanBk=(int)($_POST['rujukan_bk_id']??0);
    if(!$tapelId) $error='Tahun Pelajaran aktif belum tersedia.';
    elseif($siswaId<=0) $error='Siswa wajib dipilih.';
    elseif(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tanggal)) $error='Tanggal tidak valid.';
    elseif(!in_array($jenis,['teguran','konsultasi','pembinaan_individu','pembinaan_ortu','perjanjian_perilaku','lainnya'],true)) $error='Jenis pembinaan tidak valid.';
    elseif($masalah==='') $error='Masalah/konteks pembinaan wajib diisi.';
    elseif(!in_array($status,['direncanakan','proses','selesai','dibatalkan'],true)) $error='Status tidak valid.';
    elseif($rekomendasi<0) $error='Nilai rekomendasi tidak valid.';
    else{
        $check=$pdo->prepare("SELECT id FROM siswa WHERE id=? AND status='aktif' LIMIT 1");$check->execute([$siswaId]); if(!$check->fetchColumn()) $error='Siswa tidak ditemukan atau tidak aktif.';
        if(!$error && $pelanggaranId>0){$st=$pdo->prepare('SELECT id FROM pelanggaran_siswa WHERE id=? AND siswa_id=? AND tahun_pelajaran_id=? LIMIT 1');$st->execute([$pelanggaranId,$siswaId,$tapelId]);if(!$st->fetchColumn())$error='Pelanggaran terkait tidak sesuai dengan siswa/tahun pelajaran.';}
        if(!$error && $rujukanBk>0){$st=$pdo->prepare('SELECT id FROM catatan_bk WHERE id=? AND siswa_id=? LIMIT 1');$st->execute([$rujukanBk,$siswaId]);if(!$st->fetchColumn())$error='Rujukan BK tidak sesuai dengan siswa.';}
    }
    if(!$error){
        $petugas=pembinaan_current_guru_id($pdo);
        $st=$pdo->prepare("INSERT INTO pembinaan_siswa (siswa_id,tahun_pelajaran_id,pelanggaran_id,tanggal,waktu,jenis,masalah,tindakan,hasil,tindak_lanjut,status,melibatkan_ortu,rekomendasi_pengurangan_poin,rujukan_bk_id,petugas_id,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $st->execute([$siswaId,$tapelId,$pelanggaranId>0?$pelanggaranId:null,$tanggal,$waktu!==''?$waktu:null,$jenis,$masalah,$tindakan!==''?$tindakan:null,$hasil!==''?$hasil:null,$tindakLanjut!==''?$tindakLanjut:null,$status,$melibatkanOrtu,$rekomendasi,$rujukanBk>0?$rujukanBk:null,$petugas?:null,user_id()?:null]);
        $id=(int)$pdo->lastInsertId(); pembinaan_audit($pdo,'create',$id,'Membuat pembinaan untuk siswa ID '.$siswaId); header('Location: '.BASE_URL.'pembinaan/detail.php?id='.$id.'&saved=1'); exit;
    }
}

$page_title='Tambah Pembinaan'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main"><header class="topbar"><div class="topbar-title">Tambah Pembinaan</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-person-check-fill"></i> Catat Pembinaan</h1><p>Data disimpan pada Tahun Pelajaran <?=e($tapel['nama']??'-')?>.</p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pembinaan/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div></div>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif;?>
<form method="post" class="dashboard-table-card"><?=csrf_field()?><div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:18px"><div>
<label class="form-label">Siswa *</label>
<?php $studentPickerValue=(int)($_POST['siswa_id']??0); ?>
<div class="student-picker" data-student-picker data-items="<?=e(json_encode(array_map(fn($r)=>['id'=>(int)$r['id'],'nis'=>(string)$r['nis'],'nisn'=>(string)($r['nisn']??''),'nama'=>(string)$r['nama'],'kelas'=>(string)($r['kelas']??'')],$students),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
<input type="hidden" name="siswa_id" data-student-value value="<?=$studentPickerValue?>">
<div class="student-picker-toolbar"><select class="form-control" data-student-class><option value="">Pilih Kelas Terlebih Dahulu</option></select><input class="form-control" type="search" data-student-search placeholder="Pilih kelas terlebih dahulu, lalu cari nama/NIS/NISN..." autocomplete="off"></div>
<div data-student-selected></div><div class="student-picker-results" data-student-results></div><div class="student-picker-empty" data-student-empty hidden>Tidak ada siswa yang sesuai.</div>
<small class="student-picker-help"><i class="bi bi-funnel"></i> Pilih kelas terlebih dahulu, kemudian pilih siswa.</small></div>
<label class="form-label" style="margin-top:12px">Pelanggaran Terkait <span class="pb-help">(opsional)</span></label><select name="pelanggaran_id" id="pelanggaran_id" class="form-control"><option value="0">Tidak dikaitkan</option><?php foreach($violations as $v):?><option value="<?=$v['id']?>" data-siswa="<?=$v['id']?>" data-student="<?=$v['siswa_id']?>"><?=e($v['tanggal'])?> · <?=e($v['nama'])?> · <?=e($v['jenis'])?> (+<?=e($v['poin'])?>)</option><?php endforeach;?></select>
<label class="form-label" style="margin-top:12px">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?=e($_POST['tanggal']??date('Y-m-d'))?>" required>
<label class="form-label" style="margin-top:12px">Waktu</label><input type="time" name="waktu" class="form-control" value="<?=e($_POST['waktu']??date('H:i'))?>">
<label class="form-label" style="margin-top:12px">Jenis Pembinaan</label><select name="jenis" class="form-control"><?php foreach(['teguran'=>'Teguran','konsultasi'=>'Konsultasi','pembinaan_individu'=>'Pembinaan Individu','pembinaan_ortu'=>'Pembinaan dengan Orang Tua','perjanjian_perilaku'=>'Perjanjian Perilaku','lainnya'=>'Lainnya'] as $v=>$l):?><option value="<?=$v?>" <?=($_POST['jenis']??'pembinaan_individu')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select>
<label class="form-label" style="margin-top:12px">Status</label><select name="status" class="form-control"><?php foreach(['direncanakan'=>'Direncanakan','proses'=>'Dalam Proses','selesai'=>'Selesai','dibatalkan'=>'Dibatalkan'] as $v=>$l):?><option value="<?=$v?>" <?=($_POST['status']??'proses')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select>
<label class="form-label" style="margin-top:12px">Masalah / Konteks</label><textarea name="masalah" class="form-control" rows="5" required placeholder="Apa yang menjadi dasar pembinaan?"><?=e($_POST['masalah']??'')?></textarea>
</div><div>
<label class="form-label">Tindakan Pembinaan</label><textarea name="tindakan" class="form-control" rows="5" placeholder="Nasihat, kesepakatan, pendampingan, atau tindakan yang dilakukan..."><?=e($_POST['tindakan']??'')?></textarea>
<label class="form-label" style="margin-top:12px">Hasil</label><textarea name="hasil" class="form-control" rows="4" placeholder="Perubahan/hasil yang terlihat..."><?=e($_POST['hasil']??'')?></textarea>
<label class="form-label" style="margin-top:12px">Tindak Lanjut</label><textarea name="tindak_lanjut" class="form-control" rows="4" placeholder="Apa yang perlu dilakukan setelah pembinaan?"><?=e($_POST['tindak_lanjut']??'')?></textarea>
<label class="form-label" style="margin-top:12px">Rujukan Catatan BK <span class="pb-help">(opsional)</span></label><select name="rujukan_bk_id" id="rujukan_bk_id" class="form-control"><option value="0">Tidak ada rujukan</option></select>
<label style="display:flex;gap:8px;align-items:center;margin-top:15px"><input type="checkbox" name="melibatkan_ortu" value="1" <?=isset($_POST['melibatkan_ortu'])?'checked':''?>> Melibatkan orang tua/wali</label>
<label class="form-label" style="margin-top:15px">Rekomendasi Pengurangan Poin</label><input type="number" name="rekomendasi_pengurangan_poin" class="form-control" min="0" step="1" value="<?=e($_POST['rekomendasi_pengurangan_poin']??0)?>"><small style="color:#64748b">Ini hanya rekomendasi. Pengurangan poin aktual dilakukan melalui penyesuaian poin yang tercatat/audit.</small>
</div></div><div style="margin-top:18px"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Pembinaan</button></div></form>
<script>
const siswa=document.querySelector('[data-student-value][name="siswa_id"]'), pel=document.getElementById('pelanggaran_id');
function filterPel(){const sid=siswa.value;Array.from(pel.options).forEach((o,i)=>{if(i===0){o.hidden=false;return;}o.hidden=!!sid && o.dataset.student!==sid;});if(pel.selectedOptions[0]&&pel.selectedOptions[0].hidden)pel.value='0';}
siswa.addEventListener('change',filterPel);filterPel();
</script>
</section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
