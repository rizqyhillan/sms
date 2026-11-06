<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
pengguna_require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') deny_access();
require_valid_csrf();

$mode = (string)($_POST['mode'] ?? 'single');
$siswaId = max(0, (int)($_POST['siswa_id'] ?? 0));
$kelasId = max(0, (int)($_POST['kelas_id'] ?? 0));

try {
    $tp = (int)$pdo->query("SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($tp <= 0) {
        pengguna_flash('Tahun Pelajaran aktif belum tersedia.');
        redirect('pengguna/akun_siswa.php');
    }

    if ($mode === 'single') {
        $st = $pdo->prepare("SELECT s.id,s.nama,s.nis FROM siswa s WHERE s.id=? AND s.status='aktif' LIMIT 1");
        $st->execute([$siswaId]);
        $siswa = $st->fetch();
        if (!$siswa) {
            pengguna_flash('Data siswa tidak ditemukan.');
            redirect('pengguna/akun_siswa.php');
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE siswa_id=? OR username=? LIMIT 1");
        $st->execute([(int)$siswa['id'], (string)$siswa['nis']]);
        if ($st->fetch()) {
            pengguna_flash('Akun siswa sudah ada atau NIS sudah digunakan.');
            redirect('pengguna/akun_siswa.php');
        }
        $password = (string)$siswa['nis'] . '*';
        $st = $pdo->prepare("INSERT INTO users(username,password,nama,role,guru_id,siswa_id,status) VALUES(?,?,?,?,?,?, 'aktif')");
        $st->execute([(string)$siswa['nis'], password_hash($password, PASSWORD_DEFAULT), (string)$siswa['nama'], 'siswa', null, (int)$siswa['id']]);
        $id = (int)$pdo->lastInsertId();
        pengguna_audit('CREATE_USER_SISWA', $id, 'Membuat akun siswa '.$siswa['nis'].' dengan password awal NIS + *');
        pengguna_flash('Akun '.$siswa['nama'].' berhasil dibuat. Username: '.$siswa['nis'].' · Password awal: '.$password);
        redirect('pengguna/akun_siswa.php');
    }

    if ($mode === 'bulk_class' && $kelasId > 0) {
        $st = $pdo->prepare("SELECT s.id,s.nama,s.nis FROM siswa s JOIN siswa_kelas sk ON sk.id=(SELECT sk2.id FROM siswa_kelas sk2 WHERE sk2.siswa_id=s.id AND sk2.tahun_pelajaran_id=? AND sk2.kelas_id=? AND sk2.status='aktif' ORDER BY sk2.id DESC LIMIT 1) WHERE s.status='aktif' ORDER BY s.nama");
        $st->execute([$tp, $kelasId]);
        $students = $st->fetchAll();
        $created = 0; $skipped = 0;
        $pdo->beginTransaction();
        foreach ($students as $siswa) {
            $check = $pdo->prepare("SELECT id FROM users WHERE siswa_id=? OR username=? LIMIT 1");
            $check->execute([(int)$siswa['id'], (string)$siswa['nis']]);
            if ($check->fetch()) { $skipped++; continue; }
            $password = (string)$siswa['nis'] . '*';
            $ins = $pdo->prepare("INSERT INTO users(username,password,nama,role,guru_id,siswa_id,status) VALUES(?,?,?,?,?,?, 'aktif')");
            $ins->execute([(string)$siswa['nis'], password_hash($password, PASSWORD_DEFAULT), (string)$siswa['nama'], 'siswa', null, (int)$siswa['id']]);
            $id = (int)$pdo->lastInsertId();
            pengguna_audit('CREATE_USER_SISWA', $id, 'Membuat akun siswa massal kelas ID '.$kelasId.' · NIS '.$siswa['nis'].' · password awal NIS + *');
            $created++;
        }
        $pdo->commit();
        pengguna_flash('Akun massal selesai. Berhasil dibuat: '.$created.' · Sudah ada/dilewati: '.$skipped.'. Password awal setiap akun: NIS + *.');
        redirect('pengguna/akun_siswa.php?kelas_id='.$kelasId.'&status=semua');
    }

    pengguna_flash('Permintaan pembuatan akun tidak valid.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    pengguna_flash('Pembuatan akun gagal. Tidak ada perubahan yang diterapkan pada proses massal.');
}
redirect('pengguna/akun_siswa.php');
