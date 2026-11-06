<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_access.php';
require_once __DIR__ . '/../includes/operational_hours.php';

function perpus_can_manage(): bool { return in_array(user_role(), ['admin','kepala_sekolah'], true); }
function perpus_can_operate(): bool {
    if (perpus_can_manage()) return true;
    global $pdo;
    return user_has_task_capability($pdo, 'perpustakaan');
}
function perpus_require_access(): void {
    require_login();
    if (has_permission('perpustakaan')) return;
    global $pdo;
    if (user_has_task_capability($pdo, 'perpustakaan')) return;
    deny_access();
}
function perpus_require_operate(): void {
    perpus_require_access();
    if (!perpus_can_operate()) deny_access();
}
function perpus_require_manage(): void { perpus_require_access(); if (!perpus_can_manage()) deny_access(); }
function perpus_flash(?string $msg=null): ?string { if($msg!==null){$_SESSION['perpus_flash']=$msg;return null;} $m=$_SESSION['perpus_flash']??null; unset($_SESSION['perpus_flash']); return is_string($m)?$m:null; }
function perpus_audit(PDO $pdo,string $aksi,?int $ref,string $ket):void { $st=$pdo->prepare("INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES (?,?,'perpustakaan',?,?,?,?)");$st->execute([user_id(),$aksi,$ref,$ket,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]); }
function perpus_audit_detail(PDO $pdo,string $aksi,?int $ref,string $ket,array $before=[],array $after=[]):void { $parts=[$ket]; if($before!==[]||$after!==[]){$parts[]='PERUBAHAN='.json_encode(['before'=>$before,'after'=>$after],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);} perpus_audit($pdo,$aksi,$ref,implode(' | ',$parts)); }
function perpus_sync_stock(PDO $pdo,int $bukuId):int { if($bukuId<=0)return 0; $st=$pdo->prepare("SELECT COUNT(*) FROM perpustakaan_eksemplar WHERE buku_id=? AND status='tersedia'");$st->execute([$bukuId]);$n=(int)$st->fetchColumn();$up=$pdo->prepare('UPDATE perpustakaan_buku SET stok=? WHERE id=?');$up->execute([$n,$bukuId]);return $n; }
function perpus_sync_all_stock(PDO $pdo):void { $pdo->exec("UPDATE perpustakaan_buku b SET b.stok=(SELECT COUNT(*) FROM perpustakaan_eksemplar e WHERE e.buku_id=b.id AND e.status='tersedia')"); }
/**
 * Perpustakaan menggunakan sumber waktu operasional reguler sekolah yang sama
 * dengan BK dan UKS: sesi Absensi Kehadiran harus OPEN.
 * Kegiatan khusus seperti Ekstrakurikuler tetap menggunakan jadwal khususnya.
 */
function perpus_waktu_pembelajaran_aktif(PDO $pdo, ?DateTimeImmutable $now = null): array
{
    return sekolah_waktu_operasional_aktif($pdo, $now);
}

function perpus_require_waktu_pembelajaran(PDO $pdo): array
{
    return sekolah_require_operasional_aktif($pdo);
}

function perpus_effective_loan_status(array $row):string { $status=(string)($row['status']??''); if(in_array($status,['dipinjam','sebagian_kembali'],true) && !empty($row['tanggal_jatuh_tempo']) && (string)$row['tanggal_jatuh_tempo'] < date('Y-m-d')) return 'terlambat'; return $status; }
function perpus_query(array $changes=[]):string{$b=$_GET??[];unset($b['page']);foreach($changes as $k=>$v){if($v===null)unset($b[$k]);else$b[$k]=$v;}return http_build_query($b);}
function perpus_label_status(string $v):string{return ['aktif'=>'Aktif','nonaktif'=>'Nonaktif','tersedia'=>'Tersedia','dipinjam'=>'Dipinjam','sebagian_kembali'=>'Sebagian Kembali','selesai'=>'Selesai','terlambat'=>'Terlambat','dibatalkan'=>'Dibatalkan','kembali'=>'Kembali','hilang'=>'Hilang'][$v]??$v;}
function perpus_member_label(array $r):string{return ($r['nama']??'-').' · '.($r['kode_anggota']??'-');}
function perpus_current_class(PDO $pdo,int $siswaId):string{$st=$pdo->prepare("SELECT mk.nama FROM siswa_kelas sk JOIN master_kelas mk ON mk.id=sk.kelas_id WHERE sk.siswa_id=? AND sk.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1) AND sk.status='aktif' ORDER BY sk.id DESC LIMIT 1");$st->execute([$siswaId]);return (string)($st->fetchColumn()??'-');}

function perpus_sync_members(PDO $pdo): void {
    // Seluruh siswa dan guru aktif otomatis menjadi anggota perpustakaan.
    // Baris anggota yang sudah ada tidak diubah, termasuk jika sengaja dibuat nonaktif.
    $pdo->exec("INSERT IGNORE INTO perpustakaan_anggota (kode_anggota,siswa_id,guru_id,tanggal_daftar,status,keterangan)
        SELECT CONCAT('LIB-S-',s.id),s.id,NULL,CURDATE(),'aktif','Anggota otomatis dari data siswa'
        FROM siswa s
        WHERE s.status='aktif' AND NOT EXISTS (SELECT 1 FROM perpustakaan_anggota a WHERE a.siswa_id=s.id)");
    $pdo->exec("INSERT IGNORE INTO perpustakaan_anggota (kode_anggota,siswa_id,guru_id,tanggal_daftar,status,keterangan)
        SELECT CONCAT('LIB-G-',g.id),NULL,g.id,CURDATE(),'aktif','Anggota otomatis dari data guru'
        FROM guru g
        WHERE g.status='aktif' AND NOT EXISTS (SELECT 1 FROM perpustakaan_anggota a WHERE a.guru_id=g.id)");
}
function perpus_ensure_member(PDO $pdo, string $jenis, int $refId): ?array {
    if ($refId<=0 || !in_array($jenis,['siswa','guru'],true)) return null;
    $col=$jenis==='siswa'?'siswa_id':'guru_id';
    $st=$pdo->prepare("SELECT id,kode_anggota,status FROM perpustakaan_anggota WHERE {$col}=? LIMIT 1");
    $st->execute([$refId]);
    if($r=$st->fetch()) return $r;
    $prefix=$jenis==='siswa'?'LIB-S-':'LIB-G-';
    $kode=$prefix.$refId;
    $cek=$pdo->prepare('SELECT 1 FROM perpustakaan_anggota WHERE kode_anggota=? LIMIT 1');
    $cek->execute([$kode]);
    if($cek->fetchColumn()) $kode=$prefix.$refId.'-'.bin2hex(random_bytes(2));
    $ins=$pdo->prepare("INSERT INTO perpustakaan_anggota(kode_anggota,{$col},tanggal_daftar,status,keterangan) VALUES(?,?,CURDATE(),'aktif',?)");
    $ins->execute([$kode,$refId,'Anggota otomatis dari data sekolah']);
    return ['id'=>(int)$pdo->lastInsertId(),'kode_anggota'=>$kode,'status'=>'aktif'];
}
function perpus_member(PDO $pdo,int $id):?array{$st=$pdo->prepare("SELECT a.*,s.nama AS siswa_nama,s.nis,s.nisn,g.nama AS guru_nama FROM perpustakaan_anggota a LEFT JOIN siswa s ON s.id=a.siswa_id LEFT JOIN guru g ON g.id=a.guru_id WHERE a.id=? LIMIT 1");$st->execute([$id]);$r=$st->fetch();return $r?:null;}
