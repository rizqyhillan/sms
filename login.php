<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/school_profile.php';
$school_profile = school_profile($pdo);

// Jika masih memiliki sesi aktif, tidak perlu login ulang.
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            $loginIp = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

            // Rate limit ringan berbasis kombinasi username + IP.
            // Tidak memblokir seluruh jaringan sekolah/NAT hanya karena satu akun.
            $failedStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM audit_log
                 WHERE aksi='LOGIN_FAILED' AND modul='AUTH'
                   AND ip_address=? AND keterangan = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
            );
            $failedStmt->execute([$loginIp, 'Username: ' . $username]);
            $recentFailures = (int) $failedStmt->fetchColumn();

            if ($recentFailures >= 5) {
                $error = 'Terlalu banyak percobaan login gagal. Silakan tunggu beberapa menit lalu coba lagi.';
            } else {
                $stmt = $pdo->prepare('
                    SELECT id, username, password, nama, role, guru_id, siswa_id, status
                    FROM users
                    WHERE username = ?
                    LIMIT 1
                ');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if (
                    !$user ||
                    $user['status'] !== 'aktif' ||
                    !password_verify($password, $user['password'])
                ) {
                    $error = 'Username atau password salah.';

                    // Audit gagal tanpa pernah menyimpan password.
                    try {
                        $auditFailed = $pdo->prepare(
                            "INSERT INTO audit_log
                             (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent)
                             VALUES (NULL, 'LOGIN_FAILED', 'AUTH', NULL, ?, ?, ?)"
                        );
                        $auditFailed->execute([
                            'Username: ' . $username,
                            $loginIp !== '' ? $loginIp : null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                        ]);
                    } catch (Throwable) {
                        // Kegagalan audit tidak boleh membuka password atau memutus halaman login.
                    }
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_role'] = (string) $user['role'];
                    $_SESSION['user_nama'] = (string) $user['nama'];
                    $_SESSION['user_guru_id'] = $user['guru_id'] !== null ? (int) $user['guru_id'] : null;
                    $_SESSION['user_siswa_id'] = $user['siswa_id'] !== null ? (int) $user['siswa_id'] : null;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $stmt->execute([$new_hash, $user['id']]);
                    }

                    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                    $stmt->execute([$user['id']]);

                    $stmt = $pdo->prepare('
                        INSERT INTO audit_log
                        (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent)
                        VALUES (?, \'LOGIN\', \'AUTH\', ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $user['id'],
                        $user['id'],
                        'User berhasil login',
                        $loginIp !== '' ? $loginIp : null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ]);

                    redirect('dashboard.php');
                }
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <title><?= e($page_title) ?> - <?= e($school_profile['nama_sekolah']) ?></title><?php if (!empty($school_profile['logo'])): ?><link rel="icon" type="image/png" href="<?= e(school_logo_url($pdo)) ?>"><?php endif; ?>
    <?php $app_css_version = is_file(__DIR__ . '/assets/css/app.css') ? (string) filemtime(__DIR__ . '/assets/css/app.css') : '1'; ?>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/css/app.css?v=<?= e($app_css_version) ?>">
</head>
<body class="login-page">

<main class="login-shell"<?php if (!empty($school_profile['foto'])): ?> style="background-image:linear-gradient(rgba(255,255,255,.86),rgba(255,255,255,.86)),url('<?= e(school_photo_url($pdo)) ?>');background-size:cover;background-position:center"<?php endif; ?>>
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-brand">
            <?php if (!empty($school_profile['logo'])): ?>
                <img src="<?= e(school_logo_url($pdo)) ?>" alt="Logo <?= e($school_profile['nama_sekolah']) ?>" style="width:72px;height:72px;object-fit:contain;border-radius:18px;background:#fff;padding:8px;box-shadow:0 10px 28px rgba(15,23,42,.12)">
            <?php else: ?>
                <span class="login-brand-mark" aria-hidden="true">S</span>
            <?php endif; ?>
            <div>
                <div class="login-brand-title"><?= e($school_profile['nama_sekolah']) ?></div>
                <div class="login-brand-school"><?= e(APP_NAME) ?></div>
            </div>
        </div>

        <div class="login-heading">
            <h1 id="login-title">Masuk ke Sistem</h1>
            <p>Gunakan akun yang telah diberikan administrator.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>

        <div class="login-footer">
            <?= e(APP_NAME) ?> &middot; <?= e(NAMA_SEKOLAH) ?>
        </div>
    </section>
</main>

</body>
</html>
