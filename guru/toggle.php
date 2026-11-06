<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';

require_valid_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_message'] = 'Guru tidak valid.';
    header('Location: ' . BASE_URL . 'guru/index.php');
    exit;
}

$guru = guru_find($pdo, $id);
if (!$guru) {
    $_SESSION['flash_message'] = 'Guru tidak ditemukan.';
    header('Location: ' . BASE_URL . 'guru/index.php');
    exit;
}

$newStatus = ($guru['status'] === 'aktif') ? 'nonaktif' : 'aktif';
$st = $pdo->prepare('UPDATE guru SET status = ? WHERE id = ?');
$st->execute([$newStatus, $id]);

guru_audit(
    $pdo,
    'STATUS',
    $id,
    'Mengubah status guru ' . $guru['nama'] . ' menjadi ' . $newStatus
);

$_SESSION['flash_message'] = 'Status guru ' . $guru['nama'] . ' berhasil diubah menjadi ' . ucfirst($newStatus) . '.';
header('Location: ' . BASE_URL . 'guru/index.php');
exit;
