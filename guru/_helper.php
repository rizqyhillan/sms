<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('guru');

function guru_find(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT * FROM guru WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function guru_generate_qr(PDO $pdo): string {
    do {
        $code = 'GURU-' . strtoupper(bin2hex(random_bytes(6)));
        $st = $pdo->prepare('SELECT 1 FROM guru WHERE kode_qr = ? LIMIT 1');
        $st->execute([$code]);
    } while ($st->fetchColumn());
    return $code;
}

function guru_audit(PDO $pdo, string $aksi, int $id, string $keterangan): void {
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([user_id(), $aksi, 'guru', $id, $keterangan, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
}

function guru_delete_dependencies(PDO $pdo, int $id): array {
    $sql = "SELECT k.TABLE_NAME, k.COLUMN_NAME, rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
             AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.REFERENCED_TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME = 'guru'
              AND k.REFERENCED_COLUMN_NAME = 'id'";
    $refs = $pdo->query($sql)->fetchAll();
    $found = [];
    foreach ($refs as $ref) {
        $rule = strtoupper((string)$ref['DELETE_RULE']);
        if (in_array($rule, ['SET NULL','CASCADE'], true)) continue;
        $childTable = (string)$ref['TABLE_NAME'];
        $column = (string)$ref['COLUMN_NAME'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $childTable) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) continue;
        $st = $pdo->prepare("SELECT COUNT(*) FROM `{$childTable}` WHERE `{$column}` = ?");
        $st->execute([$id]);
        $count = (int)$st->fetchColumn();
        if ($count > 0) $found[] = ['table'=>$childTable,'column'=>$column,'count'=>$count];
    }
    return $found;
}
