CREATE TABLE IF NOT EXISTS perpustakaan_kunjungan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id BIGINT UNSIGNED NOT NULL,
  waktu_kunjungan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metode ENUM('kamera','usb','manual') NOT NULL DEFAULT 'manual',
  petugas_id BIGINT UNSIGNED DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_perpus_kunjungan_siswa_waktu (siswa_id,waktu_kunjungan),
  KEY idx_perpus_kunjungan_waktu (waktu_kunjungan),
  KEY idx_perpus_kunjungan_petugas (petugas_id),
  CONSTRAINT fk_perpus_kunjungan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_perpus_kunjungan_petugas FOREIGN KEY (petugas_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
