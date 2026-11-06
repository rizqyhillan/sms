-- SMS / SPLASMA - Kehadiran Masuk/Pulang + Finalisasi Harian
-- Aman untuk baseline yang sudah memiliki tabel absensi dan sesi_absensi.
-- MIGRATION INI BELUM DIJALANKAN OTOMATIS.

SET @db = DATABASE();

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi' AND COLUMN_NAME='waktu_pulang')=0,
  'ALTER TABLE absensi ADD COLUMN waktu_pulang DATETIME NULL AFTER waktu_scan', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi' AND COLUMN_NAME='pulang_oleh')=0,
  'ALTER TABLE absensi ADD COLUMN pulang_oleh BIGINT UNSIGNED NULL AFTER waktu_pulang', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi' AND COLUMN_NAME='pulang_at')=0,
  'ALTER TABLE absensi ADD COLUMN pulang_at DATETIME NULL AFTER pulang_oleh', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='finalisasi_at')=0,
  'ALTER TABLE sesi_absensi ADD COLUMN finalisasi_at DATETIME NULL AFTER ditutup_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='finalisasi_oleh')=0,
  'ALTER TABLE sesi_absensi ADD COLUMN finalisasi_oleh BIGINT UNSIGNED NULL AFTER finalisasi_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='finalisasi_mode')=0,
  "ALTER TABLE sesi_absensi ADD COLUMN finalisasi_mode ENUM('MANUAL','AUTO') NULL AFTER finalisasi_oleh", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi' AND INDEX_NAME='idx_absensi_pulang')=0,
  'ALTER TABLE absensi ADD KEY idx_absensi_pulang (sesi_id,siswa_id,waktu_pulang)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi' AND CONSTRAINT_NAME='fk_absensi_pulang_oleh')=0,
  'ALTER TABLE absensi ADD CONSTRAINT fk_absensi_pulang_oleh FOREIGN KEY (pulang_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND CONSTRAINT_NAME='fk_sesi_finalisasi_oleh')=0,
  'ALTER TABLE sesi_absensi ADD CONSTRAINT fk_sesi_finalisasi_oleh FOREIGN KEY (finalisasi_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO pengaturan (kunci,nilai,keterangan)
SELECT 'batas_finalisasi_kehadiran','17:00','Batas finalisasi otomatis kehadiran harian (WIB).'
WHERE NOT EXISTS (SELECT 1 FROM pengaturan WHERE kunci='batas_finalisasi_kehadiran');

-- Aktifkan fitur absen pulang setelah migration diterapkan.
UPDATE pengaturan SET nilai='1' WHERE kunci='absen_pulang_aktif';
