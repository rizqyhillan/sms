<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_permission('audit_log');

$today = date('Y-m-d');

$mulai = (string)($_GET['mulai'] ?? date('Y-m-d', strtotime('-6 days')));
$selesai = (string)($_GET['selesai'] ?? $today);
$modul = trim((string)($_GET['modul'] ?? ''));
$aksi = trim((string)($_GET['aksi'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
$cari = trim((string)($_GET['cari'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $mulai = date('Y-m-d', strtotime('-6 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) $selesai = $today;
if ($mulai > $selesai) [$mulai, $selesai] = [$selesai, $mulai];

$allowedPerPage = [10, 20, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, $allowedPerPage, true)) $perPage = 20;

$page = max(1, (int)($_GET['page'] ?? 1));

$modules = $pdo->query("
    SELECT DISTINCT modul
    FROM audit_log
    WHERE modul <> ''
    ORDER BY modul
")->fetchAll(PDO::FETCH_COLUMN);

$actions = $pdo->query("
    SELECT DISTINCT aksi
    FROM audit_log
    WHERE aksi <> ''
    ORDER BY aksi
")->fetchAll(PDO::FETCH_COLUMN);

$users = $pdo->query("
    SELECT id, nama, username
    FROM users
    ORDER BY nama, username
")->fetchAll();

$where = ['DATE(al.created_at) BETWEEN ? AND ?'];
$params = [$mulai, $selesai];

if ($modul !== '') {
    $where[] = 'al.modul = ?';
    $params[] = $modul;
}

if ($aksi !== '') {
    $where[] = 'al.aksi = ?';
    $params[] = $aksi;
}

if ($userId > 0) {
    $where[] = 'al.user_id = ?';
    $params[] = $userId;
}

if ($cari !== '') {
    $where[] = '(
        al.keterangan LIKE ?
        OR al.aksi LIKE ?
        OR al.modul LIKE ?
        OR u.nama LIKE ?
        OR u.username LIKE ?
        OR al.ip_address LIKE ?
    )';
    $q = '%' . $cari . '%';
    array_push($params, $q, $q, $q, $q, $q, $q);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSql
");
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        al.id,
        al.user_id,
        al.aksi,
        al.modul,
        al.referensi_id,
        al.keterangan,
        al.ip_address,
        al.user_agent,
        al.created_at,
        u.nama AS user_nama,
        u.username
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSql
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT al.user_id) AS pengguna,
        COUNT(DISTINCT al.modul) AS modul
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSql
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch() ?: ['total' => 0, 'pengguna' => 0, 'modul' => 0];

$page_title = 'Audit Log';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main">
<header class="topbar">
    <div class="topbar-title">Audit Log</div>
    <div class="topbar-user"><?=e(user_name())?></div>
</header>

<section class="content">

<style>
.audit-security-note{
    display:flex;
    gap:12px;
    align-items:flex-start;
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    padding:14px 16px;
    border-radius:12px;
    margin-bottom:18px;
}
.audit-security-note i{font-size:19px}
.audit-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:5px 9px;
    border-radius:7px;
    background:#eff6ff;
    color:#1d4ed8;
    font-size:11px;
    font-weight:800;
}
.audit-action{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:5px 8px;
    border-radius:7px;
    background:#f1f5f9;
    color:#334155;
    font-size:11px;
    font-weight:800;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
}
.audit-detail{
    max-width:380px;
    white-space:normal;
    line-height:1.45;
}
.audit-meta{
    margin-top:4px;
    color:#64748b;
    font-size:11px;
}
.audit-id{
    color:#64748b;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    font-size:11px;
}
.audit-user{
    font-weight:750;
}
.audit-user small{
    display:block;
    margin-top:2px;
    color:#64748b;
    font-weight:500;
}
.audit-ip{
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    font-size:11px;
    color:#475569;
}
.audit-ua{
    max-width:270px;
    color:#64748b;
    font-size:10px;
    line-height:1.35;
    word-break:break-word;
}
.audit-date{
    white-space:nowrap;
    font-size:12px;
}
.audit-filter-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}
.audit-filter-wide{
    grid-column:span 2;
}
@media(max-width:1000px){
    .audit-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .audit-filter-wide{grid-column:span 2}
}
@media(max-width:700px){
    .audit-filter-grid{grid-template-columns:1fr}
    .audit-filter-wide{grid-column:span 1}
}
</style>

<div class="page-header">
    <div>
        <h1><i class="bi bi-shield-check" style="margin-right:8px"></i>Audit Log</h1>
        <p>Riwayat aktivitas penting sistem. Data audit bersifat baca-saja dan tidak menyediakan aksi hapus.</p>
    </div>
</div>

<div class="audit-security-note">
    <i class="bi bi-shield-lock-fill"></i>
    <div>
        <strong>Catatan keamanan</strong>
        <div style="margin-top:3px">
            Audit mencatat pengguna, waktu, modul, aksi, referensi, alamat IP, dan informasi perangkat
            ketika data tersedia. Log tidak diubah melalui halaman ini.
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-label">Aktivitas Periode</div>
        <div class="card-number"><?=number_format((int)$summary['total'])?></div>
        <div class="card-description">Total catatan audit</div>
    </div>
    <div class="dashboard-card">
        <div class="card-label">Pengguna</div>
        <div class="card-number"><?=number_format((int)$summary['pengguna'])?></div>
        <div class="card-description">Pengguna yang tercatat</div>
    </div>
    <div class="dashboard-card">
        <div class="card-label">Modul</div>
        <div class="card-number"><?=number_format((int)$summary['modul'])?></div>
        <div class="card-description">Modul yang memiliki aktivitas</div>
    </div>
</div>

<div class="dashboard-table-card">
    <div class="section-title">
        <div>
            <h2>Filter Audit</h2>
            <p>Gunakan filter untuk menelusuri aktivitas tertentu.</p>
        </div>
    </div>

    <form method="get" class="filter-bar">
        <div class="audit-filter-grid" style="width:100%">
            <label>
                <span class="form-label">Mulai</span>
                <input type="date" name="mulai" value="<?=e($mulai)?>" class="form-control">
            </label>

            <label>
                <span class="form-label">Sampai</span>
                <input type="date" name="selesai" value="<?=e($selesai)?>" class="form-control">
            </label>

            <label>
                <span class="form-label">Modul</span>
                <select name="modul" class="form-control">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modules as $item): ?>
                        <option value="<?=e($item)?>" <?=$modul===$item?'selected':''?>><?=e($item)?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="form-label">Aksi</span>
                <select name="aksi" class="form-control">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($actions as $item): ?>
                        <option value="<?=e($item)?>" <?=$aksi===$item?'selected':''?>><?=e($item)?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="form-label">Pengguna</span>
                <select name="user_id" class="form-control">
                    <option value="0">Semua Pengguna</option>
                    <?php foreach ($users as $item): ?>
                        <option value="<?= (int)$item['id'] ?>" <?=$userId===(int)$item['id']?'selected':''?>>
                            <?=e($item['nama'])?> · <?=e($item['username'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="audit-filter-wide">
                <span class="form-label">Pencarian</span>
                <input type="search" name="cari" value="<?=e($cari)?>" class="form-control"
                       placeholder="Cari keterangan, aksi, modul, pengguna, atau IP...">
            </label>
        </div>

        <select name="per_page" class="form-control" style="max-width:120px">
            <?php foreach ($allowedPerPage as $n): ?>
                <option value="<?=$n?>" <?=$perPage===$n?'selected':''?>><?=$n?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">
            <i class="bi bi-funnel-fill"></i> Terapkan
        </button>

        <a class="btn btn-secondary" href="<?=BASE_URL?>audit_log/index.php">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
        </a>
    </form>
</div>

<div class="dashboard-table-card">
    <div class="section-title">
        <div>
            <h2>Riwayat Aktivitas</h2>
            <p>Menampilkan <?=$total ? ($offset + 1) . '–' . min($offset + $perPage, $total) : '0'?> dari <?=number_format($total)?> catatan.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th>No</th>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Modul</th>
                <th>Aksi</th>
                <th>Referensi</th>
                <th>Keterangan</th>
                <th>IP / Perangkat</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="bi bi-shield-x" style="font-size:28px;display:block;margin-bottom:8px"></i>
                        Tidak ada catatan audit sesuai filter.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><span class="audit-id">#<?= (int)$row['id'] ?></span></td>

                        <td class="audit-date">
                            <strong><?=e(date('d-m-Y', strtotime((string)$row['created_at'])))?></strong><br>
                            <span class="audit-meta"><?=e(date('H:i:s', strtotime((string)$row['created_at'])))?></span>
                        </td>

                        <td>
                            <?php if ($row['user_id']): ?>
                                <span class="audit-user"><?=e($row['user_nama'] ?? 'Pengguna tidak ditemukan')?></span>
                                <small><?=e($row['username'] ?? '')?></small>
                            <?php else: ?>
                                <span class="audit-user">Sistem / Tidak diketahui</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="audit-chip">
                                <i class="bi bi-grid-1x2-fill"></i>
                                <?=e($row['modul'])?>
                            </span>
                        </td>

                        <td>
                            <span class="audit-action"><?=e($row['aksi'])?></span>
                        </td>

                        <td>
                            <?php if ($row['referensi_id'] !== null): ?>
                                <span class="audit-id">#<?= (int)$row['referensi_id'] ?></span>
                            <?php else: ?>
                                <span class="audit-meta">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="audit-detail"><?=e($row['keterangan'])?></div>
                        </td>

                        <td>
                            <div class="audit-ip"><?=e($row['ip_address'] ?? '-')?></div>
                            <?php if (!empty($row['user_agent'])): ?>
                                <details style="margin-top:5px">
                                    <summary style="cursor:pointer;color:#2563eb;font-size:10px">Perangkat</summary>
                                    <div class="audit-ua" style="margin-top:4px"><?=e($row['user_agent'])?></div>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination-bar">
        <div class="text-muted">
            Halaman <?=$page?> dari <?=$totalPages?>
        </div>

        <div class="pagination">
            <?php
            $query = $_GET;
            for ($pg = 1; $pg <= $totalPages; $pg++):
                $query['page'] = $pg;
            ?>
                <a class="btn btn-sm <?=$pg===$page?'btn-primary':'btn-secondary'?>"
                   href="?<?=e(http_build_query($query))?>">
                    <?=$pg?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

</section>
</main>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
