<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

function home_visit_require_access(): void { require_permission('home_visit'); }
function home_visit_can_manage(): bool { return in_array(user_role(), ['admin','kepala_sekolah','guru_bk'], true); }
function home_visit_active_tapel(PDO $pdo): ?array { $s=$pdo->query("SELECT id,nama FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1"); $r=$s->fetch(); return $r?:null; }
function home_visit_status_label(string $s): string { return match($s){'direncanakan'=>'Direncanakan','dilaksanakan'=>'Dilaksanakan','ditunda'=>'Ditunda','dibatalkan'=>'Dibatalkan',default=>ucfirst($s)}; }
function home_visit_status_class(string $s): string { return match($s){'direncanakan'=>'hv-plan','dilaksanakan'=>'hv-done','ditunda'=>'hv-delay','dibatalkan'=>'hv-cancel',default=>'hv-plan'}; }
function home_visit_status_transitions(string $current): array {
    return match($current) {
        'direncanakan' => ['direncanakan','dilaksanakan','ditunda','dibatalkan'],
        'ditunda' => ['ditunda','direncanakan','dilaksanakan','dibatalkan'],
        'dilaksanakan' => ['dilaksanakan'],
        'dibatalkan' => ['dibatalkan'],
        default => [$current],
    };
}
function home_visit_status_can_change(string $current, string $next): bool {
    return in_array($next, home_visit_status_transitions($current), true);
}
function home_visit_audit(PDO $pdo,string $aksi,?int $id,string $ket): void { $s=$pdo->prepare("INSERT INTO audit_log (user_id,aksi,modul,referensi_id,keterangan,ip_address,user_agent,created_at) VALUES (?,?,'home_visit',?,?,?, ?,NOW())"); $s->execute([user_id()?:null,$aksi,$id?:null,$ket,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]); }
