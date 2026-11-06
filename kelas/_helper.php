<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('kelas');

function kelas_find(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT * FROM master_kelas WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function kelas_audit(PDO $pdo, string $aksi, int $id, string $keterangan): void {
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([user_id(), $aksi, 'kelas', $id, $keterangan, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
}
