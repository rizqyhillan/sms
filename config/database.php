<?php

declare(strict_types=1);

// =====================================================
// KONFIGURASI DATABASE
// =====================================================
//
// Lokal/XAMPP:
//   tetap dapat memakai fallback root tanpa password agar
//   instalasi offline yang sedang berjalan tidak rusak.
//
// Produksi:
//   set APP_ENV=production dan gunakan DB_HOST, DB_NAME,
//   DB_USER, DB_PASSWORD melalui environment/server config.
//   Root database dengan password kosong ditolak.

$app_env = strtolower(trim((string) (getenv('APP_ENV') ?: 'local')));

$host = (string) (getenv('DB_HOST') ?: 'localhost');
$db   = (string) (getenv('DB_NAME') ?: 'sms');
$user = (string) (getenv('DB_USER') ?: 'root');
$pass = (string) (getenv('DB_PASSWORD') ?: '');
$charset = 'utf8mb4';

if ($app_env === 'production') {
    if ($user === '' || strtolower($user) === 'root') {
        http_response_code(500);
        exit('Konfigurasi database produksi tidak valid: gunakan user database khusus aplikasi.');
    }

    if ($pass === '') {
        http_response_code(500);
        exit('Konfigurasi database produksi tidak valid: password database wajib diisi.');
    }
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Jangan bocorkan detail koneksi database ke browser.
    http_response_code(500);
    exit('Koneksi database gagal.');
}
