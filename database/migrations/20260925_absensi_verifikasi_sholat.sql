-- APK SEKOLAH V2 - Migration 2026-09-25
-- Jalankan SEKALI pada database absensi_sekolah_v2 yang sudah terpasang.
-- Tidak menghapus data lama.

ALTER TABLE absensi
  ADD COLUMN metode ENUM('QR','MANUAL') NOT NULL DEFAULT 'QR' AFTER scan_oleh,
  ADD COLUMN verifikasi_status ENUM('belum','diverifikasi') NOT NULL DEFAULT 'belum' AFTER metode,
  ADD COLUMN diverifikasi_oleh BIGINT UNSIGNED NULL AFTER verifikasi_status,
  ADD COLUMN diverifikasi_at DATETIME NULL AFTER diverifikasi_oleh,
  ADD KEY idx_absensi_verifikasi (verifikasi_status),
  ADD KEY fk_absensi_verifikasi_oleh (diverifikasi_oleh);

ALTER TABLE sesi_absensi
  ADD COLUMN verifikasi_oleh BIGINT UNSIGNED NULL AFTER ditutup_at,
  ADD COLUMN verifikasi_at DATETIME NULL AFTER verifikasi_oleh,
  ADD KEY fk_sesi_verifikasi_oleh (verifikasi_oleh);

CREATE TABLE absensi_sholat (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sesi_id BIGINT UNSIGNED NOT NULL,
  siswa_id BIGINT UNSIGNED NOT NULL,
  status ENUM('SUDAH','BELUM','BERHALANGAN') NOT NULL DEFAULT 'SUDAH',
  waktu_scan DATETIME NULL,
  scan_oleh BIGINT UNSIGNED NULL,
  metode ENUM('QR','MANUAL') NOT NULL DEFAULT 'QR',
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_absensi_sholat_sesi_siswa (sesi_id,siswa_id),
  KEY idx_absensi_sholat_siswa (siswa_id),
  KEY idx_absensi_sholat_status (status),
  KEY idx_absensi_sholat_scan (waktu_scan),
  KEY idx_absensi_sholat_scan_oleh (scan_oleh),
  CONSTRAINT fk_absensi_sholat_sesi FOREIGN KEY (sesi_id) REFERENCES sesi_absensi(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absensi_sholat_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE,
  CONSTRAINT fk_absensi_sholat_scan_oleh FOREIGN KEY (scan_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE absensi_sholat_perubahan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  absensi_sholat_id BIGINT UNSIGNED NOT NULL,
  status_lama ENUM('SUDAH','BELUM','BERHALANGAN') NULL,
  status_baru ENUM('SUDAH','BELUM','BERHALANGAN') NOT NULL,
  alasan VARCHAR(255) NULL,
  diubah_oleh BIGINT UNSIGNED NULL,
  diubah_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_absensi_sholat_perubahan_absensi (absensi_sholat_id),
  KEY idx_absensi_sholat_perubahan_user (diubah_oleh),
  CONSTRAINT fk_absensi_sholat_perubahan_absensi FOREIGN KEY (absensi_sholat_id) REFERENCES absensi_sholat(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absensi_sholat_perubahan_user FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrasi data sholat lama dari tabel absensi.
-- H/T legacy dipetakan menjadi SUDAH; I/S/A legacy dipetakan menjadi BELUM
-- karena status tersebut tidak digunakan pada modul sholat baru. Keterangan asal dipertahankan.
INSERT INTO absensi_sholat (sesi_id,siswa_id,status,waktu_scan,scan_oleh,metode,keterangan)
SELECT a.sesi_id,a.siswa_id,
       CASE WHEN a.status IN ('H','T') THEN 'SUDAH' ELSE 'BELUM' END,
       a.waktu_scan,a.scan_oleh,COALESCE(a.metode,'QR'),
       CASE WHEN a.status IN ('H','T') THEN a.keterangan ELSE CONCAT('Migrasi legacy status sholat: ',a.status,IF(a.keterangan IS NULL,'',CONCAT(' — ',a.keterangan))) END
FROM absensi a
JOIN sesi_absensi sa ON sa.id=a.sesi_id
JOIN jenis_absensi ja ON ja.id=sa.jenis_absensi_id AND ja.kategori='sholat'
LEFT JOIN absensi_sholat x ON x.sesi_id=a.sesi_id AND x.siswa_id=a.siswa_id
WHERE x.id IS NULL;

ALTER TABLE absensi ADD CONSTRAINT fk_absensi_verifikasi_oleh FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE sesi_absensi ADD CONSTRAINT fk_sesi_verifikasi_oleh FOREIGN KEY (verifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO jenis_tugas (id,kode,nama,kategori,aktif,keterangan,created_at,updated_at)
SELECT 14,'PETUGAS_PIKET','Petugas Piket','Kesiswaan',1,'Guru yang mendapat tugas tambahan untuk verifikasi absensi kehadiran.',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM jenis_tugas WHERE kode='PETUGAS_PIKET');
