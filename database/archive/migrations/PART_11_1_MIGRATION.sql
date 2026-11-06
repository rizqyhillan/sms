-- PART 11.1
-- Penyederhanaan Jadwal Sholat: satu konfigurasi per jenis sholat per tahun pelajaran.
-- Jalankan pada database absensi_sekolah_v2 setelah PART 10/11.
SET FOREIGN_KEY_CHECKS=0;

-- Pertahankan satu baris jadwal pertama untuk setiap tahun + jenis sholat.
DELETE j1 FROM jadwal_sholat j1
JOIN jadwal_sholat j2
  ON j1.tahun_pelajaran_id=j2.tahun_pelajaran_id
 AND j1.jenis_sholat_id=j2.jenis_sholat_id
 AND j1.id>j2.id;

ALTER TABLE jadwal_sholat DROP INDEX uk_jadwal_sholat;
ALTER TABLE jadwal_sholat DROP INDEX idx_jadwal_sholat_hari;
ALTER TABLE jadwal_sholat MODIFY hari TINYINT UNSIGNED NULL COMMENT 'Tidak digunakan lagi; jadwal berlaku pada semua hari efektif sekolah';
ALTER TABLE jadwal_sholat ADD UNIQUE KEY uk_jadwal_sholat_tahun_jenis (tahun_pelajaran_id, jenis_sholat_id);

SET FOREIGN_KEY_CHECKS=1;
