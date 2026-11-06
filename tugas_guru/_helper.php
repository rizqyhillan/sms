<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_login();
function tugas_require_admin(): void { if (user_role() !== 'admin') deny_access(); }
