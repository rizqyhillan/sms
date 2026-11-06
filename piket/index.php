<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
piket_require();
$taskCodes = task_registry_capability_codes('piket');
$taskPh = implode(',', array_fill(0, count($taskCodes), '?'));

$tp = active_tapel_id($pdo);
$sem = active_semester_id($pdo, $tp);
$gid = user_guru_id();
$today = (new DateTimeImmutable('now'))->format('Y-m-d');

$tasks = [];
if ($gid && $tp) {
    $st = $pdo->prepare("SELECT tg.id,tg.keterangan,jt.nama AS jenis_nama,tp.nama AS tapel_nama
        FROM tugas_guru tg
        INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        INNER JOIN tahun_pelajaran tp ON tp.id=tg.tahun_pelajaran_id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode IN ($taskPh)
          AND jt.aktif=1 AND tg.aktif=1
        ORDER BY tg.id");
    $st->execute(array_merge([$gid,$tp,$sem], $taskCodes));
    $tasks = $st->fetchAll();
}

$session = absensi_get_or_create_hari_ini($pdo);
$sessionSummary = [
    'total' => 0,
    'tercatat' => 0,
    'diverifikasi' => 0,
    'belum' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'izin' => 0,
    'sakit' => 0,
    'alpa' => 0,
];

if ($session) {
    $st = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(a.id IS NOT NULL) AS tercatat,
        SUM(a.id IS NOT NULL AND a.status_verifikasi IN ('valid','dikoreksi')) AS diverifikasi,
        SUM(a.id IS NULL OR a.status_verifikasi='belum') AS belum,
        SUM(a.status='H') AS hadir,
        SUM(a.status='T') AS terlambat,
        SUM(a.status='I') AS izin,
        SUM(a.status='S') AS sakit,
        SUM(a.status='A') AS alpa
        FROM siswa s
        LEFT JOIN siswa_kelas sk ON sk.id=(
            SELECT sk2.id FROM siswa_kelas sk2
            WHERE sk2.siswa_id=s.id
              AND sk2.tahun_pelajaran_id=?
              AND sk2.status='aktif'
            ORDER BY sk2.id DESC LIMIT 1
        )
        LEFT JOIN absensi a ON a.sesi_id=? AND a.siswa_id=s.id
        WHERE s.status='aktif'");
    $st->execute([(int)$session['tahun_pelajaran_id'],(int)$session['id']]);
    $row = $st->fetch();
    if ($row) {
        foreach ($sessionSummary as $key => $_) {
            $sessionSummary[$key] = (int)($row[$key] ?? 0);
        }
    }
}

$page_title='Piket Hari Ini';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main guru-piket-page">
<header class="topbar">
    <div class="topbar-title">Piket Hari Ini</div>
    <div class="topbar-user"><?=e(user_name())?></div>
</header>
<section class="content">
    <div class="page-header">
        <div>
            <h1>Piket Hari Ini</h1>
            <p>Panel operasional guru piket untuk verifikasi kehadiran dan pemantauan izin keluar.</p>
        </div>
        <div class="page-header-actions">
            <?php if ($session): ?>
                <a class="btn btn-primary" href="<?=BASE_URL?>absensi/daftar.php?sesi_id=<?=(int)$session['id']?>&menu=piket_verifikasi">
                    <i class="bi bi-person-check-fill"></i> Buka Verifikasi
                </a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?=BASE_URL?>izin/index.php?menu=piket_izin">
                <i class="bi bi-box-arrow-right"></i> Izin Keluar
            </a>
        </div>
    </div>

    <div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:18px">
        <div class="stat-card stat-task"><div class="stat-icon"><i class="bi bi-person-badge-fill"></i></div><div><div class="stat-label">Penugasan Aktif</div><div class="stat-value"><?=count($tasks)?></div></div></div>
        <div class="stat-card stat-student"><div class="stat-icon"><i class="bi bi-people-fill"></i></div><div><div class="stat-label">Siswa</div><div class="stat-value"><?=$session ? $sessionSummary['total'] : 0?></div></div></div>
        <div class="stat-card stat-qr"><div class="stat-icon"><i class="bi bi-qr-code"></i></div><div><div class="stat-label">QR Tercatat</div><div class="stat-value"><?=$session ? $sessionSummary['tercatat'] : 0?></div></div></div>
        <div class="stat-card stat-pending"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-label">Belum Diverifikasi</div><div class="stat-value"><?=$session ? $sessionSummary['belum'] : 0?></div></div></div>
    </div>

    <div class="dashboard-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr)">
        <div class="form-card">
            <div class="section-title">
                <div>
                    <h2>Verifikasi Kehadiran</h2>
                    <p>QR adalah pencatatan awal. Guru piket mencocokkan kondisi nyata siswa di kelas.</p>
                </div>
                <?php if ($session): ?>
                    <?php $verifikasiAktif = $session ? absensi_piket_aktif_pada_tanggal($pdo,(string)$session['tanggal']) : false; ?><span class="status-badge <?=($session && $session['status']==='aktif')?'status-aktif':($verifikasiAktif?'status-aktif':'status-ditutup')?>"><?=($session && $session['status']==='aktif')?'Sesi scan aktif':($verifikasiAktif?'Verifikasi piket aktif':'Sesi ditutup')?></span>
                <?php endif; ?>
            </div>

            <?php if ($session): ?>
                <div class="detail-grid" style="margin-top:16px">
                    <div><small>Tanggal</small><strong><?=e($session['tanggal'])?></strong></div>
                    <div><small>Sesi</small><strong><?=e($session['nama_sesi'])?></strong></div>
                    <div><small>Jam</small><strong><?=e(substr((string)$session['jam_mulai'],0,5))?>–<?=e(substr((string)$session['jam_selesai'],0,5))?></strong></div>
                    <div><small>Status</small><strong><?=e(ucfirst((string)$session['status']))?></strong></div>
                </div>
                <div class="dashboard-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:18px">
                    <div><small>Hadir</small><strong><?= $sessionSummary['hadir'] ?></strong></div>
                    <div><small>Terlambat</small><strong><?= $sessionSummary['terlambat'] ?></strong></div>
                    <div><small>Izin</small><strong><?= $sessionSummary['izin'] ?></strong></div>
                    <div><small>Sakit</small><strong><?= $sessionSummary['sakit'] ?></strong></div>
                    <div><small>Alpa</small><strong><?= $sessionSummary['alpa'] ?></strong></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <a class="btn btn-primary" href="<?=BASE_URL?>absensi/daftar.php?sesi_id=<?=(int)$session['id']?>&menu=piket_verifikasi">Verifikasi per Siswa</a>
                </div>
                <?php
                $kelasHariIni=[];
                if($session){
                    $ks=$pdo->prepare("SELECT mk.id,mk.nama,mk.tingkat,mk.urutan,COUNT(DISTINCT s.id) total,COALESCE(SUM(a.status_verifikasi IN ('valid','dikoreksi')),0) diverifikasi
                        FROM master_kelas mk
                        LEFT JOIN siswa_kelas sk ON sk.kelas_id=mk.id AND sk.tahun_pelajaran_id=? AND sk.status='aktif'
                        LEFT JOIN siswa s ON s.id=sk.siswa_id AND s.status='aktif'
                        LEFT JOIN absensi a ON a.sesi_id=? AND a.siswa_id=s.id
                        WHERE mk.aktif=1 GROUP BY mk.id,mk.nama,mk.tingkat,mk.urutan ORDER BY mk.tingkat,mk.urutan,mk.nama");
                    $ks->execute([(int)$session['tahun_pelajaran_id'],(int)$session['id']]);$kelasHariIni=$ks->fetchAll();
                }
                ?>
                <?php if($kelasHariIni): ?><div style="margin-top:18px"><div style="font-weight:800;margin-bottom:10px">Verifikasi Per Kelas</div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px"><?php foreach($kelasHariIni as $k): $done=(int)$k['total']>0 && (int)$k['diverifikasi']>=(int)$k['total']; ?><a href="<?=BASE_URL?>absensi/daftar.php?sesi_id=<?=(int)$session['id']?>&kelas_id=<?=(int)$k['id']?>&menu=piket_verifikasi" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid <?= $done?'#86efac':'#e2e8f0' ?>;border-radius:12px;background:<?= $done?'#f0fdf4':'#fff' ?>;color:#0f172a;text-decoration:none"><span><strong><?=e($k['nama'])?></strong><small style="display:block;color:#64748b;margin-top:2px"><?= (int)$k['diverifikasi'] ?>/<?= (int)$k['total'] ?> terverifikasi</small></span><span><?= $done?'🟢':'🟡' ?></span></a><?php endforeach; ?></div></div><?php endif; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:28px 8px">Belum ada sesi kehadiran untuk hari ini. Periksa Jadwal/Kalender Kehadiran jika hari ini seharusnya merupakan hari sekolah.</div>
            <?php endif; ?>
        </div>

        <div class="form-card">
            <div class="section-title">
                <div>
                    <h2>Penugasan Piket</h2>
                    <p>Penugasan guru piket mengikuti <strong>hari</strong>. Guru piket dapat memverifikasi seluruh kelas selama jam pembelajaran masih aktif.</p>
                </div>
            </div>
            <?php if (!$tasks): ?>
                <div class="empty-state" style="padding:28px 8px">Tidak ada penugasan piket aktif.</div>
            <?php else: ?>
                <div style="display:grid;gap:12px;margin-top:12px">
                    <?php foreach ($tasks as $task): ?>
                        <div style="padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc">
                            <strong><?=e($task['jenis_nama'])?></strong>
                            <div style="margin-top:5px;color:#64748b;font-size:13px"><?=e($task['tapel_nama'])?></div>
                            <?php if (!empty($task['keterangan'])): ?>
                                <div style="margin-top:6px;font-size:13px;color:#475569"><?=e($task['keterangan'])?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-card" style="margin-top:18px">
        <div class="section-title">
            <div>
                <h2>Alur Operasional</h2>
                <p>Scan adalah pencatatan awal. Verifikasi guru piket tetap dapat dilakukan setelah sesi scan ditutup selama jam pembelajaran masih aktif.</p>
            </div>
        </div>
        <div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-top:12px">
            <div><strong>1. QR</strong><p class="text-muted">Siswa melakukan scan. Record QR menjadi pencatatan awal.</p></div>
            <div><strong>2. Cek kelas</strong><p class="text-muted">Guru piket mencocokkan siswa yang benar-benar hadir.</p></div>
            <div><strong>3. Verval</strong><p class="text-muted">Ubah menjadi H, T, I, S, atau A bila kondisi berbeda.</p></div>
            <div><strong>4. Catatan</strong><p class="text-muted">Izin dan sakit memiliki pilihan alasan serta keterangan tambahan.</p></div>
        </div>
    </div>
</section>
</main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
