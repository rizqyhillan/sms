<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();
header('Location: ' . BASE_URL . 'pengaturan/pemeliharaan/hapus_siswa.php?mode=massal', true, 302);
exit;
