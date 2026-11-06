-- PART76: 1 buku fisik = 1 eksemplar = 1 QR unik
CREATE TABLE IF NOT EXISTS perpustakaan_eksemplar (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buku_id BIGINT UNSIGNED NOT NULL,
  kode_eksemplar VARCHAR(80) NOT NULL,
  kondisi ENUM('baik','rusak_ringan','rusak_berat') NOT NULL DEFAULT 'baik',
  status ENUM('tersedia','dipinjam','rusak','hilang','nonaktif') NOT NULL DEFAULT 'tersedia',
  lokasi VARCHAR(100) DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_perpus_eksemplar_kode (kode_eksemplar),
  KEY idx_perpus_eksemplar_buku (buku_id),
  KEY idx_perpus_eksemplar_status (status),
  CONSTRAINT fk_perpus_eksemplar_buku FOREIGN KEY (buku_id) REFERENCES perpustakaan_buku(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perpustakaan_detail_peminjaman' AND COLUMN_NAME='eksemplar_id');
SET @sql := IF(@has_col=0, 'ALTER TABLE perpustakaan_detail_peminjaman ADD COLUMN eksemplar_id BIGINT UNSIGNED NULL AFTER buku_id, ADD KEY idx_perpus_detail_eksemplar (eksemplar_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_perpus_detail_eksemplar');
SET @sql := IF(@has_fk=0, 'ALTER TABLE perpustakaan_detail_peminjaman ADD CONSTRAINT fk_perpus_detail_eksemplar FOREIGN KEY (eksemplar_id) REFERENCES perpustakaan_eksemplar(id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrasi stok lama menjadi eksemplar yang tersedia.
-- Riwayat peminjaman lama tetap menggunakan buku_id dan tidak dipaksakan memiliki eksemplar.
DELIMITER $$
DROP PROCEDURE IF EXISTS perpus76_seed_eksemplar$$
CREATE PROCEDURE perpus76_seed_eksemplar()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_kode VARCHAR(50);
  DECLARE v_stok INT UNSIGNED;
  DECLARE i INT;
  DECLARE cur CURSOR FOR SELECT id,kode_buku,stok FROM perpustakaan_buku WHERE stok > 0;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_id,v_kode,v_stok;
    IF done=1 THEN LEAVE read_loop; END IF;
    SET i=1;
    WHILE i<=v_stok DO
      INSERT IGNORE INTO perpustakaan_eksemplar(buku_id,kode_eksemplar,kondisi,status,lokasi)
      SELECT v_id,CONCAT(v_kode,'-',LPAD(i,4,'0')),b.kondisi,'tersedia',b.lokasi
      FROM perpustakaan_buku b WHERE b.id=v_id;
      SET i=i+1;
    END WHILE;
  END LOOP;
  CLOSE cur;
END$$
CALL perpus76_seed_eksemplar()$$
DROP PROCEDURE perpus76_seed_eksemplar$$
DELIMITER ;
