-- PART66 - Penyelarasan UKS: layanan berbasis antrian + absensi petugas guru/siswa
-- Menyatukan alur operasional UKS tanpa menggabungkan transaksi dengan absensi kehadiran/sholat.
-- Idempotensi: migration_key mencegah penerapan ulang.

SET @db := DATABASE();

-- 1. Kunjungan UKS memakai lifecycle seperti Layanan BK.
ALTER TABLE uks_kunjungan
  ADD COLUMN IF NOT EXISTS nomor_antrian INT UNSIGNED NULL AFTER waktu,
  ADD COLUMN IF NOT EXISTS waktu_mulai TIME NULL AFTER nomor_antrian,
  ADD COLUMN IF NOT EXISTS waktu_selesai TIME NULL AFTER waktu_mulai,
  ADD COLUMN IF NOT EXISTS jenis_layanan VARCHAR(50) NULL AFTER status;

-- Status batal diperlukan agar antrian dapat dibatalkan tanpa menghapus transaksi.
ALTER TABLE uks_kunjungan
  MODIFY COLUMN status ENUM('baru','ditangani','selesai','batal') NOT NULL DEFAULT 'baru';

UPDATE uks_kunjungan
SET nomor_antrian = id
WHERE nomor_antrian IS NULL;

-- Nomor antrian unik per tanggal.
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='uks_kunjungan'
    AND INDEX_NAME='uk_uks_kunjungan_antrian_harian'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE uks_kunjungan ADD UNIQUE KEY uk_uks_kunjungan_antrian_harian (tanggal,nomor_antrian)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE uks_kunjungan
  ADD KEY IF NOT EXISTS idx_uks_kunjungan_status_tanggal (tanggal,status,nomor_antrian);

-- 2. Absensi petugas UKS dapat menyimpan guru maupun siswa, tetap terpisah dari absensi siswa.
ALTER TABLE uks_petugas_absensi
  MODIFY COLUMN petugas_jadwal_id BIGINT(20) UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS petugas_tipe ENUM('siswa','guru') NOT NULL DEFAULT 'siswa' AFTER petugas_jadwal_id,
  ADD COLUMN IF NOT EXISTS guru_id BIGINT(20) UNSIGNED NULL AFTER petugas_tipe,
  ADD COLUMN IF NOT EXISTS siswa_id BIGINT(20) UNSIGNED NULL AFTER guru_id,
  ADD COLUMN IF NOT EXISTS petugas_kunci VARCHAR(60)
    GENERATED ALWAYS AS (
      CONCAT(
        petugas_tipe, ':',
        IF(petugas_tipe='guru', COALESCE(guru_id,0), COALESCE(siswa_id,0))
      )
    ) STORED;

-- Migrasikan record lama siswa dari jadwal jaga ke referensi siswa.
UPDATE uks_petugas_absensi a
JOIN uks_petugas_jadwal j ON j.id=a.petugas_jadwal_id
JOIN siswa_kelas sk ON sk.id=j.siswa_kelas_id
SET a.petugas_tipe='siswa',
    a.siswa_id=sk.siswa_id
WHERE a.siswa_id IS NULL;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='uks_petugas_absensi'
    AND INDEX_NAME='uk_uks_absensi_petugas_tanggal'
);
SET @sql := IF(@idx=0,
  'ALTER TABLE uks_petugas_absensi ADD UNIQUE KEY uk_uks_absensi_petugas_tanggal (petugas_kunci,tanggal)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE uks_petugas_absensi
  ADD KEY IF NOT EXISTS idx_uks_absensi_guru_tanggal (guru_id,tanggal),
  ADD KEY IF NOT EXISTS idx_uks_absensi_siswa_tanggal (siswa_id,tanggal);

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='uks_petugas_absensi'
    AND CONSTRAINT_NAME='fk_uks_absensi_guru'
);
SET @sql := IF(@fk=0,
  'ALTER TABLE uks_petugas_absensi ADD CONSTRAINT fk_uks_absensi_guru FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='uks_petugas_absensi'
    AND CONSTRAINT_NAME='fk_uks_absensi_siswa'
);
SET @sql := IF(@fk=0,
  'ALTER TABLE uks_petugas_absensi ADD CONSTRAINT fk_uks_absensi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'PART66_UKS_LAYANAN_ABSENSI_PETUGAS',
       'Menyelaraskan layanan UKS dengan alur antrian BK dan memperluas absensi petugas UKS untuk guru dan siswa.'
WHERE NOT EXISTS (
  SELECT 1 FROM app_migration WHERE migration_key='PART66_UKS_LAYANAN_ABSENSI_PETUGAS'
);
