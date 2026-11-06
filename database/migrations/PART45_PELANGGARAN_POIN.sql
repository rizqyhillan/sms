-- PART45 - Modul Pelanggaran & Sistem Poin
-- Jalankan SATU KALI pada database absensi_sekolah_v2.
-- Tidak menghapus riwayat pelanggaran yang sudah ada.

ALTER TABLE pelanggaran_siswa
  ADD COLUMN tahun_pelajaran_id BIGINT(20) UNSIGNED NULL AFTER siswa_id,
  ADD KEY idx_pelanggaran_tapel (tahun_pelajaran_id);

CREATE TABLE IF NOT EXISTS pelanggaran_poin_penyesuaian (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id BIGINT(20) UNSIGNED NOT NULL,
  tahun_pelajaran_id BIGINT(20) UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  delta_poin INT NOT NULL,
  alasan TEXT NOT NULL,
  tindakan TEXT DEFAULT NULL,
  petugas_id BIGINT(20) UNSIGNED DEFAULT NULL,
  created_by BIGINT(20) UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_penyesuaian_siswa (siswa_id),
  KEY idx_penyesuaian_tapel (tahun_pelajaran_id),
  KEY idx_penyesuaian_tanggal (tanggal),
  KEY idx_penyesuaian_petugas (petugas_id),
  CONSTRAINT fk_penyesuaian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON UPDATE CASCADE,
  CONSTRAINT fk_penyesuaian_tapel FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON UPDATE CASCADE,
  CONSTRAINT fk_penyesuaian_petugas FOREIGN KEY (petugas_id) REFERENCES guru(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_penyesuaian_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Untuk instalasi baru/bersih, semua transaksi PART45 baru wajib memiliki Tapel.
-- Data pelanggaran lama yang belum memiliki Tapel akan tetap NULL dan ditampilkan sebagai
-- "Tapel belum ditentukan" agar tidak terjadi klaim histori yang tidak dapat diverifikasi.
