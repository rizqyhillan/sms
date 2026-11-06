<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
require_once __DIR__ . '/../includes/task_access.php';
require_once __DIR__ . '/../includes/task_registry.php';
tugas_require_admin();

$years = $pdo->query("SELECT id,nama,aktif FROM tahun_pelajaran ORDER BY tanggal_mulai DESC,id DESC")->fetchAll();
$activeYear = 0;
foreach ($years as $y) { if ((int)$y['aktif'] === 1) { $activeYear = (int)$y['id']; break; } }
$semesters = $pdo->query("SELECT id,tahun_pelajaran_id,nama,aktif FROM semester ORDER BY tahun_pelajaran_id DESC,id")->fetchAll();
$error = '';
$success = '';

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
$editSchedule = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $action = (string)($_POST['action'] ?? 'save_task');
    try {
        if ($action === 'save_piket_day') {
            $piketJenis = (int)($_POST['piket_jenis_tugas_id'] ?? 0);
            $piketTapel = (int)($_POST['piket_tahun_pelajaran_id'] ?? 0);
            $piketSemester = (int)($_POST['piket_semester_id'] ?? 0);
            $piketHari = (int)($_POST['piket_hari'] ?? 0);
            $piketGuru = $_POST['piket_guru_id'] ?? [];
            if (!is_array($piketGuru)) $piketGuru = [];
            $piketGuru = array_values(array_unique(array_filter(array_map('intval', $piketGuru), fn($id) => $id > 0)));
            // Guru Piket mengikuti jam sekolah/KBM; tidak ada pengaturan jam per tugas piket.
            if ($piketTapel < 1 || $piketSemester < 1 || $piketHari < 1 || $piketHari > 7) throw new RuntimeException('Tahun Pelajaran, Semester, dan hari piket wajib dipilih.');
            $qjt = $pdo->prepare("SELECT id,kode FROM jenis_tugas WHERE id=? AND aktif=1 AND kode IN ('GURU_PIKET','PETUGAS_PIKET') LIMIT 1");
            $qjt->execute([$piketJenis]);
            $piketJenisRow = $qjt->fetch(PDO::FETCH_ASSOC);
            if (!$piketJenisRow) throw new RuntimeException('Jenis tugas piket tidak valid.');
            $qsem = $pdo->prepare('SELECT id FROM semester WHERE id=? AND tahun_pelajaran_id=? LIMIT 1');
            $qsem->execute([$piketSemester, $piketTapel]);
            if (!$qsem->fetchColumn()) throw new RuntimeException('Semester tidak sesuai dengan Tahun Pelajaran.');
            if ($piketGuru) {
                $ph = implode(',', array_fill(0, count($piketGuru), '?'));
                $qg = $pdo->prepare("SELECT id FROM guru WHERE status='aktif' AND id IN ($ph)");
                $qg->execute($piketGuru);
                $validGuru = array_map('intval', $qg->fetchAll(PDO::FETCH_COLUMN));
                if (count($validGuru) !== count($piketGuru)) throw new RuntimeException('Daftar guru piket tidak valid.');
            }
            $pdo->beginTransaction();
            // Hari yang dipilih menjadi sumber kebenaran: lepaskan dulu hari tersebut dari penugasan piket aktif pada periode ini.
            $qold = $pdo->prepare("SELECT tg.id FROM tugas_guru tg WHERE tg.jenis_tugas_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND tg.target_tipe IS NULL AND tg.target_id IS NULL");
            $qold->execute([$piketJenis, $piketTapel, $piketSemester]);
            $oldIds = array_map('intval', $qold->fetchAll(PDO::FETCH_COLUMN));
            if ($oldIds) {
                $phOld = implode(',', array_fill(0, count($oldIds), '?'));
                $delDay = $pdo->prepare("DELETE FROM tugas_guru_jadwal WHERE tugas_guru_id IN ($phOld) AND hari=?");
                $delDay->execute(array_merge($oldIds, [$piketHari]));
            }
            foreach ($piketGuru as $gid) {
                $qtask = $pdo->prepare("SELECT id FROM tugas_guru WHERE guru_id=? AND jenis_tugas_id=? AND tahun_pelajaran_id=? AND semester_id=? AND target_tipe IS NULL AND target_id IS NULL ORDER BY aktif DESC,id DESC LIMIT 1");
                $qtask->execute([$gid,$piketJenis,$piketTapel,$piketSemester]);
                $taskId = (int)($qtask->fetchColumn() ?: 0);
                if ($taskId > 0) {
                    $up = $pdo->prepare('UPDATE tugas_guru SET aktif=1 WHERE id=?');
                    $up->execute([$taskId]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO tugas_guru(guru_id,jenis_tugas_id,tahun_pelajaran_id,semester_id,target_tipe,target_id,mulai,selesai,aktif,keterangan) VALUES(?,?,?,?,NULL,NULL,NULL,NULL,1,NULL)");
                    $ins->execute([$gid,$piketJenis,$piketTapel,$piketSemester]);
                    $taskId = (int)$pdo->lastInsertId();
                }
                $insDay = $pdo->prepare("INSERT INTO tugas_guru_jadwal(tugas_guru_id,hari,jam_mulai,jam_selesai,aktif) VALUES(?,?,NULL,NULL,1) ON DUPLICATE KEY UPDATE jam_mulai=NULL,jam_selesai=NULL,aktif=1");
                $insDay->execute([$taskId,$piketHari]);
            }
            // Penugasan yang sudah tidak memiliki hari aktif dinonaktifkan; histori tetap tersimpan.
            if ($oldIds) {
                $phOld = implode(',', array_fill(0, count($oldIds), '?'));
                $cleanup = $pdo->prepare("UPDATE tugas_guru tg SET tg.aktif=0 WHERE tg.id IN ($phOld) AND NOT EXISTS (SELECT 1 FROM tugas_guru_jadwal j WHERE j.tugas_guru_id=tg.id AND j.aktif=1)");
                $cleanup->execute($oldIds);
            }
            $pdo->commit();
            redirect('tugas_guru/index.php?piket_tapel=' . $piketTapel . '&piket_semester=' . $piketSemester . '&piket_hari=' . $piketHari . '#piket-bulk');
        }

        if ($action === 'delete_task') {
            $taskId = (int)($_POST['id'] ?? 0);
            if ($taskId < 1) throw new RuntimeException('Penugasan tidak valid.');
            $chk = $pdo->prepare("SELECT id FROM tugas_guru WHERE id=? LIMIT 1");
            $chk->execute([$taskId]);
            if (!$chk->fetchColumn()) throw new RuntimeException('Penugasan tidak ditemukan.');
            $del = $pdo->prepare("DELETE FROM tugas_guru WHERE id=?");
            $del->execute([$taskId]);
            redirect('tugas_guru/index.php');
        }

        if ($action === 'toggle_task') {
            $taskId = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("UPDATE tugas_guru SET aktif=IF(aktif=1,0,1) WHERE id=?");
            $st->execute([$taskId]);
            redirect('tugas_guru/index.php');
        }

        $taskId = (int)($_POST['id'] ?? 0);
        $guru = (int)($_POST['guru_id'] ?? 0);
        $jenis = (int)($_POST['jenis_tugas_id'] ?? 0);
        $tapel = (int)($_POST['tahun_pelajaran_id'] ?? 0);
        $semester = (int)($_POST['semester_id'] ?? 0);
        $targetType = trim((string)($_POST['target_tipe'] ?? ''));
        $targetId = (int)($_POST['target_id'] ?? 0);
        $ket = trim((string)($_POST['keterangan'] ?? ''));

        $st = $pdo->prepare("SELECT id FROM guru WHERE id=? AND status='aktif'");
        $st->execute([$guru]);
        $jt = $pdo->prepare("SELECT id,kode FROM jenis_tugas WHERE id=? AND aktif=1");
        $jt->execute([$jenis]);
        $jr = $jt->fetch();
        $sem = $pdo->prepare("SELECT id FROM semester WHERE id=? AND tahun_pelajaran_id=?");
        $sem->execute([$semester, $tapel]);
        $semOk = $sem->fetchColumn();
        $expectedTargetType = $jr ? task_default_target_type((string)$jr['kode']) : null;
        $isPiket = $jr && in_array((string)$jr['kode'], ['GURU_PIKET','PETUGAS_PIKET'], true);
        $days = $_POST['hari'] ?? [];
        if (!is_array($days)) $days = [];
        $days = array_values(array_unique(array_filter(array_map('intval', $days), fn($d) => $d >= 1 && $d <= 7)));
        // Untuk Guru Piket, jam selalu mengikuti jam sekolah/KBM.
        $jamMulai = '';
        $jamSelesai = '';

        if (!$st->fetch() || !$jr || !$tapel || !$semOk) {
            throw new RuntimeException('Guru, jenis tugas, Tahun Pelajaran, dan Semester wajib dipilih.');
        }
        if ($expectedTargetType !== null && ($targetType !== $expectedTargetType || $targetId < 1)) {
            throw new RuntimeException('Jenis tugas ini harus memiliki target ' . $expectedTargetType . '.');
        }
        if ($expectedTargetType === null && ($targetType !== '' || $targetId > 0)) {
            throw new RuntimeException('Jenis tugas ini tidak menggunakan target data.');
        }
        if ($isPiket && !$days) throw new RuntimeException('Guru Piket wajib memiliki minimal satu hari jadwal.');

        $dupSql = "SELECT id FROM tugas_guru WHERE guru_id=? AND jenis_tugas_id=? AND tahun_pelajaran_id=? AND semester_id=? AND COALESCE(target_tipe,'')=COALESCE(?, '') AND COALESCE(target_id,0)=?";
        $dupParams = [$guru,$jenis,$tapel,$semester,$targetType,$targetId];
        if ($taskId > 0) { $dupSql .= " AND id<>?"; $dupParams[] = $taskId; }
        $dupSql .= " AND aktif=1 LIMIT 1";
        $dup = $pdo->prepare($dupSql);
        $dup->execute($dupParams);
        if ($dup->fetch()) throw new RuntimeException('Penugasan aktif yang sama sudah ada pada semester tersebut.');

        $pdo->beginTransaction();
        if ($taskId > 0) {
            $chk = $pdo->prepare("SELECT id FROM tugas_guru WHERE id=? LIMIT 1");
            $chk->execute([$taskId]);
            if (!$chk->fetchColumn()) throw new RuntimeException('Penugasan yang akan diubah tidak ditemukan.');
            $upd = $pdo->prepare("UPDATE tugas_guru SET guru_id=?,jenis_tugas_id=?,tahun_pelajaran_id=?,semester_id=?,target_tipe=?,target_id=?,mulai=NULL,selesai=NULL,keterangan=? WHERE id=?");
            $upd->execute([$guru,$jenis,$tapel,$semester,$targetType !== '' ? $targetType : null,$targetId > 0 ? $targetId : null,$ket !== '' ? $ket : null,$taskId]);
            $newId = $taskId;
        } else {
            $ins = $pdo->prepare("INSERT INTO tugas_guru(guru_id,jenis_tugas_id,tahun_pelajaran_id,semester_id,target_tipe,target_id,mulai,selesai,aktif,keterangan) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $ins->execute([$guru,$jenis,$tapel,$semester,$targetType !== '' ? $targetType : null,$targetId > 0 ? $targetId : null,null,null,1,$ket !== '' ? $ket : null]);
            $newId = (int)$pdo->lastInsertId();
        }

        $del = $pdo->prepare("DELETE FROM tugas_guru_jadwal WHERE tugas_guru_id=?");
        $del->execute([$newId]);
        if ($isPiket) {
            $qi = $pdo->prepare("INSERT INTO tugas_guru_jadwal(tugas_guru_id,hari,jam_mulai,jam_selesai,aktif) VALUES(?,?,NULL,NULL,1)");
            foreach ($days as $day) $qi->execute([$newId,$day]);
        }
        $pdo->commit();
        redirect('tugas_guru/index.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$guruRows = $pdo->query("SELECT id,nama,nip FROM guru WHERE status='aktif' ORDER BY nama")->fetchAll();
$jenisRows = $pdo->query("SELECT id,kode,nama FROM jenis_tugas WHERE aktif=1 AND kode <> 'GURU_BK' ORDER BY kategori,nama,id")->fetchAll();
$kelasRows = $pdo->query("SELECT id,nama,tingkat FROM master_kelas WHERE aktif=1 ORDER BY tingkat,urutan,nama")->fetchAll();
$extraRows = $pdo->query("SELECT id,nama FROM ekstrakurikuler WHERE aktif=1 ORDER BY nama")->fetchAll();

if ($editId > 0) {
    $q = $pdo->prepare("SELECT tg.*, jt.kode jenis_kode FROM tugas_guru tg JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id WHERE tg.id=? LIMIT 1");
    $q->execute([$editId]);
    $editRow = $q->fetch();
    if (!$editRow) { $editId = 0; $error = 'Penugasan yang akan diedit tidak ditemukan.'; }
    else {
        $qj = $pdo->prepare("SELECT * FROM tugas_guru_jadwal WHERE tugas_guru_id=? AND aktif=1 ORDER BY hari");
        $qj->execute([$editId]);
        $editSchedule = $qj->fetchAll();
    }
}

$rows = $pdo->query("SELECT tg.*,g.nama guru_nama,jt.nama jenis_nama,jt.kode jenis_kode,tp.nama tapel,s.nama semester_nama,
 CASE WHEN tg.target_tipe='kelas' THEN (SELECT mk.nama FROM master_kelas mk WHERE mk.id=tg.target_id) WHEN tg.target_tipe='ekstrakurikuler' THEN (SELECT e.nama FROM ekstrakurikuler e WHERE e.id=tg.target_id) ELSE NULL END target_nama
 FROM tugas_guru tg JOIN guru g ON g.id=tg.guru_id JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id JOIN tahun_pelajaran tp ON tp.id=tg.tahun_pelajaran_id JOIN semester s ON s.id=tg.semester_id ORDER BY tp.tanggal_mulai DESC,s.id DESC,tg.id DESC")->fetchAll();
$jadwalByTask = [];
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $ph = implode(',', array_fill(0,count($ids),'?'));
    $q = $pdo->prepare("SELECT * FROM tugas_guru_jadwal WHERE tugas_guru_id IN ($ph) AND aktif=1 ORDER BY hari,jam_mulai");
    $q->execute($ids);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $j) $jadwalByTask[(int)$j['tugas_guru_id']][] = $j;
}
$dayNames = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];

$initialTapel = $editRow ? (int)$editRow['tahun_pelajaran_id'] : $activeYear;
$initialSemester = $editRow ? (int)$editRow['semester_id'] : 0;
$initialTargetId = $editRow ? (int)($editRow['target_id'] ?? 0) : 0;
$initialTargetType = $editRow ? (string)($editRow['target_tipe'] ?? '') : '';
$initialDays = array_map(fn($j) => (int)$j['hari'], $editSchedule);
$initialJamMulai = $editSchedule[0]['jam_mulai'] ?? '';
$initialJamSelesai = $editSchedule[0]['jam_selesai'] ?? '';

$piketJenisId = 0;
foreach ($jenisRows as $jr) { if (in_array((string)$jr['kode'], ['GURU_PIKET','PETUGAS_PIKET'], true)) { $piketJenisId = (int)$jr['id']; break; } }
$piketTapel = (int)($_GET['piket_tapel'] ?? $activeYear);
$piketSemester = (int)($_GET['piket_semester'] ?? 0);
$piketHari = (int)($_GET['piket_hari'] ?? 1);
if ($piketHari < 1 || $piketHari > 7) $piketHari = 1;
if ($piketSemester < 1) {
    foreach ($semesters as $s) { if ((int)$s['tahun_pelajaran_id'] === $piketTapel && (int)$s['aktif'] === 1) { $piketSemester = (int)$s['id']; break; } }
}
$piketAssigned = [];
if ($piketJenisId > 0 && $piketTapel > 0 && $piketSemester > 0) {
    $qa = $pdo->prepare("SELECT tg.guru_id FROM tugas_guru tg JOIN tugas_guru_jadwal tgj ON tgj.tugas_guru_id=tg.id AND tgj.aktif=1 AND tgj.hari=? WHERE tg.jenis_tugas_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND tg.aktif=1 AND tg.target_tipe IS NULL AND tg.target_id IS NULL");
    $qa->execute([$piketHari,$piketJenisId,$piketTapel,$piketSemester]);
    foreach ($qa->fetchAll(PDO::FETCH_ASSOC) as $ar) {
        $piketAssigned[(int)$ar['guru_id']] = true;
    }
}

$page_title = 'Tugas Tambahan Guru';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<main class="main guru-task-page"><header class="topbar"><div class="topbar-title">Tugas Tambahan Guru</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1>Tugas Tambahan Guru</h1><p>Penugasan berlaku berdasarkan Tahun Pelajaran + Semester. Riwayat semester sebelumnya tetap tersimpan.</p><p class="text-muted" style="margin-top:6px">Periode tanggal tugas tidak digunakan; masa berlaku mengikuti Semester yang dipilih.</p><p class="text-muted" style="margin-top:6px">Untuk Guru Piket, jadwal mingguan menentukan kapan tugas tersebut aktif sebagai tugas hari ini.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="form-card piket-bulk-card" id="piket-bulk"><div class="section-title"><div><h2><i class="bi bi-calendar2-week"></i> Penjadwalan Guru Piket</h2><p>Pilih jenis tugas dan hari terlebih dahulu, lalu centang guru yang bertugas pada hari tersebut.</p></div><span class="status-badge status-aktif"><?=e($dayNames[$piketHari])?></span></div><form method="get" class="piket-filter"><div><label>Jenis Tugas<select class="form-control" disabled><option><?=e($jenisRows[array_search($piketJenisId,array_column($jenisRows,'id'))]['nama'] ?? 'Guru Piket')?></option></select><input type="hidden" name="piket_tapel" value="<?=$piketTapel?>"><input type="hidden" name="piket_semester" value="<?=$piketSemester?>"></label></div><div><label>Tahun Pelajaran<select name="piket_tapel" class="form-control" onchange="this.form.submit()"><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=$piketTapel===(int)$y['id']?'selected':''?>><?=e($y['nama'])?><?= $y['aktif']?' (aktif)':''?></option><?php endforeach;?></select></label></div><div><label>Semester<select name="piket_semester" class="form-control" onchange="this.form.submit()"><?php foreach($semesters as $s): if((int)$s['tahun_pelajaran_id']!==$piketTapel) continue;?><option value="<?=$s['id']?>" <?=$piketSemester===(int)$s['id']?'selected':''?>><?=e($s['nama'])?><?= $s['aktif']?' (aktif)':''?></option><?php endforeach;?></select></label></div><div><label>Hari Piket<select name="piket_hari" class="form-control" onchange="this.form.submit()"><?php foreach($dayNames as $d=>$nm):?><option value="<?=$d?>" <?=$piketHari===$d?'selected':''?>><?=e($nm)?></option><?php endforeach;?></select></label></div></form><form method="post" class="piket-bulk-form"><?=csrf_field()?><input type="hidden" name="action" value="save_piket_day"><input type="hidden" name="piket_jenis_tugas_id" value="<?=$piketJenisId?>"><input type="hidden" name="piket_tahun_pelajaran_id" value="<?=$piketTapel?>"><input type="hidden" name="piket_semester_id" value="<?=$piketSemester?>"><input type="hidden" name="piket_hari" value="<?=$piketHari?>"><div class="piket-teacher-head"><strong>Guru yang bertugas <?=e($dayNames[$piketHari])?></strong><span class="text-muted">Centang semua guru piket untuk hari ini.</span></div><div class="piket-teacher-grid"><?php foreach($guruRows as $g):?><label class="piket-teacher-item"><input type="checkbox" name="piket_guru_id[]" value="<?=$g['id']?>" <?=isset($piketAssigned[(int)$g['id']])?'checked':''?>><span><strong><?=e($g['nama'])?></strong><?php if($g['nip']):?><small><?=e($g['nip'])?></small><?php endif;?></span></label><?php endforeach;?></div><div class="piket-actions"><span class="text-muted">Jam piket mengikuti jam sekolah/KBM.</span><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Simpan Guru Piket</button></div></form></div>
<div class="form-card" id="editor"><h2><i class="bi bi-person-workspace"></i> <?=$editId ? 'Edit Penugasan' : 'Tambah Penugasan'?></h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_task"><input type="hidden" name="id" value="<?=$editId?>"><div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
<label>Guru<select name="guru_id" class="form-control" required><option value="">Pilih guru</option><?php foreach($guruRows as $g):?><option value="<?=$g['id']?>" <?=($editRow && (int)$editRow['guru_id']===(int)$g['id'])?'selected':''?>><?=e($g['nama'])?><?= $g['nip']?' · '.e($g['nip']):''?></option><?php endforeach;?></select></label>
<label>Jenis Tugas<select name="jenis_tugas_id" id="jenisTugas" class="form-control" required><option value="">Pilih tugas</option><?php foreach($jenisRows as $j):?><option value="<?=$j['id']?>" data-kode="<?=e($j['kode'])?>" <?=($editRow && (int)$editRow['jenis_tugas_id']===(int)$j['id'])?'selected':''?>><?=e($j['nama'])?></option><?php endforeach;?></select></label>
<label>Tahun Pelajaran<select name="tahun_pelajaran_id" id="tapel" class="form-control" required><option value="">Pilih Tapel</option><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=$initialTapel===(int)$y['id']?'selected':''?>><?=e($y['nama'])?><?= $y['aktif']?' (aktif)':''?></option><?php endforeach;?></select></label>
<label>Semester<select name="semester_id" id="semester" class="form-control" required><option value="">Pilih semester</option><?php foreach($semesters as $s):?><option value="<?=$s['id']?>" data-tapel="<?=$s['tahun_pelajaran_id']?>" <?=$initialSemester===(int)$s['id']?'selected':''?>><?=e($s['nama'])?><?= $s['aktif']?' (aktif)':''?></option><?php endforeach;?></select></label>
<label id="targetWrap">Target<select name="target_id" id="targetId" class="form-control"><option value="0">Tidak ada target</option></select><input type="hidden" name="target_tipe" id="targetTipe" value="<?=e($initialTargetType)?>"></label>
<label style="grid-column:1/-1">Keterangan<input type="text" name="keterangan" class="form-control" maxlength="255" value="<?=e((string)($editRow['keterangan']??''))?>" placeholder="Opsional"></label>
<div id="piketSchedule" style="grid-column:1/-1;display:none;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc"><strong><i class="bi bi-calendar-week"></i> Jadwal Mingguan Piket</strong><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px"><?php foreach($dayNames as $d=>$nm):?><label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="hari[]" value="<?=$d?>" <?=in_array($d,$initialDays,true)?'checked':''?>> <?=e($nm)?></label><?php endforeach;?></div><small class="text-muted">Jam piket mengikuti jam sekolah/KBM dan tidak diatur per penugasan.</small><small class="text-muted">Jadwal dapat diubah kapan saja selama semester penugasan.</small></div>
</div><div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> <?=$editId?'Simpan Perubahan':'Simpan Penugasan'?></button><?php if($editId):?><a class="btn btn-secondary" href="<?=BASE_URL?>tugas_guru/index.php"><i class="bi bi-x-circle"></i> Batal</a><?php endif;?></div></form></div>
<div class="dashboard-table-card"><div class="section-title"><div><h2>Riwayat Penugasan</h2><p>Penugasan lama tetap tersimpan per Tahun Pelajaran dan Semester.</p></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Guru</th><th>Tugas</th><th>Periode Akademik</th><th>Target</th><th>Jadwal</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7" class="empty-state">Belum ada penugasan.</td></tr><?php else:foreach($rows as $r):?><tr><td><strong><?=e($r['guru_nama'])?></strong></td><td><?=e($r['jenis_nama'])?></td><td><?=e($r['tapel'])?> · <?=e($r['semester_nama'])?></td><td><?=e($r['target_nama']??'-')?></td><td><?php $js=$jadwalByTask[(int)$r['id']]??[]; if(!$js):?><span class="text-muted">Tidak terjadwal</span><?php else:foreach($js as $j):?><span class="status-badge" style="margin:2px"><?=e($dayNames[(int)$j['hari']])?></span><?php endforeach;endif;?></td><td><span class="status-badge <?=$r['aktif']?'status-aktif':'status-nonaktif'?>"><?=$r['aktif']?'Aktif':'Nonaktif'?></span></td><td><div class="table-actions"><a class="btn btn-secondary btn-sm btn-icon-sm" title="Edit penugasan" aria-label="Edit penugasan" href="<?=BASE_URL?>tugas_guru/index.php?edit=<?=(int)$r['id']?>#editor"><i class="bi bi-pencil-square" aria-hidden="true"></i></a><form method="post" style="display:inline" onsubmit="return confirm('Ubah status penugasan ini?')"><?=csrf_field()?><input type="hidden" name="action" value="toggle_task"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-warning btn-sm btn-icon-sm" type="submit" title="<?=$r['aktif']?'Nonaktifkan':'Aktifkan'?> penugasan" aria-label="<?=$r['aktif']?'Nonaktifkan':'Aktifkan'?> penugasan"><i class="bi <?=$r['aktif']?'bi-pause-circle':'bi-play-circle'?>" aria-hidden="true"></i></button></form><form method="post" style="display:inline" onsubmit="return confirm('Hapus penugasan ini? Jadwal piket terkait juga akan dihapus. Riwayat penugasan yang sudah tidak diperlukan sebaiknya dinonaktifkan, bukan dihapus.')"><?=csrf_field()?><input type="hidden" name="action" value="delete_task"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-danger btn-sm btn-icon-sm" type="submit" title="Hapus penugasan" aria-label="Hapus penugasan"><i class="bi bi-trash3" aria-hidden="true"></i></button></form></div></td></tr><?php endforeach;endif;?></tbody></table></div></div>
</section></main>
<style>
.guru-task-page .piket-bulk-card{border-top:4px solid #f59e0b}.guru-task-page .piket-filter{display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(160px,1fr) minmax(140px,.8fr) minmax(140px,.8fr);gap:12px;margin:14px 0 18px}.guru-task-page .piket-filter label,.guru-task-page .piket-time-row label{display:flex;flex-direction:column;gap:6px;font-weight:700;font-size:.86rem}.guru-task-page .piket-filter .form-control{width:100%}.guru-task-page .piket-teacher-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}.guru-task-page .piket-teacher-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;max-height:330px;overflow:auto;padding:4px}.guru-task-page .piket-teacher-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer}.guru-task-page .piket-teacher-item:hover{border-color:#f59e0b;background:#fffaf0}.guru-task-page .piket-teacher-item input{width:18px;height:18px;accent-color:#f59e0b;flex:0 0 auto}.guru-task-page .piket-teacher-item span{display:flex;flex-direction:column;min-width:0}.guru-task-page .piket-teacher-item small{color:#64748b;margin-top:2px}.guru-task-page .piket-time-row{display:grid;grid-template-columns:160px 160px 1fr;gap:12px;align-items:end;margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0}.guru-task-page .piket-actions{display:flex;justify-content:flex-end}.guru-task-page .piket-actions .btn{min-height:42px}@media(max-width:900px){.guru-task-page .piket-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.guru-task-page .piket-teacher-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.guru-task-page .piket-filter,.guru-task-page .piket-time-row{grid-template-columns:1fr}.guru-task-page .piket-teacher-grid{grid-template-columns:1fr}.guru-task-page .piket-actions{justify-content:stretch}.guru-task-page .piket-actions .btn{width:100%}.guru-task-page .piket-teacher-head{align-items:flex-start;flex-direction:column}}
</style>
<script>
const jt=document.getElementById('jenisTugas'),ti=document.getElementById('targetId'),tt=document.getElementById('targetTipe'),tapel=document.getElementById('tapel'),semester=document.getElementById('semester'),piket=document.getElementById('piketSchedule');
const kelas=<?=json_encode($kelasRows,JSON_UNESCAPED_UNICODE)?>,extra=<?=json_encode($extraRows,JSON_UNESCAPED_UNICODE)?>;
const initialSemesterId=<?=json_encode($initialSemester)?>, initialTargetId=<?=json_encode($initialTargetId)?>;
const targetRules={WALI_KELAS:{type:'kelas',rows:kelas},PEMBINA_EKSTRA:{type:'ekstrakurikuler',rows:extra},KOORDINATOR_EKSTRA:{type:'ekstrakurikuler',rows:extra},PEMBINA_PRAMUKA:{type:'ekstrakurikuler',rows:extra},PEMBINA_PMR:{type:'ekstrakurikuler',rows:extra},PEMBINA_BASKET:{type:'ekstrakurikuler',rows:extra},PEMBINA_FUTSAL:{type:'ekstrakurikuler',rows:extra}};
function refreshSemester(){const t=Number(tapel.value||0);[...semester.options].forEach(o=>{if(!o.value)return;o.hidden=Number(o.dataset.tapel)!==t;o.disabled=Number(o.dataset.tapel)!==t;});if(initialSemesterId && [...semester.options].some(o=>Number(o.value)===initialSemesterId&&!o.disabled)) semester.value=String(initialSemesterId); else {const selected=[...semester.options].find(o=>o.value&&Number(o.dataset.tapel)===t&&o.textContent.includes('(aktif)'));if(selected)semester.value=selected.value;else if(![...semester.options].some(o=>o.value===semester.value&&!o.disabled))semester.value='';}}
function refreshTarget(){const k=jt?.selectedOptions[0]?.dataset.kode||'',rule=targetRules[k]||null,arr=rule?.rows||[],type=rule?.type||'';tt.value=type;ti.innerHTML='<option value="0">'+(type?'Pilih target':'Tidak ada target')+'</option>';arr.forEach(x=>{const o=document.createElement('option');o.value=x.id;o.textContent=x.nama;if(Number(x.id)===Number(initialTargetId))o.selected=true;ti.appendChild(o);});ti.disabled=!type;piket.style.display=(k==='GURU_PIKET'||k==='PETUGAS_PIKET')?'block':'none';}
tapel?.addEventListener('change',()=>{refreshSemester();});jt?.addEventListener('change',refreshTarget);refreshSemester();refreshTarget();
</script><?php require_once __DIR__ . '/../layouts/footer.php'; ?>
