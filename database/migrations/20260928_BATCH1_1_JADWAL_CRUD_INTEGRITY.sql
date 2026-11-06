-- BATCH 1.1 — CRUD & INTEGRITY JADWAL KEHADIRAN/SHOLAT
-- Idempotent, aman dijalankan setelah Batch 1.
-- Prinsip: konfigurasi jadwal per semester; histori sesi/transaksi tidak dihapus.

SET @db := DATABASE();

-- -----------------------------------------------------
-- 1. Pastikan struktur semester pada kedua jadwal
-- -----------------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND COLUMN_NAME='semester_id');
SET @sql := IF(@c=0,'ALTER TABLE jadwal_kehadiran ADD COLUMN semester_id BIGINT(20) UNSIGNED NULL AFTER tahun_pelajaran_id','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND COLUMN_NAME='semester_id');
SET @sql := IF(@c=0,'ALTER TABLE jadwal_sholat ADD COLUMN semester_id BIGINT(20) UNSIGNED NULL AFTER tahun_pelajaran_id','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE jadwal_kehadiran j
JOIN tahun_pelajaran tp ON tp.id=j.tahun_pelajaran_id
LEFT JOIN semester sa ON sa.tahun_pelajaran_id=tp.id AND sa.aktif=1
LEFT JOIN semester sl ON sl.tahun_pelajaran_id=tp.id AND sl.id=(SELECT MAX(s2.id) FROM semester s2 WHERE s2.tahun_pelajaran_id=tp.id)
SET j.semester_id=COALESCE(j.semester_id,sa.id,sl.id)
WHERE j.semester_id IS NULL;

UPDATE jadwal_sholat j
JOIN tahun_pelajaran tp ON tp.id=j.tahun_pelajaran_id
LEFT JOIN semester sa ON sa.tahun_pelajaran_id=tp.id AND sa.aktif=1
LEFT JOIN semester sl ON sl.tahun_pelajaran_id=tp.id AND sl.id=(SELECT MAX(s2.id) FROM semester s2 WHERE s2.tahun_pelajaran_id=tp.id)
SET j.semester_id=COALESCE(j.semester_id,sa.id,sl.id)
WHERE j.semester_id IS NULL;

-- -----------------------------------------------------
-- 2. Arsip konfigurasi duplikat Jadwal Sholat sebelum
--    unique semester+jenis diberlakukan.
--    Tidak menyentuh sesi/transaksi.
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS jadwal_sholat_arsip_batch11 (
  arsip_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  jadwal_id BIGINT(20) UNSIGNED NOT NULL,
  tahun_pelajaran_id BIGINT(20) UNSIGNED NOT NULL,
  semester_id BIGINT(20) UNSIGNED NOT NULL,
  jenis_sholat_id BIGINT(20) UNSIGNED NOT NULL,
  hari TINYINT(3) UNSIGNED NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  nama_sesi VARCHAR(150) NOT NULL,
  aktif TINYINT(1) NOT NULL,
  alasan VARCHAR(255) NOT NULL,
  diarsipkan_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (arsip_id),
  KEY idx_arsip_jadwal (jadwal_id),
  KEY idx_arsip_konteks (semester_id,jenis_sholat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Untuk tiap semester+jenis, pertahankan konfigurasi yang paling baru diubah.
-- Jika updated_at sama, id terbesar menjadi pemenang.
INSERT INTO jadwal_sholat_arsip_batch11
  (jadwal_id,tahun_pelajaran_id,semester_id,jenis_sholat_id,hari,jam_mulai,jam_selesai,nama_sesi,aktif,alasan)
SELECT j.id,j.tahun_pelajaran_id,j.semester_id,j.jenis_sholat_id,j.hari,j.jam_mulai,j.jam_selesai,j.nama_sesi,j.aktif,
       'Duplikat jadwal sholat pada semester+jenis; konfigurasi terbaru dipertahankan.'
FROM jadwal_sholat j
JOIN jadwal_sholat k
  ON k.semester_id=j.semester_id
 AND k.jenis_sholat_id=j.jenis_sholat_id
 AND (k.updated_at>j.updated_at OR (k.updated_at=j.updated_at AND k.id>j.id));

-- Hapus hanya duplikat yang lebih lama. Sesi/transaksi tidak dihapus karena tidak FK ke jadwal_sholat.
DELETE j FROM jadwal_sholat j
JOIN jadwal_sholat k
  ON k.semester_id=j.semester_id
 AND k.jenis_sholat_id=j.jenis_sholat_id
 AND (k.updated_at>j.updated_at OR (k.updated_at=j.updated_at AND k.id>j.id));

-- -----------------------------------------------------
-- 3. Constraint jadwal
-- -----------------------------------------------------
-- PENTING: index UNIQUE lama juga dipakai InnoDB sebagai
-- supporting index untuk FK tahun_pelajaran_id.
-- Karena itu index pendukung non-unique harus dibuat lebih dulu
-- sebelum UNIQUE lama dihapus.
SET @supportidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND INDEX_NAME='idx_jadwal_kehadiran_tahun_b11');
SET @sql := IF(@supportidx=0,'ALTER TABLE jadwal_kehadiran ADD KEY idx_jadwal_kehadiran_tahun_b11 (tahun_pelajaran_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @oldidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND INDEX_NAME='uk_jadwal_kehadiran_tahun_hari');
SET @sql := IF(@oldidx>0,'ALTER TABLE jadwal_kehadiran DROP INDEX uk_jadwal_kehadiran_tahun_hari','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @newidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND INDEX_NAME='uk_jadwal_kehadiran_semester_hari_b1');
SET @sql := IF(@newidx=0,'ALTER TABLE jadwal_kehadiran ADD UNIQUE KEY uk_jadwal_kehadiran_semester_hari_b1 (semester_id,hari)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @supportidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND INDEX_NAME='idx_jadwal_sholat_tahun_b11');
SET @sql := IF(@supportidx=0,'ALTER TABLE jadwal_sholat ADD KEY idx_jadwal_sholat_tahun_b11 (tahun_pelajaran_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @oldidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND INDEX_NAME='uk_jadwal_sholat_tahun_jenis');
SET @sql := IF(@oldidx>0,'ALTER TABLE jadwal_sholat DROP INDEX uk_jadwal_sholat_tahun_jenis','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @newidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND INDEX_NAME='uk_jadwal_sholat_semester_jenis_b1');
SET @sql := IF(@newidx=0,'ALTER TABLE jadwal_sholat ADD UNIQUE KEY uk_jadwal_sholat_semester_jenis_b1 (semester_id,jenis_sholat_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- -----------------------------------------------------
-- 4. Audit migration
-- -----------------------------------------------------
INSERT INTO app_migration (migration_key,keterangan)
SELECT 'BATCH1_1_JADWAL_CRUD_INTEGRITY','CRUD aman jadwal per semester, normalisasi duplikat jadwal sholat, dan unique constraint per semester'
WHERE NOT EXISTS (SELECT 1 FROM app_migration WHERE migration_key='BATCH1_1_JADWAL_CRUD_INTEGRITY');
