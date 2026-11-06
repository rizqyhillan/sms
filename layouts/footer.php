</div>

<div class="global-app-footer">© 2026 <strong>@irulcoe</strong> · <?= e(APP_NAME) ?> · <?= e(NAMA_SEKOLAH) ?></div>

<?php
$app_js_version = is_file(__DIR__ . '/../assets/js/app.js') ? (string) filemtime(__DIR__ . '/../assets/js/app.js') : '1';
$siswa_picker_version = is_file(__DIR__ . '/../assets/js/siswa-picker.js') ? (string) filemtime(__DIR__ . '/../assets/js/siswa-picker.js') : '1';
?>
<script
    src="<?= BASE_URL ?>assets/js/app.js?v=<?= e($app_js_version) ?>"
></script>
<?php $scan_result_js_version = is_file(__DIR__ . '/../assets/js/scan-result.js') ? (string) filemtime(__DIR__ . '/../assets/js/scan-result.js') : '1'; ?>
<script src="<?= BASE_URL ?>assets/js/scan-result.js?v=<?= e($scan_result_js_version) ?>"></script>
<script
    src="<?= BASE_URL ?>assets/js/siswa-picker.js?v=<?= e($siswa_picker_version) ?>"
></script>

</body>
</html>