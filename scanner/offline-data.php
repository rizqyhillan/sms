<?php
declare(strict_types=1);
require_once __DIR__ . '/../absensi/_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../includes/auto_sessions.php';
    auto_sessions_today($pdo);

    $today=(new DateTimeImmutable('now'))->format('Y-m-d');
    $st=$pdo->prepare("SELECT s.id,s.tanggal,s.nama_sesi,s.jam_mulai,s.jam_selesai,s.batas_terlambat,s.status,s.status_sesi,ja.kode jenis_kode,ja.kategori,js.nama sholat_nama FROM sesi_absensi s JOIN jenis_absensi ja ON ja.id=s.jenis_absensi_id LEFT JOIN jenis_sholat js ON js.id=s.jenis_sholat_id WHERE s.tanggal=? AND s.status_sesi IN ('DRAFT','OPEN','CLOSED') ORDER BY s.tanggal DESC,s.jam_mulai ASC,s.id ASC");
    $st->execute([$today]);
    $sessions=$st->fetchAll();
    $students = $pdo->query("SELECT id,nis,nisn,nama,kode_qr,status FROM siswa WHERE status='aktif' ORDER BY nama")->fetchAll();
    echo json_encode(['ok'=>true,'generated_at'=>date('c'),'csrf_token'=>csrf_token(),'sessions'=>$sessions,'students'=>$students], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Data offline tidak dapat disiapkan.']);
}
