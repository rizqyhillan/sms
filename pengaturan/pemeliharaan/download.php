<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/maintenance.php';
maintenance_require_admin();
$name=basename((string)($_GET['name']??''));
if(!preg_match('/^backup_[0-9]{8}_[0-9]{6}\.zip$/',$name)) {http_response_code(400);exit('File tidak valid.');}
$path=maintenance_backup_dir().DIRECTORY_SEPARATOR.$name;
if(!is_file($path)) {http_response_code(404);exit('Backup tidak ditemukan.');}
header('Content-Type: application/zip');header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.$name.'"');header('X-Content-Type-Options: nosniff');readfile($path);exit;
