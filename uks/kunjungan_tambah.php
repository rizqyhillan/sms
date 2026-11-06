<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/_helper.php';
uks_require_access($pdo);
if(!uks_can_manage($pdo)){deny_access();}
header('Location: '.BASE_URL.'uks/kunjungan.php?view=antrian');
exit;
