-- PART 12 - UKS V1 + stok obat
-- Database: absensi_sekolah_v2

ALTER TABLE uks_kunjungan
  ADD COLUMN IF NOT EXISTS status ENUM('baru','ditangani','selesai') NOT NULL DEFAULT 'baru' AFTER petugas_id;

CREATE TABLE IF NOT EXISTS uks_obat (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(50) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(50) NOT NULL,
  stok_minimum INT UNSIGNED NOT NULL DEFAULT 0,
  stok_saat_ini INT UNSIGNED NOT NULL DEFAULT 0,
  lokasi VARCHAR(150) DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uk_uks_obat_kode (kode), KEY idx_uks_obat_aktif (aktif), KEY idx_uks_obat_stok (stok_saat_ini,stok_minimum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uks_stok_transaksi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  obat_id BIGINT UNSIGNED NOT NULL,
  jenis ENUM('masuk','keluar','koreksi') NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  stok_sebelum INT UNSIGNED NOT NULL,
  stok_sesudah INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  kunjungan_id BIGINT UNSIGNED DEFAULT NULL,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_uks_stok_obat (obat_id), KEY idx_uks_stok_tanggal (tanggal), KEY idx_uks_stok_kunjungan (kunjungan_id), KEY idx_uks_stok_user (user_id),
  CONSTRAINT fk_uks_stok_obat FOREIGN KEY (obat_id) REFERENCES uks_obat(id) ON UPDATE CASCADE,
  CONSTRAINT fk_uks_stok_kunjungan FOREIGN KEY (kunjungan_id) REFERENCES uks_kunjungan(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_uks_stok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uks_kunjungan_obat (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kunjungan_id BIGINT UNSIGNED NOT NULL,
  obat_id BIGINT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  aturan_pakai VARCHAR(255) DEFAULT NULL,
  catatan VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_uks_kunjungan_obat_kunjungan (kunjungan_id), KEY idx_uks_kunjungan_obat_obat (obat_id),
  CONSTRAINT fk_uks_kunjungan_obat_kunjungan FOREIGN KEY (kunjungan_id) REFERENCES uks_kunjungan(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_uks_kunjungan_obat_obat FOREIGN KEY (obat_id) REFERENCES uks_obat(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO jenis_tugas (kode,nama,kategori,aktif,keterangan)
SELECT 'PETUGAS_UKS','Petugas UKS','Layanan',1,'Penanggung jawab operasional UKS dan stok obat'
WHERE NOT EXISTS (SELECT 1 FROM jenis_tugas WHERE kode='PETUGAS_UKS');
