<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auto_sessions.php';
require_login();
require_permission('absensi');

function absensi_audit(PDO $pdo, string $aksi, ?int $id, string $keterangan): void {
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([user_id(), $aksi, 'absensi', $id, $keterangan, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
}

function absensi_find_session(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT s.*, ta.nama AS tahun_nama, se.nama AS semester_nama, ja.kode AS jenis_kode, ja.nama AS jenis_nama, ja.kategori, js.nama AS sholat_nama FROM sesi_absensi s JOIN tahun_pelajaran ta ON ta.id=s.tahun_pelajaran_id JOIN semester se ON se.id=s.semester_id JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id WHERE s.id=? LIMIT 1');
    $st->execute([$id]);
    $row=$st->fetch();
    return $row ?: null;
}

function absensi_session_validate(PDO $pdo, int $tahunId, int $semesterId, int $jenisId, ?int $sholatId, string $tanggal, string $mulai, string $selesai, ?string $terlambat, string $nama): array {
    $errors=[];
    $st=$pdo->prepare('SELECT id,nama,kategori,aktif FROM jenis_absensi WHERE id=? LIMIT 1'); $st->execute([$jenisId]); $jenis=$st->fetch();
    if(!$jenis) $errors[]='Jenis absensi tidak ditemukan.';
    elseif(!(int)$jenis['aktif']) $errors[]='Jenis absensi sedang tidak aktif.';
    $st=$pdo->prepare('SELECT id,nama,tahun_pelajaran_id,aktif FROM semester WHERE id=? LIMIT 1'); $st->execute([$semesterId]); $sem=$st->fetch();
    if(!$sem) $errors[]='Semester tidak ditemukan.';
    elseif((int)$sem['tahun_pelajaran_id']!==$tahunId) $errors[]='Semester tidak sesuai dengan tahun pelajaran.';
    $st=$pdo->prepare('SELECT id FROM tahun_pelajaran WHERE id=? LIMIT 1'); $st->execute([$tahunId]); if(!$st->fetch()) $errors[]='Tahun pelajaran tidak ditemukan.';
    if(trim($nama)==='') $errors[]='Nama sesi wajib diisi.';
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tanggal)) $errors[]='Tanggal tidak valid.';
    if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$mulai) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$selesai)) $errors[]='Jam mulai dan selesai wajib menggunakan format 24 jam WIB (HH:mm).';
    if($mulai >= $selesai) $errors[]='Jam selesai harus setelah jam mulai.';
    if($terlambat!==null && $terlambat!=='' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$terlambat)) $errors[]='Batas terlambat tidak valid. Gunakan format HH:mm.';
    if($jenis && $jenis['kategori']==='sholat' && !$sholatId) $errors[]='Jenis sholat wajib dipilih untuk sesi sholat.';
    if($jenis && $jenis['kategori']==='kehadiran' && $sholatId) $errors[]='Sesi kehadiran biasa tidak boleh memiliki jenis sholat.';
    if($sholatId){ $st=$pdo->prepare('SELECT id,nama,aktif FROM jenis_sholat WHERE id=? LIMIT 1'); $st->execute([$sholatId]); $sh=$st->fetch(); if(!$sh) $errors[]='Jenis sholat tidak ditemukan.'; elseif(!$sh['aktif']) $errors[]='Jenis sholat sedang tidak aktif.'; }
    return [$errors,$jenis];
}

function absensi_hari_sekolah(PDO $pdo, string $tanggal): ?array {
    $tahun=$pdo->prepare('SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 AND tanggal_mulai<=? AND tanggal_selesai>=? ORDER BY id DESC LIMIT 1');
    $tahun->execute([$tanggal,$tanggal]); $tp=$tahun->fetch(); if(!$tp)return null;
    $ov=$pdo->prepare('SELECT * FROM kalender_kehadiran WHERE tahun_pelajaran_id=? AND tanggal=? LIMIT 1'); $ov->execute([$tp['id'],$tanggal]); $override=$ov->fetch();
    if($override && $override['jenis']==='libur')return ['aktif'=>false,'tahun'=>$tp,'override'=>$override];
    $hari=(int)(new DateTimeImmutable($tanggal))->format('N');
    $semSt=$pdo->prepare('SELECT id FROM semester WHERE tahun_pelajaran_id=? AND aktif=1 AND tanggal_mulai<=? AND tanggal_selesai>=? ORDER BY id DESC LIMIT 1');$semSt->execute([$tp['id'],$tanggal,$tanggal]);$semester=$semSt->fetch();
    $st=$pdo->prepare('SELECT * FROM jadwal_kehadiran WHERE tahun_pelajaran_id=? AND semester_id=? AND hari=? LIMIT 1');$st->execute([$tp['id'],(int)($semester['id']??0),$hari]);$jadwal=$st->fetch();
    if($override && $override['jenis']==='masuk_khusus')return ['aktif'=>true,'tahun'=>$tp,'jadwal'=>$jadwal,'override'=>$override];
    return ['aktif'=>(bool)($jadwal && (int)$jadwal['aktif']===1),'tahun'=>$tp,'jadwal'=>$jadwal,'override'=>null];
}




function absensi_setting_value(PDO $pdo, string $kode, ?string $default = null): ?string
{
    try {
        $st=$pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci=? LIMIT 1');
        $st->execute([$kode]);
        $v=$st->fetchColumn();
        return $v===false ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default;
    }
}

function absensi_pulang_aktif(PDO $pdo): bool
{
    return absensi_setting_value($pdo,'absen_pulang_aktif','0') === '1';
}

function absensi_batas_finalisasi(PDO $pdo): string
{
    $v=absensi_setting_value($pdo,'batas_finalisasi_kehadiran','17:00');
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',(string)$v) ? (string)$v : '17:00';
}

function absensi_is_finalized(PDO $pdo, int $sesiId): bool
{
    $st=$pdo->prepare('SELECT finalisasi_at FROM sesi_absensi WHERE id=? LIMIT 1');
    $st->execute([$sesiId]);
    return (bool)$st->fetchColumn();
}

/**
 * Finalisasi otomatis harian. Dipanggil pada request yang menyentuh modul absensi.
 * Finalisasi tidak mengubah status CLOSED sesi; CLOSED tetap berarti jendela scan sesi selesai.
 */
function absensi_sync_finalisasi_otomatis(PDO $pdo, ?int $sesiId=null, ?DateTimeImmutable $now=null): void
{
    $now=$now ?: new DateTimeImmutable('now');
    $tanggal=$now->format('Y-m-d');
    $batas=absensi_batas_finalisasi($pdo);
    if($now->format('H:i') < $batas) return;

    $sql="SELECT s.id,s.tanggal,s.finalisasi_at FROM sesi_absensi s
          JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
          WHERE s.tanggal=? AND ja.kategori='kehadiran' AND s.jenis_sholat_id IS NULL
            AND s.finalisasi_at IS NULL";
    $params=[$tanggal];
    if($sesiId!==null && $sesiId>0){$sql.=' AND s.id=?';$params[]=$sesiId;}
    $sql.=' ORDER BY s.id DESC';
    $st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
    if(!$rows)return;

    foreach($rows as $row){
        $up=$pdo->prepare("UPDATE sesi_absensi SET finalisasi_at=NOW(),finalisasi_oleh=NULL,finalisasi_mode='AUTO' WHERE id=? AND finalisasi_at IS NULL");
        $up->execute([(int)$row['id']]);
        if($up->rowCount()>0){
            absensi_audit($pdo,'AUTO_FINALIZE',(int)$row['id'],'Finalisasi otomatis kehadiran harian pada batas '.$batas.' WIB.');
        }
    }
}

function absensi_get_or_create_hari_ini(PDO $pdo): ?array {
    $row=auto_absensi_hari_ini($pdo);
    if($row && !empty($row['id'])) absensi_sync_finalisasi_otomatis($pdo,(int)$row['id']);
    return $row;
}

/**
 * Compatibility wrapper. Lifecycle sesi sekarang otomatis DRAFT -> OPEN -> CLOSED
 * berdasarkan waktu WIB, tanpa cron dan tanpa tombol buka/tutup manual.
 */
function absensi_auto_close_sesi(PDO $pdo): void {
    auto_sync_session_lifecycle($pdo);
}

function absensi_get_or_create_sholat_hari_ini(PDO $pdo): array {
    return auto_sholat_hari_ini($pdo);
}

/**
 * Verifikasi guru piket berlaku berdasarkan HARI tugas piket, bukan kelas.
 * Jendela verifikasi mengikuti jam pembelajaran/kehadiran pada hari aktif.
 * Sesi scan boleh sudah CLOSED; verifikasi tetap boleh sampai jam pembelajaran berakhir.
 */
function absensi_piket_aktif_pada_tanggal(PDO $pdo, string $tanggal, ?DateTimeImmutable $now = null): bool
{
    if (has_role(['admin','kepala_sekolah'])) return true;
    if (!has_role('guru')) return false;
    $guruId = user_guru_id();
    $tpId = active_tapel_id($pdo);
    $semId = active_semester_id($pdo, $tpId);
    if (!$guruId || !$tpId || !$semId) return false;

    $day = (int)(new DateTimeImmutable($tanggal))->format('N');
    $codes = task_registry_capability_codes('piket');
    if (!$codes) return false;
    $ph = implode(',', array_fill(0, count($codes), '?'));
    $sql = "SELECT COUNT(*) FROM tugas_guru tg
        INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
        INNER JOIN tugas_guru_jadwal tgj ON tgj.tugas_guru_id=tg.id
        WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=?
          AND jt.kode IN ($ph) AND jt.aktif=1 AND tg.aktif=1
          AND tgj.hari=? AND tgj.aktif=1
          AND (tg.mulai IS NULL OR tg.mulai<=?)
          AND (tg.selesai IS NULL OR tg.selesai>=?)
        LIMIT 1";
    $params = array_merge([$guruId,$tpId,$semId], $codes, [$day,$tanggal,$tanggal]);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ((int)$st->fetchColumn() < 1) return false;

    $hari = absensi_hari_sekolah($pdo, $tanggal);
    if (!$hari || empty($hari['aktif'])) return false;
    $jadwal = $hari['jadwal'] ?? null;
    if (!$jadwal) return false;

    $now = $now ?: new DateTimeImmutable('now');
    if ($now->format('Y-m-d') !== $tanggal) return false;
    $clock = $now->format('H:i:s');
    $mulai = (string)($jadwal['jam_mulai'] ?? '');
    $selesai = (string)($jadwal['jam_selesai'] ?? '');
    if ($mulai === '' || $selesai === '') return false;
    return $clock >= $mulai && $clock < $selesai;
}

function user_can_verifikasi_kehadiran(): bool
{
    if (has_role(['admin','kepala_sekolah'])) return true;
    if (!has_role('guru')) return false;
    global $pdo;
    return absensi_piket_aktif_pada_tanggal($pdo, date('Y-m-d'));
}

function require_verifikasi_kehadiran(): void
{
    require_login();
    if (!user_can_verifikasi_kehadiran()) deny_access();
}

function absensi_is_final(PDO $pdo, int $sesiId): bool
{
    // Segarkan lifecycle sebelum mengambil keputusan kunci agar endpoint
    // tidak bergantung pada halaman yang dibuka sebelumnya.
    auto_sync_session_lifecycle($pdo, $sesiId);
    $st=$pdo->prepare("SELECT status FROM sesi_absensi WHERE id=? LIMIT 1");
    $st->execute([$sesiId]);
    return $st->fetchColumn()==='ditutup';
}

/**
 * Sesi yang sudah CLOSED tidak boleh dimodifikasi melalui CRUD operasional.
 * Jalur verifikasi guru piket tetap ditangani oleh daftar.php sesuai
 * kewenangan dan jendela jam pembelajaran yang sudah mapan.
 */
function absensi_require_editable_session(PDO $pdo, int $sesiId): void
{
    auto_sync_session_lifecycle($pdo, $sesiId);
    if (absensi_is_final($pdo, $sesiId)) {
        http_response_code(409);
        exit('Sesi sudah ditutup dan dikunci. Data absensi tidak dapat diedit atau dihapus.');
    }
}
