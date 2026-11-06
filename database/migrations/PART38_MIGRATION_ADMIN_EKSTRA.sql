-- PART38: Administrasi Ekstrakurikuler
-- Jalankan sekali pada database APK SEKOLAH.
CREATE TABLE IF NOT EXISTS ekstra_pembina_absensi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ekstrakurikuler_id BIGINT UNSIGNED NOT NULL,
  tahun_pelajaran_id BIGINT UNSIGNED NOT NULL,
  guru_id BIGINT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  status ENUM('H','I','S','A') NOT NULL DEFAULT 'H',
  waktu TIME DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  dicatat_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ekstra_pembina_tgl (ekstrakurikuler_id,tahun_pelajaran_id,guru_id,tanggal),
  KEY idx_epa_extra_tapel_tanggal (ekstrakurikuler_id,tahun_pelajaran_id,tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS ekstra_jurnal_kendala (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesi_id BIGINT UNSIGNED NOT NULL,
  kendala TEXT NOT NULL,
  tindakan TEXT DEFAULT NULL,
  tindak_lanjut TEXT DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ekstra_kendala_sesi (sesi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS ekstra_dokumentasi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesi_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  uploaded_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ekstra_dokumentasi_sesi (sesi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
