-- PART 16 - UKS V8: Rujukan Puskesmas / Rumah Sakit
-- Jalankan sekali setelah baseline UKS V7.

CREATE TABLE IF NOT EXISTS uks_rujukan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kunjungan_id BIGINT UNSIGNED NOT NULL,
  siswa_id BIGINT UNSIGNED NOT NULL,
  tujuan_jenis ENUM('puskesmas','rumah_sakit','klinik','lainnya') NOT NULL DEFAULT 'puskesmas',
  fasilitas_nama VARCHAR(200) NOT NULL,
  tanggal DATE NOT NULL,
  waktu TIME DEFAULT NULL,
  pendamping_guru_id BIGINT UNSIGNED DEFAULT NULL,
  pendamping_nama VARCHAR(150) DEFAULT NULL,
  alasan TEXT NOT NULL,
  cara_berangkat ENUM('diantar_sekolah','diantar_orangtua','datang_sendiri_dengan_persetujuan','lainnya') NOT NULL DEFAULT 'diantar_orangtua',
  status ENUM('direncanakan','dirujuk','selesai','tidak_jadi') NOT NULL DEFAULT 'direncanakan',
  hasil_tindak_lanjut TEXT DEFAULT NULL,
  catatan TEXT DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_uks_rujukan_kunjungan (kunjungan_id),
  KEY idx_uks_rujukan_siswa (siswa_id),
  KEY idx_uks_rujukan_tanggal (tanggal),
  KEY idx_uks_rujukan_tujuan (tujuan_jenis),
  KEY idx_uks_rujukan_status (status),
  CONSTRAINT fk_uks_rujukan_kunjungan FOREIGN KEY (kunjungan_id) REFERENCES uks_kunjungan(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_uks_rujukan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_uks_rujukan_guru FOREIGN KEY (pendamping_guru_id) REFERENCES guru(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_uks_rujukan_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
