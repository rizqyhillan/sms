<?php
declare(strict_types=1);

/**
 * Satu-satunya mesin pembentukan sesi otomatis.
 *
 * Penting:
 * - Lifecycle sesi dikelola otomatis berdasarkan jam WIB: DRAFT -> OPEN -> CLOSED.
 * - Status legacy tetap diselaraskan untuk kompatibilitas modul lama.
 * - Tidak membutuhkan cron/background worker; lifecycle disegarkan saat aplikasi meminta data sesi.
 * - jenis_sholat_id pada sesi selalu mengacu ke jenis_sholat.id.
 */


/**
 * Menghitung lifecycle sesi berdasarkan waktu WIB.
 * DRAFT = sebelum jam mulai, OPEN = di dalam jendela, CLOSED = setelah jam selesai.
 */
function auto_session_state(array $sesi, ?DateTimeImmutable $now = null): string {
    // Sesi yang sudah dikunci/finalisasi manusia bersifat CLOSED permanen,
    // terlepas dari apakah jam_selesai kalender sudah tercapai atau belum.
    $finalized = (string)($sesi['status_sesi'] ?? '') === 'CLOSED'
        && (string)($sesi['status'] ?? '') === 'ditutup'
        && !empty($sesi['ditutup_oleh'])
        && !empty($sesi['ditutup_at']);
    if ($finalized) return 'CLOSED';

    $now = $now ?: new DateTimeImmutable('now');
    $tanggal = (string)($sesi['tanggal'] ?? '');
    $mulai = (string)($sesi['jam_mulai'] ?? '');
    $selesai = (string)($sesi['jam_selesai'] ?? '');
    if ($tanggal === '' || $mulai === '') return 'DRAFT';

    $today = $now->format('Y-m-d');
    if ($tanggal > $today) return 'DRAFT';
    if ($tanggal < $today) return 'CLOSED';

    $clock = $now->format('H:i:s');
    if ($clock < $mulai) return 'DRAFT';
    if ($selesai !== '' && $clock >= $selesai) return 'CLOSED';
    return 'OPEN';
}

/**
 * Menyelaraskan status_sesi dan status legacy untuk satu/semua sesi tanggal tertentu.
 * CANCELLED tidak disentuh bila kelak dipakai oleh modul pembatalan.
 */
function auto_sync_session_lifecycle(PDO $pdo, ?int $sessionId = null, ?DateTimeImmutable $now = null): void {
    $now = $now ?: new DateTimeImmutable('now');
    if ($sessionId !== null && $sessionId > 0) {
        $st = $pdo->prepare('SELECT id,tanggal,jam_mulai,jam_selesai,status_sesi,status,ditutup_oleh,ditutup_at FROM sesi_absensi WHERE id=? LIMIT 1');
        $st->execute([$sessionId]);
        $rows = ($r = $st->fetch()) ? [$r] : [];
    } else {
        $tanggal = $now->format('Y-m-d');
        $st = $pdo->prepare('SELECT id,tanggal,jam_mulai,jam_selesai,status_sesi,status,ditutup_oleh,ditutup_at FROM sesi_absensi WHERE tanggal=? ORDER BY id');
        $st->execute([$tanggal]);
        $rows = $st->fetchAll();
    }

    if (!$rows) return;
    $up = $pdo->prepare("UPDATE sesi_absensi SET status_sesi=?, status=? WHERE id=? AND status_sesi<> 'CANCELLED'");
    foreach ($rows as $row) {
        // CLOSED yang sudah difinalisasi manusia bersifat permanen.
        // Tanpa pengecualian ini, request berikutnya sebelum jam_selesai dapat
        // menghitung ulang OPEN dan membuka kembali sesi yang sudah dikunci.
        $finalized = (string)($row['status_sesi'] ?? '') === 'CLOSED'
            && (string)($row['status'] ?? '') === 'ditutup'
            && !empty($row['ditutup_oleh'])
            && !empty($row['ditutup_at']);
        if ($finalized) {
            continue;
        }
        $state = auto_session_state($row, $now);
        // Legacy status dipertahankan agar modul lama tidak salah menganggap DRAFT/OPEN sebagai final.
        $legacy = $state === 'CLOSED' ? 'ditutup' : ($state === 'OPEN' ? 'aktif' : 'draft');
        $up->execute([$state, $legacy, (int)$row['id']]);
    }
}

function auto_session_is_open(array $sesi, ?DateTimeImmutable $now = null): bool {
    return auto_session_state($sesi, $now) === 'OPEN';
}

function auto_hari_sekolah(PDO $pdo, string $tanggal): ?array {
    $st = $pdo->prepare(
        'SELECT id,nama FROM tahun_pelajaran
         WHERE aktif=1 AND tanggal_mulai<=? AND tanggal_selesai>=?
         ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$tanggal, $tanggal]);
    $tp = $st->fetch();
    if (!$tp) return null;

    $st = $pdo->prepare(
        'SELECT * FROM kalender_kehadiran
         WHERE tahun_pelajaran_id=? AND tanggal=? LIMIT 1'
    );
    $st->execute([$tp['id'], $tanggal]);
    $override = $st->fetch();

    if ($override && $override['jenis'] === 'libur') {
        return ['aktif'=>false, 'tahun'=>$tp, 'semester'=>null, 'jadwal'=>null, 'override'=>$override];
    }

    $hari = (int)(new DateTimeImmutable($tanggal))->format('N');
    $semSt = $pdo->prepare(
        'SELECT id,nama FROM semester
         WHERE tahun_pelajaran_id=? AND aktif=1
           AND tanggal_mulai<=? AND tanggal_selesai>=?
         ORDER BY id DESC LIMIT 1'
    );
    $semSt->execute([$tp['id'], $tanggal, $tanggal]);
    $semester = $semSt->fetch();
    if (!$semester) {
        return ['aktif'=>false, 'tahun'=>$tp, 'semester'=>null, 'jadwal'=>null, 'override'=>$override];
    }
    $st = $pdo->prepare(
        'SELECT * FROM jadwal_kehadiran
         WHERE tahun_pelajaran_id=? AND semester_id=? AND hari=? LIMIT 1'
    );
    $st->execute([$tp['id'], $semester['id'], $hari]);
    $jadwal = $st->fetch();

    if ($override && $override['jenis'] === 'masuk_khusus') {
        return ['aktif'=>true, 'tahun'=>$tp, 'semester'=>$semester, 'jadwal'=>$jadwal, 'override'=>$override];
    }

    return [
        'aktif'=>(bool)($jadwal && (int)$jadwal['aktif']===1),
        'tahun'=>$tp,
        'semester'=>$semester,
        'jadwal'=>$jadwal,
        'override'=>$override
    ];
}

function auto_semester(PDO $pdo, int $tahunId, string $tanggal): ?array {
    $st = $pdo->prepare(
        'SELECT id,nama FROM semester
         WHERE tahun_pelajaran_id=? AND aktif=1
           AND tanggal_mulai<=? AND tanggal_selesai>=?
         ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$tahunId, $tanggal, $tanggal]);
    $row = $st->fetch();
    return $row ?: null;
}

function auto_absensi_hari_ini(PDO $pdo): ?array {
    $tanggal = (new DateTimeImmutable('now'))->format('Y-m-d');
    $cal = auto_hari_sekolah($pdo, $tanggal);
    if (!$cal || !$cal['aktif']) return null;

    $tp = $cal['tahun'];
    $sem = $cal['semester'] ?? auto_semester($pdo, (int)$tp['id'], $tanggal);
    if (!$sem) return null;

    $jenis = $pdo->query(
        "SELECT id FROM jenis_absensi
         WHERE kode='MASUK' AND kategori='kehadiran' AND aktif=1
         ORDER BY id LIMIT 1"
    )->fetch();
    if (!$jenis) return null;

    $st = $pdo->prepare(
        'SELECT s.* FROM sesi_absensi s
         WHERE s.tahun_pelajaran_id=? AND s.semester_id=?
           AND s.jenis_absensi_id=? AND s.jenis_sholat_id IS NULL
           AND s.tanggal=?
         ORDER BY s.id DESC LIMIT 1'
    );
    $st->execute([$tp['id'], $sem['id'], $jenis['id'], $tanggal]);
    $existing = $st->fetch();
    $jadwal = $cal['jadwal'] ?? [];

    if ($existing) {
        // Sinkronkan perubahan jadwal hanya bila sesi belum memiliki transaksi.
        // Setelah transaksi ada, waktu sesi menjadi bagian dari histori/audit dan dikunci.
        if ($jadwal && (($cal['override']['jenis'] ?? '') !== 'masuk_khusus')) {
            $tx = $pdo->prepare('SELECT COUNT(*) FROM absensi WHERE sesi_id=?');
            $tx->execute([(int)$existing['id']]);
            if ((int)$tx->fetchColumn() === 0) {
                $namaBaru = (string)($jadwal['nama_sesi'] ?? 'Absensi Kehadiran');
                $mulaiBaru = (string)($jadwal['jam_mulai'] ?? '07:00:00');
                $selesaiBaru = (string)($jadwal['jam_selesai'] ?? '09:00:00');
                $batasBaru = $jadwal['batas_terlambat'] ?? null;
                if ((string)$existing['jam_mulai'] !== $mulaiBaru
                    || (string)$existing['jam_selesai'] !== $selesaiBaru
                    || (string)($existing['nama_sesi'] ?? '') !== $namaBaru
                    || (string)($existing['batas_terlambat'] ?? '') !== (string)($batasBaru ?? '')) {
                    $up = $pdo->prepare('UPDATE sesi_absensi SET nama_sesi=?,jam_mulai=?,jam_selesai=?,batas_terlambat=? WHERE id=?');
                    $up->execute([$namaBaru,$mulaiBaru,$selesaiBaru,$batasBaru ?: null,(int)$existing['id']]);
                }
            }
        }
        auto_sync_session_lifecycle($pdo, (int)$existing['id']);
        $st->execute([$tp['id'], $sem['id'], $jenis['id'], $tanggal]);
        return $st->fetch() ?: $existing;
    }

    if (!$jadwal && (($cal['override']['jenis'] ?? '') !== 'masuk_khusus')) return null;

    $nama = $cal['override']['nama'] ?? ($jadwal['nama_sesi'] ?? 'Absensi Kehadiran');
    $mulai = $jadwal['jam_mulai'] ?? '07:00:00';
    $selesai = $jadwal['jam_selesai'] ?? '09:00:00';
    $batas = $jadwal['batas_terlambat'] ?? null;

    try {
        $ins = $pdo->prepare(
            "INSERT INTO sesi_absensi
            (tahun_pelajaran_id,semester_id,jenis_absensi_id,jenis_sholat_id,
             tanggal,nama_sesi,jam_mulai,jam_selesai,batas_terlambat,status,status_sesi)
            VALUES (?,?,?,?,?,?,?,?,?,'draft','DRAFT')"
        );
        $ins->execute([
            $tp['id'], $sem['id'], $jenis['id'], null,
            $tanggal, $nama, $mulai, $selesai, $batas ?: null
        ]);
    } catch (Throwable $e) {
        // Request bersamaan boleh mengambil sesi yang sudah dibuat request lain.
    }

    $st->execute([$tp['id'], $sem['id'], $jenis['id'], $tanggal]);
    $created = $st->fetch() ?: null;
    if ($created) {
        auto_sync_session_lifecycle($pdo, (int)$created['id']);
        $st->execute([$tp['id'], $sem['id'], $jenis['id'], $tanggal]);
        $created = $st->fetch() ?: $created;
    }
    return $created;
}

function auto_sholat_hari_ini(PDO $pdo): array {
    $tanggal = (new DateTimeImmutable('now'))->format('Y-m-d');
    $cal = auto_hari_sekolah($pdo, $tanggal);
    if (!$cal || !$cal['aktif']) return [];

    $tp = $cal['tahun'];
    $sem = $cal['semester'] ?? auto_semester($pdo, (int)$tp['id'], $tanggal);
    if (!$sem) return [];

    $jenis = $pdo->query(
        "SELECT id FROM jenis_absensi
         WHERE kode='SHOLAT' AND kategori='sholat' AND aktif=1
         LIMIT 1"
    )->fetch();
    if (!$jenis) return [];

    $st = $pdo->prepare(
        "SELECT
            js.id AS jenis_sholat_id,
            js.nama,
            j.id AS jadwal_id,
            j.jam_mulai,
            j.jam_selesai,
            j.nama_sesi
         FROM jadwal_sholat j
         JOIN jenis_sholat js ON js.id=j.jenis_sholat_id
         WHERE j.tahun_pelajaran_id=? AND j.semester_id=? AND j.aktif=1 AND js.aktif=1
         ORDER BY j.jam_mulai,j.id"
    );
    $st->execute([$tp['id'], $sem['id']]);
    $jadwal = $st->fetchAll();
    $hasil = [];

    foreach ($jadwal as $r) {
        // KRITIS: sesi menyimpan jenis_sholat.id, BUKAN jadwal_sholat.id.
        $jenisSholatId = (int)$r['jenis_sholat_id'];

        $q = $pdo->prepare(
            'SELECT s.* FROM sesi_absensi s
             WHERE s.tahun_pelajaran_id=? AND s.semester_id=?
               AND s.jenis_absensi_id=? AND s.jenis_sholat_id=?
               AND s.tanggal=?
             ORDER BY s.id DESC LIMIT 1'
        );
        $q->execute([
            $tp['id'], $sem['id'], $jenis['id'], $jenisSholatId, $tanggal
        ]);
        $existing = $q->fetch();
        if ($existing) {
            // Perubahan Jadwal Sholat mengikuti sesi selama belum ada transaksi.
            // Begitu transaksi ada, sesi menjadi histori dan tidak diubah otomatis.
            $tx = $pdo->prepare('SELECT COUNT(*) FROM absensi_sholat WHERE sesi_id=?');
            $tx->execute([(int)$existing['id']]);
            if ((int)$tx->fetchColumn() === 0) {
                $namaBaru = (string)($r['nama_sesi'] ?: ('Sholat '.$r['nama']));
                $mulaiBaru = (string)$r['jam_mulai'];
                $selesaiBaru = (string)$r['jam_selesai'];
                if ((string)$existing['jam_mulai'] !== $mulaiBaru
                    || (string)$existing['jam_selesai'] !== $selesaiBaru
                    || (string)($existing['nama_sesi'] ?? '') !== $namaBaru) {
                    $up = $pdo->prepare('UPDATE sesi_absensi SET nama_sesi=?,jam_mulai=?,jam_selesai=? WHERE id=?');
                    $up->execute([$namaBaru,$mulaiBaru,$selesaiBaru,(int)$existing['id']]);
                }
            }
            auto_sync_session_lifecycle($pdo, (int)$existing['id']);
            $q->execute([$tp['id'], $sem['id'], $jenis['id'], $jenisSholatId, $tanggal]);
            $existing = $q->fetch() ?: $existing;
        }

        if (!$existing) {
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO sesi_absensi
                    (tahun_pelajaran_id,semester_id,jenis_absensi_id,jenis_sholat_id,
                     tanggal,nama_sesi,jam_mulai,jam_selesai,batas_terlambat,status,status_sesi)
                    VALUES (?,?,?,?,?,?,?,?,NULL,'draft','DRAFT')"
                );
                $ins->execute([
                    $tp['id'], $sem['id'], $jenis['id'], $jenisSholatId,
                    $tanggal,
                    $r['nama_sesi'] ?: ('Sholat '.$r['nama']),
                    $r['jam_mulai'],
                    $r['jam_selesai']
                ]);
            } catch (Throwable $e) {
                // Request bersamaan: ambil sesi yang telah dibuat.
            }

            $q->execute([
                $tp['id'], $sem['id'], $jenis['id'], $jenisSholatId, $tanggal
            ]);
            $existing = $q->fetch();
            if ($existing) {
                auto_sync_session_lifecycle($pdo, (int)$existing['id']);
                $q->execute([$tp['id'], $sem['id'], $jenis['id'], $jenisSholatId, $tanggal]);
                $existing = $q->fetch() ?: $existing;
            }
        }

        if ($existing) $hasil[] = $existing;
    }

    return $hasil;
}

/**
 * Resolver sesi untuk layar publik.
 * Read-only: tidak membuat atau mengubah sesi; status dihitung dari waktu saat ini.
 */
function auto_sessions_today_readonly(PDO $pdo, ?DateTimeImmutable $now = null): array {
    $now = $now ?: new DateTimeImmutable('now');
    $tanggal = $now->format('Y-m-d');
    $st = $pdo->prepare(
        "SELECT s.*, ja.kode AS jenis_kode, ja.kategori, ja.nama AS jenis_nama, js.nama AS sholat_nama
         FROM sesi_absensi s
         JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id
         LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id
         WHERE s.tanggal=? AND s.status_sesi IN ('DRAFT','OPEN','CLOSED')
         ORDER BY s.jam_mulai ASC, s.id ASC"
    );
    $st->execute([$tanggal]);
    $rows = $st->fetchAll();
    foreach ($rows as &$row) {
        $state = auto_session_state($row, $now);
        $row['status_sesi'] = $state;
        $row['status'] = $state === 'CLOSED' ? 'ditutup' : ($state === 'OPEN' ? 'aktif' : 'draft');
    }
    unset($row);
    return $rows;
}

function auto_sessions_today(PDO $pdo): array {
    $out = [];
    $absensi = auto_absensi_hari_ini($pdo);
    if ($absensi) $out[] = $absensi;
    foreach (auto_sholat_hari_ini($pdo) as $sesi) {
        $out[] = $sesi;
    }
    auto_sync_session_lifecycle($pdo);
    // Refresh rows so callers always receive the effective lifecycle state.
    foreach ($out as $i => $row) {
        $st = $pdo->prepare('SELECT s.*,ja.kategori,ja.kode jenis_kode,js.nama sholat_nama FROM sesi_absensi s JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id WHERE s.id=? LIMIT 1');
        $st->execute([(int)$row['id']]);
        if ($fresh = $st->fetch()) $out[$i] = array_merge($row, $fresh);
    }
    return $out;
}
