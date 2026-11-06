<?php
declare(strict_types=1);require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../uks/_helper.php';uks_require_access($pdo);
$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01');if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');if($from>$to){$tmp=$from;$from=$to;$to=$tmp;}
$teacherScope=uks_teacher_scope($pdo,'s');
$scopeSummary=uks_teacher_scope($pdo,'s');
$summarySql="SELECT COUNT(*) total, SUM(uk.status='baru') baru, SUM(uk.status='ditangani') ditangani, SUM(uk.status='selesai') selesai FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id WHERE uk.tanggal BETWEEN ? AND ? AND {$scopeSummary['sql']}";
$summary=$pdo->prepare($summarySql);$summary->execute(array_merge([$from,$to],$scopeSummary['params']));$sum=$summary->fetch()?:[];
$classSql="SELECT mk.nama kelas, COUNT(*) jumlah FROM uks_kunjungan uk JOIN siswa s ON s.id=uk.siswa_id LEFT JOIN master_kelas mk ON mk.id=uk.kelas_id WHERE uk.tanggal BETWEEN ? AND ? AND {$scopeSummary['sql']} GROUP BY mk.id,mk.nama ORDER BY jumlah DESC,mk.nama";
$class=$pdo->prepare($classSql);$class->execute(array_merge([$from,$to],$scopeSummary['params']));$classRows=$class->fetchAll();
$drugSql="SELECT o.nama,o.satuan,SUM(ko.jumlah) jumlah FROM uks_kunjungan_obat ko JOIN uks_kunjungan uk ON uk.id=ko.kunjungan_id JOIN siswa s ON s.id=uk.siswa_id JOIN uks_obat o ON o.id=ko.obat_id WHERE uk.tanggal BETWEEN ? AND ? AND {$scopeSummary['sql']} GROUP BY o.id,o.nama,o.satuan ORDER BY jumlah DESC,o.nama";
$drugs=$pdo->prepare($drugSql);$drugs->execute(array_merge([$from,$to],$scopeSummary['params']));$drugRows=$drugs->fetchAll();$refSql="SELECT r.tujuan_jenis,COUNT(*) jumlah FROM uks_rujukan r JOIN siswa s ON s.id=r.siswa_id WHERE r.tanggal BETWEEN ? AND ? AND {$scopeSummary['sql']} GROUP BY r.tujuan_jenis ORDER BY jumlah DESC";$refs=$pdo->prepare($refSql);$refs->execute(array_merge([$from,$to],$scopeSummary['params']));$refRows=$refs->fetchAll();
$page_title='Laporan UKS';require_once __DIR__.'/../layouts/header.php';require_once __DIR__.'/../layouts/sidebar.php';?><main class="main uks-module-page"><header class="topbar"><div class="topbar-title">Laporan UKS</div><div class="topbar-user"><?=e(user_name())?></div></header><section class="content"><div class="page-header"><div><h1><i class="bi bi-bar-chart-line-fill"></i> Laporan UKS</h1><p>Rekap kunjungan dan penggunaan obat berdasarkan periode.</p></div><div class="page-header-actions"><button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak Laporan</button><a class="btn btn-secondary" href="<?=BASE_URL?>uks/index.php"><i class="bi bi-arrow-left"></i> Kembali</a></div></div><div class="form-card"><form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><label><span class="form-label">Dari</span><input class="form-control" type="date" name="from" value="<?=e($from)?>"></label><label><span class="form-label">Sampai</span><input class="form-control" type="date" name="to" value="<?=e($to)?>"></label><button class="btn btn-primary"><i class="bi bi-filter"></i> Tampilkan</button></form></div><div class="form-card"><h3>Ringkasan Kunjungan</h3><div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px"><div class="stat"><strong><?=number_format((int)($sum['total']??0))?></strong><span>Total</span></div><div class="stat"><strong><?=number_format((int)($sum['baru']??0))?></strong><span>Baru</span></div><div class="stat"><strong><?=number_format((int)($sum['ditangani']??0))?></strong><span>Ditangani</span></div><div class="stat"><strong><?=number_format((int)($sum['selesai']??0))?></strong><span>Selesai</span></div></div></div><div class="uks-report-grid"><div class="form-card"><h3>Kunjungan per Kelas</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Kelas</th><th>Jumlah</th></tr></thead><tbody><?php if(!$classRows):?><tr><td colspan="2" class="empty-state">Belum ada data.</td></tr><?php else:foreach($classRows as $r):?><tr><td><?=e($r['kelas']??'Tanpa kelas')?></td><td><strong><?=number_format((int)$r['jumlah'])?></strong></td></tr><?php endforeach;endif;?></tbody></table></div></div><div class="form-card"><h3>Penggunaan Obat</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Obat</th><th>Jumlah</th><th>Satuan</th></tr></thead><tbody><?php if(!$drugRows):?><tr><td colspan="3" class="empty-state">Belum ada penggunaan obat.</td></tr><?php else:foreach($drugRows as $r):?><tr><td><?=e($r['nama'])?></td><td><strong><?=number_format((int)$r['jumlah'])?></strong></td><td><?=e($r['satuan'])?></td></tr><?php endforeach;endif;?></tbody></table></div></div><div class="form-card"><h3>Rujukan Siswa</h3><div class="table-responsive"><table class="data-table"><thead><tr><th>Tujuan</th><th>Jumlah</th></tr></thead><tbody><?php if(!$refRows):?><tr><td colspan="2" class="empty-state">Belum ada rujukan.</td></tr><?php else:foreach($refRows as $r):?><tr><td><?=e(['puskesmas'=>'Puskesmas','rumah_sakit'=>'Rumah Sakit','klinik'=>'Klinik','lainnya'=>'Lainnya'][$r['tujuan_jenis']]??$r['tujuan_jenis'])?></td><td><strong><?=number_format((int)$r['jumlah'])?></strong></td></tr><?php endforeach;endif;?></tbody></table></div></div></div></section></main>
<style>
@media print{
  @page{size:A4 landscape;margin:12mm;}
  body{background:#fff!important;}
  .sidebar,.topbar,.page-header-actions,.btn,form{display:none!important;}
  .main{margin:0!important;padding:0!important;width:100%!important;max-width:none!important;}
  .content{padding:0!important;}
  .form-card{box-shadow:none!important;border:1px solid #cbd5e1!important;break-inside:avoid;}
  .uks-report-grid{grid-template-columns:1fr 1fr!important;}
  .table-responsive{overflow:visible!important;}
  .data-table{font-size:11px!important;}
  h1{font-size:20px!important;}
  .page-header{margin-bottom:12px!important;}
}

/* UKS report: keep the page inside the viewport; tables scroll inside their own cards. */
.uks-report-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;min-width:0;}
.uks-report-grid>.form-card{min-width:0;max-width:100%;overflow:hidden;}
.uks-report-grid .table-responsive{min-width:0;max-width:100%;overflow-x:auto;}
.uks-report-grid .data-table{width:100%;min-width:0;}
@media (max-width:900px){
    .uks-report-grid{grid-template-columns:1fr;}
}
@media (max-width:700px){
    .uks-report-grid{gap:14px;}
}
</style>
<?php require_once __DIR__.'/../layouts/footer.php';
