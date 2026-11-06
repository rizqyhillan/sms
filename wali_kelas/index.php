<?php
declare(strict_types=1);
require_once __DIR__ . '/_helper.php';
wali_kelas_require();
$tp=active_tapel_id($pdo);$sem=active_semester_id($pdo,$tp);
$gid=user_guru_id();
$st=$pdo->prepare("SELECT tg.target_id,mk.nama kelas_nama,mk.tingkat,
    (SELECT COUNT(*) FROM siswa_kelas sk WHERE sk.kelas_id=tg.target_id AND sk.tahun_pelajaran_id=tg.tahun_pelajaran_id AND sk.status='aktif') jumlah
    FROM tugas_guru tg INNER JOIN jenis_tugas jt ON jt.id=tg.jenis_tugas_id
    INNER JOIN master_kelas mk ON mk.id=tg.target_id
    WHERE tg.guru_id=? AND tg.tahun_pelajaran_id=? AND tg.semester_id=? AND jt.kode='WALI_KELAS' AND tg.target_tipe='kelas' AND tg.aktif=1
    ORDER BY mk.tingkat,mk.urutan,mk.nama");
$st->execute([$gid,$tp,$sem]);$kelas=$st->fetchAll();
$page_title='Kelas Saya'; require_once __DIR__.'/../layouts/header.php'; require_once __DIR__.'/../layouts/sidebar.php';
?><main class="main guru-wali-page"><header class="topbar"><div class="topbar-title">Wali Kelas</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content"><div class="page-header"><div><h1>Kelas Saya</h1><p>Kelas yang menjadi tanggung jawab Anda pada Tahun Pelajaran aktif.</p></div></div><div class="dashboard-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))"><?php if(!$kelas):?><div class="form-card"><strong>Belum ada penugasan Wali Kelas aktif.</strong></div><?php else: foreach($kelas as $k):?><div class="form-card"><div style="display:flex;justify-content:space-between;gap:12px"><div><div class="text-muted">Kelas <?=$k['tingkat']?></div><h2 style="margin:4px 0 8px"><?=e($k['kelas_nama'])?></h2><div><?=number_format((int)$k['jumlah'])?> siswa aktif</div></div><i class="bi bi-people-fill" style="font-size:2rem"></i></div><div class="form-actions"><a class="btn btn-primary" href="<?=BASE_URL?>wali_kelas/siswa.php?kelas_id=<?=(int)$k['target_id']?>"><i class="bi bi-people"></i> Siswa</a><a class="btn btn-secondary" href="<?=BASE_URL?>wali_kelas/rekap.php?kelas_id=<?=(int)$k['target_id']?>"><i class="bi bi-bar-chart"></i> Rekap</a><a class="btn btn-secondary" href="<?=BASE_URL?>izin/index.php?menu=wali_izin"><i class="bi bi-box-arrow-right"></i> Izin</a></div></div><?php endforeach;endif;?></div></section></main><?php require_once __DIR__.'/../layouts/footer.php'; ?>
