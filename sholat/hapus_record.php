<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

if (user_role() !== 'admin') deny_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
require_valid_csrf();
$id = (int)($_POST['id'] ?? 0);
$sesiId = (int)($_POST['sesi_id'] ?? 0);
if ($id <= 0 || $sesiId <= 0) { redirect('sholat/index.php?error=' . rawurlencode('Data absensi sholat tidak valid.')); }

try {
    auto_sync_session_lifecycle($pdo, $sesiId);
    $lock = $pdo->prepare('SELECT status,status_sesi FROM sesi_absensi WHERE id=? LIMIT 1');
    $lock->execute([$sesiId]);
    $sessionState = $lock->fetch();
    if (!$sessionState) throw new RuntimeException('Sesi sholat tidak ditemukan.');
    if (($sessionState['status'] ?? '') === 'ditutup' || ($sessionState['status_sesi'] ?? '') === 'CLOSED') {
        throw new RuntimeException('Sesi sholat sudah ditutup dan dikunci. Record tidak dapat dihapus.');
    }

    $st = $pdo->prepare("SELECT ash.id,ash.sesi_id,ash.siswa_id,ash.status,ash.waktu_scan,s.nama_sesi,s.tanggal,sw.nama AS nama_siswa FROM absensi_sholat ash JOIN sesi_absensi s ON s.id=ash.sesi_id JOIN siswa sw ON sw.id=ash.siswa_id JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id WHERE ash.id=? AND ash.sesi_id=? AND ja.kategori='sholat' LIMIT 1");
    $st->execute([$id,$sesiId]);
    $row = $st->fetch();
    if (!$row) redirect('sholat/daftar.php?sesi_id=' . $sesiId . '&error=' . rawurlencode('Record absensi sholat tidak ditemukan.'));

    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM absensi_sholat WHERE id=? AND sesi_id=?');
    $del->execute([$id,$sesiId]);
    if ($del->rowCount() !== 1) throw new RuntimeException('Record absensi sholat tidak dapat dihapus.');
    sholat_audit($pdo,'DELETE_RECORD',$id,'Menghapus record sholat '.$row['nama_siswa'].' pada sesi '.$row['nama_sesi'].' tanggal '.$row['tanggal'].' untuk pengujian/koreksi.');
    $pdo->commit();
    redirect('sholat/daftar.php?sesi_id=' . $sesiId . '&saved=' . rawurlencode('Record absensi sholat berhasil dihapus. Sesi tetap dipertahankan.'));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect('sholat/daftar.php?sesi_id=' . $sesiId . '&error=' . rawurlencode('Record absensi sholat tidak dapat dihapus: ' . $e->getMessage()));
}
