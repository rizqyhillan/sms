<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task_access.php';

function izin_active_tapel_id(PDO $pdo): ?int { static $loaded=false,$id=null; if($loaded)return $id; $loaded=true; $v=$pdo->query("SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetchColumn(); return $id=($v!==false?(int)$v:null); }
function izin_guru_has_task(PDO $pdo,string $kode): bool { if(in_array(user_role(),['admin','kepala_sekolah'],true))return true; return user_has_task($pdo,$kode); }
function izin_can_access(PDO $pdo): bool { return in_array(user_role(),['admin','kepala_sekolah'],true)||(in_array(user_role(),['guru','guru_bk'],true)&&(user_has_task_capability($pdo,'wali_kelas')||user_has_task_capability($pdo,'piket'))); }
function izin_is_wali(PDO $pdo): bool { return in_array(user_role(),['admin','kepala_sekolah'],true)||user_has_task_capability($pdo,'wali_kelas'); }
function izin_is_piket(PDO $pdo): bool { return in_array(user_role(),['admin','kepala_sekolah'],true)||user_has_task_capability($pdo,'piket'); }
function izin_require_access(PDO $pdo): void { require_login(); if(!izin_can_access($pdo))deny_access(); }
function izin_require_wali(PDO $pdo): void { izin_require_access($pdo); if(!izin_is_wali($pdo))deny_access(); }
function izin_student_scope_sql(PDO $pdo,string $studentAlias='s'):array { if(in_array(user_role(),['admin','kepala_sekolah'],true))return['sql'=>'1=1','params'=>[]];$gid=user_guru_id();$tp=izin_active_tapel_id($pdo);$sem=active_semester_id($pdo,$tp);if(!$gid||!$tp||!$sem)return['sql'=>'1=0','params'=>[]];$s=preg_replace('/[^a-zA-Z0-9_]/','',$studentAlias)?:'s';return['sql'=>"EXISTS(SELECT 1 FROM siswa_kelas sk_scope INNER JOIN tugas_guru tg_scope ON tg_scope.target_id=sk_scope.kelas_id INNER JOIN jenis_tugas jt_scope ON jt_scope.id=tg_scope.jenis_tugas_id WHERE sk_scope.siswa_id={$s}.id AND sk_scope.tahun_pelajaran_id=? AND sk_scope.status='aktif' AND tg_scope.guru_id=? AND tg_scope.semester_id=? AND jt_scope.kode='WALI_KELAS' AND UPPER(COALESCE(tg_scope.target_tipe,''))='KELAS' AND tg_scope.aktif=1)",'params'=>[$tp,$gid,$sem]]; }
function izin_get(int $id,PDO $pdo):?array{$st=$pdo->prepare("SELECT iz.*,s.nis,s.nisn,s.nama AS siswa_nama,s.jk,mk.nama AS kelas_nama,tp.nama AS tapel_nama,ua.nama AS pengaju_nama,ub.nama AS penyetuju_nama FROM izin_siswa iz INNER JOIN siswa s ON s.id=iz.siswa_id INNER JOIN master_kelas mk ON mk.id=iz.kelas_id INNER JOIN tahun_pelajaran tp ON tp.id=iz.tahun_pelajaran_id LEFT JOIN users ua ON ua.id=iz.diajukan_oleh LEFT JOIN users ub ON ub.id=iz.disetujui_oleh WHERE iz.id=? LIMIT 1");$st->execute([$id]);$r=$st->fetch();return$r?:null;}
function izin_audit(PDO $pdo,string $aksi,?int $refId,string $ket):void{$st=$pdo->prepare("INSERT INTO audit_log(user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)");$st->execute([user_id(),$aksi,'izin',$refId,$ket,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);}
function izin_status_label(string $s):string{return['DIAJUKAN'=>'Diajukan','DISETUJUI'=>'Disetujui','DITOLAK'=>'Ditolak','SELESAI'=>'Selesai'][$s]??$s;}
function izin_status_class(string $s):string{return['DIAJUKAN'=>'warning','DISETUJUI'=>'success','DITOLAK'=>'danger','SELESAI'=>'primary'][$s]??'secondary';}
