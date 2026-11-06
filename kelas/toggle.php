<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
require_valid_csrf();

$id = (int)($_POST['id'] ?? 0);
$kelas = kelas_find($pdo, $id);
if (!$kelas) {
    http_response_code(404);
    exit('Kelas tidak ditemukan.');
}

$target = ((int)$kelas['aktif'] === 1) ? 0 : 1;
$st = $pdo->prepare('UPDATE master_kelas SET aktif=? WHERE id=?');
$st->execute([$target, $id]);

kelas_audit(
    $pdo,
    'UPDATE',
    $id,
    ($target ? 'Mengaktifkan kelas ' : 'Menonaktifkan kelas ') . $kelas['nama']
);

redirect('kelas/index.php?status=' . ($target ? 'aktif' : 'nonaktif'));
