-- Batch 2 HOTFIX 1
-- Memperbaiki instalasi yang sudah memasang kode Batch 2 tetapi kolom lifecycle
-- belum ada pada tabel sesi_absensi.
-- Aman dijalankan ulang (idempotent).

SET @db := DATABASE();

-- 1. Kolom lifecycle eksplisit.
SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='status_sesi'
);
SET @sql := IF(@c=0,
  "ALTER TABLE sesi_absensi ADD COLUMN status_sesi ENUM('DRAFT','OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'DRAFT' AFTER status",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Backfill status lifecycle dari status legacy.
UPDATE sesi_absensi
SET status_sesi = CASE status
  WHEN 'aktif' THEN 'OPEN'
  WHEN 'ditutup' THEN 'CLOSED'
  WHEN 'draft' THEN 'DRAFT'
  ELSE 'DRAFT'
END
WHERE status_sesi IS NULL OR status_sesi='DRAFT';

-- 3. Kunci konteks sesi. NULL jenis_sholat_id harus tetap dianggap satu konteks.
SET @c := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='jenis_sesi_key'
);
SET @sql := IF(@c=0,
  "ALTER TABLE sesi_absensi ADD COLUMN jenis_sesi_key BIGINT(20) UNSIGNED GENERATED ALWAYS AS (COALESCE(jenis_sholat_id,0)) STORED AFTER jenis_sholat_id",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Unique context sesi.
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND INDEX_NAME='uk_sesi_absensi_konteks_b1'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE sesi_absensi ADD UNIQUE KEY uk_sesi_absensi_konteks_b1 (tahun_pelajaran_id,semester_id,jenis_absensi_id,jenis_sesi_key,tanggal)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Index lifecycle untuk pencarian scanner/detail.
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND INDEX_NAME='idx_sesi_absensi_status_sesi_b1'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE sesi_absensi ADD KEY idx_sesi_absensi_status_sesi_b1 (status_sesi,tanggal,jam_mulai,jam_selesai)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'BATCH2_HOTFIX1_SESSION_LIFECYCLE',
       'Menambahkan status_sesi, kunci konteks sesi, dan index lifecycle untuk Batch 2.'
WHERE NOT EXISTS (
  SELECT 1 FROM app_migration WHERE migration_key='BATCH2_HOTFIX1_SESSION_LIFECYCLE'
);
