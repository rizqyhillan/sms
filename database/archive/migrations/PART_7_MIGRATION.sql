-- PART 7: Kalender & Jadwal Kehadiran
-- Jalankan sekali pada database absensi_sekolah_v2.

CREATE TABLE IF NOT EXISTS jadwal_kehadiran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun_pelajaran_id BIGINT UNSIGNED NOT NULL,
  hari TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
  aktif TINYINT(1) NOT NULL DEFAULT 0,
  jam_mulai TIME NOT NULL DEFAULT '06:30:00',
  jam_selesai TIME NOT NULL DEFAULT '09:00:00',
  batas_terlambat TIME DEFAULT NULL,
  nama_sesi VARCHAR(150) NOT NULL DEFAULT 'Absensi Kehadiran',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_jadwal_kehadiran_tahun_hari (tahun_pelajaran_id,hari),
  CONSTRAINT fk_jadwal_kehadiran_tahun FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS kalender_kehadiran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun_pelajaran_id BIGINT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  jenis ENUM('libur','masuk_khusus') NOT NULL,
  nama VARCHAR(150) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kalender_kehadiran_tahun_tanggal (tahun_pelajaran_id,tanggal),
  KEY idx_kalender_kehadiran_tanggal (tanggal),
  CONSTRAINT fk_kalender_kehadiran_tahun FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_kalender_kehadiran_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO jadwal_kehadiran (tahun_pelajaran_id,hari,aktif,jam_mulai,jam_selesai,batas_terlambat,nama_sesi)
SELECT tp.id, d.hari, CASE WHEN d.hari BETWEEN 1 AND 5 THEN 1 ELSE 0 END,
       '06:30:00','09:00:00','07:00:00','Absensi Kehadiran'
FROM tahun_pelajaran tp
CROSS JOIN (SELECT 1 hari UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7) d
WHERE NOT EXISTS (SELECT 1 FROM jadwal_kehadiran jk WHERE jk.tahun_pelajaran_id=tp.id AND jk.hari=d.hari);
