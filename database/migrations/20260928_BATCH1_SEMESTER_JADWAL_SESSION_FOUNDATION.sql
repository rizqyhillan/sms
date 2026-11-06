-- BATCH 1 - Fondasi Semester + Jadwal + Identitas Sesi
-- Baseline: absensi(20260928-095329).zip
-- Jalankan SATU KALI pada database aplikasi.
-- Tidak menghapus histori absensi.
--
-- Tujuan:
-- 1. Jadwal Kehadiran dan Jadwal Sholat terikat ke Semester.
-- 2. Sesi memiliki identitas unik per tanggal/konteks.
-- 3. Menambahkan status_sesi untuk lifecycle DRAFT/OPEN/CLOSED/CANCELLED.
-- 4. Menyamakan nama kolom pencatat Sholat menjadi dicatat_oleh bila database lama masih memakai scan_oleh.

SET @db := DATABASE();

-- =====================================================
-- 1. jadwal_kehadiran.semester_id
-- =====================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND COLUMN_NAME='semester_id');
SET @sql := IF(@c=0,
  'ALTER TABLE jadwal_kehadiran ADD COLUMN semester_id BIGINT(20) UNSIGNED NULL AFTER tahun_pelajaran_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill berdasarkan semester aktif pada Tapel. Jika tidak ada, gunakan semester terbaru.
UPDATE jadwal_kehadiran j
JOIN tahun_pelajaran tp ON tp.id=j.tahun_pelajaran_id
LEFT JOIN semester sa ON sa.tahun_pelajaran_id=tp.id AND sa.aktif=1
LEFT JOIN semester sl ON sl.tahun_pelajaran_id=tp.id
  AND sl.id=(SELECT MAX(s2.id) FROM semester s2 WHERE s2.tahun_pelajaran_id=tp.id)
SET j.semester_id=COALESCE(sa.id,sl.id)
WHERE j.semester_id IS NULL;

-- Pastikan tidak ada jadwal tanpa semester sebelum dibuat NOT NULL.
SET @orphan := (SELECT COUNT(*) FROM jadwal_kehadiran WHERE semester_id IS NULL);
SET @sql := IF(@orphan=0,
  'ALTER TABLE jadwal_kehadiran MODIFY COLUMN semester_id BIGINT(20) UNSIGNED NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK semester.
SET @fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA=@db AND CONSTRAINT_NAME='fk_jadwal_kehadiran_semester_b1');
SET @sql := IF(@fk=0,
  'ALTER TABLE jadwal_kehadiran ADD CONSTRAINT fk_jadwal_kehadiran_semester_b1 FOREIGN KEY (semester_id) REFERENCES semester(id) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Ganti unique lama tahun+hari menjadi semester+hari.
SET @oldidx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND INDEX_NAME='uk_jadwal_kehadiran_tahun_hari');
SET @sql := IF(@oldidx>0,
  'ALTER TABLE jadwal_kehadiran DROP INDEX uk_jadwal_kehadiran_tahun_hari',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @newidx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_kehadiran' AND INDEX_NAME='uk_jadwal_kehadiran_semester_hari_b1');
SET @sql := IF(@newidx=0,
  'ALTER TABLE jadwal_kehadiran ADD UNIQUE KEY uk_jadwal_kehadiran_semester_hari_b1 (semester_id,hari)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Salin pola jadwal kehadiran dari semester pertama setiap Tapel ke semester lain
-- yang belum memiliki baris. Operator tetap dapat mengubah jam/status per semester.
INSERT INTO jadwal_kehadiran
  (tahun_pelajaran_id,semester_id,hari,aktif,jam_mulai,jam_selesai,batas_terlambat,nama_sesi)
SELECT j.tahun_pelajaran_id,s.id,j.hari,j.aktif,j.jam_mulai,j.jam_selesai,j.batas_terlambat,j.nama_sesi
FROM jadwal_kehadiran j
JOIN semester s ON s.tahun_pelajaran_id=j.tahun_pelajaran_id AND s.id<>j.semester_id
WHERE j.semester_id=(SELECT MIN(sx.id) FROM semester sx WHERE sx.tahun_pelajaran_id=j.tahun_pelajaran_id)
  AND NOT EXISTS (SELECT 1 FROM jadwal_kehadiran x WHERE x.semester_id=s.id AND x.hari=j.hari);

-- =====================================================
-- 2. jadwal_sholat.semester_id
-- =====================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND COLUMN_NAME='semester_id');
SET @sql := IF(@c=0,
  'ALTER TABLE jadwal_sholat ADD COLUMN semester_id BIGINT(20) UNSIGNED NULL AFTER tahun_pelajaran_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE jadwal_sholat j
JOIN tahun_pelajaran tp ON tp.id=j.tahun_pelajaran_id
LEFT JOIN semester sa ON sa.tahun_pelajaran_id=tp.id AND sa.aktif=1
LEFT JOIN semester sl ON sl.tahun_pelajaran_id=tp.id
  AND sl.id=(SELECT MAX(s2.id) FROM semester s2 WHERE s2.tahun_pelajaran_id=tp.id)
SET j.semester_id=COALESCE(sa.id,sl.id)
WHERE j.semester_id IS NULL;

SET @orphan := (SELECT COUNT(*) FROM jadwal_sholat WHERE semester_id IS NULL);
SET @sql := IF(@orphan=0,
  'ALTER TABLE jadwal_sholat MODIFY COLUMN semester_id BIGINT(20) UNSIGNED NOT NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA=@db AND CONSTRAINT_NAME='fk_jadwal_sholat_semester_b1');
SET @sql := IF(@fk=0,
  'ALTER TABLE jadwal_sholat ADD CONSTRAINT fk_jadwal_sholat_semester_b1 FOREIGN KEY (semester_id) REFERENCES semester(id) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @oldidx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND INDEX_NAME='uk_jadwal_sholat_tahun_jenis');
SET @sql := IF(@oldidx>0,
  'ALTER TABLE jadwal_sholat DROP INDEX uk_jadwal_sholat_tahun_jenis',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @newidx := (SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jadwal_sholat' AND INDEX_NAME='uk_jadwal_sholat_semester_jenis_b1');
SET @sql := IF(@newidx=0,
  'ALTER TABLE jadwal_sholat ADD UNIQUE KEY uk_jadwal_sholat_semester_jenis_b1 (semester_id,jenis_sholat_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Salin jadwal sholat dari semester pertama setiap Tapel ke semester lain
-- yang belum memiliki jenis sholat tersebut.
INSERT INTO jadwal_sholat
  (tahun_pelajaran_id,semester_id,jenis_sholat_id,hari,jam_mulai,jam_selesai,nama_sesi,aktif)
SELECT j.tahun_pelajaran_id,s.id,j.jenis_sholat_id,j.hari,j.jam_mulai,j.jam_selesai,j.nama_sesi,j.aktif
FROM jadwal_sholat j
JOIN semester s ON s.tahun_pelajaran_id=j.tahun_pelajaran_id AND s.id<>j.semester_id
WHERE j.semester_id=(SELECT MIN(sx.id) FROM semester sx WHERE sx.tahun_pelajaran_id=j.tahun_pelajaran_id)
  AND NOT EXISTS (SELECT 1 FROM jadwal_sholat x WHERE x.semester_id=s.id AND x.jenis_sholat_id=j.jenis_sholat_id);

-- =====================================================
-- 3. sesi_absensi: status lifecycle + unique context
-- =====================================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='status_sesi');
SET @sql := IF(@c=0,
  "ALTER TABLE sesi_absensi ADD COLUMN status_sesi ENUM('DRAFT','OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'DRAFT' AFTER status",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill lifecycle baru dari status legacy. Status legacy tidak dihapus pada Batch 1
-- agar modul verifikasi/koreksi lama tetap kompatibel.
UPDATE sesi_absensi SET status_sesi=CASE status
  WHEN 'draft' THEN 'DRAFT'
  WHEN 'aktif' THEN 'OPEN'
  WHEN 'ditutup' THEN 'CLOSED'
  ELSE 'DRAFT' END;

-- Generated key mengubah NULL jenis_sholat_id menjadi 0 agar UNIQUE benar-benar
-- mencegah dua sesi Kehadiran yang identik pada tanggal/semester yang sama.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND COLUMN_NAME='jenis_sesi_key');
SET @sql := IF(@c=0,
  "ALTER TABLE sesi_absensi ADD COLUMN jenis_sesi_key BIGINT(20) UNSIGNED GENERATED ALWAYS AS (COALESCE(jenis_sholat_id,0)) STORED AFTER jenis_sholat_id",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND INDEX_NAME='uk_sesi_absensi_konteks_b1');
SET @sql := IF(@idx=0,
  'ALTER TABLE sesi_absensi ADD UNIQUE KEY uk_sesi_absensi_konteks_b1 (tahun_pelajaran_id,semester_id,jenis_absensi_id,jenis_sesi_key,tanggal)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='sesi_absensi' AND INDEX_NAME='idx_sesi_absensi_status_sesi_b1');
SET @sql := IF(@idx=0,
  'ALTER TABLE sesi_absensi ADD KEY idx_sesi_absensi_status_sesi_b1 (status_sesi,tanggal,jam_mulai,jam_selesai)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- =====================================================
-- 4. Konsistensi pencatat absensi sholat.
-- =====================================================
SET @has_scan := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi_sholat' AND COLUMN_NAME='scan_oleh');
SET @has_dicatat := (SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=@db AND TABLE_NAME='absensi_sholat' AND COLUMN_NAME='dicatat_oleh');
SET @sql := IF(@has_scan=1 AND @has_dicatat=0,
  'ALTER TABLE absensi_sholat CHANGE COLUMN scan_oleh dicatat_oleh BIGINT(20) UNSIGNED NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK/index pencatat yang sudah ada tetap dipertahankan; tidak membuat index duplikat.

-- =====================================================
-- 5. Catatan migration
-- =====================================================
INSERT INTO app_migration (migration_key,keterangan)
SELECT 'BATCH1_SEMESTER_JADWAL_SESSION_FOUNDATION',
       'Jadwal terikat semester, unique sesi, lifecycle status_sesi, dan konsistensi pencatat Sholat'
WHERE NOT EXISTS (SELECT 1 FROM app_migration WHERE migration_key='BATCH1_SEMESTER_JADWAL_SESSION_FOUNDATION');
