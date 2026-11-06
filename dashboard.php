<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auto_sessions.php';
require_once __DIR__ . '/includes/school_profile.php';
require_once __DIR__ . '/includes/task_access.php';

require_login();

// Kepala Sekolah memiliki satu dashboard pimpinan saja.
// Arahkan dashboard global ke halaman monitoring pimpinan agar tidak ada dua dashboard.
if (user_role() === 'kepala_sekolah') {
    header('Location: ' . BASE_URL . 'saya/laporan_kepala.php');
    exit;
}

$school_profile = school_profile($pdo);
$page_title = 'Dashboard';
$role = (string)(user_role() ?? '');
$today = date('Y-m-d');
$dayNumber = (int)date('N');
$dayNames = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
$todayName = $dayNames[$dayNumber] ?? '';
auto_sessions_today($pdo);

/**
 * Dashboard PART60
 * - Tampilan berbeda menurut role.
 * - Warna identitas role hanya untuk chrome/dashboard; warna fungsi tetap dipakai
 *   untuk informasi sehingga pengguna dapat membedakan konteks dengan cepat.
 * - Query defensif agar dashboard tidak mati hanya karena modul opsional kosong.
 */
function dash_scalar(PDO $pdo, string $sql, array $params = [], int $default = 0): int
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? $default : (int)$v;
    } catch (Throwable) {
        return $default;
    }
}

function dash_row(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

function dash_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}

$roleMeta = match ($role) {
    'admin' => ['label'=>'Administrator','icon'=>'shield-lock-fill','class'=>'role-admin','description'=>'Kontrol penuh sistem, data, keamanan, dan pemeliharaan.'],
    'kepala_sekolah' => ['label'=>'Kepala Sekolah','icon'=>'person-vcard-fill','class'=>'role-ks','description'=>'Ringkasan kondisi sekolah dan pemantauan administrasi.'],
    'guru' => ['label'=>'Guru','icon'=>'person-badge-fill','class'=>'role-guru','description'=>'Aktivitas mengajar, absensi, dan tugas tambahan Anda.'],
    'guru_bk' => ['label'=>'Guru BK','icon'=>'heart-pulse-fill','class'=>'role-bk','description'=>'Pendampingan siswa, catatan BK, dan tindak lanjut kesiswaan.'],
    'tu' => ['label'=>'Tata Usaha','icon'=>'briefcase-fill','class'=>'role-tu','description'=>'Administrasi sekolah dan layanan data operasional.'],
    'siswa' => ['label'=>'Siswa','icon'=>'mortarboard-fill','class'=>'role-siswa','description'=>'Ringkasan kehadiran dan aktivitas pribadi.'],
    'ortu' => ['label'=>'Orang Tua / Wali','icon'=>'people-fill','class'=>'role-ortu','description'=>'Ringkasan perkembangan dan kehadiran anak yang terhubung.'],
    default => ['label'=>'Pengguna','icon'=>'person-fill','class'=>'role-default','description'=>'Ringkasan aktivitas sistem.'],
};

/* ---------------------------------------------------------------
 * TAPEL / SEMESTER AKTIF
 * --------------------------------------------------------------- */
$tahunAktif = dash_row($pdo, "SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1");
$tapelId = (int)($tahunAktif['id'] ?? 0);
$tapelName = (string)($tahunAktif['nama'] ?? '-');
$semesterAktif = $tapelId ? dash_row($pdo, "SELECT id,nama FROM semester WHERE tahun_pelajaran_id=? AND aktif=1 ORDER BY id DESC LIMIT 1", [$tapelId]) : [];
$semesterId = (int)($semesterAktif['id'] ?? 0);
$semesterName = (string)($semesterAktif['nama'] ?? '-');

/* ---------------------------------------------------------------
 * DATA UMUM SEKOLAH
 * --------------------------------------------------------------- */
$totalSiswa = dash_scalar($pdo, "SELECT COUNT(*) FROM siswa WHERE status='aktif'");
$totalGuru = dash_scalar($pdo, "SELECT COUNT(*) FROM guru WHERE status='aktif'");
$totalKelas = dash_scalar($pdo, "SELECT COUNT(*) FROM master_kelas WHERE aktif=1");
$totalSiswaL = dash_scalar($pdo, "SELECT COUNT(*) FROM siswa WHERE status='aktif' AND jk='L'");
$totalSiswaP = dash_scalar($pdo, "SELECT COUNT(*) FROM siswa WHERE status='aktif' AND jk='P'");

/* ---------------------------------------------------------------
 * ABSENSI HARI INI — digunakan oleh admin/KS/TU dan ringkas untuk guru
 * --------------------------------------------------------------- */
$absensi = ['sudah'=>0,'hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'belum'=>0,'persen'=>0,'sesi'=>0,'aktif'=>0];
if ($tapelId && $semesterId) {
    $absensi['sesi'] = dash_scalar($pdo, "SELECT COUNT(*) FROM sesi_absensi WHERE tahun_pelajaran_id=? AND semester_id=? AND tanggal=?", [$tapelId,$semesterId,$today]);
    $absensi['aktif'] = dash_scalar($pdo, "SELECT COUNT(*) FROM sesi_absensi WHERE tahun_pelajaran_id=? AND semester_id=? AND tanggal=? AND status='aktif'", [$tapelId,$semesterId,$today]);
    $rec = dash_rows($pdo, "SELECT a.status,COUNT(DISTINCT a.siswa_id) jumlah FROM absensi a INNER JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE sa.tahun_pelajaran_id=? AND sa.semester_id=? AND sa.tanggal=? GROUP BY a.status", [$tapelId,$semesterId,$today]);
    foreach ($rec as $r) {
        $k = match ((string)$r['status']) { 'H'=>'hadir','T'=>'terlambat','I'=>'izin','S'=>'sakit','A'=>'alpa', default=>null };
        if ($k) $absensi[$k] = (int)$r['jumlah'];
    }
    $absensi['sudah'] = dash_scalar($pdo, "SELECT COUNT(DISTINCT a.siswa_id) FROM absensi a INNER JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE sa.tahun_pelajaran_id=? AND sa.semester_id=? AND sa.tanggal=?", [$tapelId,$semesterId,$today]);
    $absensi['belum'] = max(0,$totalSiswa-$absensi['sudah']);
    $totalStatus = $absensi['hadir']+$absensi['terlambat']+$absensi['izin']+$absensi['sakit']+$absensi['alpa'];
    $absensi['persen'] = $totalStatus>0 ? round((($absensi['hadir']+$absensi['terlambat'])/$totalStatus)*100,1) : 0;
}

/* Sesi kehadiran hari ini untuk shortcut operasional Guru. */
$todayAttendanceSessionId = 0;
if ($role === 'guru' && $tapelId && $semesterId) {
    $todayAttendanceSession = dash_row($pdo, "SELECT s.id
        FROM sesi_absensi s
        INNER JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
        WHERE s.tahun_pelajaran_id=? AND s.semester_id=? AND s.tanggal=?
          AND ja.kategori='kehadiran'
        ORDER BY (s.status='aktif') DESC, s.id DESC
        LIMIT 1", [$tapelId,$semesterId,$today]);
    $todayAttendanceSessionId = (int)($todayAttendanceSession['id'] ?? 0);
}

/* ---------------------------------------------------------------
 * TUGAS TAMBAHAN USER AKTIF
 * --------------------------------------------------------------- */
$taskRows = [];
$todayPiket = false;
$waliKelas = [];
$extraTasks = [];
if (in_array($role,['guru','guru_bk','tu'],true) && $tapelId && $semesterId && user_guru_id()) {
    $taskRows = dash_rows($pdo, "SELECT tg.id,jt.kode,jt.nama,jt.kategori,tg.target_tipe,tg.target_id,tg.keterangan FROM tugas_guru tg INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.aktif=1 AND tg.aktif=1 ORDER BY jt.kategori,jt.nama,tg.id", [user_guru_id(),$tapelId,$semesterId]);
    $todayPiket = user_has_task_on_date($pdo,'GURU_PIKET',$today) || user_has_task_on_date($pdo,'PETUGAS_PIKET',$today);
    foreach ($taskRows as $t) {
        $code = strtoupper((string)$t['kode']);
        if ($code==='WALI_KELAS') $waliKelas[]=$t;
        if (in_array($code,['PEMBINA_EKSTRA','KOORDINATOR_EKSTRA','PEMBINA_PRAMUKA','PEMBINA_PMR','PEMBINA_BASKET','PEMBINA_FUTSAL'],true)) $extraTasks[]=$t;
    }
}

/* ---------------------------------------------------------------
 * PERINGATAN PELANGGARAN — KHUSUS WALI KELAS
 * Dihitung dari poin efektif yang sudah ada; tidak menambah tabel baru.
 * --------------------------------------------------------------- */
$waliPelanggaranNotifications = [];
if ($tapelId && $role === 'guru' && function_exists('user_has_task_capability') && user_has_task_capability($pdo, 'wali_kelas')) {
    $kelasWaliIds = array_values(array_filter(array_map('intval', user_task_capability_targets($pdo, 'wali_kelas', 'kelas')), fn($v) => $v > 0));
    if ($kelasWaliIds) {
        $ph = implode(',', array_fill(0, count($kelasWaliIds), '?'));
        $params = array_merge([$tapelId], $kelasWaliIds, [$tapelId, $tapelId, $tapelId]);
        $waliPelanggaranNotifications = dash_rows($pdo, "SELECT s.id,s.nis,s.nama,mk.nama kelas,
                COALESCE(v.gross,0)+COALESCE(a.delta,0) poin_efektif,
                CASE WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=100 THEN 'P4'
                     WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=75 THEN 'P3'
                     WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=50 THEN 'P2'
                     WHEN COALESCE(v.gross,0)+COALESCE(a.delta,0)>=25 THEN 'P1'
                     ELSE 'Normal' END tahap,
                (SELECT p.status FROM pembinaan_siswa p WHERE p.siswa_id=s.id AND p.tahun_pelajaran_id=? ORDER BY p.tanggal DESC,p.id DESC LIMIT 1) pb_status
            FROM siswa s
            INNER JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.kelas_id IN ($ph) AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
            INNER JOIN master_kelas mk ON mk.id=sk.kelas_id
            LEFT JOIN (SELECT siswa_id,SUM(poin) gross FROM pelanggaran_siswa WHERE tahun_pelajaran_id=? GROUP BY siswa_id) v ON v.siswa_id=s.id
            LEFT JOIN (SELECT siswa_id,SUM(delta_poin) delta FROM pelanggaran_poin_penyesuaian WHERE tahun_pelajaran_id=? GROUP BY siswa_id) a ON a.siswa_id=s.id
            WHERE s.status='aktif' AND COALESCE(v.gross,0)+COALESCE(a.delta,0)>=25
            ORDER BY poin_efektif DESC,s.nama ASC LIMIT 30",
            array_merge([$tapelId, $tapelId], $kelasWaliIds, [$tapelId, $tapelId]));
    }
}

/* ---------------------------------------------------------------
 * BK / KESISWAAN
 * --------------------------------------------------------------- */
$bkToday = 0; $bkOpen = 0; $pelanggaranToday = 0; $pembinaanOpen = 0; $homeVisitPlan = 0;
if ($role==='guru_bk' || in_array($role,['admin','kepala_sekolah'],true)) {
    $bkToday = dash_scalar($pdo,"SELECT COUNT(*) FROM catatan_bk WHERE tanggal=?",[$today]);
    $bkOpen = dash_scalar($pdo,"SELECT COUNT(*) FROM catatan_bk WHERE status IN ('baru','proses')");
    $pelanggaranToday = dash_scalar($pdo,"SELECT COUNT(*) FROM pelanggaran_siswa WHERE tanggal=?",[$today]);
    $pembinaanOpen = dash_scalar($pdo,"SELECT COUNT(*) FROM pembinaan_siswa WHERE status IN ('direncanakan','proses')");
    $homeVisitPlan = dash_scalar($pdo,"SELECT COUNT(*) FROM home_visit WHERE status='direncanakan'");
}

/* ---------------------------------------------------------------
 * UKS / PERPUSTAKAAN untuk role yang relevan
 * --------------------------------------------------------------- */
$uksToday = 0; $uksOpen = 0; $stokMenipis = 0; $perpusToday = 0;
if (in_array($role,['admin','kepala_sekolah','guru','guru_bk','tu'],true)) {
    $uksToday = dash_scalar($pdo,"SELECT COUNT(*) FROM uks_kunjungan WHERE tanggal=?",[$today]);
    $uksOpen = dash_scalar($pdo,"SELECT COUNT(*) FROM uks_kunjungan WHERE status IN ('baru','ditangani')");
    $stokMenipis = dash_scalar($pdo,"SELECT COUNT(*) FROM uks_obat WHERE aktif=1 AND stok_saat_ini<=stok_minimum");
    $perpusToday = dash_scalar($pdo,"SELECT COUNT(*) FROM perpustakaan_kunjungan WHERE DATE(waktu_kunjungan)=?",[$today]);
}

/* ---------------------------------------------------------------
 * DATA GURU / SISWA PRIBADI
 * --------------------------------------------------------------- */
$guruInfo = [];
if (user_guru_id()) {
    $guruInfo = dash_row($pdo,"SELECT id,nama,jk FROM guru WHERE id=?",[user_guru_id()]);
}

$siswaInfo = [];
if (user_siswa_id()) {
    $siswaInfo = dash_row($pdo,"SELECT s.id,s.nama,s.nis,sk.kelas_id,mk.nama kelas FROM siswa s LEFT JOIN siswa_kelas sk ON sk.siswa_id=s.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif' LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE s.id=? LIMIT 1",[$tapelId,user_siswa_id()]);
}

$siswaPribadi = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'sesi'=>0];
if (user_siswa_id() && $tapelId) {
    $r = dash_row($pdo,"SELECT COALESCE(SUM(a.status='H'),0) hadir,COALESCE(SUM(a.status='T'),0) terlambat,COALESCE(SUM(a.status='I'),0) izin,COALESCE(SUM(a.status='S'),0) sakit,COALESCE(SUM(a.status='A'),0) alpa,COUNT(DISTINCT a.sesi_id) sesi FROM absensi a INNER JOIN sesi_absensi sa ON sa.id=a.sesi_id WHERE a.siswa_id=? AND sa.tahun_pelajaran_id=? AND sa.jenis_sholat_id IS NULL",[user_siswa_id(),$tapelId]);
    foreach ($siswaPribadi as $k=>$_) $siswaPribadi[$k]=(int)($r[$k]??0);
}

/* ---------------------------------------------------------------
 * ORTU
 * --------------------------------------------------------------- */
$ortuChildren = [];
if ($role==='ortu') {
    require_once __DIR__.'/ortu/_helper.php';
    $oid = function_exists('ortu_get_id') ? ortu_get_id($pdo) : 0;
    $ortuChildren = $oid && function_exists('ortu_children') ? ortu_children($pdo,$oid) : [];
}

$todayLabel = date('d-m-Y');
$roleLabel = $roleMeta['label'];

require_once __DIR__ . '/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';
?>

<style>
/* ================================================================
   PART60 — DASHBOARD DINAMIS
   ================================================================ */
:root{
  --role-primary:#475569; --role-deep:#334155; --role-soft:#f1f5f9; --role-border:#cbd5e1;
  --ink:#0f172a; --muted:#64748b; --surface:#ffffff; --page:#f5f7fb;
  --success:#15803d; --success-bg:#ecfdf3; --success-border:#bbf7d0;
  --info:#1d4ed8; --info-bg:#eff6ff; --info-border:#bfdbfe;
  --warning:#b45309; --warning-bg:#fffbeb; --warning-border:#fde68a;
  --danger:#b91c1c; --danger-bg:#fef2f2; --danger-border:#fecaca;
  --purple:#6d28d9; --purple-bg:#f5f3ff; --purple-border:#ddd6fe;
  --teal:#0f766e; --teal-bg:#f0fdfa; --teal-border:#99f6e4;
}
.dashboard-page.role-admin{--role-primary:#4f46e5;--role-deep:#3730a3;--role-soft:#eef2ff;--role-border:#c7d2fe}
.dashboard-page.role-ks{--role-primary:#059669;--role-deep:#047857;--role-soft:#ecfdf5;--role-border:#a7f3d0}
.dashboard-page.role-guru{--role-primary:#2563eb;--role-deep:#1d4ed8;--role-soft:#eff6ff;--role-border:#bfdbfe}
.dashboard-page.role-bk{--role-primary:#7c3aed;--role-deep:#6d28d9;--role-soft:#f5f3ff;--role-border:#ddd6fe}
.dashboard-page.role-tu{--role-primary:#d97706;--role-deep:#b45309;--role-soft:#fffbeb;--role-border:#fde68a}
.dashboard-page.role-siswa{--role-primary:#0891b2;--role-deep:#0e7490;--role-soft:#ecfeff;--role-border:#a5f3fc}
.dashboard-page.role-ortu{--role-primary:#ea580c;--role-deep:#c2410c;--role-soft:#fff7ed;--role-border:#fed7aa}
.dashboard-page.role-default{--role-primary:#475569;--role-deep:#334155;--role-soft:#f1f5f9;--role-border:#cbd5e1}
.dashboard-page{background:var(--page);min-height:100%;}
.dashboard-hero{position:relative;overflow:hidden;border:1px solid var(--role-border);border-radius:22px;padding:24px 26px;background:linear-gradient(135deg,#fff 0%,var(--role-soft) 100%);box-shadow:0 12px 32px rgba(15,23,42,.06);display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}
.dashboard-hero:after{content:"";position:absolute;right:-80px;top:-100px;width:260px;height:260px;border-radius:50%;background:var(--role-primary);opacity:.07}
.hero-copy{position:relative;z-index:1}.hero-kicker{display:inline-flex;align-items:center;gap:7px;color:var(--role-deep);font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:7px}.hero-copy h2{margin:0;color:var(--ink);font-size:25px;letter-spacing:-.025em;font-weight:800}.hero-copy p{margin:7px 0 0;color:var(--muted);font-size:13px;max-width:760px}.hero-badge{position:relative;z-index:1;min-width:76px;height:76px;border-radius:20px;background:var(--role-primary);color:#fff;display:grid;place-items:center;font-size:30px;box-shadow:0 12px 22px rgba(15,23,42,.14)}
.dashboard-context{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:20px}.context-chip{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;background:#fff;border:1px solid #e2e8f0;color:#475569;font-size:12px;font-weight:700}.context-chip i{color:var(--role-primary)}
.dashboard-alert-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:24px}.dashboard-alert{position:relative;min-height:108px;border:1px solid transparent;border-radius:17px;padding:16px 17px;display:flex;gap:13px;align-items:flex-start;box-shadow:0 9px 22px rgba(15,23,42,.10);transition:transform .18s ease,box-shadow .18s ease;color:#fff}.dashboard-alert:hover{transform:translateY(-2px);box-shadow:0 13px 28px rgba(15,23,42,.15)}.dashboard-alert .alert-icon{width:42px;height:42px;flex:0 0 42px;border-radius:13px;display:grid;place-items:center;font-size:18px;background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.16)}.dashboard-alert .alert-label{font-size:12px;font-weight:800;letter-spacing:.01em;margin-bottom:3px;color:rgba(255,255,255,.92)}.dashboard-alert .alert-value{font-size:24px;line-height:1.1;font-weight:850;letter-spacing:-.025em;color:#fff}.dashboard-alert .alert-text{font-size:11px;color:rgba(255,255,255,.86);margin-top:4px}.alert-success{background:#15803d;border-color:#166534}.alert-success .alert-icon{background:rgba(255,255,255,.16);color:#fff}.alert-info{background:#1d4ed8;border-color:#1e40af}.alert-info .alert-icon{background:rgba(255,255,255,.16);color:#fff}.alert-warning{background:#b45309;border-color:#92400e}.alert-warning .alert-icon{background:rgba(255,255,255,.17);color:#fff}.alert-danger{background:#b91c1c;border-color:#991b1b}.alert-danger .alert-icon{background:rgba(255,255,255,.16);color:#fff}.alert-purple{background:#6d28d9;border-color:#5b21b6}.alert-purple .alert-icon{background:rgba(255,255,255,.16);color:#fff}.alert-teal{background:#0f766e;border-color:#115e59}.alert-teal .alert-icon{background:rgba(255,255,255,.16);color:#fff}
.dashboard-section{margin-top:24px}.dashboard-section-head{display:flex;justify-content:space-between;gap:15px;align-items:end;margin-bottom:12px}.dashboard-section-head h3{margin:0;color:var(--ink);font-size:17px;font-weight:800}.dashboard-section-head p{margin:4px 0 0;color:var(--muted);font-size:12px}.section-accent{width:4px;height:28px;border-radius:8px;background:var(--role-primary);display:inline-block;margin-right:9px;vertical-align:-8px}
.quick-action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.quick-action{display:flex;align-items:center;gap:13px;min-width:0;min-height:78px;padding:14px 15px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;text-decoration:none;color:var(--ink);box-shadow:0 6px 16px rgba(15,23,42,.045);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease}.quick-action:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(15,23,42,.09);color:var(--ink)}.quick-action-icon{width:44px;height:44px;flex:0 0 44px;border-radius:13px;display:grid;place-items:center;font-size:19px}.quick-action-copy{display:flex;flex-direction:column;min-width:0;flex:1}.quick-action-copy strong{font-size:13px;font-weight:850;line-height:1.25}.quick-action-copy small{margin-top:4px;color:var(--muted);font-size:11px;line-height:1.4}.quick-action-arrow{font-size:13px;color:#94a3b8;flex:0 0 auto}.quick-action-primary{border-color:#bfdbfe}.quick-action-primary .quick-action-icon{background:#eff6ff;color:#2563eb}.quick-action-primary:hover{border-color:#93c5fd;background:#f8fbff}.quick-action-success{border-color:#bbf7d0}.quick-action-success .quick-action-icon{background:#ecfdf5;color:#15803d}.quick-action-success:hover{border-color:#86efac;background:#f7fff9}.quick-action-warning{border-color:#fde68a}.quick-action-warning .quick-action-icon{background:#fffbeb;color:#b45309}.quick-action-warning:hover{border-color:#fcd34d;background:#fffdf5}.quick-action-teal{border-color:#99f6e4}.quick-action-teal .quick-action-icon{background:#f0fdfa;color:#0f766e}.quick-action-teal:hover{border-color:#5eead4;background:#f8fffe}.quick-action-danger{border-color:#fecaca}.quick-action-danger .quick-action-icon{background:#fef2f2;color:#b91c1c}.quick-action-danger:hover{border-color:#fca5a5;background:#fffafa}.quick-action-purple{border-color:#ddd6fe}.quick-action-purple .quick-action-icon{background:#f5f3ff;color:#6d28d9}.quick-action-purple:hover{border-color:#c4b5fd;background:#fcfaff}.dashboard-grid-modern{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.modern-card{position:relative;overflow:hidden;background:var(--surface);border:1px solid #e2e8f0;border-radius:17px;padding:17px 18px;box-shadow:0 7px 18px rgba(15,23,42,.045)}.modern-card .card-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.modern-card .card-label{font-size:12px;color:#475569;font-weight:750}.modern-card .card-icon{width:38px;height:38px;border-radius:11px;background:var(--role-soft);color:var(--role-primary);display:grid;place-items:center;font-size:17px}.modern-card .card-number{font-size:25px;color:var(--ink);font-weight:850;letter-spacing:-.03em;margin-top:13px}.modern-card .card-meta{font-size:11px;color:var(--muted);margin-top:4px}.modern-card .card-link{display:inline-flex;align-items:center;gap:5px;margin-top:10px;font-size:11px;font-weight:800;color:var(--role-primary);text-decoration:none}.modern-card .card-link:hover{text-decoration:underline}
.task-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.task-card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:15px 16px;display:flex;gap:12px;align-items:flex-start}.task-card .task-icon{width:38px;height:38px;border-radius:11px;background:var(--role-soft);color:var(--role-primary);display:grid;place-items:center;flex:0 0 auto}.task-card strong{display:block;font-size:13px;color:var(--ink)}.task-card span{display:block;color:var(--muted);font-size:11px;margin-top:3px}.task-today{border-color:var(--role-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--role-primary) 8%,transparent)}
.dashboard-note{border-radius:15px;padding:14px 16px;display:flex;gap:11px;align-items:flex-start;font-size:12px}.dashboard-note i{font-size:17px;margin-top:1px}.dashboard-note strong{display:block;margin-bottom:3px}.note-role{background:var(--role-soft);border:1px solid var(--role-border);color:#334155}.note-role i{color:var(--role-primary)}
.dashboard-table{background:#fff;border:1px solid #e2e8f0;border-radius:17px;overflow:hidden}.dashboard-table table{width:100%;border-collapse:collapse}.dashboard-table th,.dashboard-table td{padding:12px 14px;border-bottom:1px solid #eef2f7;text-align:left;font-size:12px}.dashboard-table th{background:#f8fafc;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:.04em}.dashboard-table tr:last-child td{border-bottom:0}.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;font-size:10px;font-weight:800}.status-pill.on{background:#dcfce7;color:#166534}.status-pill.off{background:#f1f5f9;color:#475569}.empty-state{padding:20px;text-align:center;color:#94a3b8;font-size:12px}
@media(max-width:1100px){.dashboard-alert-grid,.dashboard-grid-modern{grid-template-columns:repeat(2,minmax(0,1fr))}.quick-action-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.task-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.quick-action-grid{grid-template-columns:1fr}.dashboard-hero{padding:19px}.hero-badge{width:58px;height:58px;min-width:58px;border-radius:16px;font-size:23px}.hero-copy h2{font-size:20px}.dashboard-alert-grid,.dashboard-grid-modern,.task-list{grid-template-columns:1fr}.dashboard-context{gap:6px}}
<style>
/* DASHBOARD COLORFUL PALETTE FIX 2 */
.dashboard-alert.alert-cyan{
    background:linear-gradient(135deg,#06b6d4 0%,#0891b2 100%);
    border-color:#0e7490;
}
.dashboard-alert.alert-cyan .alert-icon{background:rgba(255,255,255,.18);color:#fff}
.dashboard-notification-grid .dashboard-alert:nth-child(1){background:linear-gradient(135deg,#f43f5e 0%,#e11d48 100%);border-color:#be123c}
.dashboard-notification-grid .dashboard-alert:nth-child(2){background:linear-gradient(135deg,#06b6d4 0%,#0284c7 100%);border-color:#0369a1}
.dashboard-notification-grid .dashboard-alert:nth-child(3){background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);border-color:#b45309}
.dashboard-notification-grid .dashboard-alert:nth-child(4){background:linear-gradient(135deg,#14b8a6 0%,#0f766e 100%);border-color:#115e59}
.dashboard-notification-grid .dashboard-alert:nth-child(1) .alert-icon,
.dashboard-notification-grid .dashboard-alert:nth-child(2) .alert-icon,
.dashboard-notification-grid .dashboard-alert:nth-child(3) .alert-icon,
.dashboard-notification-grid .dashboard-alert:nth-child(4) .alert-icon{background:rgba(255,255,255,.18);color:#fff}

.dashboard-page .dashboard-alert-grid + .dashboard-alert-grid{margin-top:2px}

/* Soft colorful attendance/status cards */
.dashboard-page .modern-card.status-hadir{
    background:linear-gradient(135deg,#f0fdf4,#dcfce7);
    border-color:#86efac;
}
.dashboard-page .modern-card.status-terlambat{
    background:linear-gradient(135deg,#fffbeb,#fef3c7);
    border-color:#fcd34d;
}
.dashboard-page .modern-card.status-izin{
    background:linear-gradient(135deg,#eff6ff,#dbeafe);
    border-color:#93c5fd;
}
.dashboard-page .modern-card.status-alpa{
    background:linear-gradient(135deg,#fff1f2,#ffe4e6);
    border-color:#fda4af;
}
</style></style>

<main class="main dashboard-page <?=e($roleMeta['class'])?>">
<header class="topbar">
    <div>
        <h1>Dashboard</h1>
        <div class="topbar-subtitle"><?=e($school_profile['nama_sekolah'])?></div>
    </div>
    <div class="topbar-user"><strong><?=e(user_name())?></strong><span><?=e($roleLabel)?></span></div>
</header>
<section class="content">

<div class="dashboard-hero">
    <div class="hero-copy">
        <div class="hero-kicker"><i class="bi bi-circle-fill" style="font-size:7px"></i><?=e($roleLabel)?></div>
        <h2>Selamat datang, <?=e(user_name())?></h2>
        <p><?=e($roleMeta['description'])?> Informasi disusun sesuai peran dan tugas Anda.</p>
    </div>
    <div class="hero-badge" aria-hidden="true"><i class="bi bi-<?=e($roleMeta['icon'])?>"></i></div>
</div>

<div class="dashboard-context">
    <span class="context-chip"><i class="bi bi-calendar3"></i><?=e($todayLabel)?> · <?=e($todayName)?></span>
    <span class="context-chip"><i class="bi bi-calendar-range"></i><?=e($tapelName)?></span>
    <span class="context-chip"><i class="bi bi-layers"></i>Semester <?=e($semesterName)?></span>
</div>

<?php if ($role==='admin' || $role==='tu'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Ringkasan Utama</h3><p>Data operasional utama.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert alert-info"><div class="alert-icon"><i class="bi bi-people-fill"></i></div><div><div class="alert-label">Siswa Aktif</div><div class="alert-value"><?=number_format($totalSiswa)?></div><div class="alert-text">L <?=number_format($totalSiswaL)?> · P <?=number_format($totalSiswaP)?></div></div></div>
        <div class="dashboard-alert alert-success"><div class="alert-icon"><i class="bi bi-person-badge-fill"></i></div><div><div class="alert-label">Guru Aktif</div><div class="alert-value"><?=number_format($totalGuru)?></div><div class="alert-text">Guru aktif</div></div></div>
        <div class="dashboard-alert alert-purple"><div class="alert-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="alert-label">Kelas Aktif</div><div class="alert-value"><?=number_format($totalKelas)?></div><div class="alert-text">Rombel aktif</div></div></div>
        <div class="dashboard-alert <?= $absensi['aktif']>0 ? 'alert-warning':'alert-info' ?>"><div class="alert-icon"><i class="bi bi-activity"></i></div><div><div class="alert-label">Sesi Aktif</div><div class="alert-value"><?=number_format($absensi['aktif'])?></div><div class="alert-text"><?=number_format($absensi['sesi'])?> sesi hari ini</div></div></div>
    </div>
</div>

<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Notifikasi Administrasi</h3><p>Indikator utama untuk perhatian hari ini.</p></div></div>
    <div class="dashboard-alert-grid dashboard-notification-grid">
        <div class="dashboard-alert alert-danger"><div class="alert-icon"><i class="bi bi-person-exclamation"></i></div><div><div class="alert-label">Belum Ada Status</div><div class="alert-value"><?=number_format($absensi['belum'])?></div><div class="alert-text">Belum tercatat hari ini</div></div></div>
        <div class="dashboard-alert alert-cyan"><div class="alert-icon"><i class="bi bi-check2-circle"></i></div><div><div class="alert-label">Kehadiran Efektif</div><div class="alert-value"><?=number_format($absensi['persen'],1)?>%</div><div class="alert-text">Hadir + terlambat</div></div></div>
        <div class="dashboard-alert alert-warning"><div class="alert-icon"><i class="bi bi-heart-pulse-fill"></i></div><div><div class="alert-label">BK Belum Selesai</div><div class="alert-value"><?=number_format($bkOpen)?></div><div class="alert-text">Catatan baru/proses</div></div></div>
        <div class="dashboard-alert alert-teal"><div class="alert-icon"><i class="bi bi-capsule"></i></div><div><div class="alert-label">Stok UKS</div><div class="alert-value"><?=number_format($stokMenipis)?></div><div class="alert-text">Obat perlu restock</div></div></div>
    </div>
</div>
<?php elseif ($role==='kepala_sekolah'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Ringkasan Pimpinan</h3><p>Semua informasi penting sekolah ditampilkan langsung tanpa memilih laporan.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert alert-info"><div class="alert-icon"><i class="bi bi-people-fill"></i></div><div><div class="alert-label">Siswa Aktif</div><div class="alert-value"><?=number_format($totalSiswa)?></div><div class="alert-text">Peserta didik aktif</div></div></div>
        <div class="dashboard-alert alert-success"><div class="alert-icon"><i class="bi bi-person-badge-fill"></i></div><div><div class="alert-label">Guru Aktif</div><div class="alert-value"><?=number_format($totalGuru)?></div><div class="alert-text">Guru aktif</div></div></div>
        <div class="dashboard-alert alert-purple"><div class="alert-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div><div><div class="alert-label">Kelas Aktif</div><div class="alert-value"><?=number_format($totalKelas)?></div><div class="alert-text">Rombel aktif</div></div></div>
        <div class="dashboard-alert alert-cyan"><div class="alert-icon"><i class="bi bi-check2-circle"></i></div><div><div class="alert-label">Kehadiran Efektif</div><div class="alert-value"><?=number_format($absensi['persen'],1)?>%</div><div class="alert-text">Hadir + terlambat hari ini</div></div></div>
    </div>
</div>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Laporan Cepat Seluruh Sekolah</h3><p>Ringkasan lintas modul: absensi, BK, kesiswaan, UKS, perpustakaan, dan ekstrakurikuler.</p></div></div>
    <div class="dashboard-grid-modern">
        <div class="modern-card"><div class="card-top"><span class="card-label">BK Belum Selesai</span><span class="card-icon"><i class="bi bi-chat-heart-fill"></i></span></div><div class="card-number"><?=number_format($bkOpen)?></div><div class="card-meta">Catatan baru/proses</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Pelanggaran Hari Ini</span><span class="card-icon"><i class="bi bi-exclamation-triangle-fill"></i></span></div><div class="card-number"><?=number_format($pelanggaranToday)?></div><div class="card-meta">Catatan kesiswaan</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Pembinaan Berjalan</span><span class="card-icon"><i class="bi bi-person-check-fill"></i></span></div><div class="card-number"><?=number_format($pembinaanOpen)?></div><div class="card-meta">Perlu dipantau</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Home Visit</span><span class="card-icon"><i class="bi bi-house-door-fill"></i></span></div><div class="card-number"><?=number_format($homeVisitPlan)?></div><div class="card-meta">Masih direncanakan</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">UKS Hari Ini</span><span class="card-icon"><i class="bi bi-heart-pulse-fill"></i></span></div><div class="card-number"><?=number_format($uksToday)?></div><div class="card-meta">Kunjungan</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Perpustakaan</span><span class="card-icon"><i class="bi bi-book-half"></i></span></div><div class="card-number"><?=number_format($perpusToday)?></div><div class="card-meta">Kunjungan hari ini</div></div>
    </div>
</div>
<div class="dashboard-section"><div class="dashboard-note note-role"><i class="bi bi-arrow-right-circle-fill"></i><div><strong>Timeline, laporan lengkap, dan tindak lanjut</strong><span>Gunakan menu di sidebar untuk melihat timeline program, seluruh ringkasan laporan, dan daftar tindak lanjut yang masih perlu diperhatikan.</span></div></div></div>
<?php elseif ($role==='guru'): ?>

<?php
/*
 * AKSES CEPAT — dibentuk dari role + capability tugas aktif.
 * Hanya presentasi/navigasi; tidak mengubah permission endpoint.
 */
$quickActions = [];
if ($role === 'guru') {
    $quickActions[] = ['class'=>'quick-action-primary','icon'=>'qr-code-scan','title'=>'Scan QR Absensi','text'=>'Buka scanner QR siswa','href'=>BASE_URL.'scanner/index.php'];
    if (function_exists('user_has_task_capability') && user_has_task_capability($pdo, 'piket')) {
        $manualHref = $todayAttendanceSessionId > 0
            ? BASE_URL.'absensi/daftar.php?sesi_id='.$todayAttendanceSessionId.'&menu=piket_verifikasi'
            : BASE_URL.'piket/index.php';
        $quickActions[] = ['class'=>'quick-action-success','icon'=>'person-check-fill','title'=>'Kehadiran Manual','text'=>'Verifikasi dan koreksi status siswa','href'=>$manualHref];
    } else {
        $attendanceHref = $todayAttendanceSessionId > 0
            ? BASE_URL.'absensi/daftar.php?sesi_id='.$todayAttendanceSessionId
            : BASE_URL.'absensi/index.php';
        $quickActions[] = ['class'=>'quick-action-success','icon'=>'calendar2-check-fill','title'=>'Kehadiran Hari Ini','text'=>'Lihat status kehadiran hari ini','href'=>$attendanceHref];
    }
}

if (in_array($role, ['guru','guru_bk','tu'], true) && function_exists('user_has_task_capability')) {
    if (user_has_task_capability($pdo, 'wali_kelas')) {
    }
    if (user_has_task_capability($pdo, 'ekstrakurikuler')) {
        $quickActions[] = ['class'=>'quick-action-teal','icon'=>'trophy-fill','title'=>'Absensi Ekstrakurikuler','text'=>'Catat kehadiran peserta kegiatan','href'=>BASE_URL.'ekstrakurikuler/absensi_index.php'];
    }
    if (user_has_task_capability($pdo, 'uks') && has_permission('uks')) {
        $quickActions[] = ['class'=>'quick-action-danger','icon'=>'heart-pulse-fill','title'=>'Kunjungan UKS','text'=>'Catat dan pantau kunjungan siswa','href'=>BASE_URL.'uks/kunjungan_tambah.php'];
    }
    if (user_has_task_capability($pdo, 'perpustakaan') && has_permission('perpustakaan')) {
        $quickActions[] = ['class'=>'quick-action-purple','icon'=>'people-fill','title'=>'Kunjungan Perpustakaan','text'=>'Catat kunjungan siswa','href'=>BASE_URL.'perpustakaan/pengunjung.php'];
        $quickActions[] = ['class'=>'quick-action-purple','icon'=>'book-half','title'=>'Peminjaman','text'=>'Kelola transaksi peminjaman','href'=>BASE_URL.'perpustakaan/peminjaman.php'];
        $quickActions[] = ['class'=>'quick-action-warning','icon'=>'arrow-return-left','title'=>'Pengembalian','text'=>'Proses buku yang dikembalikan','href'=>BASE_URL.'perpustakaan/pengembalian.php'];
    }
}
?>
<?php if ($waliPelanggaranNotifications): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head">
        <div><h3><span class="section-accent"></span><i class="bi bi-bell-fill"></i> Peringatan Siswa</h3><p>Siswa di kelas Anda yang telah mencapai ambang poin P1–P4 dan memerlukan perhatian.</p></div>
        <span class="dashboard-badge"><?=count($waliPelanggaranNotifications)?> siswa</span>
    </div>
    <div class="table-responsive"><table class="data-table"><thead><tr><th>Siswa</th><th>Poin Efektif</th><th>Tahap</th><th>Status Pembinaan</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($waliPelanggaranNotifications as $wn):
        $stage=(string)$wn['tahap'];
        $stageClass=['P1'=>'warn-p1','P2'=>'warn-p2','P3'=>'warn-p3','P4'=>'warn-p4'][$stage] ?? 'warn-normal';
        $stageIcon=['P1'=>'bi-bell','P2'=>'bi-exclamation-triangle','P3'=>'bi-exclamation-octagon','P4'=>'bi-shield-x'][$stage] ?? 'bi-check-circle';
        $pb=(string)($wn['pb_status']??'');
        $pbLabel=['direncanakan'=>'Perlu Tindak Lanjut','proses'=>'Sedang Dibina','selesai'=>'Selesai'][$pb] ?? 'Belum ada pembinaan';
    ?>
    <tr><td><strong><?=e($wn['nama'])?></strong><br><span class="muted"><?=e($wn['nis'])?> · <?=e($wn['kelas'])?></span></td><td><strong><?=number_format((int)$wn['poin_efektif'])?></strong> poin</td><td><span class="warn <?=e($stageClass)?>"><i class="bi <?=e($stageIcon)?>"></i> <?=e($stage)?></span></td><td><span class="muted"><?=e($pbLabel)?></span></td><td><a class="btn btn-secondary btn-sm" href="<?=BASE_URL?>pelanggaran/detail.php?id=<?=((int)$wn['id'])?>"><i class="bi bi-eye"></i> Lihat</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<style>.dashboard-badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#fef2f2;color:#b91c1c;font-size:12px;font-weight:800}.warn{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:850;padding:4px 7px;border-radius:999px}.warn-p1{background:#fef3c7;color:#92400e}.warn-p2{background:#ffedd5;color:#9a3412}.warn-p3{background:#fee2e2;color:#b91c1c}.warn-p4{background:#fce7f3;color:#9d174d}.warn-normal{background:#dcfce7;color:#166534}.muted{color:#64748b;font-size:12px}</style>
<?php endif; ?>

<?php if ($quickActions): ?>
<div class="dashboard-section dashboard-quick-access">
    <div class="dashboard-section-head">
        <div>
            <h3><span class="section-accent"></span>Akses Cepat Hari Ini</h3>
            <p>Fungsi yang paling relevan dengan tugas dan kegiatan Anda saat ini.</p>
        </div>
    </div>
    <div class="quick-action-grid">
        <?php foreach ($quickActions as $action): ?>
            <a class="quick-action <?=e($action['class'])?>" href="<?=e($action['href'])?>">
                <span class="quick-action-icon"><i class="bi bi-<?=e($action['icon'])?>"></i></span>
                <span class="quick-action-copy"><strong><?=e($action['title'])?></strong><small><?=e($action['text'])?></small></span>
                <i class="bi bi-arrow-up-right quick-action-arrow" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Yang Perlu Anda Perhatikan</h3><p>Hanya tugas dan informasi yang relevan dengan aktivitas Anda.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert <?= $todayPiket ? 'alert-success':'alert-info' ?>"><div class="alert-icon"><i class="bi bi-clipboard-check"></i></div><div><div class="alert-label">Piket Hari Ini</div><div class="alert-value"><?= $todayPiket ? 'YA':'—' ?></div><div class="alert-text"><?= $todayPiket ? 'Anda terjadwal sebagai petugas piket':'Tidak ada jadwal piket hari ini' ?></div></div></div>
        <div class="dashboard-alert <?= count($waliKelas)>0 ? 'alert-purple':'alert-info' ?>"><div class="alert-icon"><i class="bi bi-people-fill"></i></div><div><div class="alert-label">Wali Kelas</div><div class="alert-value"><?=count($waliKelas)?></div><div class="alert-text"><?=count($waliKelas)>0 ? e((string)($waliKelas[0]['target_id'] ?? 'Kelas terpilih')):'Tidak ada penugasan wali kelas'?></div></div></div>
        <div class="dashboard-alert <?= count($extraTasks)>0 ? 'alert-teal':'alert-info' ?>"><div class="alert-icon"><i class="bi bi-trophy-fill"></i></div><div><div class="alert-label">Ekstrakurikuler</div><div class="alert-value"><?=count($extraTasks)?></div><div class="alert-text">Penugasan ekstra semester aktif</div></div></div>
        <div class="dashboard-alert alert-warning"><div class="alert-icon"><i class="bi bi-calendar-check"></i></div><div><div class="alert-label">Absensi Hari Ini</div><div class="alert-value"><?=number_format($absensi['sudah'])?></div><div class="alert-text">Dari <?=number_format($totalSiswa)?> siswa tercatat</div></div></div>
    </div>
</div>
<?php elseif ($role==='guru_bk'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Pusat BK</h3><p>Ringkasan pekerjaan BK yang perlu diperhatikan.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert alert-purple"><div class="alert-icon"><i class="bi bi-chat-heart-fill"></i></div><div><div class="alert-label">Catatan Hari Ini</div><div class="alert-value"><?=number_format($bkToday)?></div><div class="alert-text">Catatan/konsultasi BK hari ini</div></div></div>
        <div class="dashboard-alert <?= $bkOpen>0 ? 'alert-warning':'alert-success' ?>"><div class="alert-icon"><i class="bi bi-hourglass-split"></i></div><div><div class="alert-label">BK Belum Selesai</div><div class="alert-value"><?=number_format($bkOpen)?></div><div class="alert-text">Status baru atau proses</div></div></div>
        <div class="dashboard-alert <?= $pelanggaranToday>0 ? 'alert-danger':'alert-success' ?>"><div class="alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="alert-label">Pelanggaran Hari Ini</div><div class="alert-value"><?=number_format($pelanggaranToday)?></div><div class="alert-text">Catatan pelanggaran siswa</div></div></div>
        <div class="dashboard-alert <?= $pembinaanOpen>0 ? 'alert-warning':'alert-success' ?>"><div class="alert-icon"><i class="bi bi-person-check-fill"></i></div><div><div class="alert-label">Pembinaan Berjalan</div><div class="alert-value"><?=number_format($pembinaanOpen)?></div><div class="alert-text">Direncanakan/proses</div></div></div>
    </div>
</div>
<?php elseif ($role==='siswa'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Ringkasan Saya</h3><p>Data kehadiran Anda pada Tahun Pelajaran aktif.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert alert-success"><div class="alert-icon"><i class="bi bi-check-circle-fill"></i></div><div><div class="alert-label">Hadir</div><div class="alert-value"><?=number_format($siswaPribadi['hadir'])?></div><div class="alert-text">Status hadir</div></div></div>
        <div class="dashboard-alert alert-warning"><div class="alert-icon"><i class="bi bi-clock-fill"></i></div><div><div class="alert-label">Terlambat</div><div class="alert-value"><?=number_format($siswaPribadi['terlambat'])?></div><div class="alert-text">Status terlambat</div></div></div>
        <div class="dashboard-alert alert-info"><div class="alert-icon"><i class="bi bi-file-earmark-text-fill"></i></div><div><div class="alert-label">Izin</div><div class="alert-value"><?=number_format($siswaPribadi['izin'])?></div><div class="alert-text">Status izin</div></div></div>
        <div class="dashboard-alert alert-danger"><div class="alert-icon"><i class="bi bi-x-circle-fill"></i></div><div><div class="alert-label">Alpa</div><div class="alert-value"><?=number_format($siswaPribadi['alpa'])?></div><div class="alert-text">Status alpa</div></div></div>
    </div>
</div>
<?php elseif ($role==='ortu'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Ringkasan Anak</h3><p>Hanya data anak yang terhubung dengan akun orang tua/wali ini.</p></div></div>
    <div class="dashboard-alert-grid">
        <div class="dashboard-alert alert-info"><div class="alert-icon"><i class="bi bi-people-fill"></i></div><div><div class="alert-label">Anak Terhubung</div><div class="alert-value"><?=count($ortuChildren)?></div><div class="alert-text">Relasi siswa pada akun ini</div></div></div>
        <div class="dashboard-alert alert-success"><div class="alert-icon"><i class="bi bi-shield-lock-fill"></i></div><div><div class="alert-label">Privasi</div><div class="alert-value">Aktif</div><div class="alert-text">Data dibatasi pada anak terhubung</div></div></div>
        <div class="dashboard-alert alert-warning"><div class="alert-icon"><i class="bi bi-calendar-range"></i></div><div><div class="alert-label">Tapel</div><div class="alert-value" style="font-size:18px"><?=e($tapelName)?></div><div class="alert-text">Semester <?=e($semesterName)?></div></div></div>
        <div class="dashboard-alert alert-purple"><div class="alert-icon"><i class="bi bi-clock-history"></i></div><div><div class="alert-label">Akses</div><div class="alert-value">Riwayat</div><div class="alert-text">Buka riwayat anak dari menu portal</div></div></div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($role,['guru','guru_bk','tu'],true) && $taskRows): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Tugas Tambahan Saya</h3><p>Penugasan aktif pada <?=e($tapelName)?> · <?=e($semesterName)?>.</p></div></div>
    <div class="task-list">
        <?php foreach ($taskRows as $t):
            $code=strtoupper((string)$t['kode']);
            $isToday = in_array($code,['GURU_PIKET','PETUGAS_PIKET'],true) ? $todayPiket : false;
            $icon = str_contains($code,'PIKET')?'clipboard-check-fill':(str_contains($code,'WALI')?'people-fill':(str_contains($code,'EKSTRA')||str_contains($code,'PRAMUKA')||str_contains($code,'PMR')||str_contains($code,'BASKET')||str_contains($code,'FUTSAL')?'trophy-fill':(str_contains($code,'LAB')?'beaker-fill':'briefcase-fill')));
        ?>
        <div class="task-card <?= $isToday ? 'task-today':'' ?>">
            <div class="task-icon"><i class="bi bi-<?=e($icon)?>"></i></div>
            <div><strong><?=e($t['nama'])?></strong><span><?=e((string)($t['kategori']??'Tugas Tambahan'))?><?= $t['target_id'] ? ' · Target #'.e((string)$t['target_id']):'' ?></span><?= $isToday ? '<span style="color:var(--role-primary);font-weight:800"><i class="bi bi-circle-fill" style="font-size:6px"></i> Bertugas hari ini</span>':'' ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif ($role==='guru' || $role==='guru_bk' || $role==='tu'): ?>
<div class="dashboard-section"><div class="dashboard-note note-role"><i class="bi bi-info-circle-fill"></i><div><strong>Belum ada tugas tambahan aktif</strong>Tidak ada penugasan tambahan pada semester aktif untuk akun ini. Jika seharusnya ada, periksa menu Tugas Guru pada akun pengelola.</div></div></div>
<?php endif; ?>

<?php if ($role==='admin' || $role==='tu'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Status Absensi Hari Ini</h3><p>Rekap ringkas <?=e($todayLabel)?>.</p></div></div>
    <div class="dashboard-grid-modern">
        <div class="modern-card"><div class="card-top"><span class="card-label">Hadir</span><span class="card-icon" style="color:var(--success);background:var(--success-bg)"><i class="bi bi-check-circle-fill"></i></span></div><div class="card-number"><?=number_format($absensi['hadir'])?></div><div class="card-meta">Status H</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Terlambat</span><span class="card-icon" style="color:var(--warning);background:var(--warning-bg)"><i class="bi bi-clock-fill"></i></span></div><div class="card-number"><?=number_format($absensi['terlambat'])?></div><div class="card-meta">Status T</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Izin / Sakit</span><span class="card-icon" style="color:var(--info);background:var(--info-bg)"><i class="bi bi-file-earmark-medical-fill"></i></span></div><div class="card-number"><?=number_format($absensi['izin']+$absensi['sakit'])?></div><div class="card-meta">Izin <?=number_format($absensi['izin'])?> · Sakit <?=number_format($absensi['sakit'])?></div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Alpa</span><span class="card-icon" style="color:var(--danger);background:var(--danger-bg)"><i class="bi bi-x-circle-fill"></i></span></div><div class="card-number"><?=number_format($absensi['alpa'])?></div><div class="card-meta">Perlu perhatian</div></div>
    </div>
</div>
<?php elseif ($role==='guru'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Informasi Kehadiran</h3><p>Ringkasan data absensi sekolah yang relevan untuk kegiatan hari ini.</p></div></div>
    <div class="dashboard-grid-modern">
        <div class="modern-card"><div class="card-top"><span class="card-label">Sudah Tercatat</span><span class="card-icon"><i class="bi bi-person-check-fill"></i></span></div><div class="card-number"><?=number_format($absensi['sudah'])?></div><div class="card-meta">dari <?=number_format($totalSiswa)?> siswa</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Belum Status</span><span class="card-icon" style="color:var(--warning);background:var(--warning-bg)"><i class="bi bi-person-exclamation"></i></span></div><div class="card-number"><?=number_format($absensi['belum'])?></div><div class="card-meta">belum tercatat hari ini</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Sesi Aktif</span><span class="card-icon" style="color:var(--success);background:var(--success-bg)"><i class="bi bi-activity"></i></span></div><div class="card-number"><?=number_format($absensi['aktif'])?></div><div class="card-meta">dari <?=number_format($absensi['sesi'])?> sesi</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Kehadiran Efektif</span><span class="card-icon"><i class="bi bi-graph-up-arrow"></i></span></div><div class="card-number"><?=number_format($absensi['persen'],1)?>%</div><div class="card-meta">hadir + terlambat</div></div>
    </div>
</div>
<?php elseif ($role==='guru_bk'): ?>
<div class="dashboard-section">
    <div class="dashboard-section-head"><div><h3><span class="section-accent"></span>Layanan Pendukung</h3><p>Informasi layanan yang terkait dengan pendampingan siswa.</p></div></div>
    <div class="dashboard-grid-modern">
        <div class="modern-card"><div class="card-top"><span class="card-label">Home Visit</span><span class="card-icon"><i class="bi bi-house-fill"></i></span></div><div class="card-number"><?=number_format($homeVisitPlan)?></div><div class="card-meta">rencana belum selesai</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">UKS Hari Ini</span><span class="card-icon" style="color:var(--danger);background:var(--danger-bg)"><i class="bi bi-heart-pulse-fill"></i></span></div><div class="card-number"><?=number_format($uksToday)?></div><div class="card-meta">kunjungan UKS</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Kunjungan Perpustakaan</span><span class="card-icon" style="color:var(--teal);background:var(--teal-bg)"><i class="bi bi-book-fill"></i></span></div><div class="card-number"><?=number_format($perpusToday)?></div><div class="card-meta">hari ini</div></div>
        <div class="modern-card"><div class="card-top"><span class="card-label">Pelanggaran Hari Ini</span><span class="card-icon" style="color:var(--danger);background:var(--danger-bg)"><i class="bi bi-exclamation-triangle-fill"></i></span></div><div class="card-number"><?=number_format($pelanggaranToday)?></div><div class="card-meta">catatan siswa</div></div>
    </div>
</div>
<?php elseif ($role==='siswa'): ?>
<div class="dashboard-section"><div class="dashboard-note note-role"><i class="bi bi-person-badge-fill"></i><div><strong><?=e($siswaInfo['nama']??user_name())?><?=!empty($siswaInfo['kelas'])?' · Kelas '.e($siswaInfo['kelas']):''?></strong>NIS <?=e($siswaInfo['nis']??'-')?> · <?=e($tapelName)?> · Semester <?=e($semesterName)?></div></div></div>
<?php elseif ($role==='ortu'): ?>
<div class="dashboard-section"><div class="dashboard-note note-role"><i class="bi bi-shield-lock-fill"></i><div><strong>Data anak terlindungi</strong>Dashboard hanya menampilkan ringkasan. Detail kehadiran, BK, pelanggaran, pembinaan, dan home visit tetap dibatasi pada anak yang terhubung ke akun Anda.</div></div></div>
<?php endif; ?>

<?php if ($role==='admin'): ?>
<div class="dashboard-section"><div class="dashboard-note note-role"><i class="bi bi-shield-check"></i><div><strong>Mode Administrator</strong>Anda dapat mengelola data, hak akses, penugasan, backup, restore, reset, dan pemeliharaan sistem sesuai permission yang tersedia.</div></div></div>
<?php endif; ?>

</section>
</main>
<?php require_once __DIR__ . '/layouts/footer.php'; ?>
