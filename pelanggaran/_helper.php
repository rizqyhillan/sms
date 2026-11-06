<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

function pelanggaran_require_access(): void
{
    require_permission('pelanggaran');
}

function pelanggaran_active_tapel(PDO $pdo): ?array
{
    $st = $pdo->query("SELECT id, nama, aktif FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC, id DESC LIMIT 1");
    $row = $st->fetch();
    return $row ?: null;
}

function pelanggaran_current_guru_id(PDO $pdo): ?int
{
    $id = user_guru_id();
    if ($id) return $id;
    $uid = user_id();
    if (!$uid) return null;
    $st = $pdo->prepare('SELECT guru_id FROM users WHERE id=? LIMIT 1');
    $st->execute([$uid]);
    $v = $st->fetchColumn();
    return $v !== false && $v !== null ? (int)$v : null;
}

function pelanggaran_can_manage_master(): bool
{
    return in_array(user_role(), ['admin','kepala_sekolah','guru_bk'], true);
}

function pelanggaran_can_record(): bool
{
    return in_array(user_role(), ['admin','kepala_sekolah','guru_bk','guru'], true);
}

function pelanggaran_can_adjust(): bool
{
    // Kewenangan pengurangan poin: BK dan Kesiswaan.
    // Pada arsitektur saat ini Kesiswaan dioperasikan oleh admin; Kepala Sekolah bersifat monitoring.
    return in_array(user_role(), ['admin','guru_bk'], true);
}

function pelanggaran_can_reduce_points(): bool
{
    return pelanggaran_can_adjust();
}

function pelanggaran_status_poin(int $total): array
{
    if ($total >= 100) return ['kode'=>'P4','label'=>'P4','class'=>'p4','icon'=>'bi-shield-x','pesan'=>'Perlu tindak lanjut serius.'];
    if ($total >= 75) return ['kode'=>'P3','label'=>'P3','class'=>'p3','icon'=>'bi-exclamation-octagon','pesan'=>'Perlu perhatian dan pembinaan lanjutan.'];
    if ($total >= 50) return ['kode'=>'P2','label'=>'P2','class'=>'p2','icon'=>'bi-exclamation-triangle','pesan'=>'Perlu segera ditindaklanjuti.'];
    if ($total >= 25) return ['kode'=>'P1','label'=>'P1','class'=>'p1','icon'=>'bi-bell','pesan'=>'Peringatan awal pembinaan.'];
    return ['kode'=>'-','label'=>'Normal','class'=>'normal','icon'=>'bi-check-circle','pesan'=>'Belum mencapai ambang peringatan.'];
}

function pelanggaran_total_poin(PDO $pdo, int $siswaId, int $tapelId): int
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(poin),0) FROM pelanggaran_siswa WHERE siswa_id=? AND tahun_pelajaran_id=?");
    $st->execute([$siswaId,$tapelId]);
    $pelanggaran = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(delta_poin),0) FROM pelanggaran_poin_penyesuaian WHERE siswa_id=? AND tahun_pelajaran_id=?");
    $st->execute([$siswaId,$tapelId]);
    return max(0, $pelanggaran + (int)$st->fetchColumn());
}


function pelanggaran_ensure_pembinaan(PDO $pdo, int $siswaId, int $tapelId, int $pelanggaranId, int $poinSebelum, int $poinSesudah): ?int
{
    $sebelum = pelanggaran_status_poin($poinSebelum)['kode'];
    $sesudah = pelanggaran_status_poin($poinSesudah)['kode'];
    $tahapBaru = in_array($sesudah, ['P1','P2','P3','P4'], true) && $sesudah !== $sebelum;
    if (!$tahapBaru) return null;

    $cek = $pdo->prepare("SELECT id FROM pembinaan_siswa WHERE siswa_id=? AND tahun_pelajaran_id=? AND status IN ('direncanakan','proses') ORDER BY id DESC LIMIT 1");
    $cek->execute([$siswaId, $tapelId]);
    $existing = $cek->fetchColumn();
    if ($existing !== false) return (int)$existing;

    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');
    $masalah = 'Otomatis: poin efektif siswa mencapai '.$poinSesudah.' ('.$sesudah.'). Pembinaan perlu ditangani oleh BK.';
    $st = $pdo->prepare("INSERT INTO pembinaan_siswa (siswa_id,tahun_pelajaran_id,pelanggaran_id,tanggal,waktu,jenis,masalah,status,created_by,created_at,updated_at) VALUES (?,?,?,?,?,'pembinaan_individu',?,'direncanakan',?,NOW(),NOW())");
    $st->execute([$siswaId,$tapelId,$pelanggaranId,$tanggal,$waktu,$masalah,user_id() ?: null]);
    $id = (int)$pdo->lastInsertId();
    pelanggaran_audit($pdo, 'AUTO_PEMBINAAN', $id, 'Otomatis membuat pembinaan '.$sesudah.' untuk siswa #'.$siswaId.' setelah poin efektif mencapai '.$poinSesudah.'.');
    return $id;
}

function pelanggaran_audit(PDO $pdo, string $aksi, ?int $referensiId, string $keterangan): void
{
    $st = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent, created_at) VALUES (?, ?, 'pelanggaran', ?, ?, ?, ?, NOW())");
    $st->execute([
        user_id() ?: null,
        $aksi,
        $referensiId ?: null,
        $keterangan,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function pelanggaran_student_picker(PDO $pdo, int $tapelId): array
{
    $st = $pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,mk.nama AS kelas
        FROM siswa s
        INNER JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1)
        LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id
        WHERE s.status='aktif'
        ORDER BY mk.urutan,mk.nama,s.nama");
    $st->execute([$tapelId]);
    return $st->fetchAll();
}
