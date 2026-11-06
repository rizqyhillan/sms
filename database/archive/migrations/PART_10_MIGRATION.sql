-- PART 10: Jadwal Sholat + otomatisasi sesi sholat
-- Jalankan pada database absensi_sekolah_v2
CREATE TABLE IF NOT EXISTS jadwal_sholat (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun_pelajaran_id BIGINT UNSIGNED NOT NULL,
  jenis_sholat_id BIGINT UNSIGNED NOT NULL,
  hari TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  nama_sesi VARCHAR(150) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_jadwal_sholat (tahun_pelajaran_id, jenis_sholat_id, hari),
  KEY idx_jadwal_sholat_tahun (tahun_pelajaran_id),
  KEY idx_jadwal_sholat_jenis (jenis_sholat_id),
  KEY idx_jadwal_sholat_hari (hari),
  KEY idx_jadwal_sholat_aktif (aktif),
  CONSTRAINT fk_jadwal_sholat_tahun FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_jadwal_sholat_jenis FOREIGN KEY (jenis_sholat_id) REFERENCES jenis_sholat(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
