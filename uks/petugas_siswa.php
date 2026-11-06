<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/_helper.php';
uks_require_access($pdo);
if (!in_array(user_role(), ['admin','kepala_sekolah'], true)) { deny_access(); }

$flash = uks_flash();
$error = null;
$editId = max(0, (int)($_GET['edit'] ?? 0));
$days = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_valid_csrf();
        $action = (string)($_POST['action'] ?? '');
        $id = max(0, (int)($_POST['id'] ?? 0));
        $siswaKelasId = max(0, (int)($_POST['siswa_kelas_id'] ?? 0));
        $semesterId = max(0, (int)($_POST['semester_id'] ?? 0));
        $hari = max(0, (int)($_POST['hari'] ?? 0));
        $jamMulai = trim((string)($_POST['jam_mulai'] ?? '')) ?: null;
        $jamSelesai = trim((string)($_POST['jam_selesai'] ?? '')) ?: null;
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $keterangan = trim((string)($_POST['keterangan'] ?? '')) ?: null;

        if ($action === 'save') {
            if ($siswaKelasId <= 0 || $semesterId <= 0 || !isset($days[$hari])) {
                throw new RuntimeException('Siswa, semester, dan hari jaga wajib dipilih.');
            }
            $st = $pdo->prepare("SELECT sk.id,sk.siswa_id,sk.tahun_pelajaran_id,sk.kelas_id,s.nama,s.nis,mk.nama kelas_nama
                FROM siswa_kelas sk JOIN siswa s ON s.id=sk.siswa_id
                LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
                JOIN semester se ON se.id=? AND se.tahun_pelajaran_id=sk.tahun_pelajaran_id
                WHERE sk.id=? AND sk.status='aktif' AND s.status='aktif' LIMIT 1");
            $st->execute([$semesterId,$siswaKelasId]);
            $student = $st->fetch();
            if (!$student) throw new RuntimeException('Siswa tidak sesuai dengan semester/tahun pelajaran yang dipilih.');
            $chk = $pdo->prepare('SELECT COUNT(*) FROM uks_petugas_jadwal WHERE siswa_kelas_id=? AND semester_id=? AND hari=? AND id<>?');
            $chk->execute([$siswaKelasId,$semesterId,$hari,$id]);
            if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Siswa tersebut sudah memiliki jadwal jaga pada hari itu.');
            if ($jamMulai !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamMulai)) throw new RuntimeException('Jam mulai tidak valid.');
            if ($jamSelesai !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamSelesai)) throw new RuntimeException('Jam selesai tidak valid.');
            if ($jamMulai !== null && $jamSelesai !== null && $jamSelesai <= $jamMulai) throw new RuntimeException('Jam selesai harus setelah jam mulai.');
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE uks_petugas_jadwal SET siswa_kelas_id=?,semester_id=?,hari=?,jam_mulai=?,jam_selesai=?,aktif=?,keterangan=? WHERE id=?');
                $st->execute([$siswaKelasId,$semesterId,$hari,$jamMulai,$jamSelesai,$aktif,$keterangan,$id]);
                uks_audit($pdo,'UPDATE_PETUGAS_UKS_SISWA',$id,'Memperbarui jadwal petugas UKS siswa.');
                uks_flash('Jadwal petugas UKS siswa berhasil diperbarui.');
            } else {
                $st = $pdo->prepare('INSERT INTO uks_petugas_jadwal (siswa_kelas_id,semester_id,hari,jam_mulai,jam_selesai,aktif,keterangan) VALUES (?,?,?,?,?,?,?)');
                $st->execute([$siswaKelasId,$semesterId,$hari,$jamMulai,$jamSelesai,$aktif,$keterangan]);
                $newId = (int)$pdo->lastInsertId();
                uks_audit($pdo,'CREATE_PETUGAS_UKS_SISWA',$newId,'Menambahkan jadwal petugas UKS siswa.');
                uks_flash('Petugas UKS siswa berhasil ditambahkan.');
            }
            redirect('uks/petugas_siswa.php');
        }
        if ($action === 'toggle' && $id > 0) {
            $st=$pdo->prepare('SELECT aktif FROM uks_petugas_jadwal WHERE id=? LIMIT 1');$st->execute([$id]);$row=$st->fetch();
            if (!$row) throw new RuntimeException('Jadwal tidak ditemukan.');
            $new=(int)$row['aktif']===1?0:1;
            $st=$pdo->prepare('UPDATE uks_petugas_jadwal SET aktif=? WHERE id=?');$st->execute([$new,$id]);
            uks_audit($pdo,$new?'ACTIVATE_PETUGAS_UKS_SISWA':'DEACTIVATE_PETUGAS_UKS_SISWA',$id,'Mengubah status jadwal petugas UKS siswa.');
            uks_flash('Status jadwal berhasil diubah.');redirect('uks/petugas_siswa.php');
        }
        throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (Throwable $e) { $error=$e->getMessage(); }

$years=$pdo->query('SELECT id,nama FROM tahun_pelajaran ORDER BY id DESC')->fetchAll();
$semesters=$pdo->query('SELECT id,tahun_pelajaran_id,nama,aktif FROM semester ORDER BY tahun_pelajaran_id DESC,id')->fetchAll();
$studentRows=$pdo->query("SELECT sk.id,s.id siswa_id,s.nama,s.nis,s.nisn,mk.nama kelas_nama,sk.tahun_pelajaran_id
    FROM siswa_kelas sk JOIN siswa s ON s.id=sk.siswa_id LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
    WHERE sk.status='aktif' AND s.status='aktif' ORDER BY s.nama")->fetchAll();

$search=trim((string)($_GET['q']??''));
$where=['1=1'];$params=[];
if($search!==''){ $where[]='(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR mk.nama LIKE ?)';$like='%'.$search.'%';array_push($params,$like,$like,$like,$like); }
$st=$pdo->prepare("SELECT uj.*,s.nama,s.nis,s.nisn,mk.nama kelas_nama,se.nama semester_nama,tp.nama tahun_nama
    FROM uks_petugas_jadwal uj JOIN siswa_kelas sk ON sk.id=uj.siswa_kelas_id JOIN siswa s ON s.id=sk.siswa_id
    LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id JOIN semester se ON se.id=uj.semester_id LEFT JOIN tahun_pelajaran tp ON tp.id=se.tahun_pelajaran_id
    WHERE ".implode(' AND ',$where)." ORDER BY uj.aktif DESC,uj.hari,uj.jam_mulai,s.nama");
$st->execute($params);$rows=$st->fetchAll();
$edit=null;if($editId>0){$st=$pdo->prepare('SELECT * FROM uks_petugas_jadwal WHERE id=? LIMIT 1');$st->execute([$editId]);$edit=$st->fetch()?:null;}
$page_title='Petugas UKS Siswa';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main uks-module-page"><header class="topbar"><div class="topbar-title">Petugas UKS Siswa</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<style>.uksps .hero{background:linear-gradient(135deg,#07111f,#12324a);color:#fff;border-radius:18px;padding:22px;margin-bottom:18px}.uksps .card{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:18px;margin-bottom:18px}.uksps .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.uksps .status{font-size:11px;font-weight:850;padding:5px 8px;border-radius:7px}.uksps .on{background:#dcfce7;color:#15803d}.uksps .off{background:#e2e8f0;color:#475569}.uksps .hint{background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:12px 14px;border-radius:12px;margin-bottom:18px;font-size:13px}@media(max-width:700px){.uksps .grid{grid-template-columns:1fr}}</style>
<div class="uksps"><div class="hero"><h1 style="margin:0;font-size:24px;font-weight:900"><i class="bi bi-people-fill"></i> Petugas UKS Siswa</h1><p style="margin:7px 0 0;color:#cbd5e1">Atur siswa yang mendapat jadwal jaga UKS. Data ini berdiri sendiri dari absensi kehadiran dan sholat.</p></div>
<div class="hint"><strong><i class="bi bi-info-circle-fill"></i> Alur:</strong> siswa ditetapkan sebagai petugas → punya jadwal jaga → melakukan absensi petugas UKS → hasil masuk ke rekap petugas UKS, bukan ke absensi kehadiran siswa.</div>
<?php if($flash):?><div class="alert alert-success"><?=e($flash)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="card"><h2 style="font-size:16px;font-weight:900;margin:0 0 14px"><?= $edit?'Edit Jadwal Petugas':'Tambah Petugas Siswa' ?></h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$edit?(int)$edit['id']:0?>"><div class="grid">
<label><span class="form-label">Siswa *</span><select class="form-control" name="siswa_kelas_id" required><option value="">Pilih siswa</option><?php foreach($studentRows as $s):?><option value="<?=$s['id']?>" data-tapel="<?=$s['tahun_pelajaran_id']?>" <?=($edit&&(int)$edit['siswa_kelas_id']===(int)$s['id'])?'selected':''?>><?=e($s['nama'])?> · <?=e($s['nis']??'-')?> · <?=e($s['kelas_nama']??'-')?></option><?php endforeach;?></select></label>
<label><span class="form-label">Semester *</span><select class="form-control" name="semester_id" required><option value="">Pilih semester</option><?php foreach($semesters as $s):?><option value="<?=$s['id']?>" data-tapel="<?=$s['tahun_pelajaran_id']?>" <?=($edit&&(int)$edit['semester_id']===(int)$s['id'])?'selected':''?>><?=e($s['nama'])?><?= $s['aktif']?' (aktif)':''?></option><?php endforeach;?></select></label>
<label><span class="form-label">Hari Jaga *</span><select class="form-control" name="hari" required><option value="">Pilih hari</option><?php foreach($days as $k=>$v):?><option value="<?=$k?>" <?=($edit&&(int)$edit['hari']===$k)?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label>
<label><span class="form-label">Jam Mulai</span><input class="form-control" type="time" name="jam_mulai" value="<?=e($edit['jam_mulai']??'')?>"></label>
<label><span class="form-label">Jam Selesai</span><input class="form-control" type="time" name="jam_selesai" value="<?=e($edit['jam_selesai']??'')?>"></label>
<label style="display:flex;align-items:center;gap:9px;margin-top:28px"><input type="checkbox" name="aktif" value="1" <?=(!$edit||(int)$edit['aktif']===1)?'checked':''?>> <span>Jadwal aktif</span></label>
</div><label style="display:block;margin-top:12px"><span class="form-label">Keterangan</span><textarea class="form-control" name="keterangan" rows="2" maxlength="255" placeholder="Contoh: Jaga bersama petugas guru"><?=e($edit['keterangan']??'')?></textarea></label><div style="display:flex;gap:8px;margin-top:14px"><button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Simpan</button><?php if($edit):?><a class="btn btn-secondary" href="<?=BASE_URL?>uks/petugas_siswa.php">Batal</a><?php endif;?></div></form></div>
<div class="card"><div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap"><div><h2 style="font-size:16px;font-weight:900;margin:0">Jadwal Petugas Siswa</h2><p style="margin:4px 0 0;color:#64748b;font-size:12px"><?=number_format(count($rows))?> jadwal.</p></div><form method="get" style="display:flex;gap:8px"><input class="form-control" type="search" name="q" value="<?=e($search)?>" placeholder="Cari nama / NIS / kelas"><button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button></form></div><div class="table-responsive" style="margin-top:14px"><table class="data-table"><thead><tr><th>Siswa</th><th>Periode</th><th>Hari</th><th>Jam</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="6" class="empty-state">Belum ada petugas UKS siswa.</td></tr><?php else:foreach($rows as $r):?><tr><td><strong><?=e($r['nama'])?></strong><div style="font-size:11px;color:#64748b"><?=e($r['nis']??'-')?> · <?=e($r['kelas_nama']??'-')?></div></td><td><?=e($r['tahun_nama']??'-')?> · <?=e($r['semester_nama']??'-')?></td><td><?=e($days[(int)$r['hari']]??'-')?></td><td><?=e($r['jam_mulai']?substr($r['jam_mulai'],0,5):'-')?><?= $r['jam_selesai']?'–'.e(substr($r['jam_selesai'],0,5)):'' ?></td><td><span class="status <?=$r['aktif']?'on':'off'?>"><?=$r['aktif']?'Aktif':'Nonaktif'?></span></td><td style="white-space:nowrap"><a class="btn btn-sm btn-primary" href="<?=BASE_URL?>uks/petugas_siswa.php?edit=<?=$r['id']?>"><i class="bi bi-pencil-square"></i></a><form method="post" style="display:inline" onsubmit="return confirm('Ubah status jadwal ini?');"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-secondary"><i class="bi bi-power"></i></button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div></div></section></main><?php require_once __DIR__.'/../layouts/footer.php';
