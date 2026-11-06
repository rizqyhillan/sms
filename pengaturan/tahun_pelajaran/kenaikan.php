<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

$years = $pdo->query('SELECT id,nama,aktif,tanggal_mulai,tanggal_selesai FROM tahun_pelajaran ORDER BY id DESC')->fetchAll();
$classes = $pdo->query('SELECT id,nama,tingkat FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama')->fetchAll();
$sourceId = (int)($_REQUEST['source_id'] ?? 0);
$targetId = (int)($_REQUEST['target_id'] ?? 0);
$errors = [];
$success = '';
$preview = [];
$stats = ['total'=>0,'siap'=>0,'sudah_ada'=>0,'belum'=>0,'kelas9'=>0];

$source = $sourceId ? tp_find($pdo, $sourceId) : null;
$target = $targetId ? tp_find($pdo, $targetId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'preview' || $action === 'apply') {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $targetId = (int)($_POST['target_id'] ?? 0);
        $source = tp_find($pdo, $sourceId);
        $target = tp_find($pdo, $targetId);

        if (!$source || !$target) {
            $errors[] = 'Tahun pelajaran sumber/tujuan tidak valid.';
        } elseif ($sourceId === $targetId) {
            $errors[] = 'Tahun sumber dan tujuan harus berbeda.';
        } elseif (!empty($source['tanggal_mulai']) && !empty($target['tanggal_mulai']) && $target['tanggal_mulai'] <= $source['tanggal_mulai']) {
            $errors[] = 'Tahun tujuan harus lebih baru daripada tahun sumber.';
        }

        $map = [];
        foreach ($classes as $c) {
            $map[(int)$c['id']] = (int)($_POST['map'][(int)$c['id']] ?? 0);
        }

        if (!$errors) {
            $st = $pdo->prepare(
                'SELECT sk.id,sk.siswa_id,sk.kelas_id,s.nama,s.nis,s.status AS siswa_status,
                        mk.nama AS kelas,mk.tingkat
                 FROM siswa_kelas sk
                 JOIN siswa s ON s.id=sk.siswa_id
                 JOIN master_kelas mk ON mk.id=sk.kelas_id
                 WHERE sk.tahun_pelajaran_id=? AND sk.status="aktif" AND s.status="aktif"
                 ORDER BY mk.tingkat,mk.urutan,mk.nama,s.nama'
            );
            $st->execute([$sourceId]);
            $sourceRows = $st->fetchAll();

            $existing = $pdo->prepare('SELECT siswa_id,kelas_id FROM siswa_kelas WHERE tahun_pelajaran_id=?');
            $existing->execute([$targetId]);
            $existingMap = [];
            foreach ($existing->fetchAll() as $ex) {
                $existingMap[(int)$ex['siswa_id']] = (int)$ex['kelas_id'];
            }

            $classNames = [];
            foreach ($classes as $c) {
                $classNames[(int)$c['id']] = $c['nama'];
            }

            foreach ($sourceRows as $r) {
                $stats['total']++;
                $targetClass = $map[(int)$r['kelas_id']] ?? 0;
                $status = 'SIAP';
                $note = '';

                if ((int)$r['tingkat'] >= 9) {
                    $status = 'KELAS 9';
                    $note = 'Siswa kelas 9 tidak diproses melalui kenaikan kelas.';
                    $stats['kelas9']++;
                } elseif (!$targetClass) {
                    $status = 'BELUM DIPETAKAN';
                    $note = 'Belum ada kelas tujuan.';
                    $stats['belum']++;
                } elseif (!isset($classNames[$targetClass])) {
                    $status = 'KELAS TUJUAN TIDAK VALID';
                    $note = 'Kelas tujuan tidak aktif/tidak tersedia.';
                    $stats['belum']++;
                } elseif (isset($existingMap[(int)$r['siswa_id']])) {
                    $status = 'SUDAH ADA';
                    $note = 'Siswa sudah memiliki penempatan pada tahun tujuan.';
                    $stats['sudah_ada']++;
                } else {
                    $stats['siap']++;
                }

                $preview[] = $r + [
                    'target_class_id' => $targetClass,
                    'target_class' => $classNames[$targetClass] ?? '-',
                    'hasil' => $status,
                    'catatan' => $note,
                ];
            }

            if ($action === 'apply' && !$errors) {
                $eligible = array_values(array_filter(
                    $preview,
                    static fn(array $p): bool => $p['hasil'] === 'SIAP' && (int)$p['target_class_id'] > 0
                ));

                if (!$eligible) {
                    $errors[] = 'Tidak ada siswa yang siap diproses.';
                } else {
                    $inserted = 0;
                    $skipped = 0;
                    $pdo->beginTransaction();
                    try {
                        // Kunci seluruh penempatan aktif pada tahun tujuan agar nomor urut aman dari benturan.
                        $lock = $pdo->prepare('SELECT siswa_id,kelas_id,nomor_urut FROM siswa_kelas WHERE tahun_pelajaran_id=? AND status="aktif" FOR UPDATE');
                        $lock->execute([$targetId]);
                        $maxNomor = [];
                        $existingTarget = [];
                        foreach ($lock->fetchAll() as $row) {
                            $sid = (int)$row['siswa_id'];
                            $cid = (int)$row['kelas_id'];
                            $existingTarget[$sid] = $cid;
                            $num = (int)($row['nomor_urut'] ?? 0);
                            if ($num > ($maxNomor[$cid] ?? 0)) {
                                $maxNomor[$cid] = $num;
                            }
                        }

                        $ins = $pdo->prepare(
                            'INSERT INTO siswa_kelas
                             (siswa_id,tahun_pelajaran_id,kelas_id,nomor_urut,status,keterangan)
                             VALUES (?,?,?,?,"aktif",?)'
                        );

                        foreach ($eligible as $p) {
                            $sid = (int)$p['siswa_id'];
                            $cid = (int)$p['target_class_id'];

                            if (isset($existingTarget[$sid])) {
                                $skipped++;
                                continue;
                            }

                            $maxNomor[$cid] = ($maxNomor[$cid] ?? 0) + 1;
                            $ins->execute([
                                $sid,
                                $targetId,
                                $cid,
                                $maxNomor[$cid],
                                'Naik kelas dari '.($source['nama'] ?? '').' / '.($p['kelas'] ?? '')
                            ]);
                            $existingTarget[$sid] = $cid;
                            $inserted++;
                        }

                        $pdo->commit();
                        tp_audit(
                            $pdo,
                            'PROMOTION',
                            $targetId,
                            'Proses kenaikan kelas dari '.$source['nama'].' ke '.$target['nama'].'; berhasil '.$inserted.' siswa; dilewati '.$skipped
                        );
                        $success = 'Kenaikan kelas selesai. '.$inserted.' siswa berhasil diproses'.($skipped ? ', '.$skipped.' siswa dilewati karena sudah memiliki penempatan.' : '').'.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }
            }
        }
    }
}

$page_title = 'Kenaikan Kelas';
require_once __DIR__ . '/../../layouts/header.php';
require_once __DIR__ . '/../../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Kenaikan Kelas</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content kenaikan-page">
<div class="page-header">
    <div><h1><i class="bi bi-arrow-up-circle"></i> Proses Kenaikan Kelas</h1><p>Petakan kelas sumber ke kelas tujuan, periksa preview, lalu simpan. Riwayat tahun lama tidak dihapus.</p></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($err)?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<div class="form-card kenaikan-form-card">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="preview">
<div class="form-grid">
<label>Tahun Sumber *<select class="form-control" name="source_id" required><option value="">- Pilih -</option><?php foreach ($years as $y): ?><option value="<?=$y['id']?>" <?=$sourceId === (int)$y['id'] ? 'selected' : ''?>><?=e($y['nama'])?><?=$y['aktif'] ? ' (aktif)' : ''?></option><?php endforeach; ?></select></label>
<label>Tahun Tujuan *<select class="form-control" name="target_id" required><option value="">- Pilih -</option><?php foreach ($years as $y): ?><option value="<?=$y['id']?>" <?=$targetId === (int)$y['id'] ? 'selected' : ''?>><?=e($y['nama'])?><?=$y['aktif'] ? ' (aktif)' : ''?></option><?php endforeach; ?></select></label>
</div>
<div class="section-title mt-4"><div><h2>Pemetaan Kelas</h2><p>Contoh: 7A → 8A dan 8A → 9A. Kelas 9 tidak diproses otomatis.</p></div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Kelas Sumber</th><th>Tingkat</th><th>Kelas Tujuan</th></tr></thead><tbody>
<?php foreach ($classes as $c): if ((int)$c['tingkat'] >= 9) continue; ?>
<tr><td><strong><?=e($c['nama'])?></strong></td><td><?=intval($c['tingkat'])?></td><td><select class="form-control" name="map[<?=$c['id']?>]"><option value="0">- Tidak dipetakan -</option><?php foreach ($classes as $t): if ((int)$t['tingkat'] !== ((int)$c['tingkat'] + 1)) continue; ?><option value="<?=$t['id']?>" <?=isset($_POST['map'][$c['id']]) && (int)$_POST['map'][$c['id']] === (int)$t['id'] ? 'selected' : ''?>><?=e($t['nama'])?></option><?php endforeach; ?></select></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="form-actions"><button class="btn btn-primary"><i class="bi bi-eye"></i> Tampilkan Preview</button></div>
</form>
</div>

<?php if ($preview): ?>
<div class="dashboard-grid mt-4 kenaikan-stats">
<div class="dashboard-card kenaikan-stat kenaikan-stat-total"><div class="card-label">Total</div><div class="card-number"><?=number_format($stats['total'])?></div><div class="card-description">Siswa aktif di tahun sumber</div></div>
<div class="dashboard-card kenaikan-stat kenaikan-stat-ready"><div class="card-label">Siap</div><div class="card-number"><?=number_format($stats['siap'])?></div><div class="card-description">Akan diproses</div></div>
<div class="dashboard-card kenaikan-stat kenaikan-stat-existing"><div class="card-label">Sudah Ada</div><div class="card-number"><?=number_format($stats['sudah_ada'])?></div><div class="card-description">Tidak dibuat ulang</div></div>
<div class="dashboard-card kenaikan-stat kenaikan-stat-attention"><div class="card-label">Perlu Perhatian</div><div class="card-number"><?=number_format($stats['belum'] + $stats['kelas9'])?></div><div class="card-description">Belum dipetakan / kelas 9</div></div>
</div>
<div class="dashboard-table-card mt-4 kenaikan-preview-card">
<div class="section-title"><div><h2>Preview Kenaikan</h2><p><?=count($preview)?> siswa dari <?=e((string)($source['nama'] ?? ''))?>. Tidak ada data yang berubah sebelum tombol proses ditekan.</p></div></div>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Nama</th><th>NIS</th><th>Kelas Lama</th><th>Kelas Baru</th><th>Hasil</th><th>Catatan</th></tr></thead><tbody>
<?php foreach ($preview as $p): ?>
<tr><td><?=e($p['nama'])?></td><td><?=e($p['nis'])?></td><td><?=e($p['kelas'])?></td><td><?=e($p['target_class'])?></td><td><span class="status-badge <?=$p['hasil'] === 'SIAP' ? 'status-aktif' : 'status-nonaktif'?>"><?=e($p['hasil'])?></span></td><td><?=e($p['catatan'] ?: '-')?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($target && $stats['siap'] > 0): ?>
<form method="post" class="mt-3" onsubmit="return confirm('Yakin memproses siswa yang berstatus SIAP? Data tahun lama tidak akan dihapus.')">
<?=csrf_field()?><input type="hidden" name="action" value="apply"><input type="hidden" name="source_id" value="<?=$sourceId?>"><input type="hidden" name="target_id" value="<?=$targetId?>">
<?php foreach ($classes as $c): ?><input type="hidden" name="map[<?=$c['id']?>]" value="<?=isset($_POST['map'][$c['id']]) ? (int)$_POST['map'][$c['id']] : 0?>"><?php endforeach; ?>
<button class="btn btn-success"><i class="bi bi-check2-circle"></i> Proses <?=number_format($stats['siap'])?> Siswa</button>
</form>
<?php endif; ?>
</div>
<?php endif; ?>
</section></main>
<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
