-- BATCH 2 EKSTRA — INTEGRITAS DATABASE
-- MariaDB 10.11+ / idempotent
-- Fokus: FK/index yang belum lengkap. Tidak mengubah logika aplikasi.
-- Tidak menghapus data dan tidak mengubah FK yang sudah ada.

SET @db := DATABASE();

-- 1. Jadwal -> Tahun Pelajaran
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_jadwal'
 AND CONSTRAINT_NAME='fk_ekstra_jadwal_tahun_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_jadwal ADD CONSTRAINT fk_ekstra_jadwal_tahun_b2 FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE SET NULL ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Dokumentasi -> Sesi
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_dokumentasi'
 AND CONSTRAINT_NAME='fk_ekstra_dokumentasi_sesi_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_dokumentasi ADD CONSTRAINT fk_ekstra_dokumentasi_sesi_b2 FOREIGN KEY (sesi_id) REFERENCES ekstra_sesi(id) ON DELETE CASCADE ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Dokumentasi -> User uploader
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_dokumentasi'
 AND CONSTRAINT_NAME='fk_ekstra_dokumentasi_user_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_dokumentasi ADD CONSTRAINT fk_ekstra_dokumentasi_user_b2 FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Jurnal Kendala -> Sesi
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_jurnal_kendala'
 AND CONSTRAINT_NAME='fk_ekstra_kendala_sesi_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_jurnal_kendala ADD CONSTRAINT fk_ekstra_kendala_sesi_b2 FOREIGN KEY (sesi_id) REFERENCES ekstra_sesi(id) ON DELETE CASCADE ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. Jurnal Kendala -> User pembuat
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_jurnal_kendala'
 AND CONSTRAINT_NAME='fk_ekstra_kendala_user_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_jurnal_kendala ADD CONSTRAINT fk_ekstra_kendala_user_b2 FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. Absensi Pembina -> Guru
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi'
 AND CONSTRAINT_NAME='fk_epa_guru_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_pembina_absensi ADD CONSTRAINT fk_epa_guru_b2 FOREIGN KEY (guru_id) REFERENCES guru(id) ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7. Absensi Pembina -> User pencatat
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi'
 AND CONSTRAINT_NAME='fk_epa_user_b2' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0,
 'ALTER TABLE ekstra_pembina_absensi ADD CONSTRAINT fk_epa_user_b2 FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 8. Index dokumentasi uploader untuk rekap/audit.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_dokumentasi' AND INDEX_NAME='idx_ekstra_dokumentasi_user_b2');
SET @sql := IF(@idx=0,
 'ALTER TABLE ekstra_dokumentasi ADD KEY idx_ekstra_dokumentasi_user_b2 (uploaded_by)',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 9. Index kendala user.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_jurnal_kendala' AND INDEX_NAME='idx_ekstra_kendala_user_b2');
SET @sql := IF(@idx=0,
 'ALTER TABLE ekstra_jurnal_kendala ADD KEY idx_ekstra_kendala_user_b2 (dibuat_oleh)',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 10. Index pembina absensi untuk pencarian per sesi.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_pembina_absensi' AND INDEX_NAME='idx_epa_sesi_status_b2');
SET @sql := IF(@idx=0,
 'ALTER TABLE ekstra_pembina_absensi ADD KEY idx_epa_sesi_status_b2 (sesi_id,status)',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 11. Index sesi berdasarkan jadwal + tanggal + status.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ekstra_sesi' AND INDEX_NAME='idx_ekstra_sesi_jadwal_tgl_b2');
SET @sql := IF(@idx=0,
 'ALTER TABLE ekstra_sesi ADD KEY idx_ekstra_sesi_jadwal_tgl_b2 (jadwal_id,tanggal,status)',
 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 12. Audit query (jalankan setelah migration untuk memastikan tidak ada orphan).
-- SELECT 'dokumentasi_tanpa_sesi' AS temuan, COUNT(*) jumlah FROM ekstra_dokumentasi d LEFT JOIN ekstra_sesi s ON s.id=d.sesi_id WHERE s.id IS NULL;
-- SELECT 'kendala_tanpa_sesi' AS temuan, COUNT(*) jumlah FROM ekstra_jurnal_kendala k LEFT JOIN ekstra_sesi s ON s.id=k.sesi_id WHERE s.id IS NULL;
-- SELECT 'pembina_tanpa_sesi' AS temuan, COUNT(*) jumlah FROM ekstra_pembina_absensi p LEFT JOIN ekstra_sesi s ON s.id=p.sesi_id WHERE p.sesi_id IS NOT NULL AND s.id IS NULL;
-- SELECT 'pembina_tanpa_guru' AS temuan, COUNT(*) jumlah FROM ekstra_pembina_absensi p LEFT JOIN guru g ON g.id=p.guru_id WHERE g.id IS NULL;
-- SELECT 'jadwal_tanpa_tapel' AS temuan, COUNT(*) jumlah FROM ekstra_jadwal j LEFT JOIN tahun_pelajaran t ON t.id=j.tahun_pelajaran_id WHERE j.tahun_pelajaran_id IS NOT NULL AND t.id IS NULL;
