<?php

declare(strict_types=1);

// =====================================================
// KONFIGURASI APLIKASI
// =====================================================

define('APP_NAME', 'Sistem Manajemen Sekolah Terpadu');
define('NAMA_SEKOLAH', 'SMP NEGERI 1 PLAOSAN');

// BASE_URL mengikuti host yang sedang dipakai browser. Ini penting untuk
// deployment LAN: localhost tetap bekerja di server, sedangkan perangkat
// lain otomatis mendapatkan URL seperti http://192.168.1.10/sms/.
// Path aplikasi tetap /sms/ sesuai struktur XAMPP saat ini.
$base_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
// HTTP_HOST hanya boleh berisi hostname/IP dan port. Jika nilainya aneh,
// gunakan fallback lokal agar konfigurasi tidak menghasilkan URL rusak.
if (!preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?$/', $base_host)) {
    $base_host = 'localhost';
}
define('BASE_URL', $base_scheme . '://' . $base_host . '/sms/');

// =====================================================
// TIMEZONE
// =====================================================

date_default_timezone_set('Asia/Jakarta');

// =====================================================
// SESSION SECURITY
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    $base_path = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
    if ($base_path === '') {
        $base_path = '/';
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $is_https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $base_path,
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Idle timeout: 8 jam. Hanya berlaku untuk sesi yang sudah login.
if (!empty($_SESSION['user_id'])) {
    $session_timeout = 8 * 60 * 60;
    $last_activity = (int) ($_SESSION['last_activity'] ?? time());

    if ((time() - $last_activity) > $session_timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['last_activity'] = time();
}
