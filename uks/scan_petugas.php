<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);
header('Location: '.BASE_URL.'uks/absensi_petugas.php');
exit;
