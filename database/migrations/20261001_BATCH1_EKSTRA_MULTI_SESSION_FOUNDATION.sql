-- BATCH 1 EKSTRA — FONDASI MULTI-SESI PER JADWAL
-- MariaDB 10.11, idempotent. Tidak menghapus transaksi absensi/jurnal/foto.
-- Tujuan: setiap ekstra_sesi memiliki sumber jadwal yang jelas.

SET @db := DATABASE();

-- 1. Hubungkan sesi langsung ke jadwal.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_sesi' AND COLUMN_NAME='jadwal_id');
SET @sql := IF(@c=0,'ALTER TABLE ekstra_sesi ADD COLUMN jadwal_id BIGINT UNSIGNED NULL AFTER ekstrakurikuler_id','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_sesi' AND INDEX_NAME='idx_ekstra_sesi_jadwal_b1');
SET @sql := IF(@idx=0,'ALTER TABLE ekstra_sesi ADD KEY idx_ekstra_sesi_jadwal_b1 (jadwal_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Backfill sesi lama secara deterministik berdasarkan ekstra + tapel + hari + jam.
-- Jika data lama memiliki dua jadwal identik, pilih jadwal aktif dengan ID terkecil.
UPDATE ekstra_sesi es
JOIN (
  SELECT es2.id AS sesi_id, MIN(ej.id) AS jadwal_id
  FROM ekstra_sesi es2
  JOIN ekstra_jadwal ej
    ON ej.ekstrakurikuler_id=es2.ekstrakurikuler_id
   AND ej.tahun_pelajaran_id=es2.tahun_pelajaran_id
   AND ej.hari=CASE DAYOFWEEK(es2.tanggal)
      WHEN 1 THEN 'Minggu' WHEN 2 THEN 'Senin' WHEN 3 THEN 'Selasa'
      WHEN 4 THEN 'Rabu' WHEN 5 THEN 'Kamis' WHEN 6 THEN 'Jumat' WHEN 7 THEN 'Sabtu' END
   AND TIME_FORMAT(ej.jam_mulai,'%H:%i:%s')=TIME_FORMAT(es2.jam_mulai,'%H:%i:%s')
   AND TIME_FORMAT(ej.jam_selesai,'%H:%i:%s')=TIME_FORMAT(es2.jam_selesai,'%H:%i:%s')
  WHERE es2.jadwal_id IS NULL
  GROUP BY es2.id
) x ON x.sesi_id=es.id
SET es.jadwal_id=x.jadwal_id
WHERE es.jadwal_id IS NULL;

-- 3. FK hanya jika belum ada.
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_sesi' AND CONSTRAINT_NAME='fk_ekstra_sesi_jadwal_b1' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,'ALTER TABLE ekstra_sesi ADD CONSTRAINT fk_ekstra_sesi_jadwal_b1 FOREIGN KEY (jadwal_id) REFERENCES ekstra_jadwal(id) ON DELETE SET NULL ON UPDATE CASCADE','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Absensi pembina juga diarahkan ke sesi. Kolom tanggal tetap dipertahankan untuk kompatibilitas/histori.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND COLUMN_NAME='sesi_id');
SET @sql := IF(@c=0,'ALTER TABLE ekstra_pembina_absensi ADD COLUMN sesi_id BIGINT UNSIGNED NULL AFTER guru_id','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND INDEX_NAME='idx_epa_sesi_b1');
SET @sql := IF(@idx=0,'ALTER TABLE ekstra_pembina_absensi ADD KEY idx_epa_sesi_b1 (sesi_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Legacy: map satu record pembina per tanggal ke sesi pertama pada tanggal tersebut.
UPDATE ekstra_pembina_absensi epa
JOIN (
  SELECT epa2.id AS abs_id, MIN(es.id) AS sesi_id
  FROM ekstra_pembina_absensi epa2
  JOIN ekstra_sesi es
    ON es.ekstrakurikuler_id=epa2.ekstrakurikuler_id
   AND es.tahun_pelajaran_id=epa2.tahun_pelajaran_id
   AND es.tanggal=epa2.tanggal
   AND es.status<>'dibatalkan'
  WHERE epa2.sesi_id IS NULL
  GROUP BY epa2.id
) x ON x.abs_id=epa.id
SET epa.sesi_id=x.sesi_id
WHERE epa.sesi_id IS NULL;

SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND CONSTRAINT_NAME='fk_epa_sesi_b1' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,'ALTER TABLE ekstra_pembina_absensi ADD CONSTRAINT fk_epa_sesi_b1 FOREIGN KEY (sesi_id) REFERENCES ekstra_sesi(id) ON DELETE CASCADE ON UPDATE CASCADE','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- 5. Satu pembina dapat hadir pada beberapa sesi di hari yang sama.
SET @oldidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND INDEX_NAME='uk_ekstra_pembina_tgl');
SET @sql := IF(@oldidx>0,'ALTER TABLE ekstra_pembina_absensi DROP INDEX uk_ekstra_pembina_tgl','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @newidx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND INDEX_NAME='uk_epa_sesi_guru_b1');
SET @sql := IF(@newidx=0,'ALTER TABLE ekstra_pembina_absensi ADD UNIQUE KEY uk_epa_sesi_guru_b1 (sesi_id,guru_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Record baru menggunakan sesi_id. Kolom tanggal tetap dipertahankan untuk kompatibilitas dan laporan lama.
