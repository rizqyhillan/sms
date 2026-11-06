<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$id = max(0, (int)($_GET['id'] ?? 0));
$siswa = siswa_find($pdo, $id);
if (!$siswa) {
    http_response_code(404);
    exit('Siswa tidak ditemukan.');
}

$tab = (string)($_GET['tab'] ?? 'profil');
$allowedTabs = ['profil','pendidikan','kehadiran','sholat'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'profil';
}

$st = $pdo->prepare("SELECT sk.*, mk.nama AS nama_kelas, mk.tingkat, tp.nama AS tahun_nama
    FROM siswa_kelas sk
    JOIN master_kelas mk ON mk.id = sk.kelas_id
    JOIN tahun_pelajaran tp ON tp.id = sk.tahun_pelajaran_id
    WHERE sk.siswa_id = ?
    ORDER BY tp.id DESC, sk.id DESC");
$st->execute([$id]);
$riwayat = $st->fetchAll();

$st = $pdo->prepare("SELECT ssr.*, tp.nama AS tahun_nama
    FROM siswa_status_riwayat ssr
    LEFT JOIN tahun_pelajaran tp ON tp.id = ssr.tahun_pelajaran_id
    WHERE ssr.siswa_id = ?
    ORDER BY ssr.tanggal_status DESC, ssr.id DESC");
$st->execute([$id]);
$statusRiwayat = $st->fetchAll();

$st = $pdo->prepare("SELECT sk.*, mk.nama AS nama_kelas, tp.nama AS tahun_nama
    FROM siswa_kelas sk
    JOIN master_kelas mk ON mk.id = sk.kelas_id
    JOIN tahun_pelajaran tp ON tp.id = sk.tahun_pelajaran_id
    WHERE sk.siswa_id = ? AND sk.status = 'aktif'
    ORDER BY tp.id DESC, sk.id DESC LIMIT 1");
$st->execute([$id]);
$currentPlacement = $st->fetch() ?: ($riwayat[0] ?? null);

$st = $pdo->prepare("SELECT COUNT(*) FROM absensi a JOIN sesi_absensi sa ON sa.id = a.sesi_id JOIN jenis_absensi ja ON ja.id = sa.jenis_absensi_id WHERE a.siswa_id = ? AND ja.kategori = 'kehadiran'");
$st->execute([$id]);
$totalKehadiran = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM absensi_sholat ash JOIN sesi_absensi sa ON sa.id = ash.sesi_id JOIN jenis_absensi ja ON ja.id = sa.jenis_absensi_id WHERE ash.siswa_id = ? AND ja.kategori = 'sholat'");
$st->execute([$id]);
$totalSholat = (int)$st->fetchColumn();

$kehadiranRows = [];
$sholatRows = [];
if ($tab === 'kehadiran') {
    $st = $pdo->prepare("SELECT a.status, a.metode_awal, a.sumber_terakhir, a.status_verifikasi, a.waktu_scan, a.keterangan,
            sa.tanggal, sa.nama_sesi, sa.jam_mulai, sa.jam_selesai
        FROM absensi a
        JOIN sesi_absensi sa ON sa.id = a.sesi_id
        JOIN jenis_absensi ja ON ja.id = sa.jenis_absensi_id
        WHERE a.siswa_id = ? AND ja.kategori = 'kehadiran'
        ORDER BY sa.tanggal DESC, sa.id DESC, a.id DESC
        LIMIT 100");
    $st->execute([$id]);
    $kehadiranRows = $st->fetchAll();
}
if ($tab === 'sholat') {
    $st = $pdo->prepare("SELECT ash.status, ash.metode, ash.waktu_scan, ash.keterangan,
            sa.tanggal, sa.nama_sesi, js.nama AS sholat_nama
        FROM absensi_sholat ash
        JOIN sesi_absensi sa ON sa.id = ash.sesi_id
        JOIN jenis_absensi ja ON ja.id = sa.jenis_absensi_id
        LEFT JOIN jenis_sholat js ON js.id = sa.jenis_sholat_id
        WHERE ash.siswa_id = ? AND ja.kategori = 'sholat'
        ORDER BY sa.tanggal DESC, sa.id DESC, ash.id DESC
        LIMIT 100");
    $st->execute([$id]);
    $sholatRows = $st->fetchAll();
}

$page_title = 'Detail Siswa';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

function detail_tab_url(int $id, string $tab): string {
    return BASE_URL . 'siswa/detail.php?id=' . $id . '&tab=' . urlencode($tab);
}
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Detail Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content siswa-detail-page">
    <div class="page-header">
        <div>
            <div style="margin-bottom:8px"><a class="btn btn-secondary btn-sm" href="<?=BASE_URL?>siswa/index.php"><i class="bi bi-arrow-left"></i> Kembali ke Data Siswa</a></div>
            <h1><?=e($siswa['nama'])?></h1>
            <p>NIS <?=e($siswa['nis'])?> · NISN <?=e((string)($siswa['nisn'] ?? '-'))?></p>
        </div>
        <div class="page-header-actions">
            <a class="btn btn-warning btn-action-edit" href="<?=BASE_URL?>siswa/edit.php?id=<?=$id?>"><i class="bi bi-pencil-square"></i> Edit</a>
            <a class="btn btn-info btn-action-qr" href="<?=BASE_URL?>siswa/qr.php?id=<?=$id?>"><i class="bi bi-qr-code"></i> QR</a>
                        <a class="btn btn-primary" href="<?=BASE_URL?>siswa/kartu.php?id=<?=$id?>"><i class="bi bi-person-vcard"></i> Kartu</a>
        </div>
    </div>

    <div class="dashboard-table-card" style="margin-bottom:20px">
        <div style="padding:20px 20px 10px">
            <div class="detail-grid">
                <div><small>Status Saat Ini</small><strong><?=e(ucfirst($siswa['status']))?></strong></div>
                <div><small>Kelas Saat Ini</small><strong><?=e((string)($currentPlacement['nama_kelas'] ?? '-'))?></strong></div>
                <div><small>Tahun Pelajaran</small><strong><?=e((string)($currentPlacement['tahun_nama'] ?? '-'))?></strong></div>
                <div><small>Jenis Kelamin</small><strong><?=e((string)($siswa['jk'] ?? '-'))?></strong></div>
                <div><small>Riwayat Kehadiran</small><strong><?=number_format($totalKehadiran)?> record</strong></div>
                <div><small>Riwayat Sholat</small><strong><?=number_format($totalSholat)?> record</strong></div>
            </div>
        </div>
    </div>

    <div class="dashboard-table-card">
        <div style="padding:0 20px">
            <nav style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-top:12px">
                <?php $tabs = ['profil'=>'Profil','pendidikan'=>'Riwayat Pendidikan','kehadiran'=>'Riwayat Kehadiran','sholat'=>'Riwayat Sholat']; foreach ($tabs as $key=>$label): ?>
                    <a href="<?=e(detail_tab_url($id,$key))?>" class="btn btn-sm siswa-tab-btn <?= $tab===$key ? 'is-active' : '' ?> siswa-tab-<?=$key?>" style="margin-bottom:10px"><i class="bi <?= $key==='profil'?'bi-person-vcard':($key==='pendidikan'?'bi-mortarboard':($key==='kehadiran'?'bi-calendar2-check':'bi-moon-stars')) ?>"></i> <?=e($label)?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if ($tab === 'profil'): ?>
            <div style="padding:20px">
                <div class="section-title" style="margin-top:0"><div><h2>Profil Siswa</h2><p>Identitas induk siswa. Data ini tetap satu meskipun siswa berganti Tapel atau kelas.</p></div></div>
                <div class="detail-grid">
                    <div><small>NIS</small><strong><?=e($siswa['nis'])?></strong></div>
                    <div><small>NISN</small><strong><?=e((string)($siswa['nisn'] ?? '-'))?></strong></div>
                    <div><small>Nama</small><strong><?=e($siswa['nama'])?></strong></div>
                    <div><small>Jenis Kelamin</small><strong><?=e((string)($siswa['jk'] ?? '-'))?></strong></div>
                    <div><small>No. HP</small><strong><?=e((string)($siswa['no_hp'] ?? '-'))?></strong></div>
                    <div><small>Kode QR</small><strong><code><?=e($siswa['kode_qr'])?></code></strong></div>
                    <div><small>Status</small><strong><?=e(ucfirst($siswa['status']))?></strong></div>
                    <div><small>Terdaftar</small><strong><?=e((string)$siswa['created_at'])?></strong></div>
                </div>
                <?php if ($statusRiwayat): ?>
                    <div class="section-title"><div><h2>Riwayat Status</h2><p>Pindah, keluar, dan lulus tetap tercatat tanpa menghapus identitas siswa.</p></div></div>
                    <div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Tapel</th><th>Status</th><th>Tujuan</th><th>Keterangan</th></tr></thead><tbody>
                    <?php foreach ($statusRiwayat as $r): ?><tr><td><?=e($r['tanggal_status'])?></td><td><?=e((string)($r['tahun_nama'] ?? '-'))?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e((string)($r['tujuan'] ?? '-'))?></td><td><?=e((string)($r['keterangan'] ?? '-'))?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        <?php elseif ($tab === 'pendidikan'): ?>
            <div style="padding:20px">
                <div class="section-title" style="margin-top:0"><div><h2>Riwayat Pendidikan</h2><p>Perjalanan siswa berdasarkan Tapel, kelas, dan status penempatannya.</p></div></div>
                <div class="table-responsive"><table class="data-table"><thead><tr><th>Tapel</th><th>Tingkat</th><th>Kelas</th><th>Status</th><th>Nomor Urut</th><th>Keterangan</th><th>Tanggal</th></tr></thead><tbody>
                <?php if (!$riwayat): ?><tr><td colspan="7" class="empty-state">Belum ada riwayat pendidikan.</td></tr><?php else: foreach ($riwayat as $r): ?><tr><td><?=e($r['tahun_nama'])?></td><td><?=e((string)$r['tingkat'])?></td><td><?=e($r['nama_kelas'])?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e((string)($r['nomor_urut'] ?? '-'))?></td><td><?=e((string)($r['keterangan'] ?? '-'))?></td><td><?=e($r['created_at'])?></td></tr><?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        <?php elseif ($tab === 'kehadiran'): ?>
            <div style="padding:20px">
                <div class="section-title" style="margin-top:0"><div><h2>Riwayat Kehadiran</h2><p>Maksimal 100 record terbaru ditampilkan. Data sumber QR dan hasil verifikasi tetap dibedakan.</p></div></div>
                <div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Sesi</th><th>Status</th><th>Metode Awal</th><th>Sumber Terakhir</th><th>Verifikasi</th><th>Waktu</th></tr></thead><tbody>
                <?php if (!$kehadiranRows): ?><tr><td colspan="7" class="empty-state">Belum ada riwayat kehadiran.</td></tr><?php else: foreach ($kehadiranRows as $r): ?><tr><td><?=e($r['tanggal'])?></td><td><?=e($r['nama_sesi'])?></td><td><strong><?=e($r['status'])?></strong></td><td><?=e($r['metode_awal'])?></td><td><?=e($r['sumber_terakhir'])?></td><td><?=e($r['status_verifikasi'])?></td><td><?=e((string)($r['waktu_scan'] ?? '-'))?></td></tr><?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        <?php else: ?>
            <div style="padding:20px">
                <div class="section-title" style="margin-top:0"><div><h2>Riwayat Sholat</h2><p>Status yang tersedia: SUDAH atau BERHALANGAN. Berhalangan hanya dapat dicatat untuk siswi oleh database.</p></div></div>
                <div class="table-responsive"><table class="data-table"><thead><tr><th>Tanggal</th><th>Sholat</th><th>Sesi</th><th>Status</th><th>Metode</th><th>Waktu</th><th>Keterangan</th></tr></thead><tbody>
                <?php if (!$sholatRows): ?><tr><td colspan="7" class="empty-state">Belum ada riwayat sholat.</td></tr><?php else: foreach ($sholatRows as $r): ?><tr><td><?=e($r['tanggal'])?></td><td><?=e((string)($r['sholat_nama'] ?? '-'))?></td><td><?=e($r['nama_sesi'])?></td><td><strong><?=e($r['status'])?></strong></td><td><?=e($r['metode'])?></td><td><?=e((string)($r['waktu_scan'] ?? '-'))?></td><td><?=e((string)($r['keterangan'] ?? '-'))?></td></tr><?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
