<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function maintenance_require_admin(): void
{
    require_login();
    if (user_role() !== 'admin') {
        deny_access();
    }
}



/**
 * Proteksi tambahan untuk operasi destruktif (reset/hapus data).
 * Re-authentication berlaku singkat dan terikat pada user admin yang sedang login.
 */
function maintenance_destructive_unlock_ttl(): int
{
    return 300; // 5 menit
}

function maintenance_destructive_is_unlocked(): bool
{
    $uid = user_id();
    if (!$uid) return false;
    $sessionUid = (int)($_SESSION['maintenance_destructive_user_id'] ?? 0);
    $at = (int)($_SESSION['maintenance_destructive_unlocked_at'] ?? 0);
    return $sessionUid === $uid && $at > 0 && (time() - $at) <= maintenance_destructive_unlock_ttl();
}

function maintenance_destructive_lock(): void
{
    unset($_SESSION['maintenance_destructive_user_id'], $_SESSION['maintenance_destructive_unlocked_at'], $_SESSION['maintenance_destructive_failures'], $_SESSION['maintenance_destructive_last_failure']);
}

function maintenance_destructive_audit(string $action, string $description): void
{
    global $pdo;
    try {
        $st = $pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");
        $st->execute([user_id(), $action, 'pemeliharaan', null, $description, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    } catch (Throwable $e) {
        // Audit keamanan tidak boleh membuat halaman verifikasi gagal total.
        // Operasi destruktif tetap dijaga oleh unlock session + CSRF + backup gate.
    }
}

function maintenance_try_destructive_unlock(string $password): array
{
    global $pdo;
    if (maintenance_destructive_is_unlocked()) {
        return ['ok' => true, 'message' => 'Verifikasi Administrator masih berlaku.'];
    }

    $now = time();
    $last = (int)($_SESSION['maintenance_destructive_last_failure'] ?? 0);
    $failures = (int)($_SESSION['maintenance_destructive_failures'] ?? 0);
    if ($failures >= 5 && ($now - $last) < 600) {
        return ['ok' => false, 'message' => 'Verifikasi operasi berisiko dikunci sementara setelah 5 percobaan gagal. Coba lagi setelah 10 menit atau login ulang.'];
    }
    if ($failures >= 5 && ($now - $last) >= 600) {
        $failures = 0;
        $_SESSION['maintenance_destructive_failures'] = 0;
    }

    $st = $pdo->prepare('SELECT password, status, role FROM users WHERE id=? LIMIT 1');
    $st->execute([user_id()]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $valid = is_array($row)
        && ($row['status'] ?? '') === 'aktif'
        && ($row['role'] ?? '') === 'admin'
        && password_verify($password, (string)($row['password'] ?? ''));

    if (!$valid) {
        $failures++;
        $_SESSION['maintenance_destructive_failures'] = $failures;
        $_SESSION['maintenance_destructive_last_failure'] = $now;
        maintenance_destructive_audit('DESTRUCTIVE_REAUTH_FAILED', 'Verifikasi ulang Administrator untuk operasi data sensitif gagal (percobaan '.$failures.').');
        if ($failures >= 5) {
            return ['ok' => false, 'message' => 'Password Administrator salah 5 kali. Operasi berisiko dikunci sementara selama 10 menit.'];
        }
        return ['ok' => false, 'message' => 'Password Administrator salah. Operasi berisiko belum dibuka.'];
    }

    session_regenerate_id(true);
    $_SESSION['maintenance_destructive_user_id'] = (int)user_id();
    $_SESSION['maintenance_destructive_unlocked_at'] = $now;
    $_SESSION['maintenance_destructive_failures'] = 0;
    $_SESSION['maintenance_destructive_last_failure'] = 0;
    maintenance_destructive_audit('DESTRUCTIVE_REAUTH_SUCCESS', 'Verifikasi ulang Administrator berhasil; operasi data sensitif dibuka selama '.maintenance_destructive_unlock_ttl().' detik.');
    return ['ok' => true, 'message' => 'Verifikasi berhasil. Operasi data sensitif dibuka sementara.'];
}

function maintenance_require_destructive_unlock(): void
{
    if (!maintenance_destructive_is_unlocked()) {
        http_response_code(403);
        exit('Operasi data sensitif terkunci. Lakukan verifikasi ulang Administrator terlebih dahulu.');
    }
}

function maintenance_app_root(): string
{
    return dirname(__DIR__);
}

function maintenance_backup_dir(): string
{
    // Backup sengaja disimpan di luar folder aplikasi agar update/redeploy
    // paket FULL tidak ikut menghapus backup yang sudah dibuat.
    // Contoh XAMPP: C:\xampp\absensi_backups
    // Contoh Linux: /var/www/absensi_backups
    return dirname(maintenance_app_root(), 2)
        . DIRECTORY_SEPARATOR . 'absensi_backups';
}

function maintenance_ensure_backup_dir(): string
{
    $dir = maintenance_backup_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        throw new RuntimeException('Folder penyimpanan backup tidak dapat dibuat: ' . $dir);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Folder penyimpanan backup tidak writable: ' . $dir);
    }
    return $dir;
}

function maintenance_find_binary(string $name): ?string
{
    $candidates = [];
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates = [
            'C:\\xampp\\mysql\\bin\\' . $name . '.exe',
            'C:\\xampp\\mysql\\bin\\' . $name,
            $name . '.exe',
            $name,
        ];
    } else {
        $candidates = ['/usr/bin/' . $name, '/usr/local/bin/' . $name, $name];
    }
    foreach ($candidates as $path) {
        if (str_contains($path, DIRECTORY_SEPARATOR) || str_contains($path, '\\')) {
            if (is_file($path)) return $path;
        } else {
            $which = @shell_exec('command -v ' . escapeshellarg($path) . ' 2>/dev/null');
            if (is_string($which) && trim($which) !== '') return trim($which);
        }
    }
    return null;
}

function maintenance_db_config(): array
{
    global $host, $db, $user, $pass;
    return [
        'host' => (string)($host ?? 'localhost'),
        'db'   => (string)($db ?? ''),
        'user' => (string)($user ?? 'root'),
        'pass' => (string)($pass ?? ''),
    ];
}

function maintenance_exec(array $command, ?string $stdin = null): array
{
    $cmd = implode(' ', array_map('escapeshellarg', $command));
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($cmd, $descriptors, $pipes, maintenance_app_root());
    if (!is_resource($process)) {
        throw new RuntimeException('Server tidak mengizinkan proses sistem untuk backup/restore.');
    }
    if ($stdin !== null) {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
}

function maintenance_sql_dump(string $outputFile): void
{
    $cfg = maintenance_db_config();
    $bin = maintenance_find_binary('mysqldump');
    if (!$bin) {
        throw new RuntimeException('mysqldump tidak ditemukan. Pastikan XAMPP/MariaDB terpasang dengan lengkap.');
    }
    $tmpCfg = tempnam(sys_get_temp_dir(), 'absensi_mysql_');
    if ($tmpCfg === false) throw new RuntimeException('Tidak dapat membuat konfigurasi sementara backup.');
    try {
        $clientCfg = "[client]\n"
            . "host=" . $cfg['host'] . "\n"
            . "user=" . $cfg['user'] . "\n";
        if ($cfg['pass'] !== '') $clientCfg .= "password=" . str_replace(["\\", "\n", "\r"], ["\\\\", "", ""], $cfg['pass']) . "\n";
        if (@file_put_contents($tmpCfg, $clientCfg, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis konfigurasi sementara backup.');
        }
        @chmod($tmpCfg, 0600);
        $args = [$bin, '--defaults-extra-file=' . $tmpCfg, '--single-transaction', '--routines', '--triggers', '--events', '--default-character-set=utf8mb4', $cfg['db']];
        $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' > ' . escapeshellarg($outputFile) . ' 2>' . escapeshellarg($outputFile . '.err');
        @exec($cmd, $out, $code);
        @unlink($tmpCfg);
        $err = is_file($outputFile . '.err') ? trim((string)file_get_contents($outputFile . '.err')) : '';
        @unlink($outputFile . '.err');
        if ($code !== 0 || !is_file($outputFile) || filesize($outputFile) < 20) {
            @unlink($outputFile);
            throw new RuntimeException('Backup database gagal.' . ($err !== '' ? ' ' . $err : ''));
        }
    } finally {
        @unlink($tmpCfg);
    }
}

function maintenance_copy_tree(string $source, string $destination): void
{
    if (!is_dir($source)) return;
    if (!is_dir($destination) && !@mkdir($destination, 0750, true)) {
        throw new RuntimeException('Folder tujuan backup tidak dapat dibuat.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $target = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($target)) @mkdir($target, 0750, true);
        } else {
            if (!@copy($item->getPathname(), $target)) {
                throw new RuntimeException('Gagal menyalin file backup: ' . $relative);
            }
        }
    }
}

function maintenance_zip_add_tree(ZipArchive $zip, string $source, string $prefix): void
{
    if (!is_dir($source)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $zip->addFile($item->getPathname(), rtrim($prefix, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative));
    }
}

function maintenance_verify_backup_file(string $zipPath): void
{
    if (!is_file($zipPath) || filesize($zipPath) < 100) {
        throw new RuntimeException('Safety backup tidak ditemukan atau ukurannya tidak valid. Reset dibatalkan.');
    }
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Ekstensi ZIP PHP belum aktif. Reset dibatalkan karena safety backup tidak dapat diverifikasi.');
    }
    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) {
        throw new RuntimeException('Safety backup tidak dapat dibuka/diverifikasi. Reset dibatalkan.');
    }
    try {
        $sqlIndex = $zip->locateName('database.sql', ZipArchive::FL_NODIR);
        $manifestIndex = $zip->locateName('manifest.json', ZipArchive::FL_NODIR);
        if ($sqlIndex === false || $manifestIndex === false) {
            throw new RuntimeException('Safety backup tidak lengkap (database.sql/manifest.json tidak ditemukan). Reset dibatalkan.');
        }
        $sqlStat = $zip->statIndex($sqlIndex);
        if (!is_array($sqlStat) || (int)($sqlStat['size'] ?? 0) < 20) {
            throw new RuntimeException('database.sql pada safety backup tidak valid. Reset dibatalkan.');
        }
        $manifestRaw = $zip->getFromIndex($manifestIndex);
        if (!is_string($manifestRaw) || json_decode($manifestRaw, true) === null) {
            throw new RuntimeException('manifest.json pada safety backup tidak valid. Reset dibatalkan.');
        }
    } finally {
        $zip->close();
    }
}

function maintenance_create_backup(bool $withUploads = true): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Ekstensi ZIP PHP belum aktif.');
    }
    $dir = maintenance_ensure_backup_dir();
    $stamp = date('Ymd_His');
    $zipPath = $dir . DIRECTORY_SEPARATOR . 'backup_' . $stamp . '.zip';
    $tmpSql = tempnam(sys_get_temp_dir(), 'absensi_sql_');
    if ($tmpSql === false) throw new RuntimeException('Tidak dapat membuat file sementara backup.');
    try {
        maintenance_sql_dump($tmpSql);
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File backup tidak dapat dibuat.');
        }
        $manifest = [
            'app' => APP_NAME,
            'database' => maintenance_db_config()['db'],
            'created_at' => date(DATE_ATOM),
            'php_version' => PHP_VERSION,
            'includes_uploads' => $withUploads,
        ];
        $zip->addFile($tmpSql, 'database.sql');
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($withUploads) {
            maintenance_zip_add_tree($zip, maintenance_app_root() . DIRECTORY_SEPARATOR . 'uploads', 'uploads');
        }
        $zip->close();
    } finally {
        @unlink($tmpSql);
    }
    if (!is_file($zipPath) || filesize($zipPath) < 100) {
        @unlink($zipPath);
        throw new RuntimeException('Backup tidak menghasilkan file yang valid.');
    }
    return $zipPath;
}

function maintenance_safe_extract(ZipArchive $zip, string $destination): void
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if ($name === '' || str_contains($name, "\0") || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $name) || preg_match('#^[A-Za-z]:#', $name) || str_starts_with($name, '/')) {
            throw new RuntimeException('Backup mengandung path yang tidak aman.');
        }
    }
    if (!$zip->extractTo($destination)) {
        throw new RuntimeException('Backup tidak dapat diekstrak.');
    }
}

function maintenance_restore_database(string $sqlFile): void
{
    $cfg = maintenance_db_config();
    $bin = maintenance_find_binary('mysql');
    if (!$bin) throw new RuntimeException('mysql client tidak ditemukan.');
    $tmpCfg = tempnam(sys_get_temp_dir(), 'absensi_mysql_');
    if ($tmpCfg === false) throw new RuntimeException('Tidak dapat membuat konfigurasi sementara restore.');
    try {
        $clientCfg = "[client]\n"
            . "host=" . $cfg['host'] . "\n"
            . "user=" . $cfg['user'] . "\n";
        if ($cfg['pass'] !== '') $clientCfg .= "password=" . str_replace(["\\", "\n", "\r"], ["\\\\", "", ""], $cfg['pass']) . "\n";
        if (@file_put_contents($tmpCfg, $clientCfg, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis konfigurasi sementara restore.');
        }
        @chmod($tmpCfg, 0600);
        $args = [$bin, '--defaults-extra-file=' . $tmpCfg, $cfg['db']];
        $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' < ' . escapeshellarg($sqlFile) . ' 2>&1';
        @exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('Restore database gagal: ' . trim(implode("\n", $output)));
        }
    } finally {
        @unlink($tmpCfg);
    }
}

function maintenance_backup_files(): array
{
    $dir = maintenance_backup_dir();
    $rows = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'backup_*.zip') ?: [] as $file) {
        if (!is_file($file)) continue;
        $rows[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => (int)filesize($file),
            'created' => date('Y-m-d H:i:s', (int)filemtime($file)),
        ];
    }
    usort($rows, fn($a,$b) => strcmp($b['name'], $a['name']));
    return $rows;
}

function maintenance_format_bytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024*1024) return number_format($bytes/1024, 1, ',', '.') . ' KB';
    if ($bytes < 1024*1024*1024) return number_format($bytes/(1024*1024), 1, ',', '.') . ' MB';
    return number_format($bytes/(1024*1024*1024), 2, ',', '.') . ' GB';
}
