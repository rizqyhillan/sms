<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// =====================================================
// WAJIB LOGIN
// =====================================================

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// =====================================================
// USER CONTEXT
// =====================================================

function user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function user_name(): ?string
{
    return $_SESSION['user_nama'] ?? null;
}

function user_guru_id(): ?int
{
    return isset($_SESSION['user_guru_id']) ? (int) $_SESSION['user_guru_id'] : null;
}

function user_siswa_id(): ?int
{
    return isset($_SESSION['user_siswa_id']) ? (int) $_SESSION['user_siswa_id'] : null;
}

// =====================================================
// ROLE
// =====================================================

function has_role(string|array $roles): bool
{
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array(user_role(), $roles, true);
}

function has_any_role(string|array $roles): bool
{
    return has_role($roles);
}

function require_role(string|array $roles): void
{
    require_login();

    if (!has_role($roles)) {
        deny_access();
    }
}

// =====================================================
// OUTPUT ESCAPE
// =====================================================

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// REDIRECT
// =====================================================

function redirect(string $url): never
{
    header('Location: ' . BASE_URL . ltrim($url, '/'));
    exit;
}

// =====================================================
// CSRF
// =====================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_valid_csrf(): void
{
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi keamanan telah berubah. Silakan muat ulang halaman.');
    }
}

// =====================================================
// PERMISSION
// =====================================================

function has_permission(string $permission_code): bool
{
    require_login();

    global $pdo;
    static $permission_cache = [];

    if (array_key_exists($permission_code, $permission_cache)) {
        return $permission_cache[$permission_code];
    }

    // Akses dasar role bersifat otomatis. Checkbox manual bukan sumber utama.
    $roleDefaults = [
        'admin' => ['*'],
        'kepala_sekolah' => ['laporan'],
        'guru_bk' => ['siswa','bk','pelanggaran','pembinaan','home_visit','laporan','izin_siswa'],
        'guru' => ['absensi','sholat','laporan','izin_siswa'],
        'tu' => ['siswa','guru','kelas','absensi','sholat','ekstrakurikuler','perpustakaan','uks','laporan','izin_siswa'],
    ];
    $role = (string) user_role();
    if (in_array('*', $roleDefaults[$role] ?? [], true) || in_array($permission_code, $roleDefaults[$role] ?? [], true)) {
        return $permission_cache[$permission_code] = true;
    }

    // Tugas tambahan menjadi sumber akses otomatis untuk modul yang relevan.
    static $taskMap = [
        'uks' => 'uks',
        'perpustakaan' => 'perpustakaan',
    ];
    if (isset($taskMap[$permission_code])) {
        require_once __DIR__ . '/task_access.php';
        if (user_has_task_capability($pdo, $taskMap[$permission_code])) {
            return $permission_cache[$permission_code] = true;
        }
    }

    // Akses manual tetap tersedia sebagai tambahan/pengecualian.
    $stmt = $pdo->prepare('
        SELECT 1
        FROM user_hak_akses uha
        INNER JOIN hak_akses ha ON ha.id = uha.hak_akses_id
        WHERE uha.user_id = ?
          AND ha.kode = ?
          AND ha.aktif = 1
        LIMIT 1
    ');
    $stmt->execute([user_id(), $permission_code]);
    return $permission_cache[$permission_code] = (bool) $stmt->fetchColumn();
}

function require_permission(string $permission_code): void
{
    require_login();

    if (!has_permission($permission_code)) {
        deny_access();
    }
}

// =====================================================
// ACCESS DENIED
// =====================================================

function deny_access(): never
{
    http_response_code(403);
    exit(
        '<!doctype html><html lang="id"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width,initial-scale=1">' .
        '<title>Akses Ditolak</title></head><body>' .
        '<main style="max-width:560px;margin:80px auto;padding:24px;font-family:Arial,sans-serif">' .
        '<h1>Akses Ditolak</h1>' .
        '<p>Anda tidak mempunyai hak akses ke halaman ini.</p>' .
        '<p><a href="' . e(BASE_URL) . 'dashboard.php">Kembali ke dashboard</a></p>' .
        '</main></body></html>'
    );
}
