MIGRASI DATABASE APK SEKOLAH

Database final saat ini menggunakan schema yang sudah tercakup di absensi_sekolah_v2.sql dan MIGRATION_FINAL_V15_SISWA_ABSENSI_SHOLAT.sql.

Tidak ada migration aktif tambahan untuk modul verifikasi kehadiran/sholat pada baseline ini.

Migration lama yang menggunakan kolom legacy seperti metode/verifikasi_status pada tabel absensi dipindahkan ke database/archive/migrations/ dan JANGAN dijalankan pada database final.
