<?php
require_once __DIR__ . '/../includes/task_access.php';
require_once __DIR__ . '/../includes/auto_sessions.php';

function piket_require(): void
{
    global $pdo;
    require_login();
    if (!user_has_task_capability($pdo, 'piket')) {
        deny_access();
    }
}

/**
 * Compatibility wrapper untuk panel Guru Piket.
 * Menggunakan mesin sesi otomatis yang sama dengan modul Absensi,
 * tanpa memaksa halaman Piket memuat permission modul Absensi.
 */
function absensi_get_or_create_hari_ini(PDO $pdo): ?array
{
    return auto_absensi_hari_ini($pdo);
}
