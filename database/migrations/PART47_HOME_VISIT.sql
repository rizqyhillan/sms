-- PART47 - Home Visit
-- Menambahkan histori Tahun Pelajaran pada tabel home_visit yang sudah ada.
-- Aman dijalankan satu kali pada database yang sudah memiliki tabel home_visit.

ALTER TABLE home_visit
  ADD COLUMN IF NOT EXISTS tahun_pelajaran_id BIGINT(20) UNSIGNED NULL AFTER siswa_id;

ALTER TABLE home_visit
  ADD INDEX idx_home_visit47_tapel (tahun_pelajaran_id);

-- Isi histori lama dengan Tahun Pelajaran aktif agar tetap terbaca.
UPDATE home_visit
SET tahun_pelajaran_id = (SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY tanggal_mulai DESC,id DESC LIMIT 1)
WHERE tahun_pelajaran_id IS NULL;

-- FK diberi nama unik agar tidak bentrok dengan constraint lama.
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND CONSTRAINT_NAME = 'fk_home_visit47_tapel'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE home_visit ADD CONSTRAINT fk_home_visit47_tapel FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
