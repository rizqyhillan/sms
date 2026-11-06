<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_login();
if(user_role()!=='admin' && user_role()!=='kepala_sekolah'){deny_access();}
header('Location: '.BASE_URL.'tugas_guru/index.php');
exit;
