<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
if (user_role() !== 'kepala_sekolah') { deny_access(); }
require_once __DIR__ . '/../includes/school_profile.php';
$profil = school_profile($pdo);

function kh_rows(PDO $pdo, string $sql, array $params=[]): array { try { $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable) { return []; } }
function kh_date(string $v): string { if (!$v) return '-'; $t=strtotime($v); return $t ? date('d-m-Y H:i', $t) : e($v); }

$module = trim((string)($_GET['module'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$allowed = ['Semua','Absensi','Sholat','Pelanggaran','Pembinaan','Home Visit','BK','UKS','Perpustakaan','Ekstrakurikuler','Mutasi/Kelulusan'];
if (!in_array($module, $allowed, true)) $module='Semua';

$rows=[];
$add=function(string $mod,string $date,string $title,string $detail,string $status='') use (&$rows){
    $rows[]=['module'=>$mod,'date'=>$date,'title'=>$title,'detail'=>$detail,'status'=>$status];
};

$baseDate=function(string $col) use ($from,$to): array {
    $w=[];$p=[];
    if($from!==''){ $w[]="$col >= ?"; $p[]=$from.' 00:00:00'; }
    if($to!==''){ $w[]="$col < ?"; $p[]=date('Y-m-d H:i:s',strtotime($to.' +1 day')); }
    return [$w,$p];
};

if($module==='Semua' || $module==='Absensi'){
    [$w,$p]=$baseDate('sa.tanggal');
    $sql="SELECT sa.tanggal,a.waktu_scan,a.status,a.keterangan,s.nama,mk.nama kelas FROM absensi a JOIN sesi_absensi sa ON sa.id=a.sesi_id JOIN siswa s ON s.id=a.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=sa.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY sa.tanggal DESC,a.id DESC LIMIT 80';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Absensi',$r['tanggal'],($r['nama']??'-').' · '.($r['kelas']??'-'),'Kehadiran: '.($r['status']??'-').(!empty($r['keterangan'])?' · '.$r['keterangan']:''),$r['status']??'');
}
if($module==='Semua' || $module==='Sholat'){
    [$w,$p]=$baseDate('sa.tanggal');
    $sql="SELECT sa.tanggal,ash.status,ash.keterangan,s.nama,js.nama sholat FROM absensi_sholat ash JOIN sesi_absensi sa ON sa.id=ash.sesi_id JOIN siswa s ON s.id=ash.siswa_id LEFT JOIN jenis_sholat js ON js.id=sa.jenis_sholat_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY sa.tanggal DESC,ash.id DESC LIMIT 80';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Sholat',$r['tanggal'],($r['nama']??'-').' · '.($r['sholat']??'Sholat'),'Status: '.($r['status']??'-').(!empty($r['keterangan'])?' · '.$r['keterangan']:''),$r['status']??'');
}
if($module==='Semua' || $module==='Pelanggaran'){
    [$w,$p]=$baseDate('ps.tanggal');
    $sql="SELECT ps.tanggal,ps.status,ps.poin,ps.keterangan,s.nama,jp.nama jenis FROM pelanggaran_siswa ps JOIN siswa s ON s.id=ps.siswa_id JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY ps.tanggal DESC,ps.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Pelanggaran',$r['tanggal'],$r['nama'].' · '.($r['jenis']??'Pelanggaran'),'Poin: '.($r['poin']??0).(!empty($r['keterangan'])?' · '.$r['keterangan']:''),$r['status']??'');
}
if($module==='Semua' || $module==='Pembinaan'){
    [$w,$p]=$baseDate('p.tanggal');
    $sql="SELECT p.tanggal,p.status,p.jenis,p.hasil,p.tindak_lanjut,s.nama FROM pembinaan_siswa p JOIN siswa s ON s.id=p.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY p.tanggal DESC,p.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Pembinaan',$r['tanggal'],$r['nama'].' · '.str_replace('_',' ',(string)($r['jenis']??'Pembinaan')),'Hasil: '.($r['hasil']??'-').(!empty($r['tindak_lanjut'])?' · '.$r['tindak_lanjut']:''),$r['status']??'');
}
if($module==='Semua' || $module==='Home Visit'){
    $dateExpr='COALESCE(hv.tanggal_pelaksanaan,hv.tanggal_rencana)'; [$w,$p]=$baseDate($dateExpr);
    $sql="SELECT hv.tanggal_rencana,hv.tanggal_pelaksanaan,hv.status,hv.tujuan,hv.hasil,hv.tindak_lanjut,s.nama FROM home_visit hv JOIN siswa s ON s.id=hv.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=" ORDER BY $dateExpr DESC,hv.id DESC LIMIT 60";
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Home Visit',$r['tanggal_pelaksanaan']?:$r['tanggal_rencana'],$r['nama'],'Tujuan: '.($r['tujuan']??'-').' · Hasil: '.($r['hasil']??'-').(!empty($r['tindak_lanjut'])?' · '.$r['tindak_lanjut']:''),$r['status']??'');
}
if($module==='Semua' || $module==='BK'){
    [$w,$p]=$baseDate('bl.tanggal');
    $sql="SELECT bl.tanggal,bl.status,bl.jenis_layanan,bl.topik,s.nama FROM bk_layanan bl JOIN siswa s ON s.id=bl.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY bl.tanggal DESC,bl.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('BK',$r['tanggal'],$r['nama'].' · '.($r['jenis_layanan']??'Layanan BK'),'Topik: '.($r['topik']??'-'),$r['status']??'');
}
if($module==='Semua' || $module==='UKS'){
    [$w,$p]=$baseDate('uk.tanggal');
    $sql="SELECT uk.tanggal,uk.status,uk.jenis_layanan,s.nama FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY uk.tanggal DESC,uk.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('UKS',$r['tanggal'],$r['nama'].' · '.($r['jenis_layanan']??'Layanan UKS'),'Layanan kesehatan tercatat',$r['status']??'');
}
if($module==='Semua' || $module==='Perpustakaan'){
    [$w,$p]=$baseDate('k.waktu_kunjungan');
    $sql="SELECT k.waktu_kunjungan,s.nama,k.metode,k.keterangan FROM perpustakaan_kunjungan k JOIN siswa s ON s.id=k.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY k.waktu_kunjungan DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Perpustakaan',$r['waktu_kunjungan'],$r['nama'].' · Kunjungan','Metode: '.($r['metode']??'-').(!empty($r['keterangan'])?' · '.$r['keterangan']:''));
    [$w,$p]=$baseDate('p.tanggal_pinjam');
    $sql="SELECT p.tanggal_pinjam,p.status,COALESCE(s.nama,g.nama) nama,b.judul FROM perpustakaan_peminjaman p JOIN perpustakaan_anggota a ON a.id=p.anggota_id LEFT JOIN siswa s ON s.id=a.siswa_id LEFT JOIN guru g ON g.id=a.guru_id JOIN perpustakaan_detail_peminjaman d ON d.peminjaman_id=p.id JOIN perpustakaan_buku b ON b.id=d.buku_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY p.tanggal_pinjam DESC,p.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Perpustakaan',$r['tanggal_pinjam'],$r['nama'].' · Peminjaman','Buku: '.($r['judul']??'-'),$r['status']??'');
}
if($module==='Semua' || $module==='Ekstrakurikuler'){
    [$w,$p]=$baseDate('es.tanggal');
    $sql="SELECT es.tanggal,es.status,es.materi,e.nama ekstra FROM ekstra_sesi es JOIN ekstrakurikuler e ON e.id=es.ekstrakurikuler_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY es.tanggal DESC,es.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Ekstrakurikuler',$r['tanggal'],$r['ekstra'],'Kegiatan: '.($r['materi']??'-'),$r['status']??'');
}
if($module==='Semua' || $module==='Mutasi/Kelulusan'){
    [$w,$p]=$baseDate('r.tanggal_status');
    $sql="SELECT r.tanggal_status,r.status,r.tujuan,r.keterangan,s.nama FROM siswa_status_riwayat r JOIN siswa s ON s.id=r.siswa_id";
    if($w)$sql.=' WHERE '.implode(' AND ',$w); $sql.=' ORDER BY r.tanggal_status DESC,r.id DESC LIMIT 60';
    foreach(kh_rows($pdo,$sql,$p) as $r) $add('Mutasi/Kelulusan',$r['tanggal_status'],$r['nama'].' · '.str_replace('_',' ',(string)$r['status']),'Tujuan: '.($r['tujuan']??'-').(!empty($r['keterangan'])?' · '.$r['keterangan']:''),$r['status']??'');
}

usort($rows,fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date']));
$total=count($rows);
$rows=array_slice($rows,0,300);
$page_title='Histori & Catatan';
require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main ks-history-page">
<header class="topbar"><div><h1>Histori & Catatan</h1><div class="topbar-subtitle"><?=e($profil['nama_sekolah'])?></div></div><div class="topbar-user"><strong><?=e(user_name())?></strong><span>Kepala Sekolah · Read Only</span></div></header>
<section class="content">
<div class="page-header"><div><h1><i class="bi bi-clock-history"></i> Histori & Catatan</h1><p>Jejak kegiatan sekolah dari modul yang memang memiliki histori pada aplikasi.</p></div></div>
<div class="history-context"><div class="history-stat"><i class="bi bi-collection"></i><div><strong><?=number_format($total)?></strong><span>Catatan ditemukan</span></div></div><div class="history-stat"><i class="bi bi-shield-check"></i><div><strong>Read Only</strong><span>Tanpa perubahan data</span></div></div></div>
<form class="history-filter" method="get"><div><label>Modul</label><select name="module"><option value="Semua">Semua Modul</option><?php foreach(array_slice($allowed,1) as $m):?><option value="<?=e($m)?>" <?= $module===$m?'selected':''?>><?=e($m)?></option><?php endforeach;?></select></div><div><label>Dari</label><input type="date" name="from" value="<?=e($from)?>"></div><div><label>Sampai</label><input type="date" name="to" value="<?=e($to)?>"></div><div class="filter-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel-fill"></i> Tampilkan</button><a class="btn btn-light" href="saya/histori.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a></div></form>
<div class="history-grid">
<?php if(!$rows):?><div class="history-empty"><i class="bi bi-inbox"></i><h3>Belum ada histori</h3><p>Tidak ada catatan yang sesuai dengan filter saat ini.</p></div><?php else: foreach($rows as $r):?><article class="history-item"><div class="history-icon mod-<?=e(strtolower(preg_replace('/[^a-z0-9]+/i','-',(string)$r['module'])))?>"><i class="bi bi-<?=e(match($r['module']){'Absensi'=>'calendar2-check-fill','Sholat'=>'moon-stars-fill','Pelanggaran'=>'exclamation-triangle-fill','Pembinaan'=>'person-check-fill','Home Visit'=>'house-heart-fill','BK'=>'chat-heart-fill','UKS'=>'heart-pulse-fill','Perpustakaan'=>'book-half','Ekstrakurikuler'=>'trophy-fill','Mutasi/Kelulusan'=>'arrow-left-right','default'=>'journal-text-fill'})?>"></i></div><div class="history-body"><div class="history-head"><span class="history-module"><?=e($r['module'])?></span><time><?=e(kh_date((string)$r['date']))?></time></div><h3><?=e($r['title'])?></h3><p><?=e($r['detail'])?></p><?php if($r['status']!==''):?><span class="history-status"><?=e($r['status'])?></span><?php endif;?></div></article><?php endforeach;endif; ?>
</div>
<div class="dashboard-note note-role"><i class="bi bi-eye-fill"></i><div><strong>Mode Kepala Sekolah — Read Only</strong>Histori ini hanya untuk pemantauan. Perubahan data tetap dilakukan melalui modul operasional oleh pengguna yang berwenang.</div></div>
</section>
<style>
.ks-history-page{background:linear-gradient(180deg,#f5f8fc 0%,#eef4f8 100%);min-height:100vh}.ks-history-page .topbar{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:82px;padding:18px 28px;border-bottom:1px solid #dbe4ee;background:#fff}.ks-history-page .topbar h1{font-size:28px;line-height:1.1;margin:0;font-weight:800;color:#16213a}.ks-history-page .topbar-subtitle{margin-top:5px;font-size:13px;color:#64748b}.ks-history-page .topbar-user{display:flex;flex-direction:column;align-items:flex-end;color:#16213a}.ks-history-page .topbar-user span{font-size:12px;color:#64748b}.history-context{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 18px}.history-stat{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:14px;background:#fff;border:1px solid #dce6f0;box-shadow:0 5px 16px rgba(25,55,90,.06)}.history-stat>i{font-size:24px;color:#2563eb}.history-stat strong{display:block;font-size:20px;color:#16213a}.history-stat span{display:block;font-size:12px;color:#64748b}.history-filter{display:flex;align-items:end;gap:10px;flex-wrap:wrap;padding:16px;background:#fff;border:1px solid #dce6f0;border-radius:16px;margin-bottom:20px}.history-filter>div{display:flex;flex-direction:column;gap:5px}.history-filter label{font-size:12px;font-weight:700;color:#475569}.history-filter select,.history-filter input{height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 11px;background:#fff;color:#172033}.history-filter select{min-width:190px}.filter-actions{flex-direction:row!important;align-items:center}.history-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.history-item{display:flex;gap:13px;padding:15px;background:#fff;border:1px solid #dce6f0;border-radius:16px;box-shadow:0 5px 15px rgba(25,55,90,.05)}.history-icon{width:46px;height:46px;flex:0 0 46px;border-radius:13px;display:grid;place-items:center;font-size:21px;background:#e8f1ff;color:#2563eb}.history-icon.mod-sholat{background:#ecebff;color:#5145cd}.history-icon.mod-pelanggaran{background:#fff0e6;color:#ea580c}.history-icon.mod-pembinaan{background:#fff7d9;color:#b77900}.history-icon.mod-home-visit{background:#e8f8f0;color:#15966a}.history-icon.mod-bk{background:#f2eaff;color:#7c3aed}.history-icon.mod-uks{background:#e7faf7;color:#0f9d8a}.history-icon.mod-perpustakaan{background:#e6f8fb;color:#0891b2}.history-icon.mod-ekstrakurikuler{background:#f0eaff;color:#7c3aed}.history-icon.mod-mutasi-kelulusan{background:#eef2ff;color:#4f46e5}.history-body{min-width:0;flex:1}.history-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.history-module{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#2563eb}.history-head time{font-size:11px;color:#64748b;white-space:nowrap}.history-body h3{margin:5px 0 4px;font-size:15px;color:#172033}.history-body p{margin:0;color:#64748b;font-size:12px;line-height:1.55}.history-status{display:inline-block;margin-top:8px;padding:4px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:10px;font-weight:800}.history-empty{grid-column:1/-1;text-align:center;background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:50px 20px;color:#64748b}.history-empty i{font-size:38px}.history-empty h3{margin:10px 0 4px;color:#334155}.note-role{margin-top:18px}@media(max-width:900px){.history-grid{grid-template-columns:1fr}}@media(max-width:640px){.ks-history-page .topbar{padding:15px 16px}.history-filter{align-items:stretch}.history-filter>div{width:100%}.history-filter select,.history-filter input{width:100%}.filter-actions{width:100%;justify-content:flex-start}.history-item{padding:12px}.history-head{align-items:flex-start;flex-direction:column;gap:2px}}
</style>
</main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
