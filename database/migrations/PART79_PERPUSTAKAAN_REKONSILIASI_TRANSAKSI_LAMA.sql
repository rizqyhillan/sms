-- PART79: Rekonsiliasi transaksi lama agar setiap peminjaman aktif/hilang
-- mempunyai representasi eksemplar tanpa mengubah stok tersedia.
-- Aman dijalankan setelah PART76; bersifat idempotent.
DELIMITER $$
DROP PROCEDURE IF EXISTS perpus79_reconcile_legacy$$
CREATE PROCEDURE perpus79_reconcile_legacy()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_detail BIGINT UNSIGNED;
  DECLARE v_buku BIGINT UNSIGNED;
  DECLARE v_jumlah INT UNSIGNED;
  DECLARE v_status VARCHAR(20);
  DECLARE v_kondisi VARCHAR(30);
  DECLARE v_lokasi VARCHAR(100);
  DECLARE i INT;
  DECLARE cur CURSOR FOR
    SELECT d.id,d.buku_id,GREATEST(d.jumlah,1),d.status,COALESCE(d.kondisi_kembali,'baik'),b.lokasi
    FROM perpustakaan_detail_peminjaman d
    JOIN perpustakaan_buku b ON b.id=d.buku_id
    WHERE d.eksemplar_id IS NULL
      AND d.status IN ('dipinjam','hilang');
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_detail,v_buku,v_jumlah,v_status,v_kondisi,v_lokasi;
    IF done=1 THEN LEAVE read_loop; END IF;
    SET i=1;
    WHILE i<=v_jumlah DO
      INSERT IGNORE INTO perpustakaan_eksemplar
        (buku_id,kode_eksemplar,kondisi,status,lokasi,keterangan)
      VALUES
        (v_buku,
         CONCAT('LEGACY-',v_detail,'-',LPAD(i,4,'0')),
         CASE WHEN v_status='hilang' THEN IF(v_kondisi IN ('rusak_ringan','rusak_berat'),'rusak_ringan', 'baik') ELSE 'baik' END,
         v_status,
         v_lokasi,
         CONCAT('Migrasi kompatibilitas transaksi lama #',v_detail));
      SET i=i+1;
    END WHILE;
  END LOOP;
  CLOSE cur;
END$$
CALL perpus79_reconcile_legacy()$$
DROP PROCEDURE perpus79_reconcile_legacy$$
DELIMITER ;

-- Sinkronisasi stok master dari eksemplar yang tersedia.
UPDATE perpustakaan_buku b
SET b.stok=(SELECT COUNT(*) FROM perpustakaan_eksemplar e WHERE e.buku_id=b.id AND e.status='tersedia');
