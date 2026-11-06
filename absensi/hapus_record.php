<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

if (user_role() !== 'admin') deny_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
require_valid_csrf();
$id = (int)($_POST['id'] ?? 0);
$sesiId = (int)($_POST['sesi_id'] ?? 0);
if ($id <= 0 || $sesiId <= 0) { redirect('absensi/index.php?error=' . rawurlencode('Data absensi tidak valid.')); }

try {
    auto_sync_session_lifecycle($pdo, $sesiId);
    $lock = $pdo->prepare('SELECT status,status_sesi FROM sesi_absensi WHERE id=? LIMIT 1');
    $lock->execute([$sesiId]);
    $sessionState = $lock->fetch();
    if (!$sessionState) throw new RuntimeException('Sesi absensi tidak ditemukan.');
    if (($sessionState['status'] ?? '') === 'ditutup' || ($sessionState['status_sesi'] ?? '') === 'CLOSED') {
        throw new RuntimeException('Sesi sudah ditutup dan dikunci. Record absensi tidak dapat dihapus.');
    }

    $st = $pdo->prepare('SELECT a.id,a.sesi_id,a.siswa_id,a.status,a.waktu_scan,s.nama_sesi,s.tanggal,sw.nama AS nama_siswa FROM absensi a JOIN sesi_absensi s ON s.id=a.sesi_id JOIN siswa sw ON sw.id=a.siswa_id WHERE a.id=? AND a.sesi_id=? AND s.jenis_sholat_id IS NULL LIMIT 1');
    $st->execute([$id,$sesiId]);
    $row = $st->fetch();
    if (!$row) redirect('absensi/daftar.php?sesi_id=' . $sesiId . '&error=' . rawurlencode('Record absensi tidak ditemukan.'));

    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM absensi WHERE id=? AND sesi_id=?');
    $del->execute([$id,$sesiId]);
    if ($del->rowCount() !== 1) throw new RuntimeException('Record absensi tidak dapat dihapus.');
    absensi_audit($pdo,'DELETE_RECORD',$id,'Menghapus record kehadiran '.$row['nama_siswa'].' pada sesi '.$row['nama_sesi'].' tanggal '.$row['tanggal'].' untuk pengujian/koreksi.');
    $pdo->commit();
    redirect('absensi/daftar.php?sesi_id=' . $sesiId . '&saved=' . rawurlencode('Record kehadiran berhasil dihapus. Sesi tetap dipertahankan.'));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect('absensi/daftar.php?sesi_id=' . $sesiId . '&error=' . rawurlencode('Record kehadiran tidak dapat dihapus: ' . $e->getMessage()));
}
