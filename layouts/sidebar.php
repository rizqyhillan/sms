<?php
/*
 * PART63 UI-19 — Sidebar icon semantics
 * Dashboard      : speedometer/dashboard
 * Data/Users     : people/person
 * Attendance     : calendar-check
 * Library        : book
 * UKS            : heart-pulse
 * Reports        : bar-chart
 * Settings       : gear
 * Audit/Security : shield-check
 * Maintenance    : tools
 * PART102 — Sidebar architecture only; business logic/routes untouched.
 */


if (is_file(__DIR__ . '/../izin/_helper.php')) {
    require_once __DIR__ . '/../izin/_helper.php';
}
if (is_file(__DIR__ . '/../includes/task_access.php')) {
    require_once __DIR__ . '/../includes/task_access.php';
}
if (is_file(__DIR__ . '/../includes/sidebar_access.php')) {
    require_once __DIR__ . '/../includes/sidebar_access.php';
}
if (is_file(__DIR__ . '/../uks/_helper.php')) {
    require_once __DIR__ . '/../uks/_helper.php';
}

$current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$current_menu = trim((string)($_GET['menu'] ?? ''));
$base_path = trim(parse_url(BASE_URL, PHP_URL_PATH), '/');

if ($base_path !== '') {
    $current_path = preg_replace('#^' . preg_quote($base_path, '#') . '/?#', '', $current_path);
}

function menu_active_path(string $path, string $activeKey = ''): string
{
    global $current_path, $current_menu;
    $path = trim($path, '/');

    // Jika satu URL dipakai oleh beberapa konteks menu, parameter menu
    // menjadi sumber kebenaran agar hanya menu yang diklik yang aktif.
    if ($activeKey !== '') {
        return $current_menu === $activeKey ? 'active' : '';
    }

    // Jika halaman sedang dibuka melalui konteks menu tertentu,
    // hanya item yang memiliki activeKey yang sama yang boleh aktif.
    // Ini mencegah dua menu berbeda dengan URL yang sama ikut menyala.
    if ($current_menu !== '') {
        return '';
    }

    // Exact match is the default. A menu pointing to an index.php may
    // also own its child pages, but a standalone sibling such as
    // uks/laporan.php must never become active just because it shares
    // the same directory with uks/index.php.
    if ($current_path === $path) {
        return 'active';
    }

    // Menu index hanya aktif pada URL-nya sendiri.
    // Halaman turunan yang memiliki menu sendiri tidak ikut mengaktifkan induknya.

    // Menu UKS hanya aktif pada dashboard/index UKS itu sendiri.
    // Halaman operasional seperti Layanan UKS, Absensi Petugas, Stok,
    // Rujukan, dan Laporan memiliki menu masing-masing dan tidak boleh
    // membuat item induk UKS ikut aktif.

    return '';
}

function sidebar_icon_tone(string $icon): string
{
    $map = [
        'speedometer2'=>'blue','people-fill'=>'indigo','person-plus-fill'=>'cyan','grid-3x3-gap-fill'=>'violet',
        'arrow-up-circle-fill'=>'green','arrow-left-right'=>'teal','box-arrow-right'=>'orange','person-dash'=>'red',
        'mortarboard-fill'=>'purple','person-badge-fill'=>'green','person-workspace'=>'amber','clipboard-check'=>'cyan',
        'trophy-fill'=>'yellow','calendar2-check-fill'=>'blue','moon-stars-fill'=>'indigo','display-fill'=>'cyan',
        'chat-heart-fill'=>'violet','clock-history'=>'blue','exclamation-triangle-fill'=>'orange','person-check-fill'=>'green',
        'house-door-fill'=>'teal','book-half'=>'amber','qr-code-scan'=>'cyan','journal-arrow-up'=>'blue','arrow-return-left'=>'green',
        'book-fill'=>'amber','tags-fill'=>'violet','bar-chart-line-fill'=>'indigo','heart-pulse-fill'=>'red','hospital-fill'=>'red',
        'capsule'=>'green','bar-chart-fill'=>'indigo','gear-fill'=>'slate','tools'=>'orange','building'=>'cyan',
        'calendar3-range'=>'blue','sliders'=>'violet','calendar-week'=>'cyan','clock-fill'=>'indigo','list-check'=>'green',
        'person-lines-fill'=>'blue','shield-check'=>'green','list-task'=>'amber','person-vcard'=>'violet','calendar3'=>'blue',
        'calendar2-check'=>'blue','box-arrow-right'=>'orange','person-badge-fill'=>'green'
    ];
    return $map[$icon] ?? 'blue';
}

function sidebar_item(string $href, string $label, string $icon, string $path = '', string $extraClass = '', string $activeKey = ''): void
{
    $active = $path !== '' ? menu_active_path($path, $activeKey) : '';
    $hrefUrl = BASE_URL . $href;
    if ($activeKey !== '') {
        $separator = str_contains($hrefUrl, '?') ? '&' : '?';
        $hrefUrl .= $separator . 'menu=' . rawurlencode($activeKey);
    }
    ?>
    <a href="<?= e($hrefUrl) ?>" class="menu-item <?= e(trim($active . ' ' . $extraClass)) ?>">
        <span class="menu-icon tone-<?= e(sidebar_icon_tone($icon)) ?>" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
        <span class="menu-label"><?= e($label) ?></span>
    </a>
    <?php
}

function sidebar_group_open(array $paths): bool
{
    global $current_path, $current_menu;

    foreach ($paths as $path) {
        $path = trim((string)$path, '/');
        if ($path !== '' && ($current_path === $path || str_starts_with($current_path, rtrim($path, '/') . '/'))) {
            return true;
        }
    }

    return false;
}

function sidebar_group(string $label, string $icon, array $paths, string $extraClass = ''): void
{
    $open = sidebar_group_open($paths);
    $groupClass = trim($extraClass . ($open ? ' has-active-child' : ''));
    ?>
    <details class="sidebar-group <?= e($groupClass) ?>" data-sidebar-group="<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label))) ?>" <?= $open ? 'open' : '' ?>>
        <summary class="sidebar-group-toggle">
            <span class="sidebar-group-title">
                <span class="sidebar-group-icon tone-<?= e(sidebar_icon_tone($icon)) ?>" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
                <span><?= e($label) ?></span>
            </span>
            <i class="bi bi-chevron-down sidebar-group-chevron" aria-hidden="true"></i>
        </summary>
        <div class="sidebar-group-body">
    <?php
}

function sidebar_group_end(): void
{
    echo '</div></details>';
}
?>

<?php $sidebar_role_class = 'sidebar-role-' . preg_replace('/[^a-z0-9_-]/i', '', (string)user_role()); ?>
<aside id="globalSidebar" class="sidebar <?= e($sidebar_role_class) ?>" aria-label="Navigasi utama">
    <div class="sidebar-brand">
        <?php if (!empty($school_profile['logo'])): ?>
            <img src="<?= e(school_logo_url($pdo)) ?>" alt="Logo Sekolah" class="sidebar-logo">
        <?php endif; ?>
        <div class="sidebar-brand-info">
            <div class="sidebar-brand-title">SPLASMA</div>
            <small><?= e($school_profile['nama_sekolah']) ?></small>
            <div class="sidebar-role-badge">
                <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                <span><?= e(sidebar_role_label()) ?></span>
            </div>
        </div>
        <?php /* Tugas tambahan ditampilkan di Dashboard, bukan di identitas sidebar. */ ?>
    </div>

    <nav class="sidebar-menu">

        <div class="menu-title sidebar-section-label">Utama</div>
        <?php sidebar_item(user_role() === 'kepala_sekolah' ? 'saya/laporan_kepala.php' : 'dashboard.php', 'Dashboard', 'speedometer2', user_role() === 'kepala_sekolah' ? 'saya/laporan_kepala.php' : 'dashboard.php'); ?>

        <?php if (sidebar_can_module('siswa') || sidebar_can_module('kelas') || user_role() === 'admin'): ?>
            <?php sidebar_group('Data Siswa & Kelas', 'people-fill', [
                'siswa', 'kelas'
            ]); ?>
                <?php if (sidebar_can_module('siswa')): ?>
                    <?php sidebar_item('siswa/index.php', 'Data Siswa', 'people-fill', 'siswa/index.php'); ?>
                    <?php sidebar_item('siswa/baru.php', 'Siswa Baru', 'person-plus-fill', 'siswa/baru.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('kelas')): ?>
                    <?php sidebar_item('kelas/index.php', 'Kelas & Rombel', 'grid-3x3-gap-fill', 'kelas/index.php'); ?>
                <?php endif; ?>
                <?php if (user_role() === 'admin'): ?>
                    <?php sidebar_item('pengaturan/tahun_pelajaran/kenaikan.php', 'Kenaikan Kelas', 'arrow-up-circle-fill', 'pengaturan/tahun_pelajaran/kenaikan.php'); ?>
                    <?php sidebar_item('siswa/mutasi/index.php', 'Mutasi', 'arrow-left-right', 'siswa/mutasi/index.php'); ?>
                    <?php sidebar_item('siswa/mutasi/pindah.php', 'Pindah', 'box-arrow-right', 'siswa/mutasi/pindah.php', 'menu-item-sub'); ?>
                    <?php sidebar_item('siswa/mutasi/keluar.php', 'Keluar', 'person-dash', 'siswa/mutasi/keluar.php', 'menu-item-sub'); ?>
                    <?php sidebar_item('siswa/kelulusan/index.php', 'Kelulusan', 'mortarboard-fill', 'siswa/kelulusan/index.php'); ?>
                <?php endif; ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php
        $has_guru_tendik_menu =
            sidebar_can_module('guru') ||
            sidebar_can_module('tugas_guru') ||
            sidebar_has_task_capability($pdo, 'wali_kelas') ||
            sidebar_has_task_capability($pdo, 'piket') ||
            sidebar_has_task_capability($pdo, 'ekstrakurikuler');
        ?>
        <?php if ($has_guru_tendik_menu): ?>
            <?php sidebar_group('Guru & Tendik', 'person-badge-fill', [
                'guru/index.php', 'tugas_guru', 'wali_kelas', 'piket', 'ekstrakurikuler/saya.php'
            ]); ?>
                <?php if (sidebar_can_module('guru')): ?>
                    <?php sidebar_item('guru/index.php', 'Data Guru', 'person-badge-fill', 'guru/index.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('tugas_guru')): ?>
                    <?php sidebar_item('tugas_guru/index.php', 'Tugas Tambahan', 'person-workspace', 'tugas_guru/index.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_has_task_capability($pdo, 'wali_kelas')): ?>
                    <?php sidebar_item('wali_kelas/index.php', 'Wali Kelas', 'people-fill', 'wali_kelas/index.php', '', 'wali_kelas'); ?>
                <?php endif; ?>
                <?php if (sidebar_has_task_capability($pdo, 'piket')): ?>
                    <?php sidebar_item('piket/index.php', 'Guru Piket', 'clipboard-check', 'piket/index.php', '', 'piket'); ?>
                <?php endif; ?>
                <?php if (sidebar_has_task_capability($pdo, 'ekstrakurikuler')): ?>
                    <?php sidebar_item('ekstrakurikuler/saya.php', 'Ekstrakurikuler Saya', 'trophy-fill', 'ekstrakurikuler/saya.php', '', 'pembina_ekstra'); ?>
                <?php endif; ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php if (sidebar_role() !== 'kepala_sekolah' && (sidebar_can_module('absensi') || (function_exists('izin_can_access') && izin_can_access($pdo)) || sidebar_can_module('sholat'))): ?>
            <?php sidebar_group('Absensi', 'calendar2-check-fill', [
                'absensi', 'izin', 'sholat'
            ]); ?>
                <?php if (sidebar_can_module('absensi')): ?>
                    <?php sidebar_item('absensi/index.php', 'Absensi Kehadiran', 'calendar2-check-fill', 'absensi/index.php'); ?>
                <?php endif; ?>
                <?php if (function_exists('izin_can_access') ? izin_can_access($pdo) : false): ?>
                    <?php sidebar_item('izin/index.php', 'Izin Siswa', 'box-arrow-right', 'izin/index.php', '', 'akademik_izin'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('sholat')): ?>
                    <?php sidebar_item('sholat/index.php', 'Absensi Sholat', 'moon-stars-fill', 'sholat/index.php'); ?>
                <?php endif; ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php if (!in_array(sidebar_role(), ['siswa','ortu','kepala_sekolah'], true)): ?>
            <div class="menu-title sidebar-section-label">Layar Publik</div>
            <?php sidebar_item('layar/index.php', 'Layar Absensi Terpadu', 'display-fill'); ?>
        <?php endif; ?>

        <?php if (sidebar_can_module('bk') || sidebar_can_module('pelanggaran') || sidebar_can_module('pembinaan') || sidebar_can_module('home_visit')): ?>
            <?php sidebar_group('Kesiswaan & BK', 'chat-heart-fill', [
                'bk', 'bk/layanan.php', 'pelanggaran', 'pembinaan', 'home_visit'
            ]); ?>
                <?php if (sidebar_can_module('bk')): ?>
                    <?php sidebar_item('bk/layanan.php', 'Layanan BK', 'chat-heart-fill', 'bk/layanan.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('pelanggaran')): ?>
                    <?php sidebar_item('pelanggaran/index.php', 'Pelanggaran', 'exclamation-triangle-fill', 'pelanggaran/index.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('pembinaan')): ?>
                    <?php sidebar_item('pembinaan/index.php', 'Pembinaan', 'person-check-fill', 'pembinaan/index.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('home_visit')): ?>
                    <?php sidebar_item('home_visit/index.php', 'Home Visit', 'house-door-fill', 'home_visit/index.php'); ?>
                <?php endif; ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php if (sidebar_role() === 'admin' || sidebar_can_module('ekstrakurikuler')): ?>
            <?php sidebar_group('Ekstrakurikuler', 'trophy-fill', ['ekstrakurikuler'], 'sidebar-module-group sidebar-module-ekstra'); ?>
                <?php sidebar_item('ekstrakurikuler/index.php', 'Master Ekstrakurikuler', 'trophy-fill', 'ekstrakurikuler/index.php'); ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php if (sidebar_can_module('perpustakaan') || sidebar_can_module('uks')): ?>
            <div class="menu-title sidebar-section-label">Kegiatan Sekolah</div>
            <?php if (sidebar_can_module('perpustakaan')): ?>
                <?php sidebar_group('Perpustakaan', 'book-half', [
                    'perpustakaan/index.php', 'perpustakaan/pengunjung.php', 'perpustakaan/peminjaman.php',
                    'perpustakaan/pengembalian.php', 'perpustakaan/buku.php', 'perpustakaan/anggota.php',
                    'perpustakaan/kategori.php', 'perpustakaan/laporan.php'
                ], 'sidebar-module-group sidebar-module-perpus'); ?>
                    <?php sidebar_item('perpustakaan/index.php', 'Ringkasan Perpustakaan', 'book-half', 'perpustakaan/index.php'); ?>
                    <?php sidebar_item('perpustakaan/pengunjung.php', 'Kunjungan', 'qr-code-scan', 'perpustakaan/pengunjung.php'); ?>
                    <?php sidebar_item('perpustakaan/peminjaman.php', 'Peminjaman', 'journal-arrow-up', 'perpustakaan/peminjaman.php'); ?>
                    <?php sidebar_item('perpustakaan/pengembalian.php', 'Pengembalian', 'arrow-return-left', 'perpustakaan/pengembalian.php'); ?>
                    <?php sidebar_item('perpustakaan/buku.php', 'Buku', 'book-fill', 'perpustakaan/buku.php'); ?>
                    <?php sidebar_item('perpustakaan/anggota.php', 'Anggota', 'people-fill', 'perpustakaan/anggota.php'); ?>
                    <?php sidebar_item('perpustakaan/kategori.php', 'Kategori Buku', 'tags-fill', 'perpustakaan/kategori.php'); ?>
                    <?php sidebar_item('perpustakaan/laporan.php', 'Laporan', 'bar-chart-fill', 'perpustakaan/laporan.php'); ?>
                <?php sidebar_group_end(); ?>
            <?php endif; ?>
            <?php if (sidebar_can_module('uks')): ?>
                <?php sidebar_group('UKS', 'heart-pulse-fill', [
                    'uks/index.php', 'uks/kunjungan.php', 'uks/rujukan.php', 'uks/petugas_siswa.php',
                    'uks/absensi_petugas.php', 'uks/obat', 'uks/laporan.php'
                ], 'sidebar-module-group sidebar-module-uks'); ?>
                    <?php sidebar_item('uks/index.php', 'Ringkasan UKS', 'heart-pulse-fill', 'uks/index.php'); ?>
                    <?php sidebar_item('uks/kunjungan.php', 'Layanan UKS', 'clipboard2-pulse-fill', 'uks/kunjungan.php'); ?>
                    <?php sidebar_item('uks/rujukan.php', 'Rujukan', 'hospital-fill', 'uks/rujukan.php'); ?>
                    <?php if (sidebar_role() === 'admin'): ?>
                        <?php sidebar_item('uks/petugas_siswa.php', 'Petugas UKS Siswa', 'people-fill', 'uks/petugas_siswa.php'); ?>
                    <?php endif; ?>
                    <?php if (function_exists('uks_can_manage') && uks_can_manage($pdo)): ?>
                        <?php sidebar_item('uks/absensi_petugas.php', 'Absensi Petugas', 'calendar2-check-fill', 'uks/absensi_petugas.php'); ?>
                        <?php sidebar_item('uks/obat/index.php', 'Obat & Stok', 'capsule', 'uks/obat/index.php'); ?>
                    <?php endif; ?>
                    <?php sidebar_item('uks/laporan.php', 'Laporan', 'bar-chart-fill', 'uks/laporan.php'); ?>
                <?php sidebar_group_end(); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (sidebar_role() !== 'kepala_sekolah' && sidebar_can_module('laporan')): ?>
            <div class="menu-title sidebar-section-label">Laporan</div>
            <?php sidebar_item('laporan/index.php', 'Laporan', 'bar-chart-fill', 'laporan/index.php'); ?>
        <?php endif; ?>

        <?php if (sidebar_role() === 'admin'): ?>
            <?php sidebar_group('Sistem & Konfigurasi', 'gear-fill', [
                'pengaturan', 'pengaturan_absensi', 'jenis_tugas', 'tugas_guru', 'pengguna', 'audit_log',
                'pengaturan/pemeliharaan', 'pengaturan/tentang.php', 'pengaturan/integritas.php', 'profil_sekolah'
            ], 'sidebar-system-group'); ?>
                <?php if (sidebar_can_module('pengaturan')): ?>
                    <?php sidebar_item('pengaturan/index.php', 'Pengaturan Sistem', 'gear-fill', 'pengaturan/index.php'); ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('pengguna')): ?>
                    <?php sidebar_item('pengguna/index.php', 'Pengguna & Hak Akses', 'person-lines-fill', 'pengguna/index.php'); ?>
                    <?php if (user_role() === 'admin'): ?>
                        <?php sidebar_item('pengguna/akun_siswa.php', 'Akun Siswa', 'mortarboard-fill', 'pengguna/akun_siswa.php'); ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (sidebar_can_module('audit_log')): ?>
                    <?php sidebar_item('audit_log/index.php', 'Audit Log', 'shield-check', 'audit_log/index.php'); ?>
                <?php endif; ?>
                <?php if (user_role() === 'admin'): ?>
                    <?php sidebar_item('profil_sekolah/index.php', 'Profil Sekolah', 'building', 'profil_sekolah/index.php'); ?>
                    <?php sidebar_item('pengaturan/pemeliharaan/index.php', 'Pemeliharaan Sistem', 'tools', 'pengaturan/pemeliharaan/index.php'); ?>
                <?php endif; ?>
            <?php sidebar_group_end(); ?>
        <?php endif; ?>

        <?php if (user_role() === 'siswa'): ?>
            <div class="menu-title sidebar-section-label">Akun Saya</div>
            <?php sidebar_item('saya/portal.php', 'Portal Siswa', 'mortarboard-fill', 'saya/portal.php'); ?>
            <?php sidebar_item('saya/riwayat.php', 'Riwayat Saya', 'clock-history', 'saya/riwayat.php', 'menu-item-sub'); ?>
            <?php sidebar_item('saya/ubah_password.php', 'Ubah Password', 'key-fill', 'saya/ubah_password.php', 'menu-item-sub'); ?>
        <?php endif; ?>
        <?php if (user_role() === 'ortu'): ?>
            <div class="menu-title sidebar-section-label">Akun Orang Tua</div>
            <?php sidebar_item('ortu/riwayat.php', 'Riwayat Anak', 'people-fill', 'ortu/riwayat.php'); ?>
        <?php endif; ?>
        <?php if (user_role() === 'kepala_sekolah'): ?>
            <div class="menu-title sidebar-section-label">Laporan Kepala Sekolah</div>
            <?php sidebar_item('saya/histori.php', 'Histori & Catatan', 'clock-history', 'saya/histori.php'); ?>
            <div class="menu-title sidebar-section-label">Akun Saya</div>
            <?php sidebar_item('saya/profil.php', 'Profil Saya', 'person-vcard-fill', 'saya/profil.php'); ?>
            <?php sidebar_item('saya/ubah_password_kepala.php', 'Ubah Password', 'key-fill', 'saya/ubah_password_kepala.php'); ?>
        <?php endif; ?>

    </nav>

    <div class="sidebar-bottom">
        <form method="post" action="<?= BASE_URL ?>logout.php" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="logout-item border-0 bg-transparent w-100 text-start">
                <span class="menu-icon tone-red" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                <span class="menu-label">Logout</span>
            </button>
        </form>
    </div>
</aside>
