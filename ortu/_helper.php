<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
function ortu_require_access(): void { require_role('ortu'); }
function ortu_get_id(PDO $pdo): int {
 $st=$pdo->prepare('SELECT id FROM orang_tua WHERE user_id=? LIMIT 1'); $st->execute([user_id()]); return (int)($st->fetchColumn()?:0);
}
function ortu_children(PDO $pdo,int $oid): array {
 $st=$pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama,ots.hubungan,mk.nama kelas FROM orang_tua_siswa ots INNER JOIN siswa s ON s.id=ots.siswa_id LEFT JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 INNER JOIN tahun_pelajaran tp2 ON tp2.id=sk2.tahun_pelajaran_id WHERE sk2.siswa_id=s.id AND sk2.status='aktif' AND tp2.aktif=1 ORDER BY sk2.id DESC LIMIT 1) LEFT JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE ots.orang_tua_id=? AND s.status IN ('aktif','lulus') ORDER BY mk.urutan,mk.nama,s.nama"); $st->execute([$oid]); return $st->fetchAll();
}
