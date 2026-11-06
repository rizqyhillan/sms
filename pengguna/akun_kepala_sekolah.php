<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pengguna_require_admin();

const KEPALA_SEKOLAH_USERNAME = 'kepsek';
const KEPALA_SEKOLAH_LEGACY_USERNAME = 'kepala.sekolah';
const KEPALA_SEKOLAH_DEFAULT_PASSWORD = 'kepsek123*';

$error = '';
$flash = pengguna_flash();

/** Pastikan akun jabatan Kepala Sekolah selalu tersedia dengan kredensial awal sistem. */
function kepala_sekolah_ensure_account(): array
{
    global $pdo;

    $st = $pdo->prepare("SELECT id,username,nama,role,status,last_login,created_at,updated_at FROM users WHERE username IN (?,?) ORDER BY CASE WHEN username=? THEN 0 ELSE 1 END, id LIMIT 1");
    $st->execute([KEPALA_SEKOLAH_USERNAME, KEPALA_SEKOLAH_LEGACY_USERNAME, KEPALA_SEKOLAH_USERNAME]);
    $akun = $st->fetch() ?: null;

    if ($akun) {
        // Migrasi ringan dari username V71 lama ke username jabatan baru.
        if ((string)$akun['username'] !== KEPALA_SEKOLAH_USERNAME) {
            $up = $pdo->prepare("UPDATE users SET username=?, role='kepala_sekolah', guru_id=NULL, siswa_id=NULL WHERE id=?");
            $up->execute([KEPALA_SEKOLAH_USERNAME, (int)$akun['id']]);
            $akun['username'] = KEPALA_SEKOLAH_USERNAME;
        }
        return $akun;
    }

    $ins = $pdo->prepare("INSERT INTO users(username,password,nama,role,guru_id,siswa_id,status) VALUES(?,?,?,'kepala_sekolah',NULL,NULL,'aktif')");
    $ins->execute([
        KEPALA_SEKOLAH_USERNAME,
        password_hash(KEPALA_SEKOLAH_DEFAULT_PASSWORD, PASSWORD_DEFAULT),
        'Kepala Sekolah'
    ]);

    $id = (int)$pdo->lastInsertId();
    return [
        'id'=>$id,
        'username'=>KEPALA_SEKOLAH_USERNAME,
        'nama'=>'Kepala Sekolah',
        'role'=>'kepala_sekolah',
        'status'=>'aktif',
        'last_login'=>null,
        'created_at'=>null,
        'updated_at'=>null,
    ];
}

/** Pastikan hak monitoring hanya Dashboard + Laporan. */
function kepala_sekolah_sync_permissions(int $userId): void
{
    global $pdo;

    $st = $pdo->query("SELECT id,kode FROM hak_akses WHERE kode IN ('dashboard','laporan') AND aktif=1");
    $perm = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $perm[(string)$row['kode']] = (int)$row['id'];
    }

    foreach ($perm as $hakId) {
        $check = $pdo->prepare('SELECT 1 FROM user_hak_akses WHERE user_id=? AND hak_akses_id=? LIMIT 1');
        $check->execute([$userId, $hakId]);
        if (!$check->fetchColumn()) {
            $add = $pdo->prepare('INSERT INTO user_hak_akses(user_id,hak_akses_id) VALUES(?,?)');
            $add->execute([$userId, $hakId]);
        }
    }

    $keepIds = array_values($perm);
    if ($keepIds) {
        $ph = implode(',', array_fill(0,count($keepIds),'?'));
        $params = array_merge([$userId], $keepIds);
        $del = $pdo->prepare("DELETE FROM user_hak_akses WHERE user_id=? AND hak_akses_id NOT IN ($ph)");
        $del->execute($params);
    } else {
        $del = $pdo->prepare('DELETE FROM user_hak_akses WHERE user_id=?');
        $del->execute([$userId]);
    }
}

// Akun jabatan dibuat otomatis oleh sistem. Admin hanya mengatur identitas dan status.
try {
    $pdo->beginTransaction();
    $akun = kepala_sekolah_ensure_account();
    kepala_sekolah_sync_permissions((int)$akun['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = 'Gagal menyiapkan akun Kepala Sekolah.';
    $akun = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $akun) {
    require_valid_csrf();
    $nama = trim((string)($_POST['nama'] ?? ''));
    $status = (string)($_POST['status'] ?? 'aktif');
    $action = (string)($_POST['action'] ?? 'save');

    if (!in_array($action, ['save','reset_password'], true)) {
        $error = 'Aksi tidak valid.';
    } elseif ($nama === '') {
        $error = 'Nama Kepala Sekolah wajib diisi.';
    } elseif (!in_array($status, ['aktif','nonaktif'], true)) {
        $error = 'Status akun tidak valid.';
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            if ($action === 'reset_password') {
                $up = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
                $up->execute([password_hash(KEPALA_SEKOLAH_DEFAULT_PASSWORD, PASSWORD_DEFAULT), (int)$akun['id']]);
                pengguna_audit('RESET_PASSWORD_KEPALA_SEKOLAH',(int)$akun['id'],'Password akun Kepala Sekolah direset ke password awal sistem. Username: '.KEPALA_SEKOLAH_USERNAME.'.');
                $message = 'Password Kepala Sekolah berhasil direset ke password awal sistem.';
            } else {
                $up = $pdo->prepare("UPDATE users SET nama=?, role='kepala_sekolah', guru_id=NULL, siswa_id=NULL, status=? WHERE id=?");
                $up->execute([$nama,$status,(int)$akun['id']]);
                pengguna_audit('UPDATE_AKUN_KEPALA_SEKOLAH',(int)$akun['id'],'Pembaruan identitas/status akun Kepala Sekolah: '.$nama.'. Username tetap '.KEPALA_SEKOLAH_USERNAME.'.');
                $message = 'Data akun Kepala Sekolah berhasil diperbarui.';
            }
            kepala_sekolah_sync_permissions((int)$akun['id']);
            $pdo->commit();
            pengguna_flash($message);
            redirect('pengguna/akun_kepala_sekolah.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal menyimpan perubahan akun Kepala Sekolah.';
        }
    }
}

$st = $pdo->prepare("SELECT id,username,nama,role,status,last_login,created_at,updated_at FROM users WHERE username=? LIMIT 1");
$st->execute([KEPALA_SEKOLAH_USERNAME]);
$akun = $st->fetch() ?: $akun;
$page_title='Akun Kepala Sekolah';
require_once __DIR__.'/../layouts/header.php';
require_once __DIR__.'/../layouts/sidebar.php';
?>
<main class="main">
<header class="topbar"><div class="topbar-title">Akun Kepala Sekolah</div><div class="topbar-user"><?=e(user_name())?></div></header>
<section class="content">
<div class="page-header"><div><h1><i class="bi bi-person-vcard"></i> Akun Kepala Sekolah</h1><p>Akun jabatan dibuat otomatis oleh sistem. Admin cukup mengatur nama, status aktif/nonaktif, dan reset password.</p></div><div class="page-header-actions"><a class="btn btn-secondary" href="<?=BASE_URL?>pengguna/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div></div>
<?php if($flash):?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?=e($flash)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?=e($error)?></div><?php endif;?>
<div class="dashboard-grid">
<div class="dashboard-card"><div class="card-label">Username Jabatan</div><div class="card-number" style="font-size:1.6rem">kepsek</div><div class="card-description">Tetap meskipun pejabat berganti.</div></div>
<div class="dashboard-card"><div class="card-label">Hak Akses</div><div class="card-number" style="font-size:1.35rem">Read Only</div><div class="card-description">Dashboard + Laporan.</div></div>
<div class="dashboard-card"><div class="card-label">Status</div><div class="card-number" style="font-size:1.35rem"><?=e($akun['status']??'Gagal dibuat')?></div><div class="card-description">Admin cukup mengaktifkan/nonaktifkan akun.</div></div>
</div>
<div class="user-security-note" style="display:flex;gap:12px;align-items:flex-start;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;padding:14px 16px;border-radius:12px;margin-bottom:18px"><i class="bi bi-shield-lock-fill"></i><div><strong>Akun jabatan otomatis</strong><div style="margin-top:3px">Username <code>kepsek</code> dan password awal <code>kepsek123*</code> dibuat otomatis oleh sistem. Password tidak ditampilkan kembali setelah dibuat. Jika lupa, Admin menggunakan tombol <strong>Reset Password</strong>.</div></div></div>
<?php if($akun):?>
<div class="form-card"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><div class="form-grid">
<label>Username Jabatan<input class="form-control" value="kepsek" readonly></label>
<label>Nama Kepala Sekolah<input class="form-control" name="nama" value="<?=e($akun['nama']??'Kepala Sekolah')?>" required></label>
<label>Status<select class="form-control" name="status"><option value="aktif" <?=($akun['status']??'aktif')==='aktif'?'selected':''?>>Aktif</option><option value="nonaktif" <?=($akun['status']??'')==='nonaktif'?'selected':''?>>Nonaktif</option></select></label>
</div><div class="form-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Status / Identitas</button></div></form></div>
<div class="dashboard-table-card" style="margin-top:18px"><div class="section-title"><div><h2>Keamanan Akun</h2><p>Reset mengembalikan password ke password awal sistem.</p></div></div><div style="padding:0 18px 18px"><form method="post" onsubmit="return confirm('Reset password akun Kepala Sekolah ke kepsek123*?');"><?=csrf_field()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="nama" value="<?=e($akun['nama'])?>"><input type="hidden" name="status" value="<?=e($akun['status'])?>"><button class="btn btn-warning" type="submit"><i class="bi bi-key-fill"></i> Reset Password</button></form></div><table class="data-table"><tbody><tr><th>Nama</th><td><?=e($akun['nama'])?></td></tr><tr><th>Username</th><td><code>kepsek</code></td></tr><tr><th>Role</th><td>Kepala Sekolah</td></tr><tr><th>Status</th><td><?=e(ucfirst((string)$akun['status']))?></td></tr><tr><th>Login terakhir</th><td><?= $akun['last_login'] ? e(date('d-m-Y H:i',strtotime((string)$akun['last_login']))) : 'Belum login' ?></td></tr></tbody></table></div>
<?php endif;?>
</section></main>
<?php require_once __DIR__.'/../layouts/footer.php'; ?>
