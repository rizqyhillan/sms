<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/school_profile.php';
require_once __DIR__ . '/../bk/_helper.php';
require_permission('laporan');

$profil = school_profile($pdo);
$today = date('Y-m-d');
$mode = (string)($_GET['mode'] ?? 'ringkasan');
$allowedModes = ['ringkasan','kelas','siswa','sholat','izin','pelanggaran','pembinaan','layanan_bk','konsultasi','home_visit'];

// Security: laporan sensitif tidak boleh dibuka oleh guru biasa hanya dengan
// memanipulasi parameter mode pada URL. Akses menu/sidebar bukan satu-satunya
// lapisan kontrol; endpoint laporan sendiri harus menerapkan pembatasan.
$role = user_role();
$roleAllowedModes = match ($role) {
    'admin', 'tu' => $allowedModes,
    'kepala_sekolah' => ['ringkasan','kelas','siswa','sholat','pelanggaran','pembinaan','home_visit'],
    'guru_bk' => ['ringkasan','kelas','siswa','sholat','izin','pelanggaran','layanan_bk','konsultasi','home_visit'],
    'guru' => ['ringkasan','kelas','siswa','sholat','izin'],
    default => ['ringkasan'],
};

if (!in_array($mode, $roleAllowedModes, true)) {
    http_response_code(403);
    exit('Akses laporan tidak diizinkan untuk peran pengguna ini.');
}

$mulai = (string)($_GET['mulai'] ?? $today);
$selesai = (string)($_GET['selesai'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $mulai = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) $selesai = $today;
if ($mulai > $selesai) [$mulai, $selesai] = [$selesai, $mulai];

$tpRows = $pdo->query("SELECT id,nama,tanggal_mulai,tanggal_selesai,aktif FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC")->fetchAll();
$activeTpId = 0;
foreach ($tpRows as $t) { if ((int)$t['aktif'] === 1) { $activeTpId = (int)$t['id']; break; } }
$tpId = (int)($_GET['tahun_pelajaran_id'] ?? $activeTpId);
$tp = null;
foreach ($tpRows as $t) { if ((int)$t['id'] === $tpId) { $tp = $t; break; } }
if (!$tp && $tpRows) { $tp = $tpRows[0]; $tpId = (int)$tp['id']; }

$kelasId = max(0, (int)($_GET['kelas_id'] ?? 0));
$siswaId = max(0, (int)($_GET['siswa_id'] ?? 0));
$sholatId = max(0, (int)($_GET['sholat_id'] ?? 0));
$cetak = (int)($_GET['cetak'] ?? 0) === 1;

$kelasRows = $pdo->query("SELECT id,nama,tingkat,urutan FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama")->fetchAll();
$sholatRows = $pdo->query("SELECT id,nama FROM jenis_sholat WHERE aktif=1 ORDER BY urutan,nama")->fetchAll();
$siswaRows = [];
if ($tpId > 0) {
    $st = $pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,sk.kelas_id,mk.nama AS kelas FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.status='aktif' ORDER BY mk.urutan,mk.nama,s.nama");
    $st->execute([$tpId]); $siswaRows = $st->fetchAll();
}

$rows=[]; $stats=[]; $title='Laporan'; $subtitle='';
$stats = ['total'=>0];

if ($mode === 'ringkasan' || $mode === 'kelas' || $mode === 'siswa' || $mode === 'sholat') {
    $kategori = $mode === 'sholat' ? 'sholat' : 'kehadiran';
    if ($mode === 'ringkasan') {
        $title='Ringkasan Sesi Absensi';
        $sql="SELECT sa.tanggal,sa.nama_sesi,sa.jam_mulai,sa.jam_selesai,sa.status AS status_sesi,ja.kategori,ja.nama AS jenis_nama,js.nama AS sholat_nama,
                COUNT(CASE WHEN ja.kategori='sholat' THEN ash.id ELSE a.id END) tercatat,
                COALESCE(SUM(CASE WHEN ja.kategori<>'sholat' AND a.status='H' THEN 1 ELSE 0 END),0) H,
                COALESCE(SUM(CASE WHEN ja.kategori<>'sholat' AND a.status='T' THEN 1 ELSE 0 END),0) T,
                COALESCE(SUM(CASE WHEN ja.kategori<>'sholat' AND a.status='I' THEN 1 ELSE 0 END),0) I,
                COALESCE(SUM(CASE WHEN ja.kategori<>'sholat' AND a.status='S' THEN 1 ELSE 0 END),0) S,
                COALESCE(SUM(CASE WHEN ja.kategori<>'sholat' AND a.status='A' THEN 1 ELSE 0 END),0) A,
                COALESCE(SUM(CASE WHEN ja.kategori='sholat' AND ash.status='SUDAH' THEN 1 ELSE 0 END),0) sholat_sudah,
                COALESCE(SUM(CASE WHEN ja.kategori='sholat' AND ash.status='BERHALANGAN' THEN 1 ELSE 0 END),0) sholat_berhalangan,
                COALESCE(SUM(CASE WHEN ja.kategori='sholat' AND ash.id IS NULL THEN 1 ELSE 0 END),0) sholat_belum
                FROM sesi_absensi sa JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id LEFT JOIN jenis_sholat js ON js.id=sa.jenis_sholat_id LEFT JOIN absensi a ON a.sesi_id=sa.id LEFT JOIN absensi_sholat ash ON ash.sesi_id=sa.id WHERE sa.tahun_pelajaran_id=? AND sa.tanggal BETWEEN ? AND ?";
        $p=[$tpId,$mulai,$selesai];
        if($kelasId){
            $sql.=" AND ((ja.kategori='sholat' AND EXISTS (SELECT 1 FROM absensi_sholat ashf JOIN siswa_kelas skf ON skf.siswa_id=ashf.siswa_id AND skf.tahun_pelajaran_id=sa.tahun_pelajaran_id AND skf.status='aktif' WHERE ashf.sesi_id=sa.id AND skf.kelas_id=?)) OR (ja.kategori<>'sholat' AND EXISTS (SELECT 1 FROM absensi af JOIN siswa_kelas skf ON skf.siswa_id=af.siswa_id AND skf.tahun_pelajaran_id=sa.tahun_pelajaran_id AND skf.status='aktif' WHERE af.sesi_id=sa.id AND skf.kelas_id=?)))";
            $p[]=$kelasId; $p[]=$kelasId;
        }
        if($siswaId){
            $sql.=" AND ((ja.kategori='sholat' AND EXISTS (SELECT 1 FROM absensi_sholat ashf WHERE ashf.sesi_id=sa.id AND ashf.siswa_id=?)) OR (ja.kategori<>'sholat' AND EXISTS (SELECT 1 FROM absensi af WHERE af.sesi_id=sa.id AND af.siswa_id=?)))";
            $p[]=$siswaId; $p[]=$siswaId;
        }
        $sql.=' GROUP BY sa.id ORDER BY sa.tanggal DESC,sa.jam_mulai DESC,sa.id DESC';
        $st=$pdo->prepare($sql); $st->execute($p); $rows=$st->fetchAll(); $stats=['total'=>count($rows)];
    } else {
        $title=$mode==='sholat'?'Rekap Absensi Sholat':($mode==='kelas'?'Rekap Absensi Per Kelas':'Rekap Absensi Per Siswa');
        $baseParams=[$tpId,$tpId,$mulai,$selesai,$kategori];
        if ($mode==='sholat') {
            // Rekap sholat mengikuti struktur modul Absensi Sholat:
            // SUDAH / BELUM (tidak ada row) / BERHALANGAN.
            // Jangan menggunakan status H/T/I/S/A dari absensi kehadiran umum.
            $sql="SELECT s.id,s.nis,s.nama,s.jk,mk.nama kelas,COUNT(DISTINCT ss.id) jumlah_sesi,
                    COALESCE(SUM(CASE WHEN ash.status='SUDAH' THEN 1 ELSE 0 END),0) sudah,
                    COALESCE(SUM(CASE WHEN ss.id IS NOT NULL AND ash.status='BERHALANGAN' THEN 1 ELSE 0 END),0) berhalangan,
                    COALESCE(SUM(CASE WHEN ss.id IS NOT NULL AND ash.id IS NULL THEN 1 ELSE 0 END),0) belum
                    FROM siswa s
                    JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
                    JOIN master_kelas mk ON mk.id=sk.kelas_id
                    LEFT JOIN (
                        SELECT sa.id,sa.jenis_sholat_id,sa.tanggal
                        FROM sesi_absensi sa
                        JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id AND ja.kategori='sholat'
                        WHERE sa.tahun_pelajaran_id=? AND sa.tanggal BETWEEN ? AND ?
                    ) ss ON 1=1
                    LEFT JOIN absensi_sholat ash ON ash.sesi_id=ss.id AND ash.siswa_id=s.id
                    WHERE s.status='aktif'";
            $p=[$tpId,$tpId,$mulai,$selesai];
            if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;}
            if($sholatId){$sql.=' AND ss.jenis_sholat_id=?';$p[]=$sholatId;}
            $sql.=' GROUP BY s.id,s.nis,s.nama,s.jk,mk.nama ORDER BY mk.nama,s.nama';
            $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
            foreach($rows as &$r){$r['sudah']=(int)$r['sudah'];$r['belum']=(int)$r['belum'];$r['berhalangan']=(int)$r['berhalangan'];$r['jumlah_sesi']=(int)$r['jumlah_sesi'];}unset($r);
        } elseif ($mode==='kelas') {
            $sql="SELECT mk.id,mk.nama,COUNT(DISTINCT sk.siswa_id) jumlah_siswa,COUNT(DISTINCT sa.id) jumlah_sesi,COALESCE(SUM(a.status='H'),0) H,COALESCE(SUM(a.status='T'),0) T,COALESCE(SUM(a.status='I'),0) I,COALESCE(SUM(a.status='S'),0) S,COALESCE(SUM(a.status='A'),0) A,COALESCE(SUM(CASE WHEN sa.status_sesi='CLOSED' AND a.id IS NULL THEN 1 ELSE 0 END),0) A_AUTO FROM master_kelas mk LEFT JOIN siswa_kelas sk ON sk.kelas_id=mk.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif' LEFT JOIN sesi_absensi sa ON sa.tahun_pelajaran_id=? AND sa.tanggal BETWEEN ? AND ? JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id AND ja.kategori=? LEFT JOIN absensi a ON a.sesi_id=sa.id AND a.siswa_id=sk.siswa_id WHERE mk.aktif=1";
            $p=$baseParams; if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;} if($siswaId){$sql.=" AND EXISTS (SELECT 1 FROM siswa_kelas skf WHERE skf.siswa_id=? AND skf.tahun_pelajaran_id=? AND skf.status='aktif' AND skf.kelas_id=mk.id)";$p[]=$siswaId;$p[]=$tpId;} $sql.=' GROUP BY mk.id,mk.nama ORDER BY mk.tingkat,mk.urutan,mk.nama';
        } else {
            $sql="SELECT s.id,s.nis,s.nama,mk.nama kelas,COUNT(DISTINCT sa.id) jumlah_sesi,COALESCE(SUM(a.status='H'),0) H,COALESCE(SUM(a.status='T'),0) T,COALESCE(SUM(a.status='I'),0) I,COALESCE(SUM(a.status='S'),0) S,COALESCE(SUM(a.status='A'),0) A,COALESCE(SUM(CASE WHEN sa.status_sesi='CLOSED' AND a.id IS NULL THEN 1 ELSE 0 END),0) A_AUTO FROM siswa s JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN sesi_absensi sa ON sa.tahun_pelajaran_id=? AND sa.tanggal BETWEEN ? AND ? JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id AND ja.kategori=? LEFT JOIN absensi a ON a.sesi_id=sa.id AND a.siswa_id=s.id WHERE s.status='aktif'";
            $p=$baseParams; if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;} if($siswaId){$sql.=' AND s.id=?';$p[]=$siswaId;} $sql.=' GROUP BY s.id,s.nis,s.nama,mk.nama ORDER BY mk.nama,s.nama';
        }
        if($mode!=='sholat'){
            $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
            foreach($rows as &$r){$r['H']=(int)$r['H'];$r['T']=(int)$r['T'];$r['I']=(int)$r['I'];$r['S']=(int)$r['S'];$r['A']=(int)$r['A'];$r['A_AUTO']=(int)($r['A_AUTO']??0);$r['A_FINAL']=$r['A']+$r['A_AUTO'];$r['expected']=(int)$r['jumlah_sesi']*(($mode==='kelas')?(int)$r['jumlah_siswa']:1);$r['B']=max(0,$r['expected']-($r['H']+$r['T']+$r['I']+$r['S']+$r['A_FINAL']));$r['pct']=$r['expected']?($r['H']+$r['T'])/$r['expected']*100:0;}unset($r);
        }
    }
    $subtitle='Periode '.e($mulai).' s.d. '.e($selesai);
}

if ($mode==='izin') {
    $title='Laporan Izin Siswa';
    $sql="SELECT iz.tanggal,iz.jenis,iz.alasan,iz.keterangan,iz.status,s.nis,s.nisn,s.nama,mk.nama kelas,u.nama penyetuju FROM izin_siswa iz JOIN siswa s ON s.id=iz.siswa_id JOIN master_kelas mk ON mk.id=iz.kelas_id LEFT JOIN users u ON u.id=iz.disetujui_oleh WHERE iz.tahun_pelajaran_id=? AND iz.tanggal BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai]; if($kelasId){$sql.=' AND iz.kelas_id=?';$p[]=$kelasId;} if($siswaId){$sql.=' AND iz.siswa_id=?';$p[]=$siswaId;} $sql.=' ORDER BY iz.tanggal DESC,iz.id DESC';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();$stats=['total'=>count($rows)];
}
if ($mode==='pelanggaran') {
    $title='Laporan Pelanggaran & Poin';
    $sql="SELECT ps.tanggal,s.nis,s.nisn,s.nama,mk.nama kelas,jp.kode,jp.nama jenis_pelanggaran,ps.poin,ps.status,g.nama petugas FROM pelanggaran_siswa ps JOIN siswa s ON s.id=ps.siswa_id JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN guru g ON g.id=ps.petugas_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=ps.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE ps.tahun_pelajaran_id=? AND ps.tanggal BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai];if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;}if($siswaId){$sql.=' AND ps.siswa_id=?';$p[]=$siswaId;}$sql.=' ORDER BY ps.tanggal DESC,ps.id DESC';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
    $totalPoin=array_sum(array_map(fn($r)=>(int)$r['poin'],$rows));$stats=['total'=>count($rows),'poin'=>$totalPoin];
}
if ($mode==='pembinaan') {
    $title='Laporan Pembinaan Siswa';
    $sql="SELECT p.tanggal,p.jenis,p.status,p.melibatkan_ortu,p.masalah,p.hasil,p.tindak_lanjut,s.nis,s.nisn,s.nama,mk.nama kelas,g.nama petugas FROM pembinaan_siswa p JOIN siswa s ON s.id=p.siswa_id LEFT JOIN guru g ON g.id=p.petugas_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=p.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE p.tahun_pelajaran_id=? AND p.tanggal BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai];if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;}if($siswaId){$sql.=' AND p.siswa_id=?';$p[]=$siswaId;}$sql.=' ORDER BY p.tanggal DESC,p.id DESC';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();$stats=['total'=>count($rows),'selesai'=>0,'proses'=>0];foreach($rows as $r){$stats[$r['status']] = ($stats[$r['status']]??0)+1;}
}
if ($mode==='layanan_bk') {
    $title='Laporan Layanan BK';
    $sql="SELECT bl.id,bl.tanggal,bl.nomor_antrian,bl.waktu_masuk,bl.waktu_mulai,bl.waktu_selesai,
                  bl.status,bl.jenis_layanan,bl.topik,bl.hasil,bl.tindak_lanjut,bl.melibatkan_ortu,
                  bl.metode_masuk,s.nis,s.nisn,s.nama,mk.nama kelas,g.nama petugas
           FROM bk_layanan bl
           JOIN siswa s ON s.id=bl.siswa_id
           LEFT JOIN master_kelas mk ON mk.id=bl.kelas_id
           LEFT JOIN guru g ON g.id=bl.petugas_id
           WHERE bl.tahun_pelajaran_id=? AND bl.tanggal BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai];
    if($kelasId){$sql.=' AND bl.kelas_id=?';$p[]=$kelasId;}
    if($siswaId){$sql.=' AND bl.siswa_id=?';$p[]=$siswaId;}
    if(user_role()==='guru_bk'){
        $gid=bk_current_guru_id($pdo);
        $sql.=' AND bl.petugas_id=?';$p[]=$gid;
    }
    $sql.=' ORDER BY bl.tanggal DESC,bl.nomor_antrian DESC,bl.id DESC';
    $st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();
    $stats=['total'=>count($rows),'menunggu'=>0,'dilayani'=>0,'selesai'=>0,'batal'=>0];
    foreach($rows as $r){$stats[$r['status']]=($stats[$r['status']]??0)+1;}
}
if ($mode==='konsultasi') {
    $title='Laporan Konsultasi BK';
    $sql="SELECT k.tanggal,k.waktu,k.jenis,k.topik,k.status,k.melibatkan_ortu,k.hasil,k.kesepakatan,k.tindak_lanjut,s.nis,s.nisn,s.nama,mk.nama kelas,g.nama petugas FROM konsultasi_bk k JOIN siswa s ON s.id=k.siswa_id LEFT JOIN guru g ON g.id=k.petugas_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=k.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE k.tahun_pelajaran_id=? AND k.tanggal BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai];if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;}if($siswaId){$sql.=' AND k.siswa_id=?';$p[]=$siswaId;}$sql.=' ORDER BY k.tanggal DESC,k.id DESC';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();$stats=['total'=>count($rows)];
}
if ($mode==='home_visit') {
    $title='Laporan Home Visit';
    $sql="SELECT COALESCE(hv.tanggal_pelaksanaan,hv.tanggal_rencana) tanggal,hv.status,hv.alasan,hv.tujuan,hv.hasil,hv.tindak_lanjut,s.nis,s.nisn,s.nama,g.nama petugas FROM home_visit hv JOIN siswa s ON s.id=hv.siswa_id LEFT JOIN guru g ON g.id=hv.petugas_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=hv.tahun_pelajaran_id AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE hv.tahun_pelajaran_id=? AND COALESCE(hv.tanggal_pelaksanaan,hv.tanggal_rencana) BETWEEN ? AND ?";
    $p=[$tpId,$mulai,$selesai];if($kelasId){$sql.=' AND mk.id=?';$p[]=$kelasId;}if($siswaId){$sql.=' AND hv.siswa_id=?';$p[]=$siswaId;}$sql.=' ORDER BY tanggal DESC,hv.id DESC';$st=$pdo->prepare($sql);$st->execute($p);$rows=$st->fetchAll();$stats=['total'=>count($rows)];
}

$page_title=$title;
if(!$cetak){require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';}
$queryBase=$_GET;$queryBase['cetak']=1;
?>
<?php if($cetak): ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> - <?=e($profil['nama_sekolah'])?></title><style>body{font-family:Arial,sans-serif;color:#111;margin:24px;font-size:11px}h1{font-size:18px;margin:0 0 4px}.school{text-align:center;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:5px;vertical-align:top}th{background:#eee}.meta{margin:8px 0 12px}.report-bk-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:0 0 14px}.report-bk-card{border:1px solid #dbe3ec;border-radius:10px;padding:10px;background:#fff}.report-bk-card span{display:block;font-size:10px;color:#64748b;font-weight:700}.report-bk-card strong{font-size:18px}.muted{color:#555}@media print{.no-print{display:none}}</style></head><body><div class="school"><?php if(!empty($profil['logo'])):?><img alt="Logo sekolah" src="<?=e(school_logo_url($pdo))?>" style="height:50px"><br><?php endif;?><strong style="font-size:16px"><?=e($profil['nama_sekolah'])?></strong><br><?=e((string)($profil['alamat']??''))?></div><?php endif; ?>
<main class="<?= $cetak?'':'main laporan-page' ?>">
<?php if(!$cetak): ?><header class="topbar"><div class="topbar-title">Pusat Laporan</div><div class="topbar-user"><?=e(user_name())?></div></header><?php endif; ?>
<section class="<?= $cetak?'':'content' ?>">
<?php if(!$cetak): ?><div class="page-header"><div><h1><i class="bi bi-bar-chart-fill"></i> Pusat Laporan</h1><p>Satu tempat untuk rekap administrasi berdasarkan Tahun Pelajaran dan periode.</p></div><a class="btn btn-secondary" target="_blank" href="?<?=e(http_build_query($queryBase))?>"><i class="bi bi-printer"></i> Cetak</a></div>
<div class="dashboard-table-card laporan-filter-card">
<div class="laporan-filter-title">
    <div>
        <strong><i class="bi bi-sliders"></i> Pilih Laporan</strong>
        <span>Filter dibuat ringkas agar ruang halaman tetap efisien.</span>
    </div>
</div>
<form method="get" class="laporan-filter-grid">
    <div class="laporan-filter-field laporan-filter-mode">
        <label for="laporan-mode">Jenis laporan</label>
        <select id="laporan-mode" name="mode" class="form-control">
            <option value="ringkasan" <?= $mode==='ringkasan'?'selected':'' ?>>Ringkasan Sesi</option>
            <option value="kelas" <?= $mode==='kelas'?'selected':'' ?>>Rekap Kehadiran Kelas</option>
            <option value="siswa" <?= $mode==='siswa'?'selected':'' ?>>Rekap Kehadiran Siswa</option>
            <option value="sholat" <?= $mode==='sholat'?'selected':'' ?>>Rekap Sholat</option>
            <option value="izin" <?= $mode==='izin'?'selected':'' ?>>Izin Siswa</option>
            <option value="pelanggaran" <?= $mode==='pelanggaran'?'selected':'' ?>>Pelanggaran &amp; Poin</option>
            <option value="pembinaan" <?= $mode==='pembinaan'?'selected':'' ?>>Pembinaan</option>
            <?php if(in_array('layanan_bk',$roleAllowedModes,true)): ?><option value="layanan_bk" <?= $mode==='layanan_bk'?'selected':'' ?>>Layanan BK</option><?php endif; ?>
            <?php if(in_array('konsultasi',$roleAllowedModes,true)): ?><option value="konsultasi" <?= $mode==='konsultasi'?'selected':'' ?>>Konsultasi BK</option><?php endif; ?>
            <?php if(in_array('home_visit',$roleAllowedModes,true)): ?><option value="home_visit" <?= $mode==='home_visit'?'selected':'' ?>>Home Visit</option><?php endif; ?>
        </select>
    </div>
    <div class="laporan-filter-field">
        <label for="laporan-tapel">Tahun Pelajaran</label>
        <select id="laporan-tapel" name="tahun_pelajaran_id" class="form-control">
            <option value="">Tahun Pelajaran</option>
            <?php foreach($tpRows as $t):?>
                <option value="<?=$t['id']?>" <?=$tpId===(int)$t['id']?'selected':''?>><?=e($t['nama'])?><?=((int)$t['aktif']===1?' · Aktif':'')?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="laporan-filter-field laporan-filter-date">
        <label for="laporan-mulai">Dari</label>
        <input id="laporan-mulai" type="date" name="mulai" value="<?=e($mulai)?>" class="form-control">
    </div>
    <div class="laporan-filter-field laporan-filter-date">
        <label for="laporan-selesai">Sampai</label>
        <input id="laporan-selesai" type="date" name="selesai" value="<?=e($selesai)?>" class="form-control">
    </div>
    <?php if($mode==='sholat'):?>
    <div class="laporan-filter-field">
        <label for="laporan-sholat">Jenis Sholat</label>
        <select id="laporan-sholat" name="sholat_id" class="form-control">
            <option value="0">Semua Sholat Aktif</option>
            <?php foreach($sholatRows as $j):?>
                <option value="<?=$j['id']?>" <?=$sholatId===(int)$j['id']?'selected':''?>><?=e($j['nama'])?></option>
            <?php endforeach;?>
        </select>
    </div>
    <?php endif;?>
    <div class="laporan-filter-field">
        <label for="laporan-kelas-filter">Kelas</label>
        <select id="laporan-kelas-filter" name="kelas_id" class="form-control">
            <option value="0">Semua Kelas</option>
            <?php foreach($kelasRows as $k):?>
                <option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option>
            <?php endforeach;?>
        </select>
    </div>
    <div class="laporan-filter-field laporan-filter-student">
        <label>Siswa</label>
        <div class="student-picker" data-student-picker data-items="<?=e(json_encode($siswaRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>">
            <input type="hidden" name="siswa_id" data-student-value value="<?=e((string)$siswaId)?>">
            <div class="student-picker-toolbar">
                <select class="form-control" data-student-class aria-hidden="true" tabindex="-1" style="display:none">
                    <option value="">Pilih Kelas Terlebih Dahulu</option>
                    <?php foreach($kelasRows as $k):?>
                        <option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option>
                    <?php endforeach;?>
                </select>
                <input class="form-control" type="search" data-student-search placeholder="Cari nama/NIS/NISN..." autocomplete="off">
            </div>
            <div data-student-selected></div>
            <div class="student-picker-results" data-student-results></div>
            <div class="student-picker-empty" data-student-empty hidden>Tidak ada siswa.</div>
        </div>
    </div>
    <div class="laporan-filter-actions">
        <button class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Tampilkan</button>
    </div>
</form>
</div><?php endif; ?>
<script>(function(){const k=document.getElementById('laporan-kelas-filter');const p=document.querySelector('[data-student-picker]');const c=p&&p.querySelector('[data-student-class]');if(!k||!c)return;function sync(){c.value=k.value||'';c.dispatchEvent(new Event('change',{bubbles:true}));}k.addEventListener('change',sync);sync();})();</script>
<div class="dashboard-table-card"><div class="section-title"><div><h2><?=e($title)?></h2><p>Tahun Pelajaran <strong><?=e((string)($tp['nama']??'-'))?></strong> · Periode <?=e($mulai)?> s.d. <?=e($selesai)?></p></div></div><?php if(in_array($mode,['kelas','siswa'],true)): ?><div class="info-box" style="margin:0 0 14px">Status <strong>Alpa</strong> pada laporan mencakup siswa yang tidak memiliki record pada sesi yang sudah <strong>CLOSED</strong>. Selama sesi belum selesai, data tetap dapat diverifikasi oleh guru piket.</div><?php endif; ?>
<?php if(!$cetak): ?>
<div class="laporan-support">
    <div class="laporan-support-head">
        <div>
            <strong><i class="bi bi-grid-1x2-fill"></i> Laporan Pendukung</strong>
            <span>Laporan khusus modul tetap tersedia tanpa memenuhi filter utama.</span>
        </div>
    </div>
    <div class="laporan-support-grid">
        <?php if(in_array(user_role(), ['admin','tu','kepala_sekolah'], true)): ?>
        <a class="laporan-support-card support-perpus" href="<?=BASE_URL?>perpustakaan/laporan.php">
            <span class="support-icon"><i class="bi bi-book-fill"></i></span>
            <span><strong>Perpustakaan</strong><small>Buku, peminjaman, pengembalian &amp; kondisi koleksi.</small></span>
            <i class="bi bi-arrow-right-short"></i>
        </a>
        <?php endif; ?>
        <?php if(sidebar_can_module('uks')): ?>
        <a class="laporan-support-card support-uks" href="<?=BASE_URL?>uks/laporan.php">
            <span class="support-icon"><i class="bi bi-heart-pulse-fill"></i></span>
            <span><strong>UKS</strong><small>Kunjungan, obat, rujukan &amp; layanan kesehatan.</small></span>
            <i class="bi bi-arrow-right-short"></i>
        </a>
        <?php endif; ?>
        <?php if(in_array(user_role(), ['admin','tu','kepala_sekolah'], true)): ?>
        <a class="laporan-support-card support-ekstra" href="<?=BASE_URL?>ekstrakurikuler/index.php">
            <span class="support-icon"><i class="bi bi-trophy-fill"></i></span>
            <span><strong>Ekstrakurikuler</strong><small>Daftar ekstra dan rekap peserta per kegiatan.</small></span>
            <i class="bi bi-arrow-right-short"></i>
        </a>
        <?php endif; ?>
        <?php if(user_role()==='guru' && sidebar_can_module('wali_kelas')): ?>
        <a class="laporan-support-card support-wali" href="<?=BASE_URL?>wali_kelas/index.php">
            <span class="support-icon"><i class="bi bi-people-fill"></i></span>
            <span><strong>Rekap Wali Kelas</strong><small>Rekap kehadiran siswa berdasarkan kelas binaan.</small></span>
            <i class="bi bi-arrow-right-short"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if($mode==='ringkasan'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Sesi</th><th>Konteks</th><th>H</th><th>T</th><th>I</th><th>S</th><th>A</th><th>Tercatat</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="9" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td><strong><?=e($r['nama_sesi'])?></strong><br><?=e(substr((string)$r['jam_mulai'],0,5))?>–<?=e(substr((string)$r['jam_selesai'],0,5))?></td><td><?=e($r['kategori']==='sholat'?'Sholat '.($r['sholat_nama']??''):'Kehadiran')?></td><td><?=$r['H']?></td><td><?=$r['T']?></td><td><?=$r['I']?></td><td><?=$r['S']?></td><td><?=$r['A']?></td><td><?=$r['tercatat']?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='sholat'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Sesi</th><th>Sudah</th><th>Belum</th><th>Berhalangan</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['nis'])?></td><td><strong><?=e($r['nama'])?></strong></td><td><?=e($r['kelas'])?></td><td><?=number_format((int)$r['jumlah_sesi'])?></td><td><?=$r['sudah']?></td><td><?=$r['belum']?></td><td><?=$r['berhalangan']?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif(in_array($mode,['kelas','siswa'],true)): ?><div class="table-responsive"><table class="data-table"><thead><tr><?php if($mode==='kelas'):?><th>Kelas</th><th>Siswa</th><?php else:?><th>NIS</th><th>Nama</th><th>Kelas</th><?php endif;?><th>Sesi</th><th>H</th><th>T</th><th>I</th><th>S</th><th>A</th><th>Belum</th><th>H/T %</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="11" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><?php if($mode==='kelas'):?><td><strong><?=e($r['nama'])?></strong></td><td><?=number_format((int)$r['jumlah_siswa'])?></td><?php else:?><td><?=e($r['nis'])?></td><td><strong><?=e($r['nama'])?></strong></td><td><?=e($r['kelas'])?></td><?php endif;?><td><?=number_format((int)$r['jumlah_sesi'])?></td><td><?=$r['H']?></td><td><?=$r['T']?></td><td><?=$r['I']?></td><td><?=$r['S']?></td><td><?=$r['A_FINAL']?></td><td><?=$r['B']?></td><td><?=number_format((float)$r['pct'],1)?>%</td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='izin'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Alasan</th><th>Status</th><th>Penyetuju</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td><strong><?=e($r['nama'])?></strong><br><small><?=e($r['nis'])?></small></td><td><?=e($r['kelas'])?></td><td><?=e($r['jenis'])?></td><td><?=e($r['alasan'])?></td><td><?=e($r['status'])?></td><td><?=e((string)($r['penyetuju']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='pelanggaran'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Pelanggaran</th><th>Poin</th><th>Status</th><th>Petugas</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td><strong><?=e($r['nama'])?></strong><br><?=e($r['nis'])?></td><td><?=e((string)$r['kelas'])?></td><td><?=e($r['kode'])?> · <?=e($r['jenis_pelanggaran'])?></td><td><strong><?=number_format((int)$r['poin'])?></strong></td><td><?=e($r['status'])?></td><td><?=e((string)($r['petugas']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='pembinaan'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Status</th><th>Ortu</th><th>Hasil</th><th>Petugas</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="8" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td><strong><?=e($r['nama'])?></strong><br><?=e($r['nis'])?></td><td><?=e((string)$r['kelas'])?></td><td><?=e($r['jenis'])?></td><td><?=e($r['status'])?></td><td><?=((int)$r['melibatkan_ortu']===1?'Ya':'Tidak')?></td><td><?=e((string)($r['hasil']??'-'))?></td><td><?=e((string)($r['petugas']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='layanan_bk'): ?><div class="report-bk-summary"><div class="report-bk-card"><span>Total</span><strong><?=number_format((int)$stats['total'])?></strong></div><div class="report-bk-card"><span>Menunggu</span><strong><?=number_format((int)$stats['menunggu'])?></strong></div><div class="report-bk-card"><span>Dilayani</span><strong><?=number_format((int)$stats['dilayani'])?></strong></div><div class="report-bk-card"><span>Selesai</span><strong><?=number_format((int)$stats['selesai'])?></strong></div><div class="report-bk-card"><span>Batal</span><strong><?=number_format((int)$stats['batal'])?></strong></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>No.</th><th>Siswa</th><th>Kelas</th><th>Keperluan</th><th>Petugas</th><th>Status</th><th>Masuk</th><th>Selesai</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="9" class="empty-state">Tidak ada data layanan BK.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td>#<?=e((string)$r['nomor_antrian'])?></td><td><strong><?=e($r['nama'])?></strong><br><small><?=e($r['nis'])?><?=!empty($r['nisn'])?' · '.e($r['nisn']):''?></small></td><td><?=e((string)($r['kelas']??'-'))?></td><td><?=e((string)($r['jenis_layanan']??'-'))?><br><small><?=e((string)($r['topik']??''))?></small></td><td><?=e((string)($r['petugas']??'-'))?></td><td><?=e(strtoupper((string)$r['status']))?></td><td><?=e(substr((string)$r['waktu_masuk'],11,5))?> WIB</td><td><?=!empty($r['waktu_selesai'])?e(substr((string)$r['waktu_selesai'],11,5)).' WIB':'-'?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php elseif($mode==='konsultasi'): ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Topik</th><th>Status</th><th>Ortu</th><th>Petugas</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="8" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?><br><small><?=e(substr((string)($r['waktu']??''),0,5))?></small></td><td><strong><?=e($r['nama'])?></strong><br><?=e($r['nis'])?></td><td><?=e((string)$r['kelas'])?></td><td><?=e($r['jenis'])?></td><td><?=e($r['topik'])?></td><td><?=e($r['status'])?></td><td><?=((int)$r['melibatkan_ortu']===1?'Ya':'Tidak')?></td><td><?=e((string)($r['petugas']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div>
<?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Status</th><th>Alasan/Tujuan</th><th>Hasil</th><th>Tindak Lanjut</th><th>Petugas</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="8" class="empty-state">Tidak ada data.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['tanggal'])?></td><td><strong><?=e($r['nama'])?></strong><br><?=e($r['nis'])?></td><td><?=e((string)($r['kelas']??'-'))?></td><td><?=e($r['status'])?></td><td><?=e((string)$r['alasan'])?><br><small><?=e((string)($r['tujuan']??''))?></small></td><td><?=e((string)($r['hasil']??'-'))?></td><td><?=e((string)($r['tindak_lanjut']??'-'))?></td><td><?=e((string)($r['petugas']??'-'))?></td></tr><?php endforeach;endif;?></tbody></table></div><?php endif; ?>
<?php if(!$cetak): ?><div style="margin-top:12px;color:#64748b;font-size:12px"><i class="bi bi-info-circle"></i> Laporan mengikuti Tahun Pelajaran yang dipilih. Data lama tidak dicampur dengan Tahun Pelajaran lain.</div><?php endif; ?></div>
</section></main>
<?php if(!$cetak): require_once __DIR__.'/../layouts/footer.php'; else: ?><p class="muted" style="margin-top:12px">Dicetak <?=date('d-m-Y H:i')?> · <?=e($title)?></p><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));</script></body></html><?php endif; ?>
