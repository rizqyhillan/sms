<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}
require_valid_csrf();

if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('
            INSERT INTO audit_log
            (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent)
            VALUES (?, \'LOGOUT\', \'AUTH\', ?, ?, ?, ?)
        ');
        $stmt->execute([
            user_id(),
            user_id(),
            'User logout',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (PDOException $e) {
        // Logout tetap harus dilanjutkan meskipun pencatatan audit gagal.
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'] ?? '',
        'secure'   => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

header('Location: ' . BASE_URL . 'login.php');
exit;
