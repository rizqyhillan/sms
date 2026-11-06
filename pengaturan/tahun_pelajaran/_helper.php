<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_login();
if (user_role() !== 'admin') {
    http_response_code(403);
    exit('Akses hanya untuk administrator.');
}

function tp_audit(PDO $pdo, string $aksi, ?int $refId, string $keterangan): void
{
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, aksi, modul, referensi_id, keterangan, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([user_id(), $aksi, 'tahun_pelajaran', $refId, $keterangan, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
}

function tp_find(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM tahun_pelajaran WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}


function tp_reference_blockers(PDO $pdo, int $id): array
{
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $sql = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME = 'tahun_pelajaran'
              AND REFERENCED_COLUMN_NAME = 'id'
              AND TABLE_NAME <> 'semester'
            ORDER BY TABLE_NAME, ORDINAL_POSITION";
    $st = $pdo->prepare($sql);
    $st->execute([$dbName]);

    $blockers = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fk) {
        $table = (string)$fk['TABLE_NAME'];
        $column = (string)$fk['COLUMN_NAME'];
        $qt = '`' . str_replace('`', '``', $table) . '`';
        $qc = '`' . str_replace('`', '``', $column) . '`';
        $q = $pdo->prepare("SELECT COUNT(*) FROM {$qt} WHERE {$qc}=?");
        $q->execute([$id]);
        $count = (int)$q->fetchColumn();
        if ($count > 0) {
            $blockers[] = ['table'=>$table, 'column'=>$column, 'count'=>$count];
        }
    }
    return $blockers;
}


function tp_reset_ekstra_foto_semester(PDO $pdo, int $semesterId): array
{
    if ($semesterId <= 0) return ['count'=>0,'bytes'=>0,'files'=>[]];
    $st=$pdo->prepare('SELECT ed.id,ed.file_path FROM ekstra_dokumentasi ed JOIN ekstra_sesi es ON es.id=ed.sesi_id WHERE es.semester_id=?');
    $st->execute([$semesterId]);
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    $files=[];$bytes=0;$base=dirname(__DIR__,2);
    foreach($rows as $row){
        $rel=ltrim((string)($row['file_path']??''),'/');
        if(strncmp($rel,'uploads/ekstra/',15)!==0) continue;
        $files[]=$rel;$full=$base.'/'.$rel;
        if(is_file($full)){ $size=@filesize($full); if($size!==false)$bytes+=(int)$size; }
    }
    $del=$pdo->prepare('DELETE ed FROM ekstra_dokumentasi ed JOIN ekstra_sesi es ON es.id=ed.sesi_id WHERE es.semester_id=?');
    $del->execute([$semesterId]);
    return ['count'=>count($rows),'bytes'=>$bytes,'files'=>$files];
}

function tp_remove_ekstra_foto_files(array $files): int
{
    $removed=0;$base=dirname(__DIR__,2);
    foreach($files as $rel){
        $rel=ltrim((string)$rel,'/');
        if(strncmp($rel,'uploads/ekstra/',15)!==0) continue;
        $full=$base.'/'.$rel;
        if(is_file($full)&&@unlink($full))$removed++;
    }
    return $removed;
}
