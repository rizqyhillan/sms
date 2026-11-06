-- PART58.3 - Semester + jadwal mingguan tugas tambahan
-- Jalankan SATU KALI setelah PART58.2.
-- Prinsip: histori tetap tersimpan; hak aktif mengikuti Tapel + Semester + periode.

SET @db := DATABASE();

-- 1) Semester wajib pada setiap penugasan.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tugas_guru' AND COLUMN_NAME='semester_id');
SET @sql := IF(@c=0,
  'ALTER TABLE tugas_guru ADD COLUMN semester_id BIGINT(20) UNSIGNED NULL AFTER tahun_pelajaran_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill penugasan lama ke semester yang aktif pada Tapel masing-masing.
UPDATE tugas_guru tg
JOIN semester s ON s.tahun_pelajaran_id=tg.tahun_pelajaran_id AND s.aktif=1
SET tg.semester_id=s.id
WHERE tg.semester_id IS NULL;

-- FK semester dibuat dengan nama unik agar aman dari duplicate constraint.
SET @fk := (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=@db AND CONSTRAINT_NAME='fk_p58_3_tugas_guru_semester');
SET @sql := IF(@fk=0,
  'ALTER TABLE tugas_guru ADD CONSTRAINT fk_p58_3_tugas_guru_semester FOREIGN KEY (semester_id) REFERENCES semester(id) ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Jadwal mingguan tugas. Hari ISO: 1=Senin ... 7=Minggu.
CREATE TABLE IF NOT EXISTS tugas_guru_jadwal (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  tugas_guru_id BIGINT(20) UNSIGNED NOT NULL,
  hari TINYINT UNSIGNED NOT NULL,
  jam_mulai TIME DEFAULT NULL,
  jam_selesai TIME DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_p58_3_tgj_hari (tugas_guru_id,hari),
  KEY idx_p58_3_tgj_tugas (tugas_guru_id),
  CONSTRAINT fk_p58_3_tgj_tugas FOREIGN KEY (tugas_guru_id) REFERENCES tugas_guru(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Index untuk query akses aktif.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='tugas_guru' AND INDEX_NAME='idx_p58_3_tg_semester_aktif');
SET @sql := IF(@idx=0,
  'ALTER TABLE tugas_guru ADD KEY idx_p58_3_tg_semester_aktif (semester_id,aktif,mulai,selesai,guru_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'PART58_3_SEMESTER_JADWAL_TUGAS','Fondasi semester dan jadwal mingguan tugas tambahan'
WHERE NOT EXISTS (SELECT 1 FROM app_migration WHERE migration_key='PART58_3_SEMESTER_JADWAL_TUGAS');
