<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';

bk_require_access($pdo);

$q = trim((string)($_GET['q'] ?? ''));
$kelasId = max(0, (int)($_GET['kelas_id'] ?? 0));
$statusSiswa = (string)($_GET['status_siswa'] ?? 'aktif');
$statusCatatan = (string)($_GET['status_catatan'] ?? '');
$perPageInput = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
$perPage = in_array($perPageInput, [10, 20, 50, 100], true) ? $perPageInput : 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$kelasRows = $pdo->query("
    SELECT id, nama FROM master_kelas WHERE aktif=1
    ORDER BY tingkat, urutan, nama
")->fetchAll();

$guruRows = $pdo->query("
    SELECT id, nama FROM guru WHERE status='aktif'
    ORDER BY nama
")->fetchAll();

$stats = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM catatan_bk")->fetchColumn(),
    'baru' => (int)$pdo->query("SELECT COUNT(*) FROM catatan_bk WHERE status='baru'")->fetchColumn(),
    'proses' => (int)$pdo->query("SELECT COUNT(*) FROM catatan_bk WHERE status='proses'")->fetchColumn(),
    'selesai' => (int)$pdo->query("SELECT COUNT(*) FROM catatan_bk WHERE status='selesai'")->fetchColumn(),
];

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR cb.jenis LIKE ?)';
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like, $like);
}

if ($kelasId > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM siswa_kelas skf
        WHERE skf.siswa_id=s.id
          AND skf.kelas_id=?
          AND skf.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1)
          AND skf.status='aktif'
    )";
    $params[] = $kelasId;
}

if ($statusSiswa !== 'semua' && in_array($statusSiswa, ['aktif','lulus','nonaktif'], true)) {
    $where[] = 's.status=?';
    $params[] = $statusSiswa;
}

if ($statusCatatan !== '' && in_array($statusCatatan, ['baru','proses','selesai'], true)) {
    $where[] = 'cb.status=?';
    $params[] = $statusCatatan;
}

if (user_role() === 'guru_bk') {
    $guruId = bk_current_guru_id($pdo);
    $where[] = 'cb.guru_bk_id=?';
    $params[] = $guruId;
}

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM catatan_bk cb
    JOIN siswa s ON s.id=cb.siswa_id
    WHERE ".implode(' AND ', $where)
);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$pages = max(1, (int)ceil($totalRows / max(1, $perPage)));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        cb.id, cb.tanggal, cb.waktu, cb.jenis, cb.status, cb.guru_bk_id,
        s.id AS siswa_id, s.nis, s.nisn, s.nama, s.status AS status_siswa,
        mk.nama AS kelas,
        g.nama AS guru_bk
    FROM catatan_bk cb
    JOIN siswa s ON s.id=cb.siswa_id
    LEFT JOIN guru g ON g.id=cb.guru_bk_id
    LEFT JOIN siswa_kelas sk ON sk.id=(
        SELECT sk2.id
        FROM siswa_kelas sk2
        WHERE sk2.siswa_id=s.id
          AND sk2.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1)
          AND sk2.status='aktif'
        ORDER BY sk2.id DESC LIMIT 1
    )
    LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
    WHERE ".implode(' AND ', $where)."
    ORDER BY cb.tanggal DESC, cb.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

function bk_query(array $changes = []): string {
    $base = $_GET ?? [];
    unset($base['page']);
    foreach ($changes as $key => $value) {
        if ($value === null) unset($base[$key]);
        else $base[$key] = $value;
    }
    return http_build_query($base);
}

$page_title = 'Bimbingan Konseling';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar">
    <div class="topbar-title">Bimbingan Konseling</div>
    <div class="topbar-user"><?=e(user_name())?></div>
</header>

<section class="content">
<style>
.bk-v2 .bk-hero{background:linear-gradient(135deg,#111827,#1e293b);color:#fff;border-radius:18px;padding:24px;margin-bottom:18px;box-shadow:0 12px 30px rgba(15,23,42,.18)}
.bk-v2 .bk-hero h1{margin:0;font-size:25px;font-weight:850}.bk-v2 .bk-hero p{margin:7px 0 0;color:#cbd5e1}
.bk-v2 .bk-privacy{display:flex;gap:12px;align-items:flex-start;padding:13px 15px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;margin-bottom:18px}
.bk-v2 .bk-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}
.bk-v2 .bk-stat{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;display:flex;gap:13px;align-items:center}
.bk-v2 .bk-stat-icon{width:43px;height:43px;border-radius:11px;display:grid;place-items:center;font-size:19px;background:#eef2ff;color:#4338ca}
.bk-v2 .bk-stat-number{font-size:24px;font-weight:900;color:#0f172a}.bk-v2 .bk-stat-label{font-size:11px;color:#64748b;font-weight:700}
.bk-v2 .bk-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.bk-v2 .bk-filter label{margin:0}.bk-v2 .bk-filter .form-control{min-width:140px}
.bk-v2 .bk-table-card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px;margin-bottom:18px}
.bk-v2 .bk-table-card h2{margin:0;font-size:16px;font-weight:850}.bk-v2 .bk-table-card p{margin:4px 0 0;color:#64748b;font-size:12px}
.bk-v2 .bk-student{display:flex;align-items:center;gap:10px}.bk-v2 .bk-avatar{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:#ede9fe;color:#6d28d9;font-weight:900}
.bk-v2 .bk-name{font-weight:800}.bk-v2 .bk-meta{font-size:11px;color:#64748b;margin-top:2px}
.bk-v2 .bk-status{font-size:11px;font-weight:850;padding:5px 8px;border-radius:7px;display:inline-block}.bk-v2 .bk-status-baru{background:#dbeafe;color:#1d4ed8}.bk-v2 .bk-status-proses{background:#ffedd5;color:#c2410c}.bk-v2 .bk-status-selesai{background:#dcfce7;color:#15803d}
.bk-v2 .bk-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:15px}.bk-v2 .bk-pages{display:flex;gap:5px;flex-wrap:wrap}
.bk-v2 .bk-pages a{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#334155;font-weight:750;font-size:12px}.bk-v2 .bk-pages a.active{background:#1e293b;color:#fff;border-color:#1e293b}
@media(max-width:900px){.bk-v2 .bk-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.bk-v2 .bk-stats{grid-template-columns:1fr}.bk-v2 .bk-filter>*{width:100%}.bk-v2 .bk-filter .form-control{width:100%}}
</style>

<div class="bk-v2">
    <div class="bk-hero">
        <h1><i class="bi bi-chat-heart-fill"></i> Bimbingan Konseling</h1>
        <p>Pengelolaan layanan, riwayat, dan tindak lanjut BK secara privat.</p>
    </div>

    <div class="bk-privacy">
        <i class="bi bi-shield-lock-fill"></i>
        <div><strong>Data BK bersifat privat.</strong><br>Isi konseling hanya tersedia melalui modul BK dan tidak ditampilkan pada layar publik.</div>
    </div>

    <div class="bk-stats">
        <div class="bk-stat"><div class="bk-stat-icon"><i class="bi bi-journal-text"></i></div><div><div class="bk-stat-number"><?=number_format($stats['total'])?></div><div class="bk-stat-label">TOTAL CATATAN</div></div></div>
        <div class="bk-stat"><div class="bk-stat-icon"><i class="bi bi-stars"></i></div><div><div class="bk-stat-number"><?=number_format($stats['baru'])?></div><div class="bk-stat-label">BARU</div></div></div>
        <div class="bk-stat"><div class="bk-stat-icon"><i class="bi bi-hourglass-split"></i></div><div><div class="bk-stat-number"><?=number_format($stats['proses'])?></div><div class="bk-stat-label">DALAM PROSES</div></div></div>
        <div class="bk-stat"><div class="bk-stat-icon"><i class="bi bi-check2-circle"></i></div><div><div class="bk-stat-number"><?=number_format($stats['selesai'])?></div><div class="bk-stat-label">SELESAI</div></div></div>
    </div>

<?php if (user_role() === 'kepala_sekolah'): ?>
    <div class="bk-table-card">
        <div class="bk-privacy" style="margin:0">
            <i class="bi bi-shield-lock-fill"></i>
            <div><strong>Mode monitoring.</strong><br>Kepala Sekolah dapat melihat statistik BK. Detail siswa, jenis layanan, dan isi catatan konseling dibatasi untuk petugas yang berwenang.</div>
        </div>
    </div>
<?php else: ?>
    <div class="bk-table-card">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
            <div><h2>Riwayat Catatan BK</h2><p><?=number_format($totalRows)?> data sesuai filter.</p></div>
        </div>

        <form method="get" class="bk-filter">
            <label><span class="form-label">Cari</span><input type="search" name="q" value="<?=e($q)?>" class="form-control" placeholder="NIS / NISN / Nama / Jenis"></label>
            <label><span class="form-label">Kelas</span><select name="kelas_id" class="form-control"><option value="0">Semua Kelas</option><?php foreach($kelasRows as $k): ?><option value="<?=$k['id']?>" <?=$kelasId===(int)$k['id']?'selected':''?>><?=e($k['nama'])?></option><?php endforeach; ?></select></label>
            <label><span class="form-label">Status Siswa</span><select name="status_siswa" class="form-control"><option value="aktif" <?=$statusSiswa==='aktif'?'selected':''?>>Aktif</option><option value="lulus" <?=$statusSiswa==='lulus'?'selected':''?>>Lulus</option><option value="nonaktif" <?=$statusSiswa==='nonaktif'?'selected':''?>>Nonaktif</option><option value="semua" <?=$statusSiswa==='semua'?'selected':''?>>Semua</option></select></label>
            <label><span class="form-label">Status Catatan</span><select name="status_catatan" class="form-control"><option value="">Semua</option><option value="baru" <?=$statusCatatan==='baru'?'selected':''?>>Baru</option><option value="proses" <?=$statusCatatan==='proses'?'selected':''?>>Proses</option><option value="selesai" <?=$statusCatatan==='selesai'?'selected':''?>>Selesai</option></select></label>
            <label><span class="form-label">Per Halaman</span><select name="per_page" class="form-control"><?php foreach([10,20,50,100] as $n): ?><option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option><?php endforeach; ?></select></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel-fill"></i> Terapkan</button>
            <a class="btn btn-secondary" href="<?=BASE_URL?>bk/index.php"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        </form>

        <div class="table-responsive" style="margin-top:14px">
            <table class="data-table">
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Tanggal</th><th>Jenis</th><th>Guru BK</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if(!$notes): ?>
                    <tr><td colspan="7" class="empty-state">Belum ada catatan sesuai filter.</td></tr>
                <?php else: foreach($notes as $n): ?>
                    <tr>
                        <td><div class="bk-student"><div class="bk-avatar"><i class="bi bi-person-fill"></i></div><div><div class="bk-name"><?=e($n['nama'])?></div><div class="bk-meta"><?=e($n['nis'])?><?=!empty($n['nisn'])?' · '.e($n['nisn']):''?></div></div></div></td>
                        <td><?=e($n['kelas'] ?? '-')?></td>
                        <td><?=e($n['tanggal'])?><?=!empty($n['waktu'])?' '.e($n['waktu']):''?></td>
                        <td><?=e($n['jenis'])?></td>
                        <td><?=e($n['guru_bk'] ?? '-')?></td>
                        <td><span class="bk-status bk-status-<?=e($n['status'])?>"><?=e(ucfirst($n['status']))?></span></td>
                        <td style="white-space:nowrap">
                            <a class="btn btn-sm btn-secondary" title="Detail" href="<?=BASE_URL?>bk/catatan_detail.php?id=<?=$n['id']?>"><i class="bi bi-eye"></i></a>
                            <?php if (bk_can_manage_note($pdo, $n)): ?>
                            <a class="btn btn-sm btn-primary" title="Edit" href="<?=BASE_URL?>bk/catatan_edit.php?id=<?=$n['id']?>"><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($pages > 1): ?>
        <div class="bk-pagination">
            <div style="font-size:12px;color:#64748b">Halaman <?=$page?> dari <?=$pages?></div>
            <div class="bk-pages">
                <?php if($page>1): ?><a href="?<?=e(bk_query(['page'=>$page-1]))?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                <?php
                $start=max(1,$page-2); $end=min($pages,$page+2);
                for($p=$start;$p<=$end;$p++):
                ?><a class="<?=$p===$page?'active':''?>" href="?<?=e(bk_query(['page'=>$p]))?>"><?=$p?></a><?php endfor; ?>
                <?php if($page<$pages): ?><a href="?<?=e(bk_query(['page'=>$page+1]))?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
</section>
</main>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
