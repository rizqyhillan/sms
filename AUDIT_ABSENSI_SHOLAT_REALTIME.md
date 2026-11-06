# Audit Realtime — Absensi Kehadiran dan Absensi Sholat

Baseline: `sms_realtime_integrasi_audit_v1.zip`
Tanggal audit: 2026-10-09

## Ringkasan
Audit ini tidak mengubah kode aplikasi, database, skema, API, maupun aturan bisnis. RealtimeManager tersedia secara global dari `layouts/header.php`, tetapi halaman daftar/detail Absensi Kehadiran dan Sholat yang diperiksa belum mendaftarkan pekerjaan polling ke `window.SPLASMA_REALTIME`.

## Temuan

### Absensi Kehadiran
- `absensi/daftar.php` menangani verifikasi H/A/I/S/T melalui POST, memakai pemeriksaan hak akses, CSRF, transaksi/row lock, serta pencatatan perubahan/audit.
- Halaman memuat ringkasan dan tabel ketika halaman dibuka. Tidak ditemukan pendaftaran polling RealtimeManager atau fetch untuk memperbarui tabel/ringkasan secara berkala.
- Finalisasi otomatis dipanggil saat halaman daftar dibuka melalui `absensi_sync_finalisasi_otomatis(...)`. Karena itu, finalisasi tepat waktu tanpa request aplikasi tetap bergantung pada penjadwal server atau mekanisme lain yang sudah ada; polling UI bukan pengganti worker terjadwal.
- Menambahkan polling yang mengganti DOM seluruh tabel berisiko mengganggu dialog verifikasi, input, filter, dan tindakan pengguna. Jangan memasang auto-refresh generik tanpa endpoint baca-saja dan strategi pembaruan parsial.

### Absensi Sholat
- `sholat/daftar.php` memuat ringkasan dan data ketika halaman dibuka; tidak ditemukan pendaftaran polling RealtimeManager pada halaman daftar/detail yang diperiksa.
- Pencatatan manual dan penghapusan memakai endpoint POST terpisah, dengan transaksi dan audit. Jangan mengubah jalur transaksi demi realtime.
- Absensi Sholat merupakan alur terpisah dari Absensi Kehadiran. Jangan mencampur jadwal pulang, finalisasi kehadiran, atau status kehadiran umum ke dalam modul Sholat.

### RealtimeManager
- `assets/js/realtime.js` dimuat dari `layouts/header.php`.
- API publik adalah `window.SPLASMA_REALTIME` dengan `register`, `start`, `stop`, `unregister`, `pauseAll`, dan `resumeAll`.
- Manager tidak menjalankan polling sendiri; modul harus mendaftarkan job secara eksplisit.
- Penggunaan harus menghindari job bernama sama, request yang tumpang tindih, dan update DOM ketika pengguna sedang mengedit.

## Rekomendasi tahap berikutnya
1. Jangan menambah polling ke halaman edit/manual atau halaman yang sedang mengisi form.
2. Jika realtime diperlukan pada daftar aktif, buat endpoint GET/read-only khusus yang memvalidasi sesi dan hak akses, mengembalikan data minimal, lalu perbarui hanya ringkasan/tabel yang aman.
3. Pastikan endpoint tidak menjalankan finalisasi, menulis data, atau menghasilkan efek samping.
4. Uji dua browser, tab tersembunyi, sesi CLOSED/FINAL, filter, dan verifikasi bersamaan sebelum mengaktifkan polling.
5. Audit terpisah mekanisme penjadwalan Auto Final; jangan mengandalkan interval browser untuk finalisasi server.

## Validasi
- Pemeriksaan sintaks PHP dilakukan pada seluruh file `.php` dalam baseline; hasil tersimpan pada `php_lint_audit_absensi_sholat.txt`.
- Tidak ada pengujian browser atau transaksi terhadap database sekolah pada audit ini.
