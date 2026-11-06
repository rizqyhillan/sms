-- V14: Status akhir siswa + riwayat perpindahan/keluar/lulus
-- Jalankan sekali pada database absensi_sekolah_v2.
ALTER TABLE siswa
  MODIFY status enum('aktif','lulus','pindah','keluar','nonaktif') NOT NULL DEFAULT 'aktif';

ALTER TABLE siswa_kelas
  MODIFY status enum('aktif','lulus','pindah','keluar') NOT NULL DEFAULT 'aktif';

CREATE TABLE IF NOT EXISTS siswa_status_riwayat (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id bigint(20) UNSIGNED NOT NULL,
  tahun_pelajaran_id bigint(20) UNSIGNED DEFAULT NULL,
  status enum('lulus','pindah','keluar') NOT NULL,
  tanggal_status date NOT NULL,
  tujuan varchar(255) DEFAULT NULL,
  keterangan varchar(500) DEFAULT NULL,
  created_by bigint(20) UNSIGNED DEFAULT NULL,
  created_at timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_ssr_siswa (siswa_id),
  KEY idx_ssr_tahun (tahun_pelajaran_id),
  KEY idx_ssr_status (status),
  CONSTRAINT fk_ssr_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ssr_tahun FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ssr_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
