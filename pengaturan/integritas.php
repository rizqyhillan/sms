<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/maintenance.php';
maintenance_require_admin();
$page_title = 'Integritas Sistem';

$schema = (string)$db;
$checks = [];

function integrity_table_exists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function integrity_quote_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

try {
    // 1. Struktur database.
    $tableCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchColumn();
    $checks[] = [
        'ok' => $tableCount > 0,
        'label' => 'Struktur database',
        'detail' => number_format($tableCount) . ' tabel aktif pada database ' . $schema,
    ];

    // 2. Metadata FK. Tidak mengubah database.
    $fkCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()")->fetchColumn();
    $checks[] = [
        'ok' => true,
        'label' => 'Relasi foreign key',
        'detail' => number_format($fkCount) . ' constraint relasional terdaftar',
    ];

    // 3. Tabel inti mengikuti arsitektur absensi yang benar.
    // Tidak ada tabel sesi_sholat terpisah: sesi sholat menggunakan sesi_absensi
    // dengan jenis_absensi berkategori sholat dan jenis_sholat sebagai referensi.
    $required = [
        'siswa',
        'users',
        'absensi',
        'sesi_absensi',
        'jenis_absensi',
        'jenis_sholat',
        'absensi_sholat',
    ];
    $missing = [];
    foreach ($required as $t) {
        if (!integrity_table_exists($pdo, $t)) {
            $missing[] = $t;
        }
    }
    $checks[] = [
        'ok' => !$missing,
        'label' => 'Tabel inti aplikasi',
        'detail' => $missing
            ? 'Tidak ditemukan: ' . implode(', ', $missing)
            : count($required) . ' tabel inti sesuai arsitektur aplikasi tersedia',
    ];

    // 4. Orphan pada FK satu kolom. FK komposit hanya dihitung sebagai metadata
    // dan tidak dipaksa melalui pemeriksaan parsial yang dapat menghasilkan false positive.
    $orphan = 0;
    $scanned = 0;
    $sql = "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
              AND REFERENCED_COLUMN_NAME IS NOT NULL
              AND ORDINAL_POSITION = 1
            ORDER BY TABLE_NAME, CONSTRAINT_NAME";
    foreach ($pdo->query($sql) as $fk) {
        $constraintCols = $pdo->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
        $constraintCols->execute([$fk['TABLE_NAME'], $fk['CONSTRAINT_NAME'] ?? '']);
        if ((int)$constraintCols->fetchColumn() !== 1) {
            continue;
        }

        $child = integrity_quote_identifier((string)$fk['TABLE_NAME']);
        $col = integrity_quote_identifier((string)$fk['COLUMN_NAME']);
        $parent = integrity_quote_identifier((string)$fk['REFERENCED_TABLE_NAME']);
        $pcol = integrity_quote_identifier((string)$fk['REFERENCED_COLUMN_NAME']);
        $q = "SELECT COUNT(*) FROM {$child} c
              LEFT JOIN {$parent} p ON p.{$pcol} = c.{$col}
              WHERE c.{$col} IS NOT NULL AND p.{$pcol} IS NULL";
        try {
            $n = (int)$pdo->query($q)->fetchColumn();
            $orphan += $n;
            $scanned++;
        } catch (Throwable) {
            // Tetap read-only dan tidak menggagalkan pemeriksaan lain.
        }
    }
    $checks[] = [
        'ok' => $orphan === 0,
        'label' => 'Data orphan relasional',
        'detail' => $scanned
            ? number_format($orphan) . ' baris orphan pada ' . number_format($scanned) . ' relasi FK tunggal yang dipindai'
            : 'Tidak ada relasi FK tunggal yang dapat dipindai',
    ];

    // 5. UNIQUE index: hitung hanya pelanggaran yang benar-benar ditolak oleh
    // aturan UNIQUE MariaDB/MySQL. Baris yang memiliki NULL pada salah satu
    // kolom unique tidak dianggap duplikat karena UNIQUE mengizinkan beberapa NULL.
    // Index prefix (SUB_PART) tidak dipaksa melalui pemeriksaan ini karena
    // perbandingan nilai prefix memerlukan aturan khusus; metadata tetap aman.
    $uniqueViolationRows = 0;
    $uniqueViolationGroups = 0;
    $uniqueChecked = 0;
    $uniquePrefixSkipped = 0;
    $uniqueIssues = [];

    $idxSql = "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX, SUB_PART
               FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND NON_UNIQUE = 0
                 AND INDEX_NAME <> 'PRIMARY'
               ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX";
    $groups = [];
    foreach ($pdo->query($idxSql) as $r) {
        $key = $r['TABLE_NAME'] . '|' . $r['INDEX_NAME'];
        $groups[$key][] = $r;
    }

    foreach ($groups as $key => $rows) {
        if (!$rows) {
            continue;
        }
        [$table, $index] = explode('|', $key, 2);
        $hasPrefix = false;
        $cols = [];
        foreach ($rows as $r) {
            if ($r['SUB_PART'] !== null) {
                $hasPrefix = true;
            }
            $cols[] = (string)$r['COLUMN_NAME'];
        }
        if ($hasPrefix || !$cols) {
            $uniquePrefixSkipped++;
            continue;
        }

        $uniqueChecked++;
        $qt = integrity_quote_identifier($table);
        $safeCols = array_map('integrity_quote_identifier', $cols);
        $notNull = implode(' AND ', array_map(fn(string $c): string => $c . ' IS NOT NULL', $safeCols));
        $groupBy = implode(', ', $safeCols);
        $q = "SELECT COUNT(*) AS duplicate_rows, COUNT(*) - 1 AS excess_rows
              FROM {$qt}
              WHERE {$notNull}
              GROUP BY {$groupBy}
              HAVING COUNT(*) > 1";
        try {
            foreach ($pdo->query($q) as $dup) {
                $rowsInGroup = (int)$dup['duplicate_rows'];
                $uniqueViolationGroups++;
                $uniqueViolationRows += max(1, $rowsInGroup - 1);
                if (count($uniqueIssues) < 5) {
                    $uniqueIssues[] = $table . '.' . $index . ' (' . $rowsInGroup . ' baris)';
                }
            }
        } catch (Throwable) {
            // Jangan mengubah status hanya karena satu indeks tidak dapat dipindai.
        }
    }

    if ($uniqueViolationGroups === 0) {
        $uniqueDetail = number_format($uniqueChecked) . ' unique index diperiksa; tidak ditemukan pelanggaran UNIQUE';
        if ($uniquePrefixSkipped > 0) {
            $uniqueDetail .= '. ' . number_format($uniquePrefixSkipped) . ' index prefix dilewati dari pemeriksaan nilai penuh';
        }
    } else {
        $uniqueDetail = number_format($uniqueViolationGroups) . ' kelompok duplikasi pada ' . number_format($uniqueViolationRows) . ' baris yang melanggar UNIQUE';
        if ($uniqueIssues) {
            $uniqueDetail .= ': ' . implode(', ', $uniqueIssues);
        }
    }

    $checks[] = [
        'ok' => $uniqueViolationGroups === 0,
        'label' => 'Konsistensi unique index',
        'detail' => $uniqueDetail,
    ];



    // 6. AUTO_INCREMENT / kapasitas primary key. Read-only.
    $autoIssues = [];
    $autoChecked = 0;
    $autoWarn = 0;
    $autoCritical = 0;
    $colSql = "SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.DATA_TYPE,
                      c.EXTRA, c.COLUMN_KEY, ts.AUTO_INCREMENT
               FROM information_schema.COLUMNS c
               LEFT JOIN information_schema.TABLES ts
                 ON ts.TABLE_SCHEMA = c.TABLE_SCHEMA AND ts.TABLE_NAME = c.TABLE_NAME
               WHERE c.TABLE_SCHEMA = DATABASE()
                 AND c.EXTRA LIKE '%auto_increment%'
               ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION";
    foreach ($pdo->query($colSql) as $r) {
        $autoChecked++;
        $type = strtolower((string)$r['DATA_TYPE']);
        $unsigned = str_contains(strtolower((string)$r['COLUMN_TYPE']), 'unsigned');
        $max = match ($type) {
            'tinyint' => $unsigned ? 255 : 127,
            'smallint' => $unsigned ? 65535 : 32767,
            'mediumint' => $unsigned ? 16777215 : 8388607,
            'int', 'integer' => $unsigned ? 4294967295 : 2147483647,
            'bigint' => $unsigned ? 18446744073709551615 : 9223372036854775807,
            default => null,
        };
        if ($max === null) continue;
        $next = $r['AUTO_INCREMENT'] !== null ? (float)$r['AUTO_INCREMENT'] : 0.0;
        $ratio = $max > 0 ? ($next / (float)$max) * 100 : 0.0;
        if ($ratio >= 95) {
            $autoCritical++;
            if (count($autoIssues) < 5) $autoIssues[] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'] . ' ' . number_format($ratio, 4) . '%';
        } elseif ($ratio >= 80) {
            $autoWarn++;
            if (count($autoIssues) < 5) $autoIssues[] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'] . ' ' . number_format($ratio, 4) . '%';
        }
    }
    $checks[] = [
        'ok' => $autoCritical === 0 && $autoWarn === 0,
        'group' => 'Database',
        'label' => 'AUTO_INCREMENT',
        'detail' => $autoChecked
            ? number_format($autoChecked) . ' kolom AUTO_INCREMENT diperiksa; ' . number_format($autoCritical) . ' kritis, ' . number_format($autoWarn) . ' perlu perhatian' . ($autoIssues ? '. Contoh: ' . implode(', ', $autoIssues) : '')
            : 'Tidak ditemukan kolom AUTO_INCREMENT',
    ];

    // 7. Primary key integer tunggal tanpa AUTO_INCREMENT: peringatan saja.
    // Tidak semua tabel wajib auto increment, terutama tabel dengan natural/composite key.
    $pkWithoutAuto = [];
    $pkSql = "SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, c.DATA_TYPE, c.EXTRA
              FROM information_schema.KEY_COLUMN_USAGE kcu
              INNER JOIN information_schema.COLUMNS c
                ON c.TABLE_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND c.TABLE_NAME = kcu.TABLE_NAME
               AND c.COLUMN_NAME = kcu.COLUMN_NAME
              WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
                AND kcu.CONSTRAINT_NAME = 'PRIMARY'
              ORDER BY kcu.TABLE_NAME, kcu.ORDINAL_POSITION";
    $pkGroups = [];
    foreach ($pdo->query($pkSql) as $r) $pkGroups[$r['TABLE_NAME']][] = $r;
    foreach ($pkGroups as $table => $cols) {
        if (count($cols) !== 1) continue;
        $r = $cols[0];
        if (!in_array(strtolower((string)$r['DATA_TYPE']), ['tinyint','smallint','mediumint','int','integer','bigint'], true)) continue;
        if (!str_contains(strtolower((string)$r['EXTRA']), 'auto_increment')) $pkWithoutAuto[] = $table . '.' . $r['COLUMN_NAME'];
    }
    $checks[] = [
        'ok' => !$pkWithoutAuto,
        'group' => 'Database',
        'label' => 'Primary key tanpa AUTO_INCREMENT',
        'detail' => $pkWithoutAuto ? count($pkWithoutAuto) . ' tabel perlu review manual: ' . implode(', ', array_slice($pkWithoutAuto, 0, 8)) : 'Tidak ditemukan PK integer tunggal yang kehilangan AUTO_INCREMENT',
    ];

    // 8. Engine tabel: FK/transaksi aplikasi mengandalkan InnoDB.
    $nonInno = [];
    $engineCount = 0;
    foreach ($pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $r) {
        $engineCount++;
        if (strtoupper((string)$r['ENGINE']) !== 'INNODB') $nonInno[] = $r['TABLE_NAME'] . ' (' . ($r['ENGINE'] ?: 'tanpa engine') . ')';
    }
    $checks[] = [
        'ok' => !$nonInno,
        'group' => 'Database',
        'label' => 'Engine tabel',
        'detail' => $nonInno ? count($nonInno) . ' tabel bukan InnoDB: ' . implode(', ', array_slice($nonInno, 0, 5)) : number_format($engineCount) . ' tabel menggunakan InnoDB',
    ];

    // 9. Charset/collation tabel. Hanya memberi peringatan; tidak mengubah data.
    $charsetIssues = [];
    $charsetCount = 0;
    foreach ($pdo->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $r) {
        $charsetCount++;
        $coll = strtolower((string)$r['TABLE_COLLATION']);
        if ($coll === '' || !str_starts_with($coll, 'utf8mb4')) $charsetIssues[] = $r['TABLE_NAME'] . ' (' . ($r['TABLE_COLLATION'] ?: '-') . ')';
    }
    $checks[] = [
        'ok' => !$charsetIssues,
        'group' => 'Database',
        'label' => 'Charset & collation',
        'detail' => $charsetIssues ? count($charsetIssues) . ' tabel perlu diperiksa: ' . implode(', ', array_slice($charsetIssues, 0, 5)) : number_format($charsetCount) . ' tabel konsisten pada utf8mb4',
    ];

    // 10. Potensi index identik berdasarkan metadata.
    $duplicateIndexGroups = 0;
    $idxMeta = [];
    foreach ($pdo->query("SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX") as $r) {
        $key = $r['TABLE_NAME'] . '|' . (int)$r['NON_UNIQUE'];
        $idxMeta[$key][] = (string)$r['COLUMN_NAME'];
    }
    $seen = [];
    foreach ($idxMeta as $key => $cols) {
        $sig = $key . '|' . implode(',', $cols);
        if (isset($seen[$sig])) $duplicateIndexGroups++;
        $seen[$sig] = true;
    }
    $checks[] = [
        'ok' => $duplicateIndexGroups === 0,
        'group' => 'Database',
        'label' => 'Potensi duplikasi index',
        'detail' => $duplicateIndexGroups === 0 ? 'Tidak ditemukan index identik pada metadata' : number_format($duplicateIndexGroups) . ' kelompok index identik terdeteksi; perlu review manual sebelum dihapus',
    ];

    // 11. Ukuran database dan tabel terbesar. Read-only.
    $dbSize = (int)$pdo->query("SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")
        ->fetchColumn();
    $topTables = [];
    foreach ($pdo->query("SELECT TABLE_NAME, COALESCE(DATA_LENGTH + INDEX_LENGTH,0) AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY bytes DESC LIMIT 3") as $r) {
        $topTables[] = $r['TABLE_NAME'] . ' ' . number_format((float)$r['bytes'] / 1048576, 2) . ' MB';
    }
    $checks[] = [
        'ok' => true,
        'group' => 'Operasional',
        'label' => 'Ukuran database',
        'detail' => number_format($dbSize / 1048576, 2) . ' MB; tabel terbesar: ' . ($topTables ? implode(', ', $topTables) : 'belum ada data'),
    ];

    // 12. Runtime PHP dan extension inti.
    $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'openssl', 'json'];
    $missingExt = array_values(array_filter($extensions, fn(string $ext): bool => !extension_loaded($ext)));
    $checks[] = [
        'ok' => !$missingExt,
        'group' => 'Aplikasi',
        'label' => 'PHP & extension',
        'detail' => 'PHP ' . PHP_VERSION . '; ' . ($missingExt ? 'belum tersedia: ' . implode(', ', $missingExt) : 'extension inti tersedia'),
    ];

    // 13. Konfigurasi waktu aplikasi.
    $tz = date_default_timezone_get();
    $checks[] = [
        'ok' => $tz === 'Asia/Jakarta',
        'group' => 'Aplikasi',
        'label' => 'Zona waktu aplikasi',
        'detail' => 'Timezone aktif: ' . $tz . ($tz === 'Asia/Jakarta' ? ' (WIB)' : '; target aplikasi adalah Asia/Jakarta (WIB)'),
    ];

    // 14. Folder storage dasar yang perlu dapat ditulis aplikasi.
    $storagePaths = [
        'uploads' => __DIR__ . '/../uploads',
        // Backup berada di luar folder aplikasi agar tidak hilang saat ZIP FULL
        // dipasang/diganti. Folder dibuat hanya saat backup pertama dibuat.
        'backups' => maintenance_backup_dir(),
    ];
    $storageIssues = [];
    foreach ($storagePaths as $label => $path) {
        if (!is_dir($path)) {
            if ($label === 'backups') {
                // Belum ada backup adalah temuan pada check Backup database,
                // bukan kerusakan storage aplikasi.
                continue;
            }
            $storageIssues[] = $label . ': folder tidak ada';
        } elseif (!is_writable($path)) {
            $storageIssues[] = $label . ': tidak writable';
        }
    }
    $checks[] = [
        'ok' => !$storageIssues,
        'group' => 'Operasional',
        'label' => 'Storage aplikasi',
        'detail' => $storageIssues
            ? implode('; ', $storageIssues)
            : 'Folder upload tersedia; storage backup berada di luar folder aplikasi dan akan dibuat saat backup pertama',
    ];


    // 15. Konsistensi schema inti: tabel dan kolom wajib. Read-only.
    $schemaRequired = [
        'siswa' => ['id'],
        'users' => ['id', 'role'],
        'absensi' => ['id', 'siswa_id'],
        'sesi_absensi' => ['id'],
        'jenis_absensi' => ['id'],
        'jenis_sholat' => ['id'],
        'absensi_sholat' => ['id'],
        'tahun_pelajaran' => ['id'],
        'semester' => ['id'],
    ];
    $schemaMissing = [];
    $schemaTableStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'");
    $schemaColStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    foreach ($schemaRequired as $table => $cols) {
        $schemaTableStmt->execute([$table]);
        if ((int)$schemaTableStmt->fetchColumn() === 0) {
            $schemaMissing[] = $table . ' (tabel)';
            continue;
        }
        foreach ($cols as $col) {
            $schemaColStmt->execute([$table, $col]);
            if ((int)$schemaColStmt->fetchColumn() === 0) $schemaMissing[] = $table . '.' . $col;
        }
    }
    $checks[] = [
        'ok' => !$schemaMissing,
        'group' => 'Database',
        'label' => 'Konsistensi schema inti',
        'detail' => $schemaMissing ? 'Komponen wajib tidak ditemukan: ' . implode(', ', array_slice($schemaMissing, 0, 10)) : count($schemaRequired) . ' tabel inti dan kolom wajib tersedia',
    ];

    // 16. Session runtime/security configuration. Read-only.
    $sessionChecks = [
        'use_only_cookies' => ini_get('session.use_only_cookies') === '1',
        'use_strict_mode' => ini_get('session.use_strict_mode') === '1',
        'use_trans_sid' => ini_get('session.use_trans_sid') === '0',
        'cookie_httponly' => ini_get('session.cookie_httponly') === '1',
        'cookie_samesite' => strtolower((string)ini_get('session.cookie_samesite')) === 'lax',
    ];
    $sessionBad = array_keys(array_filter($sessionChecks, fn($v) => !$v));
    $checks[] = [
        'ok' => !$sessionBad,
        'group' => 'Aplikasi',
        'label' => 'Keamanan session',
        'detail' => $sessionBad
            ? 'Perlu diperiksa: ' . implode(', ', $sessionBad)
            : 'Cookie-only, strict mode, tanpa trans_sid, HttpOnly, dan SameSite=Lax aktif',
    ];

    // 17. Debug/error display configuration. Read-only.
    $displayErrors = strtolower((string)ini_get('display_errors'));
    $displayStartup = strtolower((string)ini_get('display_startup_errors'));
    $logErrors = strtolower((string)ini_get('log_errors'));
    $debugIssues = [];
    if (in_array($displayErrors, ['1', 'on', 'true'], true)) $debugIssues[] = 'display_errors aktif';
    if (in_array($displayStartup, ['1', 'on', 'true'], true)) $debugIssues[] = 'display_startup_errors aktif';
    if (!in_array($logErrors, ['1', 'on', 'true'], true)) $debugIssues[] = 'log_errors nonaktif';
    $checks[] = [
        'ok' => !$debugIssues,
        'group' => 'Aplikasi',
        'label' => 'Debug & error logging',
        'detail' => $debugIssues ? implode('; ', $debugIssues) : 'Error tidak ditampilkan ke browser dan error logging aktif',
    ];

    // 18. Konfigurasi aplikasi/database tanpa menampilkan password. Read-only.
    $appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'local')));
    $dbConfigIssues = [];
    if ($appEnv === 'production') {
        $dbUser = (string)($user ?? '');
        $dbPass = (string)($pass ?? '');
        if ($dbUser === '' || strtolower($dbUser) === 'root') $dbConfigIssues[] = 'user database produksi tidak boleh root';
        if ($dbPass === '') $dbConfigIssues[] = 'password database produksi kosong';
    }
    $checks[] = [
        'ok' => !$dbConfigIssues,
        'group' => 'Aplikasi',
        'label' => 'Konfigurasi aplikasi/database',
        'detail' => $dbConfigIssues
            ? implode('; ', $dbConfigIssues)
            : 'APP_ENV=' . ($appEnv ?: 'local') . '; konfigurasi database tidak menunjukkan pelanggaran aturan mode aktif',
    ];

    // 19. File dan asset penting. Read-only.
    $root = dirname(__DIR__);
    $requiredFiles = [
        'config/app.php',
        'config/database.php',
        'includes/auth.php',
        'includes/maintenance.php',
        'layouts/header.php',
        'layouts/sidebar.php',
        'assets/css/app.css',
        'assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
        'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
        'assets/js/vendor/qrcode.min.js',
        'ekstrakurikuler/sw.js',
        'scanner/sw.js',
        'manifest.json',
    ];
    $missingFiles = [];
    foreach ($requiredFiles as $rel) if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) $missingFiles[] = $rel;
    $checks[] = [
        'ok' => !$missingFiles,
        'group' => 'Aplikasi',
        'label' => 'Asset & file inti',
        'detail' => $missingFiles ? 'File tidak ditemukan: ' . implode(', ', array_slice($missingFiles, 0, 8)) : count($requiredFiles) . ' file inti tersedia',
    ];

    // 20. QR decoder / service worker. jsQR Ekstra disediakan melalui cache
    // Service Worker; tidak mengklaim ada file vendor jsQR fisik jika memang tidak ada.
    $qrFiles = [
        'ekstra_sw' => is_file($root . '/ekstrakurikuler/sw.js'),
        'scanner_sw' => is_file($root . '/scanner/sw.js'),
        'extra_qr_page' => is_file($root . '/ekstrakurikuler/absensi.php'),
        'main_qr_page' => is_file($root . '/scanner/index.php'),
    ];
    $qrBad = array_keys(array_filter($qrFiles, fn($v) => !$v));
    $checks[] = [
        'ok' => !$qrBad,
        'group' => 'Aplikasi',
        'label' => 'QR & Service Worker',
        'detail' => $qrBad
            ? 'Komponen tidak ditemukan: ' . implode(', ', $qrBad)
            : 'Scanner utama dan Ekstrakurikuler memiliki Service Worker; jsQR fallback disiapkan melalui cache browser saat online',
    ];

    // 21. PHP capability untuk upload/image processing. Read-only.
    $capabilities = [
        'GD' => extension_loaded('gd'),
        'FileInfo' => extension_loaded('fileinfo'),
        'Exif' => extension_loaded('exif'),
        'JSON' => extension_loaded('json'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
    ];
    $capBad = array_keys(array_filter($capabilities, fn($v) => !$v));
    $checks[] = [
        'ok' => !$capBad,
        'group' => 'Aplikasi',
        'label' => 'Kemampuan upload & gambar',
        'detail' => $capBad ? 'Extension belum tersedia: ' . implode(', ', $capBad) : 'GD, FileInfo, JSON, dan PDO MySQL tersedia; EXIF juga tersedia',
    ];

    // 22. Runtime HTTP/SAPI dan koneksi database. Read-only.
    $sapi = PHP_SAPI;
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $checks[] = [
        'ok' => $httpHost !== '',
        'group' => 'Aplikasi',
        'label' => 'Runtime web',
        'detail' => 'SAPI=' . $sapi . '; Host=' . ($httpHost ?: '-') . '; Protokol=' . ($https ? 'HTTPS' : 'HTTP') . '; PDO database aktif',
    ];

    // 23. Ukuran/izin log aplikasi jika tersedia. Read-only.
    $logCandidates = [
        $root . '/logs',
        $root . '/storage/logs',
    ];
    $existingLogDirs = [];
    $logWritableIssues = [];
    foreach ($logCandidates as $dir) {
        if (is_dir($dir)) {
            $existingLogDirs[] = $dir;
            if (!is_writable($dir)) $logWritableIssues[] = basename($dir) . ': tidak writable';
        }
    }
    $checks[] = [
        'ok' => !$logWritableIssues,
        'group' => 'Operasional',
        'label' => 'Direktori log',
        'detail' => $existingLogDirs
            ? ($logWritableIssues ? implode('; ', $logWritableIssues) : 'Direktori log tersedia dan writable')
            : 'Belum ada direktori log aplikasi khusus; error PHP tetap mengikuti konfigurasi server',
    ];

    // 24. Kesehatan UI/UX otomatis: asset ikon, struktur HTML, gambar, dan responsive baseline.
    // Pemeriksaan ini bersifat indikatif; kualitas visual akhir tetap memerlukan review pada browser.
    $uiRoot = $root;
    $scanFiles = [];
    $excludeDirs = ['database', 'uploads', 'backups', 'storage', 'cache', 'vendor'];
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uiRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php','html','htm'], true)) continue;
            $path = $file->getPathname();
            $rel = ltrim(str_replace($uiRoot, '', $path), DIRECTORY_SEPARATOR);
            $parts = preg_split('~[\\/]~', $rel);
            if (array_intersect($excludeDirs, $parts)) continue;
            $scanFiles[] = $path;
        }
    } catch (Throwable) {
        $scanFiles = [];
    }

    // 24a. Bootstrap Icons: scan hanya HTML literal hasil template PHP, bukan string PHP/SQL.
    $iconClasses = [];
    $iconUsageCount = 0;
    $missingIcons = [];
    $iconCss = $uiRoot . '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css';
    $iconCssText = is_file($iconCss) ? (string)file_get_contents($iconCss) : '';
    $inlineHtml = static function(string $path): string {
        $txt = (string)@file_get_contents($path);
        if (function_exists('token_get_all')) {
            $out = '';
            foreach (token_get_all($txt) as $tok) {
                if (is_array($tok) && $tok[0] === T_INLINE_HTML) $out .= $tok[1];
            }
            return $out;
        }
        return preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', '', $txt) ?: '';
    };
    foreach ($scanFiles as $path) {
        // Proses setiap token HTML secara terpisah. Jangan menggabungkan semua
        // token T_INLINE_HTML karena blok PHP di antara dua fragmen dapat membuat
        // class dinamis terlihat sebagai class palsu seperti `bi--circle`.
        $htmlParts = [];
        $txt = (string)@file_get_contents($path);
        if (function_exists('token_get_all')) {
            foreach (token_get_all($txt) as $tok) {
                if (is_array($tok) && $tok[0] === T_INLINE_HTML) $htmlParts[] = $tok[1];
            }
        } else {
            $htmlParts[] = $inlineHtml($path);
        }
        foreach ($htmlParts as $html) {
            if (preg_match_all('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $html, $attrs)) {
                foreach ($attrs[1] as $classAttr) {
                    $classes = preg_split('/\s+/', trim($classAttr)) ?: [];
                    foreach ($classes as $ic) {
                        if (!preg_match('/^bi-[a-zA-Z0-9-]+$/', $ic)) continue;
                        $iconClasses[$ic] = true;
                        $iconUsageCount++;
                    }
                }
            }
        }
    }
    foreach (array_keys($iconClasses) as $ic) {
        if ($iconCssText === '' || !preg_match('/\.' . preg_quote($ic, '/') . '(?:::before|\s*\{)/', $iconCssText)) $missingIcons[] = $ic;
    }
    $checks[] = [
        'ok' => !$missingIcons && $iconCssText !== '',
        'group' => 'UI/UX',
        'label' => 'Ikon & Bootstrap Icons',
        'detail' => $iconCssText === ''
            ? 'CSS Bootstrap Icons tidak ditemukan'
            : ($missingIcons ? count($missingIcons) . ' class ikon tidak ditemukan: ' . implode(', ', array_slice($missingIcons, 0, 8)) : count($iconClasses) . ' class ikon unik (' . number_format($iconUsageCount) . ' penggunaan) sesuai dengan library'),
    ];

    // 24b. Gambar tanpa alt: indikasi aksesibilitas/markup yang perlu review.
    $imgWithoutAlt = 0;
    foreach ($scanFiles as $path) {
        // Gunakan HTML literal hasil tokenisasi PHP agar contoh regex/string
        // di dalam source scanner sendiri tidak dihitung sebagai tag <img>.
        $html = $inlineHtml($path);
        if (preg_match_all('/<img\b[^>]*>/i', $html, $imgs)) {
            foreach ($imgs[0] as $tag) {
                if (!preg_match('/\balt\s*=\s*[\"\']/', $tag)) $imgWithoutAlt++;
            }
        }
    }
    $checks[] = [
        'ok' => $imgWithoutAlt === 0,
        'group' => 'UI/UX',
        'label' => 'Aksesibilitas gambar',
        'detail' => $imgWithoutAlt ? number_format($imgWithoutAlt) . ' tag gambar tanpa atribut alt terdeteksi; perlu review' : 'Semua tag gambar yang dipindai memiliki atribut alt',
    ];

    // 24c. ID HTML literal duplikat dalam satu file.
    // Hanya atribut id yang benar-benar berada di dalam tag HTML yang dihitung.
    // Ini sengaja tidak memakai pencarian "\bid=..." global karena source PHP dapat
    // memuat string/fragment seperti <i class="..."> yang sebelumnya terbaca sebagai ID palsu.
    $duplicateIds = [];
    foreach ($scanFiles as $path) {
        $html = $inlineHtml($path);
        if (!preg_match_all('/<[a-zA-Z][^>]*\sid\s*=\s*[\"\']([^\"\']+)[\"\'][^>]*>/s', $html, $m)) continue;
        $seenIds = [];
        foreach ($m[1] as $id) {
            $id = trim($id);
            if ($id === '') continue;
            if (isset($seenIds[$id])) {
                $duplicateIds[] = basename($path) . '#' . $id;
                if (count($duplicateIds) >= 10) break 2;
            }
            $seenIds[$id] = true;
        }
    }
    $checks[] = [
        'ok' => !$duplicateIds,
        'group' => 'UI/UX',
        'label' => 'ID HTML duplikat',
        'detail' => $duplicateIds ? count($duplicateIds) . ' indikasi ID duplikat: ' . implode(', ', array_slice($duplicateIds, 0, 6)) : 'Tidak ditemukan ID literal duplikat dalam file UI yang dipindai',
    ];

    // 24d. Responsive baseline: viewport meta + media query pada stylesheet aplikasi.
    $headerFile = $uiRoot . '/layouts/header.php';
    $headerText = is_file($headerFile) ? (string)file_get_contents($headerFile) : '';
    $cssFiles = [];
    $cssIt = is_dir($uiRoot . '/assets/css') ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uiRoot . '/assets/css', FilesystemIterator::SKIP_DOTS)) : null;
    if ($cssIt) foreach ($cssIt as $file) if ($file->isFile() && strtolower($file->getExtension()) === 'css') $cssFiles[] = $file->getPathname();
    $mediaQueries = 0;
    foreach ($cssFiles as $cssFile) {
        $cssText = (string)@file_get_contents($cssFile);
        $mediaQueries += preg_match_all('/@media\b/i', $cssText, $dummy);
    }
    $hasViewport = (bool)preg_match('/<meta[^>]+name=[\"\']viewport[\"\']/i', $headerText);
    $checks[] = [
        'ok' => $hasViewport && $mediaQueries > 0,
        'group' => 'UI/UX',
        'label' => 'Responsive baseline',
        'detail' => ($hasViewport ? 'Viewport tersedia' : 'Viewport meta tidak ditemukan') . '; ' . number_format($mediaQueries) . ' blok @media pada CSS aplikasi',
    ];

    // 24e. Referensi asset lokal yang literal dan jelas. Hanya memeriksa path assets/ yang benar-benar tertulis.
    $assetMissing = [];
    foreach ($scanFiles as $path) {
        $txt = (string)@file_get_contents($path);
        if (!preg_match_all('~(?:href|src|action)\s*=\s*[\"\'](?:\\?<?=\s*BASE_URL\s*\?>)?assets/([^\"\'?#]+)~i', $txt, $m)) continue;
        foreach ($m[1] as $relAsset) {
            $relAsset = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $relAsset), DIRECTORY_SEPARATOR);
            if (!is_file($uiRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $relAsset)) $assetMissing[] = $relAsset;
            if (count($assetMissing) >= 10) break 2;
        }
    }
    $checks[] = [
        'ok' => !$assetMissing,
        'group' => 'UI/UX',
        'label' => 'Referensi asset lokal',
        'detail' => $assetMissing ? count($assetMissing) . ' asset berpotensi tidak ditemukan: ' . implode(', ', array_slice($assetMissing, 0, 6)) : 'Referensi asset lokal yang dapat dipindai sesuai dengan file yang tersedia',
    ];

    // 24f. Review visual manual tetap diperlukan untuk proporsi/kerapian yang tidak dapat dipastikan dari source.
    $checks[] = [
        'ok' => true,
        'group' => 'UI/UX',
        'label' => 'Review visual manual',
        'detail' => 'Pemeriksaan otomatis tidak dapat menilai sepenuhnya kerapian, kepadatan, alignment, dan proporsi; review browser tetap diperlukan pada desktop dan HP',
    ];

    // 25. Maintenance Health: storage, backup, log, dan retensi dokumentasi. Read-only.
    // Tidak ada file/database yang dihapus atau diubah dari pemeriksaan ini.
    $formatBytes = static function (int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
    };

    // 25a. Storage: ukuran upload, backup, dan audit log.
    $storageRoots = [
        'uploads' => $uiRoot . '/uploads',
        'backups' => maintenance_backup_dir(),
        'audit_log' => $uiRoot . '/audit_log',
    ];
    $storageSummary = [];
    foreach ($storageRoots as $label => $dir) {
        $bytes = 0; $files = 0;
        if (is_dir($dir)) {
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile()) { $files++; $bytes += (int)$f->getSize(); }
                }
            } catch (Throwable) {}
        }
        $storageSummary[] = $label . ': ' . $files . ' file / ' . $formatBytes($bytes);
    }
    $checks[] = [
        'ok' => true,
        'group' => 'Maintenance',
        'label' => 'Ukuran storage',
        'detail' => implode('; ', $storageSummary),
    ];

    // 25b. Backup terakhir: file ZIP harus ada dan tidak terlalu lama.
    $backupRows = function_exists('maintenance_backup_files') ? maintenance_backup_files() : [];
    $backupIssues = [];
    $backupDetail = 'Belum ada backup ZIP yang ditemukan';
    if ($backupRows) {
        $latest = $backupRows[0];
        $ageDays = max(0, (time() - (int)filemtime($latest['path'])) / 86400);
        if ($ageDays > 7) $backupIssues[] = 'backup terakhir berumur ' . number_format($ageDays, 1, ',', '.') . ' hari';
        $backupDetail = 'Backup terakhir: ' . $latest['name'] . ' (' . $formatBytes((int)$latest['size']) . ', ' . $latest['created'] . ')';
    } else {
        $backupIssues[] = 'belum ada backup';
    }
    $checks[] = [
        'ok' => !$backupIssues,
        'group' => 'Maintenance',
        'label' => 'Backup database',
        'detail' => $backupIssues ? implode('; ', $backupIssues) . '. ' . $backupDetail : $backupDetail,
    ];

    // 25c. Validitas struktur backup terakhir tanpa melakukan restore.
    $backupIntegrityOk = true;
    $backupIntegrityDetail = 'Belum dapat diuji karena belum ada backup ZIP';
    if ($backupRows) {
        $latestPath = $backupRows[0]['path'];
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $opened = $zip->open($latestPath);
            if ($opened === true) {
                $hasSql = $zip->locateName('database.sql', ZipArchive::FL_NODIR) !== false;
                $hasManifest = $zip->locateName('manifest.json', ZipArchive::FL_NODIR) !== false;
                $zip->close();
                $backupIntegrityOk = $hasSql && $hasManifest;
                $backupIntegrityDetail = $backupIntegrityOk
                    ? 'Backup terakhir dapat dibuka; database.sql dan manifest.json tersedia. Restore belum dijalankan.'
                    : 'Backup ZIP dapat dibuka tetapi database.sql/manifest.json tidak lengkap';
            } else {
                $backupIntegrityOk = false;
                $backupIntegrityDetail = 'Backup ZIP terakhir tidak dapat dibuka/diperiksa';
            }
        } else {
            $backupIntegrityOk = false;
            $backupIntegrityDetail = 'Ekstensi ZIP PHP tidak tersedia untuk memeriksa backup';
        }
    }
    $checks[] = [
        'ok' => $backupIntegrityOk,
        'group' => 'Maintenance',
        'label' => 'Integritas backup',
        'detail' => $backupIntegrityDetail,
    ];

    // 25d. Dokumentasi foto ekstra: histori database dipertahankan, file foto dapat
    // menjadi kandidat penghapusan setelah semester berakhir. Pemeriksaan hanya membaca.
    $photoMissing = 0; $photoCandidates = 0; $photoRows = 0; $photoBytes = 0;
    if (integrity_table_exists($pdo, 'ekstra_dokumentasi') && integrity_table_exists($pdo, 'ekstra_sesi') && integrity_table_exists($pdo, 'semester')) {
        $photoSql = "SELECT ed.id, ed.file_path, es.tanggal, se.nama semester_nama, se.tanggal_selesai
                     FROM ekstra_dokumentasi ed
                     JOIN ekstra_sesi es ON es.id=ed.sesi_id
                     JOIN semester se ON se.id=es.semester_id";
        try {
            foreach ($pdo->query($photoSql) as $r) {
                $photoRows++;
                $rel = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, (string)$r['file_path']), DIRECTORY_SEPARATOR);
                $rel = preg_replace('~^' . preg_quote(str_replace(['\\','/'], DIRECTORY_SEPARATOR, 'uploads/'), '~') . '~i', '', $rel);
                $candidatePaths = [
                    $uiRoot . DIRECTORY_SEPARATOR . $rel,
                    $uiRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $rel,
                ];
                $found = false; $foundPath = null;
                foreach ($candidatePaths as $cp) {
                    if (is_file($cp)) { $found = true; $foundPath = $cp; break; }
                }
                if (!$found) {
                    $photoMissing++;
                } else {
                    $photoBytes += (int)filesize($foundPath);
                }
                $end = $r['tanggal_selesai'] ?? null;
                if ($end && strtotime((string)$end . ' 23:59:59') < time() && $found) $photoCandidates++;
            }
        } catch (Throwable) {
            $photoRows = 0;
        }
    }
    $checks[] = [
        'ok' => $photoMissing === 0,
        'group' => 'Maintenance',
        'label' => 'Dokumentasi foto & histori',
        'detail' => number_format($photoRows) . ' metadata foto diperiksa; ' . number_format($photoMissing) . ' file foto tidak ditemukan; ' . number_format($photoCandidates) . ' file dari semester yang sudah berakhir menjadi kandidat retensi. Histori database tidak dihapus.' . ($photoRows ? ' Ukuran file yang ditemukan: ' . $formatBytes($photoBytes) : ''),
    ];

    // 25e. File log yang terlalu besar perlu perhatian, tetapi tidak dihapus otomatis.
    $logBytes = 0; $logFiles = 0; $largeLogs = [];
    foreach ($existingLogDirs as $logDir) {
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $logFiles++; $size=(int)$f->getSize(); $logBytes += $size;
                if ($size >= 50*1048576 && count($largeLogs) < 5) $largeLogs[] = basename($f->getPathname()) . ' ' . $formatBytes($size);
            }
        } catch (Throwable) {}
    }
    $checks[] = [
        'ok' => !$largeLogs,
        'group' => 'Maintenance',
        'label' => 'Ukuran log',
        'detail' => number_format($logFiles) . ' file log / ' . $formatBytes($logBytes) . ($largeLogs ? '; file besar: ' . implode(', ', $largeLogs) : '; tidak ada file log >= 50 MB'),
    ];

    // 25f. Kebersihan backup: hitung jumlah backup lama tanpa menghapusnya.
    $oldBackups = 0;
    foreach ($backupRows as $b) {
        if ((time() - (int)filemtime($b['path'])) > 30*86400) $oldBackups++;
    }
    $checks[] = [
        'ok' => true,
        'group' => 'Maintenance',
        'label' => 'Retensi backup',
        'detail' => number_format(count($backupRows)) . ' backup ZIP terdeteksi; ' . number_format($oldBackups) . ' berumur lebih dari 30 hari. Pemeriksaan tidak menghapus backup otomatis.',
    ];

    // 26. Audit & Monitoring Health. Seluruh pemeriksaan di bawah ini READ-ONLY.
    $auditSummary = [
        'total' => 0,
        'today' => 0,
        'last7' => 0,
        'last24' => 0,
        'last_at' => null,
        'login_success_7d' => 0,
        'delete_24h' => 0,
        'status_changes_7d' => 0,
        'status_audit_7d' => 0,
        'config_7d' => 0,
        'actor_null_7d' => 0,
        'future_rows' => 0,
    ];
    $auditRecent = [];
    $auditActorNullRows = [];
    $auditReady = integrity_table_exists($pdo, 'audit_log');

    if (!$auditReady) {
        $checks[] = [
            'ok' => false,
            'group' => 'Audit & Monitoring',
            'label' => 'Audit log',
            'detail' => 'Tabel audit_log tidak ditemukan; aktivitas penting tidak dapat dipantau dari pusat kesehatan sistem',
        ];
    } else {
        // 26a. Struktur audit: kolom penting + indeks waktu.
        $auditCols = [];
        $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_log'");
        $colStmt->execute();
        foreach ($colStmt->fetchAll(PDO::FETCH_COLUMN) as $c) $auditCols[(string)$c] = true;
        $auditRequired = ['id','user_id','aksi','modul','referensi_id','keterangan','ip_address','user_agent','created_at'];
        $auditMissingCols = array_values(array_filter($auditRequired, fn(string $c): bool => !isset($auditCols[$c])));
        $hasCreatedIndex = false;
        try {
            $idx = $pdo->query("SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_log' ORDER BY INDEX_NAME, SEQ_IN_INDEX")->fetchAll();
            foreach ($idx as $r) if (strtolower((string)$r['COLUMN_NAME']) === 'created_at') { $hasCreatedIndex = true; break; }
        } catch (Throwable) {}
        $checks[] = [
            'ok' => !$auditMissingCols && $hasCreatedIndex,
            'group' => 'Audit & Monitoring',
            'label' => 'Fondasi audit log',
            'detail' => !$auditMissingCols && $hasCreatedIndex
                ? 'Kolom audit utama tersedia dan indeks created_at tersedia untuk pemantauan berbasis waktu'
                : 'Perlu review: ' . ($auditMissingCols ? 'kolom hilang ' . implode(', ', $auditMissingCols) : 'indeks created_at tidak ditemukan'),
        ];

        // 26b. Volume dan keterkinian audit.
        try {
            $auditSummary['total'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
            $auditSummary['today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= CURDATE()")->fetchColumn();
            $auditSummary['last24'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
            $auditSummary['last7'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $auditSummary['last_at'] = $pdo->query("SELECT MAX(created_at) FROM audit_log")->fetchColumn() ?: null;
        } catch (Throwable) {}

        $auditAgeHours = null;
        if ($auditSummary['last_at']) {
            $ts = strtotime((string)$auditSummary['last_at']);
            if ($ts !== false) $auditAgeHours = max(0, (time() - $ts) / 3600);
        }
        $checks[] = [
            'ok' => $auditSummary['last_at'] !== null && $auditAgeHours !== null && $auditAgeHours <= 168,
            'group' => 'Audit & Monitoring',
            'label' => 'Keterkinian audit',
            'detail' => $auditSummary['last_at']
                ? 'Audit terakhir ' . date('d-m-Y H:i:s', strtotime((string)$auditSummary['last_at'])) . ' WIB; ' . number_format($auditSummary['last7']) . ' event dalam 7 hari terakhir'
                : 'Belum ada event audit yang tersimpan',
        ];

        // 26c. Login berhasil + coverage login gagal. Sistem saat ini sudah mencatat LOGIN sukses;
        // pemeriksaan source memastikan login gagal juga mempunyai event audit terpisah.
        $auditSummary['login_success_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE aksi='LOGIN' AND modul='AUTH' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $loginSource = is_file(__DIR__ . '/../login.php') ? (string)@file_get_contents(__DIR__ . '/../login.php') : '';
        $hasFailedLoginAudit = (bool)preg_match('/LOGIN[_-](?:FAILED|FAIL)|LOGIN_FAILED|LOGIN_GAGAL/i', $loginSource);
        $checks[] = [
            'ok' => $hasFailedLoginAudit,
            'group' => 'Audit & Monitoring',
            'label' => 'Audit login berhasil/gagal',
            'detail' => number_format($auditSummary['login_success_7d']) . ' login berhasil tercatat dalam 7 hari; ' . ($hasFailedLoginAudit ? 'source aplikasi memiliki penanda audit login gagal' : 'login gagal belum terlihat memiliki event audit khusus; perlu review agar percobaan gagal dapat ditelusuri'),
        ];

        // 26d. Penghapusan/destructive action dalam 24 jam. Ini bukan vonis serangan;
        // jumlah tinggi hanya menjadi sinyal untuk ditinjau.
        $auditSummary['delete_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND (UPPER(aksi) LIKE 'DELETE%' OR UPPER(aksi) LIKE '%_DELETE%' OR UPPER(aksi) LIKE 'HAPUS%' OR UPPER(aksi) LIKE '%RESET%')")->fetchColumn();
        $checks[] = [
            'ok' => $auditSummary['delete_24h'] <= 10,
            'group' => 'Audit & Monitoring',
            'label' => 'Aktivitas penghapusan / reset',
            'detail' => number_format($auditSummary['delete_24h']) . ' event destructive dalam 24 jam terakhir' . ($auditSummary['delete_24h'] > 10 ? '; jumlah tinggi, perlu review Audit Log' : '; tidak melewati ambang review 10 event'),
        ];

        // 26e. Perubahan status kehadiran dibandingkan dengan audit event terkait.
        if (integrity_table_exists($pdo, 'absensi_perubahan')) {
            $auditSummary['status_changes_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM absensi_perubahan WHERE diubah_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $auditSummary['status_audit_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND aksi IN ('VERIFY_STATUS','MANUAL_STATUS','KOREKSI_STATUS','VERIFY_QR_BATCH','FINALIZE_VERIFICATION')")->fetchColumn();
        }
        $checks[] = [
            'ok' => $auditSummary['status_changes_7d'] === 0 || $auditSummary['status_audit_7d'] >= $auditSummary['status_changes_7d'],
            'group' => 'Audit & Monitoring',
            'label' => 'Perubahan status absensi',
            'detail' => number_format($auditSummary['status_changes_7d']) . ' perubahan status absensi dalam 7 hari; ' . number_format($auditSummary['status_audit_7d']) . ' event audit terkait terdeteksi. Pemeriksaan ini membantu menemukan perubahan yang belum punya jejak audit tingkat modul.',
        ];

        // 26f. Perubahan konfigurasi/admin dari event audit yang sudah ada.
        $auditSummary['config_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND (modul LIKE 'pengaturan%' OR modul IN ('tahun_pelajaran','jenis_tugas','pengguna')) AND UPPER(aksi) REGEXP 'CREATE|UPDATE|DELETE|RESET|ACTIVATE|DEACTIVATE|SETTING|CONFIG'")->fetchColumn();
        $checks[] = [
            'ok' => true,
            'group' => 'Audit & Monitoring',
            'label' => 'Perubahan konfigurasi',
            'detail' => number_format($auditSummary['config_7d']) . ' event perubahan konfigurasi/admin terdeteksi dalam 7 hari. Data ini dipantau, bukan diubah dari halaman kesehatan.',
        ];

        // 26g. Anomali dasar: actor kosong dan timestamp masa depan.
        $auditSummary['actor_null_7d'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id IS NULL AND UPPER(COALESCE(aksi,'')) NOT IN ('LOGIN_FAILED','LOGIN-FAILED','LOGIN_GAGAL')")->fetchColumn();
        $auditSummary['future_rows'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at > DATE_ADD(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        $checks[] = [
            'ok' => $auditSummary['future_rows'] === 0 && $auditSummary['actor_null_7d'] === 0,
            'group' => 'Audit & Monitoring',
            'label' => 'Anomali jejak audit',
            'detail' => number_format($auditSummary['actor_null_7d']) . ' event tanpa actor dalam 7 hari dan ' . number_format($auditSummary['future_rows']) . ' event bertimestamp lebih dari 5 menit ke depan. Jika ada, review diperlukan karena audit seharusnya dapat ditelusuri.',
        ];

        // 26h. Aktivitas harian 7 hari: dipakai sebagai monitoring ringkas.
        try {
            $dayStmt = $pdo->query("SELECT DATE(created_at) AS hari, COUNT(*) AS jumlah, COUNT(DISTINCT user_id) AS pengguna FROM audit_log WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY hari DESC");
            $auditDaily = $dayStmt->fetchAll();
            $recentStmt = $pdo->query("SELECT al.created_at, al.aksi, al.modul, al.keterangan, u.nama AS user_nama FROM audit_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC, al.id DESC LIMIT 8");
            $auditRecent = $recentStmt->fetchAll();
            $actorNullStmt = $pdo->query("SELECT al.id, al.created_at, al.user_id, al.aksi, al.modul, al.referensi_id, al.keterangan, al.ip_address, al.user_agent FROM audit_log al WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND al.user_id IS NULL AND UPPER(COALESCE(al.aksi,'')) NOT IN ('LOGIN_FAILED','LOGIN-FAILED','LOGIN_GAGAL') ORDER BY al.created_at DESC, al.id DESC LIMIT 20");
            $auditActorNullRows = $actorNullStmt->fetchAll();
        } catch (Throwable) {
            $auditDaily = [];
            $auditRecent = [];
            $auditActorNullRows = [];
    $auditActorNullRows = [];
        }
    }

    // 27. Security & Access Health. Seluruh pemeriksaan di bawah ini READ-ONLY.
    // Heuristik source hanya memberi indikator untuk review; bukan pengganti penetration test.
    $securityScanFiles = $scanFiles;
    $securityExclude = ['database','uploads','backups','storage','cache','vendor'];
    $postForms = 0; $postFormsNoCsrf = [];
    $mutationFiles = 0; $mutationUnprotected = [];
    $rawSqlInput = [];
    $uploadFiles = [];
    $passwordHashFound = false; $passwordVerifyFound = false; $passwordPlainPattern = [];

    foreach ($securityScanFiles as $path) {
        $txt = (string)@file_get_contents($path);
        $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        $lower = strtolower($rel);

        // Form POST harus memiliki csrf_field() atau handler require_valid_csrf().
        if (preg_match_all("~<form\\b[^>]*\\bmethod\\s*=\\s*[\"\']post[\"\'][^>]*>~i", $txt, $forms)) {
            foreach ($forms[0] as $_form) {
                $postForms++;
                if (strpos($txt, 'csrf_field(') === false && strpos($txt, 'require_valid_csrf(') === false) {
                    if (count($postFormsNoCsrf) < 10) $postFormsNoCsrf[] = $rel;
                }
            }
        }

        // File yang menjalankan mutation database harus memiliki guard akses.
        $isMutation = (bool)preg_match('/\\b(?:INSERT\\s+INTO|UPDATE\\s+[`\\w]|DELETE\\s+FROM|ALTER\\s+TABLE|DROP\\s+TABLE|TRUNCATE\\s+TABLE)\\b/i', $txt);
        // includes/auto_sessions.php adalah mesin internal yang dipanggil modul,
        // bukan endpoint browser. Ia tidak dinilai sebagai halaman akses langsung.
        if ($isMutation && $lower !== 'includes/auto_sessions.php') {
            $mutationFiles++;
            $hasGuard = (bool)preg_match('/(?:require_(?:login|role|permission|valid_csrf)|_require_(?:admin|access)|maintenance_require_admin|pengguna_require_admin|tugas_require_admin)/i', $txt);
            if (!$hasGuard && count($mutationUnprotected) < 10) $mutationUnprotected[] = $rel;
        }

        // Query literal yang langsung memasukkan superglobal atau concatenation
        // input pengguna ke query/exec adalah indikator yang perlu direview.
        $rawQueryPattern = preg_match('/->(?:query|exec)\s*\(\s*"[^"]*\$_(?:GET|POST|REQUEST|COOKIE)/i', $txt)
            || preg_match('~->(?:query|exec)\s*\(\s*\'[^\']*\$_(?:GET|POST|REQUEST|COOKIE)~i', $txt)
            || preg_match('/->(?:query|exec)\s*\(\s*"[^"]*"\s*\.\s*\$\w+/i', $txt)
            || preg_match('~->(?:query|exec)\s*\(\s*\'[^\']*\'\s*\.\s*\$\w+~i', $txt);
        // Query metadata yang menggunakan PDO::quote untuk whitelist/identifier
        // (contoh pemeriksaan information_schema pada maintenance) bukan injection.
        $safeMetadataQuery = strpos($txt, 'information_schema.TABLES') !== false && strpos($txt, '$pdo->quote(') !== false;
        if ($rawQueryPattern && !$safeMetadataQuery) {
            if (count($rawSqlInput) < 10) $rawSqlInput[] = $rel;
        }

        if (strpos($txt, '$_FILES') !== false) $uploadFiles[] = [$rel, $txt];
        if (strpos($txt, 'password_hash(') !== false) $passwordHashFound = true;
        if (strpos($txt, 'password_verify(') !== false) $passwordVerifyFound = true;
        if (preg_match('/(?:INSERT\\s+INTO|UPDATE)\\s+[^;]*password[^;]*\\$_(?:POST|GET|REQUEST)/i', $txt)) {
            if (count($passwordPlainPattern) < 10) $passwordPlainPattern[] = $rel;
        }
    }

    $checks[] = [
        'ok' => $postFormsNoCsrf === [],
        'group' => 'Security & Access',
        'label' => 'Proteksi CSRF form',
        'detail' => $postForms . ' form POST dipindai; ' . ($postFormsNoCsrf === [] ? 'semuanya memiliki indikator CSRF pada handler/markup' : count($postFormsNoCsrf) . ' form perlu review: ' . implode(', ', $postFormsNoCsrf)),
    ];

    $checks[] = [
        'ok' => $mutationUnprotected === [],
        'group' => 'Security & Access',
        'label' => 'Guard endpoint yang mengubah data',
        'detail' => $mutationFiles . ' file dengan operasi mutation database dipindai; ' . ($mutationUnprotected === [] ? 'tidak ditemukan endpoint mutation yang jelas tanpa guard akses' : count($mutationUnprotected) . ' file perlu review: ' . implode(', ', $mutationUnprotected)),
    ];

    $checks[] = [
        'ok' => $rawSqlInput === [],
        'group' => 'Security & Access',
        'label' => 'SQL langsung dari input',
        'detail' => $rawSqlInput === [] ? 'Tidak ditemukan pola query/exec yang secara literal memasukkan superglobal atau concatenation variabel ke query pada scan statis.' : 'Ditemukan ' . count($rawSqlInput) . ' file yang perlu review: ' . implode(', ', $rawSqlInput),
    ];

    $checks[] = [
        'ok' => $passwordHashFound && $passwordVerifyFound && $passwordPlainPattern === [],
        'group' => 'Security & Access',
        'label' => 'Penyimpanan password',
        'detail' => ($passwordHashFound && $passwordVerifyFound ? 'password_hash/password_verify tersedia' : 'mekanisme hashing/verifikasi password belum lengkap') . ($passwordPlainPattern === [] ? '; tidak ditemukan pola penyimpanan plaintext dari input langsung pada scan statis' : '; perlu review: ' . implode(', ', $passwordPlainPattern)),
    ];

    // Upload: image upload harus memeriksa MIME/content dan ukuran; XLSX dibuka
    // menggunakan ZipArchive/xlsx reader sehingga file harus benar-benar dapat dibaca sebagai XLSX.
    $uploadWeak = [];
    foreach ($uploadFiles as [$rel, $txt]) {
        $isImage = (bool)preg_match('/getimagesize|finfo\\s*\\(\\s*FILEINFO_MIME_TYPE/i', $txt);
        $isXlsx = strpos($txt, 'xlsx_read_rows(') !== false && strpos($txt, 'pathinfo') !== false;
        $hasSize = (bool)preg_match("~\\$[^;]*\\[\\s*[\"\']size[\"\']\\s*\\]|\\$[^;]*->size~i", $txt);
        if (!(($isImage || $isXlsx) && $hasSize && strpos($txt, 'is_uploaded_file') !== false)) $uploadWeak[] = $rel;
    }
    $checks[] = [
        'ok' => $uploadWeak === [],
        'group' => 'Security & Access',
        'label' => 'Validasi upload',
        'detail' => count($uploadFiles) . ' endpoint upload dipindai; ' . ($uploadWeak === [] ? 'seluruhnya memiliki indikator validasi file/ukuran dan pemeriksaan upload' : count($uploadWeak) . ' endpoint perlu review: ' . implode(', ', $uploadWeak)),
    ];

    // File sensitif: konfigurasi, includes, dan backup seharusnya tidak dapat dibaca langsung.
    $protectedDirs = ['config', 'includes', 'backups'];
    $protectedDirIssues = [];
    foreach ($protectedDirs as $dirName) {
        $ht = $root . '/' . $dirName . '/.htaccess';
        if (!is_file($ht)) $protectedDirIssues[] = $dirName . ': .htaccess tidak ditemukan';
    }
    $rootHt = $root . '/.htaccess';
    $rootHtText = is_file($rootHt) ? (string)@file_get_contents($rootHt) : '';
    $sensitiveDenied = (bool)preg_match('/<FilesMatch[^>]*\\(\\?:.*?\\b(?:bak|sql|md|log)\\b.*?<\/FilesMatch>/is', $rootHtText) || (bool)preg_match('/\\.(?:bak|sql|md|log)/i', $rootHtText);
    $checks[] = [
        'ok' => $protectedDirIssues === [] && is_file($rootHt) && $sensitiveDenied,
        'group' => 'Security & Access',
        'label' => 'Proteksi file/direktori sensitif',
        'detail' => $protectedDirIssues === [] && is_file($rootHt) && $sensitiveDenied ? 'config, includes, backups memiliki proteksi direktori dan root .htaccess memiliki aturan penolakan file pengembangan/backup' : 'Perlu review: ' . implode('; ', $protectedDirIssues ?: ['proteksi root/format sensitif belum terdeteksi lengkap']),
    ];

    // Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy,
    // Permissions-Policy dan CSP terdeteksi pada root .htaccess.
    $headerNames = ['X-Content-Type-Options','X-Frame-Options','Referrer-Policy','Permissions-Policy','Content-Security-Policy'];
    $missingHeaders = [];
    foreach ($headerNames as $hn) if (!preg_match('/Header\\s+always\\s+set\\s+' . preg_quote($hn, '/') . '\\b/i', $rootHtText)) $missingHeaders[] = $hn;
    $cspReportOnly = (bool)preg_match('/Content-Security-Policy-Report-Only/i', $rootHtText);
    $checks[] = [
        'ok' => $missingHeaders === [],
        'group' => 'Security & Access',
        'label' => 'Security headers',
        'detail' => $missingHeaders === [] ? 'Header keamanan dasar terdeteksi pada .htaccess' . ($cspReportOnly ? '; CSP saat ini mode Report-Only sehingga belum memblokir pelanggaran' : '; CSP terdeteksi sebagai header enforcement') : 'Header belum terdeteksi: ' . implode(', ', $missingHeaders),
    ];

    // Static access-gate coverage. File internal/endpoint publik dikecualikan;
    // hasil ini hanya indikator review, bukan bukti bahwa seluruh route sudah diuji runtime.
    $phpEntryCount = 0; $phpGateMissing = [];
    foreach ($securityScanFiles as $path) {
        $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        if (str_starts_with($rel, 'includes' . DIRECTORY_SEPARATOR) || str_starts_with($rel, 'layouts' . DIRECTORY_SEPARATOR)) continue;
        if (in_array(basename($path), ['login.php','index.php'], true) && dirname($rel) === '.') continue;
        $txt = (string)@file_get_contents($path);
        if (!preg_match('/<main|header\\s*\\(|require_once|require_role|require_permission|require_login|_require_|maintenance_require_admin/i', $txt)) continue;
        $phpEntryCount++;
        $hasGate = (bool)preg_match('/(?:require_login|require_role|require_permission|maintenance_require_admin|pengguna_require_admin|tugas_require_admin|_require_(?:access|admin))/i', $txt);
        $hasHelper = (bool)preg_match('/require_once\\s+__DIR__[^;]*(?:_helper\\.php|auth\\.php)/i', $txt);
        if (!$hasGate && !$hasHelper && count($phpGateMissing) < 10) $phpGateMissing[] = $rel;
    }
    $checks[] = [
        'ok' => $phpGateMissing === [],
        'group' => 'Security & Access',
        'label' => 'Gate akses halaman',
        'detail' => $phpGateMissing === [] ? $phpEntryCount . ' entry/page yang dipindai memiliki indikator gate akses langsung atau melalui helper' : count($phpGateMissing) . ' file perlu review: ' . implode(', ', $phpGateMissing),
    ];

    $checks[] = [
        'ok' => true,
        'group' => 'Security & Access',
        'label' => 'Catatan audit keamanan',
        'detail' => 'Hasil security scan bersifat statis dan indikatif. Temuan perlu dikonfirmasi dengan pengujian runtime/penetration test sebelum dianggap sebagai kerentanan.',
    ];

    // 28. Performance & Database Optimization Health. Seluruh pemeriksaan READ-ONLY.
    // Fokus pada metadata/index yang dapat memengaruhi stabilitas dan performa.
    $perfGroup = 'Performance & Database';

    // Tabel tanpa PRIMARY KEY.
    $noPk = [];
    $pkSql = "SELECT t.TABLE_NAME
              FROM information_schema.TABLES t
              LEFT JOIN information_schema.TABLE_CONSTRAINTS c
                ON c.CONSTRAINT_SCHEMA = t.TABLE_SCHEMA
               AND c.TABLE_NAME = t.TABLE_NAME
               AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
              WHERE t.TABLE_SCHEMA = DATABASE()
                AND t.TABLE_TYPE = 'BASE TABLE'
                AND c.CONSTRAINT_NAME IS NULL
              ORDER BY t.TABLE_NAME";
    foreach ($pdo->query($pkSql) as $r) $noPk[] = (string)$r['TABLE_NAME'];
    $checks[] = [
        'ok' => $noPk === [],
        'group' => $perfGroup,
        'label' => 'Primary key semua tabel',
        'detail' => $noPk === []
            ? 'Semua tabel aktif memiliki PRIMARY KEY'
            : count($noPk) . ' tabel belum memiliki PRIMARY KEY: ' . implode(', ', array_slice($noPk, 0, 10)),
    ];

    // FK tanpa index yang diawali kolom FK. MariaDB/InnoDB dapat membuat index
    // otomatis dalam beberapa kondisi; pemeriksaan ini hanya mendeteksi metadata
    // yang tidak memiliki index yang memadai, bukan bukti query lambat.
    $fkNoIndex = [];
    $fkSql = "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION
              FROM information_schema.KEY_COLUMN_USAGE
              WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IS NOT NULL
              ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION";
    $fkCols = [];
    foreach ($pdo->query($fkSql) as $r) {
        $key = $r['TABLE_NAME'] . '|' . $r['CONSTRAINT_NAME'];
        $fkCols[$key][] = $r;
    }
    foreach ($fkCols as $key => $rows) {
        [$table, $constraint] = explode('|', $key, 2);
        $cols = array_map(fn($r) => (string)$r['COLUMN_NAME'], $rows);
        $st = $pdo->prepare("SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX");
        $st->execute([$table]);
        $idx = [];
        foreach ($st as $r) $idx[$r['INDEX_NAME']][] = (string)$r['COLUMN_NAME'];
        $covered = false;
        foreach ($idx as $indexCols) {
            if (count($indexCols) >= count($cols) && array_slice($indexCols, 0, count($cols)) === $cols) { $covered = true; break; }
        }
        if (!$covered) $fkNoIndex[] = $table . '.' . $constraint;
    }
    $checks[] = [
        'ok' => $fkNoIndex === [],
        'group' => $perfGroup,
        'label' => 'Index foreign key',
        'detail' => $fkNoIndex === []
            ? 'Seluruh FK memiliki index dengan urutan kolom yang sesuai'
            : count($fkNoIndex) . ' FK perlu review index: ' . implode(', ', array_slice($fkNoIndex, 0, 10)),
    ];

    // Index duplikat/prefix sederhana. Tidak menghapus index; hanya indikator.
    $idxRows = [];
    $dupIdxSql = "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
                  FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                  ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX";
    foreach ($pdo->query($dupIdxSql) as $r) {
        $idxRows[$r['TABLE_NAME']][$r['INDEX_NAME']]['non_unique'] = (int)$r['NON_UNIQUE'];
        $idxRows[$r['TABLE_NAME']][$r['INDEX_NAME']]['cols'][] = (string)$r['COLUMN_NAME'];
    }
    $duplicateIndexes = [];
    foreach ($idxRows as $table => $indexes) {
        $names = array_keys($indexes);
        for ($i=0; $i<count($names); $i++) {
            for ($j=$i+1; $j<count($names); $j++) {
                $a=$indexes[$names[$i]]; $b=$indexes[$names[$j]];
                if ($a['non_unique'] === $b['non_unique'] && $a['cols'] === $b['cols']) {
                    $duplicateIndexes[] = $table . ': ' . $names[$i] . ' = ' . $names[$j];
                }
            }
        }
    }
    $checks[] = [
        'ok' => $duplicateIndexes === [],
        'group' => $perfGroup,
        'label' => 'Duplikasi index',
        'detail' => $duplicateIndexes === []
            ? 'Tidak ditemukan index identik pada metadata tabel'
            : count($duplicateIndexes) . ' pasangan index identik perlu review: ' . implode('; ', array_slice($duplicateIndexes, 0, 8)),
    ];

    // Tabel besar: indikator beban/storage, bukan diagnosis performa.
    $largeTables = [];
    $sizeSql = "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY (COALESCE(DATA_LENGTH,0) + COALESCE(INDEX_LENGTH,0)) DESC";
    foreach ($pdo->query($sizeSql) as $r) {
        $bytes = (int)$r['DATA_LENGTH'] + (int)$r['INDEX_LENGTH'];
        if ($bytes >= 100 * 1024 * 1024) {
            $largeTables[] = $r['TABLE_NAME'] . ' (' . number_format($bytes / 1048576, 1) . ' MB)';
        }
    }
    $checks[] = [
        'ok' => $largeTables === [],
        'group' => $perfGroup,
        'label' => 'Tabel berukuran besar',
        'detail' => $largeTables === []
            ? 'Tidak ada tabel yang melewati indikator 100 MB'
            : count($largeTables) . ' tabel melewati 100 MB: ' . implode(', ', array_slice($largeTables, 0, 8)) . '. Ini bukan error, tetapi perlu monitoring performa/backup.',
    ];

    // Tabel dengan AUTO_INCREMENT yang sangat tinggi tetap hanya indikator kapasitas;
    // tidak dilakukan reset atau perubahan nilai.
    $autoCapacityReview = [];
    $autoSql = "SELECT c.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE, c.COLUMN_TYPE, t.AUTO_INCREMENT
                 FROM information_schema.COLUMNS c
                 JOIN information_schema.TABLES t
                   ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
                 WHERE c.TABLE_SCHEMA=DATABASE()
                   AND c.EXTRA LIKE '%auto_increment%'
                   AND t.AUTO_INCREMENT IS NOT NULL";
    $capacityMap = ['tinyint'=>255, 'smallint'=>65535, 'mediumint'=>16777215, 'int'=>4294967295, 'bigint'=>18446744073709551615];
    foreach ($pdo->query($autoSql) as $r) {
        $type = strtolower((string)$r['DATA_TYPE']);
        if (!isset($capacityMap[$type])) continue;
        $max = (float)$capacityMap[$type];
        $next = (float)$r['AUTO_INCREMENT'];
        $pct = $max > 0 ? ($next / $max) * 100 : 0;
        if ($pct >= 80) $autoCapacityReview[] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'] . ' (' . number_format($pct, 2) . '%)';
    }
    $checks[] = [
        'ok' => $autoCapacityReview === [],
        'group' => $perfGroup,
        'label' => 'Kapasitas AUTO_INCREMENT',
        'detail' => $autoCapacityReview === []
            ? 'Tidak ada AUTO_INCREMENT yang mencapai 80% kapasitas tipe datanya'
            : count($autoCapacityReview) . ' kolom mendekati kapasitas: ' . implode(', ', array_slice($autoCapacityReview, 0, 8)),
    ];

    $checks[] = [
        'ok' => true,
        'group' => $perfGroup,
        'label' => 'Catatan performance audit',
        'detail' => 'Pemeriksaan ini hanya membaca metadata database. Tidak ada index yang dibuat/dihapus, tabel yang diubah, atau AUTO_INCREMENT yang di-reset.',
    ];

    // 25. Penegasan mode pemeriksaan.
    $checks[] = [
        'ok' => true,
        'label' => 'Mode pemeriksaan',
        'detail' => 'Read-only: halaman ini tidak INSERT, UPDATE, DELETE, ALTER, atau memperbaiki data otomatis',
    ];
} catch (Throwable $e) {
    $checks[] = [
        'ok' => false,
        'label' => 'Pemeriksaan database',
        'detail' => 'Pemeriksaan tidak selesai: koneksi atau struktur database perlu diperiksa',
    ];
}

// Ringkasan hasil audit manual: tabel pemeriksaan tetap menjadi sumber detail utama.
// Scanner bersifat indikatif; hasil verifikasi manual G1-G6 digunakan agar temuan
// yang sudah CLOSED tidak terus tampil sebagai pekerjaan terbuka.
$auditDisposition = [
    'Guard endpoint yang mengubah data' => [
        'status' => 'HARDENED',
        'priority' => 'Selesai hardening',
        'note' => 'Mesin internal auto_sessions sudah dipisahkan dari jalur baca layar publik; indikator scanner tetap dipertahankan sebagai informasi.',
    ],
    'SQL langsung dari input' => [
        'status' => 'CLOSED',
        'priority' => 'Closed',
        'note' => 'pengguna/edit.php sudah diverifikasi menggunakan cast integer dan prepared statement; tidak ditemukan SQL injection nyata.',
    ],
    'Validasi upload' => [
        'status' => 'HARDENED',
        'priority' => 'Selesai hardening',
        'note' => 'Import XLSX sudah diverifikasi; restore ZIP sudah diberi pembatasan hasil ekstraksi. Detail scanner tetap dipertahankan.',
    ],
    'Proteksi file/direktori sensitif' => [
        'status' => 'HARDENED',
        'priority' => 'Selesai hardening',
        'note' => 'Direktori sensitif dan format backup/log sudah diproteksi; file audit internal juga diblokir dari akses web.',
    ],
    'Gate akses halaman' => [
        'status' => 'CLOSED',
        'priority' => 'Closed',
        'note' => 'Enam file sudah diverifikasi sesuai fungsi publik/static-nya; tidak ditemukan kebutuhan menambahkan login.',
    ],
    'Audit login berhasil/gagal' => [
        'status' => 'CLOSED',
        'priority' => 'Closed',
        'note' => 'LOGIN_FAILED sudah ditambahkan ke audit log dan password tidak dicatat.',
    ],
];

$warningChecks = array_values(array_filter($checks, fn($c) => !$c['ok']));
$warningSummary = [];
$closedCount = 0;
$hardenedCount = 0;
$openCount = 0;
foreach ($warningChecks as $wc) {
    $label = (string)($wc['label'] ?? 'Pemeriksaan');
    $group = (string)($wc['group'] ?? 'Database');
    $disposition = $auditDisposition[$label] ?? null;

    if ($disposition) {
        if ($disposition['status'] === 'CLOSED') {
            $closedCount++;
        } elseif ($disposition['status'] === 'HARDENED') {
            $hardenedCount++;
        }
        // Temuan CLOSED/HARDENED tidak dimasukkan ke daftar pekerjaan terbuka.
        continue;
    }

    $priority = 'Review';
    $note = 'Perlu diperiksa berdasarkan detail temuan; status warning belum berarti kerusakan atau celah keamanan terbukti.';
    if (in_array($label, ['Backup database', 'Kapasitas AUTO_INCREMENT', 'Index foreign key'], true)) {
        $priority = 'Perhatian';
    } elseif (in_array($label, ['Debug & error logging', 'Kemampuan upload & gambar', 'Ikon & Bootstrap Icons', 'Aksesibilitas gambar', 'ID HTML duplikat'], true)) {
        $priority = 'Perbaikan';
    }
    $openCount++;
    $warningSummary[] = [
        'group' => $group,
        'label' => $label,
        'detail' => (string)($wc['detail'] ?? ''),
        'priority' => $priority,
        'note' => $note,
        'status' => 'OPEN',
    ];
}

$warningRawCount = count($warningChecks);
$auditedCount = $closedCount + $hardenedCount + $openCount;
$okCount = count(array_filter($checks, fn($c) => $c['ok']));
$total = count($checks);
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>
<style>
@media (max-width:900px){.health-monitor-grid{grid-template-columns:1fr!important}}
</style>
<main class="main"><header class="topbar"><div class="topbar-title">Integritas Sistem</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content">
<div class="page-header"><div><h1><i class="bi bi-shield-check"></i> Integritas Sistem</h1><p>Pemeriksaan read-only terhadap struktur, relasi, dan konsistensi database.</p></div><a class="btn btn-secondary" href="<?=BASE_URL?>pengaturan/pemeliharaan/index.php"><i class="bi bi-arrow-left"></i> Pemeliharaan</a></div>
<div class="integrity-summary <?= $okCount === $total ? 'is-ok' : 'is-warning' ?>"><div class="integrity-summary-icon"><i class="bi <?= $okCount === $total ? 'bi-shield-check' : 'bi-shield-exclamation' ?>"></i></div><div><strong><?= $okCount === $total ? 'Pemeriksaan selesai' : 'Perlu perhatian' ?></strong><div><?=number_format($okCount)?> dari <?=number_format($total)?> pemeriksaan berstatus OK.</div><small><?=e(date('d-m-Y H:i:s'))?> WIB · Database <?=e($schema)?></small></div></div>
<div class="integrity-grid">
<?php foreach ($checks as $c): ?>
<div class="integrity-card <?= $c['ok'] ? 'ok' : 'warn' ?>">
<div class="integrity-icon"><i class="bi <?= $c['ok'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i></div>
<div><small style="display:block;font-weight:700;opacity:.65;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;"><?=e((string)($c['group'] ?? 'Database'))?></small><h3><?=e($c['label'])?></h3><p><?=e($c['detail'])?></p></div>
</div>
<?php endforeach; ?>
</div>
<div class="maintenance-warning" style="margin-top:18px">
<div class="maintenance-warning-icon"><i class="bi bi-clipboard-check"></i></div>
<div style="width:100%">
<strong>Ringkasan Hasil Audit</strong>
<p style="margin:5px 0 10px">Tabel pemeriksaan di atas tetap menjadi sumber detail scanner. Ringkasan ini menampilkan hanya pekerjaan yang masih terbuka; temuan scanner yang sudah diverifikasi CLOSED/HARDENED tidak dihitung sebagai pekerjaan baru.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
<span style="padding:6px 10px;border-radius:999px;background:#e8f7ef;color:#137a4b;font-weight:700">CLOSED <?=number_format($closedCount)?></span>
<span style="padding:6px 10px;border-radius:999px;background:#fff7df;color:#8a5a00;font-weight:700">HARDENED <?=number_format($hardenedCount)?></span>
<span style="padding:6px 10px;border-radius:999px;background:#fff0f0;color:#a33a3a;font-weight:700">OPEN <?=number_format($openCount)?></span>
<span style="padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#475569;font-weight:700">Indikator scanner <?=number_format($warningRawCount)?></span>
</div>
<?php if (!empty($warningSummary)): ?>
<div style="overflow:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">No</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Temuan</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Sumber</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Prioritas</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Keterangan</th></tr></thead>
<tbody>
<?php foreach ($warningSummary as $i => $w): ?>
<tr>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=number_format($i+1)?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:700"><?=e($w['label'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e($w['group'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e($w['priority'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><div><?=e($w['detail'])?></div><small style="opacity:.7;display:block;margin-top:3px"><?=e($w['note'])?></small></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div style="padding:10px 0;color:#137a4b"><strong>Tidak ada pekerjaan OPEN dari indikator yang sudah diverifikasi.</strong></div>
<?php endif; ?>
</div>
</div>
<?php if ($auditReady): ?>
<div class="health-monitor-grid" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;margin-top:18px">
<div class="maintenance-warning" style="margin-top:0"><div class="maintenance-warning-icon"><i class="bi bi-bar-chart-line"></i></div><div style="width:100%"><strong>Ringkasan aktivitas 7 hari</strong><div style="overflow:auto;margin-top:10px"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Tanggal</th><th style="text-align:right;padding:8px;border-bottom:1px solid #e2e8f0">Event</th><th style="text-align:right;padding:8px;border-bottom:1px solid #e2e8f0">Pengguna</th></tr></thead><tbody><?php foreach (($auditDaily ?? []) as $d): ?><tr><td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e((string)$d['hari'])?></td><td style="padding:8px;text-align:right;border-bottom:1px solid #f1f5f9"><?=number_format((int)$d['jumlah'])?></td><td style="padding:8px;text-align:right;border-bottom:1px solid #f1f5f9"><?=number_format((int)$d['pengguna'])?></td></tr><?php endforeach; ?><?php if (empty($auditDaily)): ?><tr><td colspan="3" style="padding:10px">Belum ada aktivitas.</td></tr><?php endif; ?></tbody></table></div></div></div>
<div class="maintenance-warning" style="margin-top:0"><div class="maintenance-warning-icon"><i class="bi bi-clock-history"></i></div><div style="width:100%"><strong>Aktivitas audit terbaru</strong><div style="margin-top:10px;display:grid;gap:8px"><?php foreach (($auditRecent ?? []) as $a): ?><div style="padding:9px 10px;border:1px solid #e2e8f0;border-radius:9px;background:#fff"><div style="font-size:11px;opacity:.7"><?=e(date('d-m-Y H:i', strtotime((string)$a['created_at'])))?> WIB · <?=e((string)($a['user_nama'] ?: 'Sistem/Tidak diketahui'))?></div><strong style="font-size:13px"><?=e((string)$a['aksi'])?></strong><span style="font-size:11px;margin-left:5px;opacity:.7"><?=e((string)$a['modul'])?></span><div style="font-size:12px;margin-top:3px"><?=e((string)($a['keterangan'] ?? ''))?></div></div><?php endforeach; ?><?php if (empty($auditRecent)): ?><div>Belum ada aktivitas audit.</div><?php endif; ?></div><div style="margin-top:12px"><a class="btn btn-secondary" href="<?=BASE_URL?>audit_log/index.php"><i class="bi bi-journal-text"></i> Buka Audit Log</a></div></div></div>
</div>
<?php endif; ?>
<?php if ($auditReady && !empty($auditActorNullRows)): ?>
<div class="maintenance-warning" style="margin-top:18px">
<div class="maintenance-warning-icon"><i class="bi bi-person-x"></i></div>
<div style="width:100%">
<strong>Detail event audit tanpa actor · 7 hari</strong>
<p style="margin:5px 0 10px">Pemeriksaan ini bersifat read-only. Event tanpa <code>user_id</code> tidak otomatis berarti kesalahan; misalnya <code>LOGIN_FAILED</code> memang dapat terjadi sebelum identitas pengguna berhasil diketahui.</p>
<div style="overflow:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">ID</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Waktu</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Aksi</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Modul</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Referensi</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">Keterangan</th><th style="text-align:left;padding:8px;border-bottom:1px solid #e2e8f0">IP</th></tr></thead>
<tbody>
<?php foreach ($auditActorNullRows as $a): ?>
<tr>
<td style="padding:8px;border-bottom:1px solid #f1f5f9">#<?=e((string)$a['id'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e(date('d-m-Y H:i:s', strtotime((string)$a['created_at'])))?> WIB</td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:700"><?=e((string)$a['aksi'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e((string)$a['modul'])?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e($a['referensi_id'] !== null ? (string)$a['referensi_id'] : '-')?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e((string)($a['keterangan'] ?? '-'))?></td>
<td style="padding:8px;border-bottom:1px solid #f1f5f9"><?=e((string)($a['ip_address'] ?? '-'))?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div></div>
<?php endif; ?>
<div class="maintenance-warning"><div class="maintenance-warning-icon"><i class="bi bi-eye"></i></div><div><strong>Pemeriksaan bersifat read-only</strong><p>Integritas Sistem hanya membaca metadata dan data untuk kontrol. Tidak ada perubahan data, penghapusan, migrasi, atau perbaikan otomatis yang dijalankan dari halaman ini.</p></div></div>
</section></main><?php require_once __DIR__ . '/../layouts/footer.php'; ?>
