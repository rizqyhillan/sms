<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/school_profile.php';
require_once __DIR__.'/../includes/auto_sessions.php';

$profil=school_profile($pdo);
$today=(new DateTimeImmutable('now'))->format('Y-m-d');
$now=(new DateTimeImmutable('now'))->format('H:i:s');

function layar_e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }

$sessions=auto_sessions_today_readonly($pdo);

/*
 * Resolver kegiatan publik:
 * - OPEN/aktif sholat mendapat prioritas ketika waktunya berlangsung.
 *   Ini penting karena layar publik harus berpindah dari kehadiran ke sholat
 *   tanpa F5/manual intervention.
 * - Jika tidak ada kegiatan aktif, tampilkan kegiatan berikutnya berdasarkan
 *   waktu mulai paling dekat. Jika waktunya sama, sholat tetap diprioritaskan.
 */
$activeCandidates=[];$nextCandidates=[];
foreach($sessions as $candidate){
    if(($candidate['status_sesi']??'')==='OPEN' || ($candidate['status']??'')==='aktif'){
        if((string)$candidate['jam_mulai']<=$now && ($candidate['jam_selesai']===null || (string)$candidate['jam_selesai']>$now)){
            $activeCandidates[]=$candidate;
            continue;
        }
    }
    if(($candidate['status_sesi']??'')==='DRAFT' || ($candidate['status']??'')==='draft'){
        if((string)$candidate['jam_mulai']>$now){
            $nextCandidates[]=$candidate;
        }
    }
}
$sessionPriority=function(array $row): int { return (($row['kategori']??'')==='sholat') ? 0 : 1; };
if($activeCandidates){
    usort($activeCandidates,function(array $a,array $b) use ($sessionPriority): int {
        $p=$sessionPriority($a)<=>$sessionPriority($b);
        if($p!==0)return $p;
        return strcmp((string)$a['jam_mulai'],(string)$b['jam_mulai']);
    });
    $active=$activeCandidates[0];
}else{
    $active=null;
}
if($nextCandidates){
    usort($nextCandidates,function(array $a,array $b) use ($sessionPriority): int {
        $p=strcmp((string)$a['jam_mulai'],(string)$b['jam_mulai']);
        if($p!==0)return $p;
        $p=$sessionPriority($a)<=>$sessionPriority($b);
        if($p!==0)return $p;
        return ((int)$a['id'])<=>((int)$b['id']);
    });
    $next=$nextCandidates[0];
}else{
    $next=null;
}
$sesi=$active;

// Mode layar publik: REKAP saat tidak ada sesi aktif, KEHADIRAN saat sesi hadir OPEN,
// SHOLAT saat sesi sholat OPEN. Pergantian mengikuti jam sesi dan refresh otomatis.
$displayMode = $sesi ? ((($sesi['kategori']??'')==='sholat') ? 'sholat' : 'kehadiran') : 'rekap';

/* Enrich the selected session with display metadata.
 * auto_sessions_today() intentionally returns the transaction/session row;
 * layar needs the category and sholat name for presentation only.
 */
if($sesi){
    $stLayar=$pdo->prepare(
        "SELECT s.*, ja.kategori, ja.nama AS jenis_nama, js.nama AS sholat_nama
         FROM sesi_absensi s
         JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
         LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id
         WHERE s.id=? LIMIT 1"
    );
    $stLayar->execute([(int)$sesi['id']]);
    $detailLayar=$stLayar->fetch();
    if($detailLayar) $sesi=array_merge($sesi,$detailLayar);
}
/*
 * Saat tidak ada sesi OPEN, layar publik tidak masuk ke empty-state.
 * Layar beralih menjadi dashboard ringkas hari ini: kiri kehadiran, kanan sholat.
 * Data tetap bersumber dari sesi/transaksi nyata agar layar tidak menampilkan angka dummy.
 */
$idleAttendance = [
    'session' => null,
    'total' => 0,
    'H' => 0, 'T' => 0, 'I' => 0, 'S' => 0, 'A' => 0, 'B' => 0,
    'kelas' => []
];
$idleSholat = [
    'total' => 0,
    'sessions' => []
];

if (!$sesi) {
    // Sesi kehadiran terakhir hari ini.
    $q = $pdo->prepare(
        "SELECT s.*
         FROM sesi_absensi s
         JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
         WHERE s.tanggal=? AND s.jenis_sholat_id IS NULL
           AND ja.kategori='kehadiran'
         ORDER BY s.id DESC LIMIT 1"
    );
    $q->execute([$today]);
    $idleAttendance['session'] = $q->fetch() ?: null;

    // Jumlah siswa aktif pada tahun pelajaran aktif.
    $q = $pdo->query(
        "SELECT COUNT(*) FROM siswa s
         WHERE s.status='aktif'"
    );
    $idleAttendance['total'] = (int)$q->fetchColumn();

    if ($idleAttendance['session']) {
        $sid=(int)$idleAttendance['session']['id'];
        $q=$pdo->prepare(
            "SELECT COALESCE(a.status,'B') status, COUNT(*) n
             FROM siswa s
             LEFT JOIN absensi a ON a.siswa_id=s.id AND a.sesi_id=?
             WHERE s.status='aktif'
             GROUP BY COALESCE(a.status,'B')"
        );
        $q->execute([$sid]);
        foreach($q as $r){
            $k=(string)$r['status'];
            if(array_key_exists($k,$idleAttendance)) $idleAttendance[$k]=(int)$r['n'];
        }
        $q=$pdo->prepare(
            "SELECT COALESCE(mk.nama,'Belum Ditempatkan') kelas,
                    COUNT(*) total,
                    SUM(a.status='H') H,
                    SUM(a.status='T') T,
                    SUM(a.status='I') I,
                    SUM(a.status='S') S,
                    SUM(a.status='A') A,
                    SUM(a.id IS NULL) B
             FROM siswa s
             LEFT JOIN siswa_kelas sk ON sk.id=(
                 SELECT sk2.id FROM siswa_kelas sk2
                 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id
                 WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1
                 ORDER BY sk2.id DESC LIMIT 1
             )
             LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
             LEFT JOIN absensi a ON a.siswa_id=s.id AND a.sesi_id=?
             WHERE s.status='aktif'
             GROUP BY mk.id,mk.nama
             ORDER BY COALESCE(mk.urutan,9999),mk.nama"
        );
        $q->execute([$sid]);
        $idleAttendance['kelas']=$q->fetchAll();
    } else {
        $idleAttendance['B']=$idleAttendance['total'];
    }

    // Semua sesi sholat hari ini. Rekap per sesi agar Dzuhur/Ashar tidak tercampur.
    $q=$pdo->prepare(
        "SELECT s.id,s.nama_sesi,s.jam_mulai,s.jam_selesai,
                COALESCE(js.nama,'Sholat') sholat_nama,
                COUNT(DISTINCT ash.siswa_id) sudah,
                COUNT(DISTINCT CASE WHEN ash.status='BERHALANGAN' THEN ash.siswa_id END) berhalangan
         FROM sesi_absensi s
         LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id
         LEFT JOIN absensi_sholat ash ON ash.sesi_id=s.id
         JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
         WHERE s.tanggal=? AND s.jenis_sholat_id IS NOT NULL
           AND ja.kategori='sholat'
         GROUP BY s.id,s.nama_sesi,s.jam_mulai,s.jam_selesai,js.nama
         ORDER BY s.jam_mulai,s.id"
    );
    $q->execute([$today]);
    $idleSholat['sessions']=$q->fetchAll();
    foreach($idleSholat['sessions'] as &$sr){
        $sr['sudah']=(int)$sr['sudah'];
        $sr['berhalangan']=(int)$sr['berhalangan'];
        $sr['belum']=max(0,$idleAttendance['total']-$sr['sudah']);
    }
    unset($sr);
}

$isSholat=(bool)$sesi && (($sesi['kategori']??'')==='sholat');
$counts=$isSholat ? ['SUDAH'=>0,'BERHALANGAN'=>0,'B'=>0] : ['H'=>0,'T'=>0,'I'=>0,'S'=>0,'A'=>0,'B'=>0];
$per=[];$last=null;$recent=[];
if($sesi){
 if($isSholat){
  $q=$pdo->prepare("SELECT COALESCE(ash.status,'B') status,COUNT(*) n FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN absensi_sholat ash ON ash.sesi_id=? AND ash.siswa_id=s.id WHERE s.status='aktif' GROUP BY COALESCE(ash.status,'B')");
  $q->execute([(int)$sesi['id']]);foreach($q as $r){if(isset($counts[$r['status']]))$counts[$r['status']]=(int)$r['n'];}
  $q=$pdo->prepare("SELECT COALESCE(mk.nama,'Belum Ditempatkan') kelas,COUNT(*) total,SUM(ash.status='SUDAH') SUDAH,SUM(ash.status='BERHALANGAN') BERHALANGAN,SUM(ash.id IS NULL) B FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN absensi_sholat ash ON ash.sesi_id=? AND ash.siswa_id=s.id WHERE s.status='aktif' GROUP BY mk.id,mk.nama ORDER BY COALESCE(mk.urutan,9999),mk.nama");
  $q->execute([(int)$sesi['id']]);$per=$q->fetchAll();
  $q=$pdo->prepare("SELECT s.nama,COALESCE(mk.nama,'Belum Ditempatkan') kelas,ash.status,DATE_FORMAT(ash.waktu_scan,'%H:%i:%s') AS waktu_scan FROM absensi_sholat ash JOIN siswa s ON s.id=ash.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE ash.sesi_id=? ORDER BY ash.waktu_scan DESC LIMIT 1");
  $q->execute([(int)$sesi['id']]);$last=$q->fetch();
  $q=$pdo->prepare("SELECT s.nama,COALESCE(mk.nama,'Belum Ditempatkan') kelas,ash.status,DATE_FORMAT(ash.waktu_scan,'%H:%i:%s') waktu FROM absensi_sholat ash JOIN siswa s ON s.id=ash.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE ash.sesi_id=? ORDER BY ash.waktu_scan DESC LIMIT 10");
 } else {
  $q=$pdo->prepare("SELECT COALESCE(a.status,'B') status,COUNT(*) n FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN absensi a ON a.sesi_id=? AND a.siswa_id=s.id WHERE s.status='aktif' GROUP BY COALESCE(a.status,'B')");$q->execute([(int)$sesi['id']]);foreach($q as $r){if(isset($counts[$r['status']]))$counts[$r['status']]=(int)$r['n'];}
  $q=$pdo->prepare("SELECT COALESCE(mk.nama,'Belum Ditempatkan') kelas,COUNT(*) total,SUM(a.status='H') H,SUM(a.status='T') T,SUM(a.status='I') I,SUM(a.status='S') S,SUM(a.status='A') A,SUM(a.id IS NULL) B FROM siswa s LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id LEFT JOIN absensi a ON a.sesi_id=? AND a.siswa_id=s.id WHERE s.status='aktif' GROUP BY mk.id,mk.nama ORDER BY COALESCE(mk.urutan,9999),mk.nama");$q->execute([(int)$sesi['id']]);$per=$q->fetchAll();
  $q=$pdo->prepare("SELECT s.nama,mk.nama kelas,sk.nomor_urut,a.status,a.waktu_scan FROM absensi a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE a.sesi_id=? ORDER BY a.waktu_scan DESC LIMIT 1");$q->execute([(int)$sesi['id']]);$last=$q->fetch();
  $q=$pdo->prepare("SELECT s.nama,COALESCE(mk.nama,'Belum Ditempatkan') kelas,a.status,DATE_FORMAT(a.waktu_scan,'%H:%i:%s') waktu FROM absensi a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE a.sesi_id=? ORDER BY a.waktu_scan DESC LIMIT 10");
 }
 $q->execute([(int)$sesi['id']]);$recent=$q->fetchAll();
}

$statsItems=$isSholat
 ? [['SUDAH','Sudah','h','check-circle-fill'],['BERHALANGAN','Berhalangan','i','person-x-fill'],['B','Belum Ada Status','b','question-circle-fill']]
 : [['H','Hadir','h','check-circle-fill'],['T','Terlambat','t','clock-history'],['I','Izin','i','file-earmark-text-fill'],['S','Sakit','s','heart-pulse-fill'],['A','Alpa','a','x-circle-fill'],['B','Belum Ada Status','b','question-circle-fill']];
$logo=school_logo_url($pdo);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title><?=layar_e($profil['nama_sekolah'])?> · Monitor Absensi</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<?php if($logo):?><link rel="icon" href="<?=layar_e($logo)?>"><?php endif;?>
<style>
@font-face{font-family:Poppins;src:url('../assets/fonts/poppins/Poppins-400.woff2') format('woff2');font-weight:400;font-display:swap}
@font-face{font-family:Poppins;src:url('../assets/fonts/poppins/Poppins-600.woff2') format('woff2');font-weight:600;font-display:swap}
@font-face{font-family:Poppins;src:url('../assets/fonts/poppins/Poppins-700.woff2') format('woff2');font-weight:700;font-display:swap}
@font-face{font-family:Poppins;src:url('../assets/fonts/poppins/Poppins-800.woff2') format('woff2');font-weight:800;font-display:swap}

:root{
 --navy:#07152d;--navy2:#0b2442;--blue:#2563eb;--blue2:#1d4ed8;--cyan:#06b6d4;
 --green:#16a34a;--yellow:#f59e0b;--red:#ef4444;--purple:#7c3aed;
 --white:#fff;--ink:#13233d;--muted:#64748b;--line:#dce7f3;--soft:#f4f8fc;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Poppins,system-ui,sans-serif}
body{
 color:var(--ink);
 background:
   radial-gradient(circle at 8% 0%,rgba(37,99,235,.20),transparent 25%),
   radial-gradient(circle at 92% 12%,rgba(6,182,212,.18),transparent 27%),
   linear-gradient(180deg,#eaf3fc 0%,#f8fbff 48%,#eef5fb 100%);
 padding:18px;
}
.wrap{max-width:1840px;margin:0 auto}
.topbar{
 display:flex;align-items:center;justify-content:space-between;gap:24px;
 padding:4px 4px 18px;
}
.brand{display:flex;align-items:center;gap:15px;min-width:0}
.logo{
 width:68px;height:68px;object-fit:contain;background:#fff;border-radius:17px;padding:7px;
 box-shadow:0 10px 28px rgba(7,21,45,.16);border:1px solid #d9e7f4;
}
.school{font-size:clamp(21px,2vw,34px);font-weight:800;letter-spacing:-.025em;color:#102443;line-height:1.05}
.live{display:flex;align-items:center;gap:8px;margin-top:7px;color:#58708e;font-size:11px;font-weight:800;letter-spacing:.12em}
.live-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.14)}
.live-badge{display:inline-flex;align-items:center;gap:8px;margin-top:9px;padding:6px 12px 6px 9px;border-radius:999px;background:#ef1010;color:#fff;box-shadow:0 6px 16px rgba(220,38,38,.22);font-size:10px;font-weight:700;letter-spacing:.04em}
.live-badge strong{font-size:12px;letter-spacing:.08em}.live-badge-dot{width:9px;height:9px;border-radius:50%;background:#fff;box-shadow:0 0 0 3px rgba(255,255,255,.18);animation:livePulse 1.5s infinite}.live-badge span:last-child{opacity:.92;font-size:9px;letter-spacing:.08em}
.screen-mode{display:inline-flex;align-items:center;gap:7px;margin-top:8px;width:max-content;padding:5px 10px;border-radius:999px;background:rgba(255,255,255,.72);border:1px solid rgba(148,163,184,.22);color:#35506f;font-size:9px;font-weight:800;letter-spacing:.06em}.screen-mode i{color:#2563eb}.mode-sholat .screen-mode i{color:#7c3aed}.mode-kehadiran .screen-mode i{color:#2563eb}.mode-rekap .screen-mode i{color:#0f766e}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.55;transform:scale(.82)}}
.top-right{display:flex;align-items:center;gap:14px}
.clock{font-size:clamp(29px,3vw,48px);font-weight:800;line-height:.95;color:#102443;letter-spacing:-.045em;text-align:right}
.date{text-align:right;margin-top:7px;color:#6c8199;font-size:11px;font-weight:700}
.fullscreen{
 border:1px solid #cbdbea;background:#fff;color:#17304f;border-radius:13px;padding:12px 15px;
 font-weight:700;cursor:pointer;box-shadow:0 7px 18px rgba(15,23,42,.08)
}
.fullscreen:hover{border-color:#8eb5df;transform:translateY(-1px)}

.hero{
 overflow:hidden;border:1px solid rgba(122,157,194,.35);border-radius:24px;
 background:#fff;box-shadow:0 18px 45px rgba(20,55,90,.12)
}
.hero-main{
 position:relative;padding:24px 26px 23px;color:#fff;
 background:linear-gradient(120deg,#173c8d 0%,#1769d1 48%,#08a4bd 100%);}
.mode-sholat .hero-main{background:linear-gradient(120deg,#3b1f76 0%,#6d28d9 50%,#2563eb 100%)}
}
.hero-main:after{
 content:"";position:absolute;right:-90px;top:-120px;width:330px;height:330px;border-radius:50%;
 background:rgba(255,255,255,.11);box-shadow:-90px 110px 0 rgba(255,255,255,.045)
}
.activity{display:flex;align-items:center;justify-content:space-between;gap:18px;position:relative;z-index:1}
.activity-left{display:flex;align-items:center;gap:15px;min-width:0}
.activity-icon{
 width:58px;height:58px;display:grid;place-items:center;border-radius:17px;
 background:rgba(255,255,255,.17);border:1px solid rgba(255,255,255,.28);
 font-size:25px;flex:0 0 auto;box-shadow:inset 0 1px 0 rgba(255,255,255,.2)
}
.activity-title{font-size:clamp(22px,2.35vw,38px);font-weight:800;line-height:1.05;letter-spacing:-.035em}
.activity-meta{margin-top:8px;color:rgba(255,255,255,.82);font-size:12px;font-weight:600}
.pill{
 display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;
 background:#dcfce7;color:#166534;font-size:11px;font-weight:800;white-space:nowrap;
 box-shadow:0 7px 18px rgba(0,0,0,.12)
}
.pill.next{background:#fef3c7;color:#92400e}
.stats{
 display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;
 padding:15px 17px 17px;background:#f8fbff
}
.stat{
 min-height:92px;border:1px solid #dce8f4;background:#fff;border-radius:16px;padding:13px;
 display:flex;align-items:center;gap:12px;box-shadow:0 7px 18px rgba(15,23,42,.045)
}
.stat-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;font-size:18px;flex:0 0 auto}
.stat b{display:block;font-size:clamp(25px,2.4vw,37px);line-height:1;font-weight:800;color:#132b4b;font-variant-numeric:tabular-nums}
.stat span{display:block;margin-top:5px;color:#6a7f96;font-size:10px;font-weight:700}
.h .stat-icon{background:#dcfce7;color:#15803d}.t .stat-icon{background:#fef3c7;color:#b45309}
.i .stat-icon{background:#dbeafe;color:#1d4ed8}.s .stat-icon{background:#ede9fe;color:#6d28d9}
.a .stat-icon{background:#fee2e2;color:#dc2626}.b .stat-icon{background:#e2e8f0;color:#475569}
.lastscan{
 display:flex;align-items:center;gap:13px;margin:0 17px 17px;padding:13px 15px;
 background:linear-gradient(135deg,#f8fbff,#eef6ff);border:1px solid #d8e6f3;border-radius:15px
}
.lastscan-icon{
 width:42px;height:42px;display:grid;place-items:center;background:#e0edff;border-radius:12px;
 color:#2563eb;flex:0 0 auto;font-size:17px
}
.lastscan-main{font-size:clamp(15px,1.35vw,21px);font-weight:700;color:#17304f}
.lastscan-meta{margin-top:3px;color:#71869d;font-size:10px;font-weight:600}
.section{
 margin-top:16px;background:#fff;border:1px solid #dce7f3;border-radius:21px;padding:17px;
 box-shadow:0 13px 32px rgba(20,55,90,.08)
}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.section-title{display:flex;align-items:center;gap:9px;margin:0;font-size:20px;font-weight:800;color:#142946}
.section-title i{color:#2563eb}.refresh-note{color:#71869d;font-size:10px;font-weight:700}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:separate;border-spacing:0;min-width:780px}
.table th,.table td{padding:10px;border-bottom:1px solid #e7eef6;text-align:center}
.table th{background:#f4f8fc;color:#667b93;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:800}
.table th:first-child,.table td:first-child{text-align:left}
.table th:first-child{border-radius:10px 0 0 10px}.table th:last-child{border-radius:0 10px 10px 0}
.table tbody tr:hover{background:#f7fbff}.table td{font-size:12px;color:#334b67;font-weight:600}
.table td:first-child strong{color:#142946;font-size:13px}
.badge{display:inline-flex;min-width:31px;justify-content:center;padding:4px 8px;border-radius:8px;font-weight:800}
.badge-h{background:#dcfce7;color:#166534}.badge-t{background:#fef3c7;color:#92400e}
.badge-i{background:#dbeafe;color:#1d4ed8}.badge-s{background:#ede9fe;color:#6d28d9}
.badge-a{background:#fee2e2;color:#b91c1c}.badge-b{background:#e2e8f0;color:#475569}
.empty{padding:38px;text-align:center;color:#6d8198;font-weight:700}
.public-activity{margin-top:16px;background:#fff;border:1px solid #dce7f3;border-radius:21px;padding:17px;box-shadow:0 13px 32px rgba(20,55,90,.08)}.public-activity-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.public-activity-title{margin:0;font-size:20px;font-weight:800;color:#142946}.public-activity-title i{color:#2563eb}.public-activity-note{font-size:10px;color:#71869d;font-weight:700}.public-activity-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.public-activity-item{display:grid;grid-template-columns:40px 1fr auto;gap:10px;align-items:center;padding:10px 12px;border:1px solid #e5edf5;border-radius:13px;background:#f9fbfe}.public-activity-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#dcfce7;color:#15803d}.public-activity-name{font-weight:800;color:#17304f;font-size:13px}.public-activity-sub{font-size:10px;color:#71869d;margin-top:2px}.public-activity-time{font-size:10px;color:#64748b;font-weight:800}.public-empty{text-align:center;padding:24px;color:#8aa0b8;font-size:12px}.recap-banner{margin-top:16px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:13px 17px;border:1px solid #cfe1f2;border-radius:16px;background:linear-gradient(90deg,#eef7ff,#f7fbff);box-shadow:0 10px 24px rgba(20,55,90,.06)}.recap-banner strong{display:flex;align-items:center;gap:8px;color:#0f4c81;font-size:12px;font-weight:800;letter-spacing:.04em}.recap-banner strong i{color:#0f766e}.recap-banner span:not(.recap-live){display:block;margin-top:3px;color:#6c8199;font-size:10px;font-weight:600}.recap-live{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-size:9px;font-weight:800;white-space:nowrap}.idle-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px}
.idle-panel{background:#fff;border:1px solid #dce7f3;border-radius:21px;padding:17px;box-shadow:0 13px 32px rgba(20,55,90,.08);min-width:0}
.idle-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:13px}.idle-panel-title{margin:0;font-size:clamp(17px,1.6vw,23px);font-weight:800;color:#142946}.idle-panel-sub{margin-top:3px;color:#71869d;font-size:10px;font-weight:600}.idle-panel-badge{white-space:nowrap;border-radius:999px;padding:7px 10px;background:#eef5ff;color:#2563eb;font-size:9px;font-weight:800}
.idle-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}.idle-stat{border:1px solid #e2eaf3;border-radius:13px;padding:11px;background:#f9fbfe}.idle-stat b{display:block;font-size:22px;line-height:1;color:#17304f}.idle-stat span{display:block;margin-top:4px;color:#71869d;font-size:9px;font-weight:700}.idle-stat.ok b{color:#15803d}.idle-stat.warn b{color:#b45309}.idle-stat.pending b{color:#64748b}
.idle-class-list{display:grid;grid-template-columns:repeat(2,1fr);gap:7px;max-height:260px;overflow:auto}.idle-class{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e6edf5;border-radius:10px;background:#fbfdff}.idle-class-name{font-size:10px;font-weight:800;color:#334b67}.idle-class-count{font-size:10px;font-weight:800;color:#2563eb}
.sholat-list{display:grid;gap:8px;max-height:337px;overflow:auto}.sholat-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;padding:10px 11px;border:1px solid #e3ebf4;border-radius:12px;background:#fbfdff}.sholat-name{font-size:11px;font-weight:800;color:#17304f}.sholat-time{margin-top:3px;font-size:9px;color:#71869d;font-weight:600}.sholat-counts{display:flex;align-items:center;gap:6px}.mini-count{min-width:42px;text-align:center;padding:6px 7px;border-radius:8px;font-size:9px;font-weight:800}.mini-ok{background:#dcfce7;color:#166534}.mini-pending{background:#e2e8f0;color:#475569}.idle-empty{padding:28px 12px;text-align:center;border:1px dashed #d7e3ef;border-radius:13px;color:#8aa0b8;font-size:10px;font-weight:700}
@media(max-width:900px){.recap-banner{align-items:flex-start;flex-direction:column;gap:8px}.idle-grid{grid-template-columns:1fr}.idle-class-list{max-height:220px}}
.footer{text-align:center;padding:13px 0 2px;color:#71869d;font-size:9px;font-weight:600;letter-spacing:.04em}

@media(max-width:1250px){.stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:760px){
 body{padding:10px}.topbar{align-items:flex-start}.logo{width:54px;height:54px}.fullscreen{display:none}
 .clock{font-size:24px}.date{font-size:9px}.activity{align-items:flex-start;flex-direction:column}.pill{align-self:flex-start}
 .stats{grid-template-columns:repeat(2,1fr);padding:10px}.hero-main{padding:18px}.section{padding:12px}.public-activity{padding:12px}.public-activity-list{grid-template-columns:1fr}
}
@media(max-width:520px){.topbar{gap:10px}.brand{gap:9px}.school{font-size:18px}.live{font-size:8px}.stats{gap:8px}.stat{min-height:82px;padding:10px;gap:8px}.stat-icon{width:36px;height:36px}.stat b{font-size:23px}}
@media print{body{background:#fff;color:#000}.fullscreen,.refresh-note{display:none}.hero,.section{box-shadow:none}}
</style>
</head>
<body class="mode-<?=layar_e($displayMode)?>">
<div class="wrap">
<header class="topbar">
 <div class="brand">
  <?php if($logo):?><img class="logo" src="<?=layar_e($logo)?>" alt="Logo Sekolah"><?php endif;?>
  <div>
   <div class="school"><?=layar_e($profil['nama_sekolah'])?></div>
   <div class="live-badge"><span class="live-badge-dot"></span><strong>LIVE</strong><span>MONITOR ABSENSI TERPADU</span></div>
   <div class="screen-mode"><i class="bi bi-<?= $displayMode==='sholat' ? 'moon-stars-fill' : ($displayMode==='kehadiran' ? 'calendar-check-fill' : 'bar-chart-fill') ?>"></i><span><?= $displayMode==='sholat' ? 'MODE ABSENSI SHOLAT' : ($displayMode==='kehadiran' ? 'MODE ABSENSI KEHADIRAN' : 'MODE REKAP HARIAN') ?></span></div>
  </div>
 </div>
 <div class="top-right">
  <div><div id="clock" class="clock"><?=date('H:i:s')?></div><div class="date"><?=date('d F Y')?></div></div>
  <button class="fullscreen" type="button" onclick="goFullscreen()"><i class="bi bi-fullscreen"></i> Layar Penuh</button>
 </div>
</header>

<?php if($sesi):?>
<section class="hero">
 <div class="hero-main">
  <div class="activity">
   <div class="activity-left">
    <div class="activity-icon"><i class="bi bi-<?=($sesi['kategori']==='sholat'?'moon-stars-fill':'calendar-check-fill')?>" aria-hidden="true"></i></div>
    <div>
     <div class="activity-title"><?=layar_e($sesi['kategori']==='sholat' ? 'ABSENSI SHOLAT '.($sesi['sholat_nama']??'') : 'ABSENSI KEHADIRAN')?></div>
     <div class="activity-meta"><i class="bi bi-clock"></i> <?=layar_e($sesi['nama_sesi'])?> · <?=substr($sesi['jam_mulai'],0,5)?>–<?=substr($sesi['jam_selesai'],0,5)?></div>
    </div>
   </div>
   <span class="pill <?=($active?'':'next')?>"><i class="bi bi-<?=($active?'broadcast-pin':'hourglass-split')?>"></i> <?=($active?'SEDANG BERLANGSUNG':'SEGERA DIMULAI')?></span>
  </div>
 </div>
 <div class="stats">
 <?php foreach($statsItems as [$k,$label,$cls,$icon]):?>
  <div class="stat <?=$cls?>"><div class="stat-icon"><i class="bi bi-<?=$icon?>"></i></div><div><b><?=number_format($counts[$k])?></b><span><?=$label?></span></div></div>
 <?php endforeach;?>
 </div>
 <?php if($last):?>
 <div class="lastscan">
  <div class="lastscan-icon"><i class="bi bi-person-check-fill"></i></div>
  <div><div class="lastscan-main"><?=layar_e($last['nama'])?> · <?=layar_e($last['kelas']??'Belum Ditempatkan')?></div>
   <div class="lastscan-meta">Status <?=layar_e($last['status'])?><?php if(!$isSholat):?> · No. <?=isset($last['nomor_urut'])&&$last['nomor_urut']!==null?(int)$last['nomor_urut']:'-'?><?php endif;?> · Scan <?=layar_e($last['waktu_scan'])?></div>
  </div>
 </div>
 <?php else:?>
 <div class="lastscan"><div class="lastscan-icon"><i class="bi bi-qr-code-scan"></i></div><div><div class="lastscan-main">Siap menerima scan QR</div><div class="lastscan-meta">Menunggu transaksi absensi berikutnya.</div></div></div>
 <?php endif;?>
</section>
<?php else:?>
<section class="recap-banner"><div><strong><i class="bi bi-bar-chart-fill"></i> REKAP HARI INI</strong><span>Belum ada sesi absensi aktif. Layar otomatis menampilkan rekap sampai sesi berikutnya dimulai.</span></div><span class="recap-live"><i class="bi bi-arrow-repeat"></i> OTOMATIS</span></section>
<section class="idle-grid">
 <div class="idle-panel">
  <div class="idle-panel-head">
   <div><h2 class="idle-panel-title"><i class="bi bi-calendar-check-fill"></i> Rekap Absensi Kehadiran</h2><div class="idle-panel-sub">Rekap sesi kehadiran terakhir hari ini</div></div>
   <span class="idle-panel-badge">HARI INI</span>
  </div>
  <div class="idle-stats">
   <div class="idle-stat ok"><b><?=number_format($idleAttendance['H'])?></b><span>Hadir</span></div>
   <div class="idle-stat warn"><b><?=number_format($idleAttendance['T'])?></b><span>Terlambat</span></div>
   <div class="idle-stat pending"><b><?=number_format($idleAttendance['B'])?></b><span>Belum Status</span></div>
  </div>
  <?php if($idleAttendance['kelas']):?><div class="idle-class-list">
   <?php foreach($idleAttendance['kelas'] as $kr):?><div class="idle-class"><span class="idle-class-name"><?=layar_e($kr['kelas'])?></span><span class="idle-class-count"><?=number_format((int)$kr['H'])?> hadir · <?=number_format((int)$kr['B'])?> belum</span></div><?php endforeach;?>
  </div><?php else:?><div class="idle-empty">Belum ada sesi kehadiran yang tercatat hari ini.</div><?php endif;?>
 </div>
 <div class="idle-panel">
  <div class="idle-panel-head">
   <div><h2 class="idle-panel-title"><i class="bi bi-moon-stars-fill"></i> Rekap Absensi Sholat</h2><div class="idle-panel-sub">Rekap seluruh sesi sholat hari ini</div></div>
   <span class="idle-panel-badge">HARI INI</span>
  </div>
  <?php if($idleSholat['sessions']):?><div class="sholat-list">
   <?php foreach($idleSholat['sessions'] as $sr):?><div class="sholat-row"><div><div class="sholat-name"><?=layar_e($sr['sholat_nama'])?></div><div class="sholat-time"><?=layar_e($sr['jam_mulai'])?>–<?=layar_e($sr['jam_selesai'])?></div></div><div class="sholat-counts"><span class="mini-count mini-ok"><?=number_format((int)$sr['sudah'])?> sudah</span><span class="mini-count mini-pending"><?=number_format((int)$sr['belum'])?> belum</span></div></div><?php endforeach;?>
  </div><?php else:?><div class="idle-empty">Belum ada sesi sholat yang tercatat hari ini.</div><?php endif;?>
 </div>
</section>
<?php endif;?>

<section class="public-activity">
 <div class="public-activity-head"><h2 class="public-activity-title"><i class="bi bi-clock-history"></i> Aktivitas Terbaru</h2><span class="public-activity-note">10 scan terakhir · nama, kelas & waktu</span></div>
 <?php if($recent):?><div class="public-activity-list">
 <?php foreach($recent as $r):?><div class="public-activity-item"><div class="public-activity-icon"><i class="bi bi-check-lg"></i></div><div><div class="public-activity-name"><?=layar_e($r['nama'])?></div><div class="public-activity-sub"><?=layar_e($r['kelas']??'Belum Ditempatkan')?><?=isset($r['status'])?' · '.layar_e($r['status']):''?></div></div><div class="public-activity-time"><?=layar_e($r['waktu'])?></div></div><?php endforeach;?>
 </div><?php else:?><div class="public-empty">Belum ada aktivitas scan pada sesi ini.</div><?php endif;?>
</section>

<section class="section">
 <div class="section-head">
  <h2 class="section-title"><i class="bi bi-bar-chart-fill"></i> Rekap Per Kelas<?= $isSholat ? ' · Sholat' : '' ?></h2>
  <span class="refresh-note"><i class="bi bi-arrow-repeat"></i> Update otomatis 2 detik · <?= $isSholat ? 'Mode Sholat' : ($sesi ? 'Mode Kehadiran' : 'Rekap') ?></span>
 </div>
 <?php if($per):?>
 <div class="table-wrap"><table class="table"><thead><tr><th>Kelas</th><th>Jumlah</th><?php if($isSholat):?><th>Sudah</th><th>Berhalangan</th><?php else:?><th>H</th><th>T</th><th>I</th><th>S</th><th>A</th><?php endif;?><th>Belum</th></tr></thead><tbody>
 <?php foreach($per as $r):?><tr><td><strong><?=layar_e($r['kelas'])?></strong></td><td><?=number_format((int)$r['total'])?></td><?php if($isSholat):?><td><span class="badge badge-h"><?=number_format((int)$r['SUDAH'])?></span></td><td><span class="badge badge-i"><?=number_format((int)$r['BERHALANGAN'])?></span></td><?php else:?><td><span class="badge badge-h"><?=number_format((int)$r['H'])?></span></td><td><span class="badge badge-t"><?=number_format((int)$r['T'])?></span></td><td><span class="badge badge-i"><?=number_format((int)$r['I'])?></span></td><td><span class="badge badge-s"><?=number_format((int)$r['S'])?></span></td><td><span class="badge badge-a"><?=number_format((int)$r['A'])?></span></td><?php endif;?><td><span class="badge badge-b"><?=number_format((int)$r['B'])?></span></td></tr><?php endforeach;?>
 </tbody></table></div>
 <?php else:?><div class="empty"><i class="bi bi-bar-chart" style="font-size:28px;display:block;margin-bottom:8px;color:#8aa0b8"></i>Belum ada rekap untuk ditampilkan.</div><?php endif;?>
</section>
<div class="footer">MONITOR ABSENSI TERPADU · <?=layar_e($profil['nama_sekolah'])?></div>
</div>
<script>
// Jam layar: 1 detik. Data layar: 2 detik tanpa full-page reload.
let layarPollingBusy=false;
let layarPollTimer=null;
const LAYAR_UPDATE_MS=2000;
const LAYAR_POLL_URL=window.location.href.split('#')[0];

function updateClock(){
  const el=document.getElementById('clock');
  if(!el)return;
  const d=new Date();
  el.textContent=d.toLocaleTimeString('id-ID',{hour12:false});
}
setInterval(updateClock,1000);
updateClock();

async function refreshPublicScreen(){
  if(layarPollingBusy || document.hidden)return;
  layarPollingBusy=true;
  try{
    const url=LAYAR_POLL_URL+(LAYAR_POLL_URL.includes('?')?'&':'?')+'_screen='+Date.now();
    const res=await fetch(url,{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!res.ok)throw new Error('HTTP '+res.status);
    const html=await res.text();
    const doc=new DOMParser().parseFromString(html,'text/html');
    const nextWrap=doc.querySelector('.wrap');
    const currentWrap=document.querySelector('.wrap');
    if(nextWrap&&currentWrap){
      const oldMode=document.body.className;
      const nextMode=doc.body.className;
      currentWrap.replaceWith(nextWrap);
      document.body.className=nextMode;
      // Pertahankan jam lokal yang terus berjalan setelah konten diganti.
      updateClock();
    }
  }catch(e){
    // Layar publik tetap menampilkan data terakhir bila server sesaat tidak merespons.
  }finally{
    layarPollingBusy=false;
  }
}

layarPollTimer=setInterval(refreshPublicScreen,LAYAR_UPDATE_MS);

document.addEventListener('visibilitychange',()=>{
  if(!document.hidden)refreshPublicScreen();
});

function goFullscreen(){
  const el=document.documentElement;
  if(!document.fullscreenElement&&el.requestFullscreen)el.requestFullscreen();
  else if(document.exitFullscreen)document.exitFullscreen();
}
</script>
</body></html>
