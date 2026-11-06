-- PART59 - Fondasi Petugas UKS Siswa
-- Menambahkan jadwal jaga dan absensi petugas UKS dari siswa.
-- Tidak mengubah tugas_guru/petugas UKS guru, absensi siswa, atau absensi sholat.
-- Aman dijalankan ulang (idempotent).

SET @db := DATABASE();

-- 1) Jadwal petugas siswa.
CREATE TABLE IF NOT EXISTS uks_petugas_jadwal (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_kelas_id BIGINT(20) UNSIGNED NOT NULL,
  semester_id BIGINT(20) UNSIGNED NOT NULL,
  hari TINYINT(3) UNSIGNED NOT NULL,
  jam_mulai TIME DEFAULT NULL,
  jam_selesai TIME DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_p59_uks_petugas_hari (siswa_kelas_id,semester_id,hari),
  KEY idx_p59_uks_petugas_siswa_kelas (siswa_kelas_id),
  KEY idx_p59_uks_petugas_semester (semester_id,aktif,hari),
  CONSTRAINT fk_p59_uks_petugas_siswa_kelas FOREIGN KEY (siswa_kelas_id)
    REFERENCES siswa_kelas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_p59_uks_petugas_semester FOREIGN KEY (semester_id)
    REFERENCES semester(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Absensi petugas siswa berdiri sendiri dari absensi kehadiran/sholat.
CREATE TABLE IF NOT EXISTS uks_petugas_absensi (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  petugas_jadwal_id BIGINT(20) UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  waktu_masuk TIME DEFAULT NULL,
  waktu_keluar TIME DEFAULT NULL,
  status ENUM('hadir','terlambat','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
  metode ENUM('qr','manual') NOT NULL DEFAULT 'qr',
  catatan VARCHAR(255) DEFAULT NULL,
  verified_by BIGINT(20) UNSIGNED DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_p59_uks_absensi_jadwal_tanggal (petugas_jadwal_id,tanggal),
  KEY idx_p59_uks_absensi_tanggal (tanggal,status),
  KEY idx_p59_uks_absensi_verifikator (verified_by),
  CONSTRAINT fk_p59_uks_absensi_jadwal FOREIGN KEY (petugas_jadwal_id)
    REFERENCES uks_petugas_jadwal(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_p59_uks_absensi_verifikator FOREIGN KEY (verified_by)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Index tambahan hanya bila belum ada.
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='uks_petugas_jadwal'
    AND INDEX_NAME='idx_p59_uks_petugas_hari_aktif'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE uks_petugas_jadwal ADD KEY idx_p59_uks_petugas_hari_aktif (semester_id,hari,aktif,jam_mulai,jam_selesai)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'PART59_UKS_PETUGAS_SISWA',
       'Fondasi jadwal dan absensi petugas UKS siswa yang berdiri sendiri dari absensi kehadiran dan sholat.'
WHERE NOT EXISTS (
  SELECT 1 FROM app_migration WHERE migration_key='PART59_UKS_PETUGAS_SISWA'
);
