<?php
declare(strict_types=1);

/**
 * Sumber waktu operasional reguler sekolah.
 *
 * Modul layanan reguler (Perpustakaan, BK, UKS) hanya menerima TRANSAKSI BARU
 * ketika sesi Absensi Kehadiran sekolah berstatus OPEN.
 *
 * Kegiatan yang memang mempunyai jadwal khusus (mis. Ekstrakurikuler/kegiatan
 * khusus) tidak menggunakan helper ini dan tetap mengikuti lifecycle jadwalnya.
 */
require_once __DIR__ . '/auto_sessions.php';

function sekolah_waktu_operasional_aktif(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));

    // Resolver ini memastikan sesi kehadiran hari ini tersedia dan lifecycle
    // DRAFT/OPEN/CLOSED tersinkron tanpa mengubah transaksi absensi.
    $sesi = auto_absensi_hari_ini($pdo);
    if (!$sesi) {
        return [
            'aktif' => false,
            'status_sesi' => null,
            'tanggal' => $now->format('Y-m-d'),
            'jam_sekarang' => $now->format('H:i'),
            'message' => 'Layanan sekolah sedang tidak aktif.'
        ];
    }

    $state = auto_session_state($sesi, $now);
    if ($state !== (string)($sesi['status_sesi'] ?? '')) {
        // Jangan bergantung pada status lama di DB untuk keputusan operasional.
        // State dihitung dari waktu WIB saat request berlangsung.
        $status = $state;
    } else {
        $status = (string)$sesi['status_sesi'];
    }

    $mulai = substr((string)($sesi['jam_mulai'] ?? ''), 0, 5);
    $selesai = substr((string)($sesi['jam_selesai'] ?? ''), 0, 5);
    $rentang = ($mulai !== '' && $selesai !== '') ? ($mulai . '–' . $selesai . ' WIB') : '-';

    if ($status === 'OPEN') {
        return [
            'aktif' => true,
            'status_sesi' => 'OPEN',
            'sesi_id' => (int)$sesi['id'],
            'tanggal' => $now->format('Y-m-d'),
            'jam_sekarang' => $now->format('H:i'),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'rentang' => $rentang,
            'nama_sesi' => (string)($sesi['nama_sesi'] ?? 'Absensi Kehadiran'),
            'message' => 'Layanan sekolah sedang aktif.'
        ];
    }

    $message = $status === 'DRAFT'
        ? 'Layanan sekolah belum dibuka. Tunggu Absensi Kehadiran dibuka.'
        : 'Layanan sekolah sudah ditutup. Absensi Kehadiran sudah CLOSED.';

    return [
        'aktif' => false,
        'status_sesi' => $status,
        'sesi_id' => (int)$sesi['id'],
        'tanggal' => $now->format('Y-m-d'),
        'jam_sekarang' => $now->format('H:i'),
        'jam_mulai' => $mulai,
        'jam_selesai' => $selesai,
        'rentang' => $rentang,
        'nama_sesi' => (string)($sesi['nama_sesi'] ?? 'Absensi Kehadiran'),
        'message' => $message
    ];
}

function sekolah_require_operasional_aktif(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    $ctx = sekolah_waktu_operasional_aktif($pdo, $now);
    if (empty($ctx['aktif'])) {
        throw new RuntimeException((string)($ctx['message'] ?? 'Kegiatan sekolah reguler sedang ditutup.'));
    }
    return $ctx;
}
