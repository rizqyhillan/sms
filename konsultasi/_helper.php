<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
function konsultasi_require_access(PDO $pdo): void { require_permission('bk'); if(!in_array(user_role(),['admin','kepala_sekolah','guru_bk'],true)){http_response_code(403);exit('Akses konsultasi ditolak.');} }
function konsultasi_tapel(PDO $pdo): ?array { $st=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1"); $r=$st->fetch(); return $r?:null; }
function konsultasi_guru_id(PDO $pdo): int { $uid=(int)($_SESSION['user_id']??0); if(!$uid)return 0; $st=$pdo->prepare('SELECT guru_id FROM users WHERE id=? LIMIT 1');$st->execute([$uid]);return (int)($st->fetchColumn()?:0); }
function konsultasi_audit(PDO $pdo,string $aksi,int $id,string $ket):void { $st=$pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent,created_at) VALUES(?,?, 'konsultasi',?,?,?,?,NOW())");$st->execute([(int)($_SESSION['user_id']??0)?:null,$aksi,$id?:null,$ket,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]); }
