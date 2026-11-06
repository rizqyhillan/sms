<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Halaman root aplikasi selalu mengarahkan pengguna ke dashboard.
// Jika belum login, dashboard akan meneruskan ke login.php melalui require_login().
redirect('dashboard.php');
