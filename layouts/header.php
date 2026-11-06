<?php

require_once __DIR__ . '/../includes/school_profile.php';
$school_profile = school_profile($pdo);
$page_title = $page_title ?? 'Dashboard';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <script>
        (function () {
            try {
                var mode = localStorage.getItem('manajemen_sekolah_sidebar_mode');
                if (mode === 'expanded' || mode === 'collapsed' || mode === 'auto') {
                    document.documentElement.setAttribute('data-sidebar-mode', mode);
                } else {
                    document.documentElement.setAttribute('data-sidebar-mode', 'expanded');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-sidebar-mode', 'expanded');
            }
        })();
    </script>

    <title><?= e($page_title) ?> - <?= e($school_profile['nama_sekolah']) ?></title>
    <?php if (!empty($school_profile['logo'])): ?>
    <link rel="icon" type="image/png" href="<?= e(school_logo_url($pdo)) ?>">
    <?php endif; ?>

    <?php
    $app_css_version = is_file(__DIR__ . '/../assets/css/app.css') ? (string) filemtime(__DIR__ . '/../assets/css/app.css') : '1';
    $icons_css_version = is_file(__DIR__ . '/../assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ? (string) filemtime(__DIR__ . '/../assets/vendor/bootstrap-icons/bootstrap-icons.min.css') : '1';
    ?>
    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>assets/css/app.css?v=<?= e($app_css_version) ?>"
    >

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/bootstrap-icons/bootstrap-icons.min.css?v=<?= e($icons_css_version) ?>">
    <?php $scan_result_css_version = is_file(__DIR__ . '/../assets/css/scan-result.css') ? (string) filemtime(__DIR__ . '/../assets/css/scan-result.css') : '1'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/scan-result.css?v=<?= e($scan_result_css_version) ?>">
    <?php $realtime_js_version = is_file(__DIR__ . '/../assets/js/realtime.js') ? (string) filemtime(__DIR__ . '/../assets/js/realtime.js') : '1'; ?>
    <script src="<?= BASE_URL ?>assets/js/realtime.js?v=<?= e($realtime_js_version) ?>"></script>

</head>

<?php
$__uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$__module_classes = [];
$__module_map = [
    'ekstrakurikuler' => 'module-ekstra',
    'siswa' => 'module-siswa',
    'guru' => 'module-guru',
    'kelas' => 'module-kelas',
    'absensi' => 'module-absensi',
    'izin' => 'module-izin',
    'sholat' => 'module-sholat',
    'bk' => 'module-bk',
    'pelanggaran' => 'module-pelanggaran',
    'pembinaan' => 'module-pembinaan',
    'home_visit' => 'module-home-visit',
    'perpustakaan' => 'module-perpustakaan',
    'uks' => 'module-uks',
    'layar' => 'module-layar',
    'laporan' => 'module-laporan',
    'pengaturan' => 'module-pengaturan',
];
foreach ($__module_map as $__needle => $__class) {
    if (strpos($__uri, '/' . $__needle . '/') !== false || str_ends_with(parse_url($__uri, PHP_URL_PATH) ?: '', '/' . $__needle . '.php')) {
        $__module_classes[] = $__class;
    }
}
$body_class = implode(' ', array_unique($__module_classes));
?>
<body class="<?= e($body_class) ?>">

<div class="app">

<!-- Global Sidebar Controller: presentation-only, applies to every role/page. -->
<button
    type="button"
    class="global-sidebar-toggle"
    id="globalSidebarToggle"
    aria-controls="globalSidebar"
    aria-expanded="true"
    aria-label="Kecilkan sidebar"
    title="Kecilkan sidebar"
>
    <i class="bi bi-chevron-left" aria-hidden="true"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>