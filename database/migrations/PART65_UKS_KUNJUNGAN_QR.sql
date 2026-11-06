-- PART65 - QR Kunjungan UKS
-- Menyamakan alur scan kunjungan UKS dengan scanner BK,
-- tetapi transaksi tetap berdiri sendiri pada uks_kunjungan.
-- Tidak menulis tabel absensi atau absensi_sholat.

ALTER TABLE uks_kunjungan
  ADD COLUMN IF NOT EXISTS metode ENUM('QR','USB','MANUAL') NOT NULL DEFAULT 'MANUAL' AFTER waktu;

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'PART65_UKS_KUNJUNGAN_QR',
       'Menambahkan metode QR/USB/MANUAL dan scanner kunjungan UKS yang menyimpan transaksi hanya pada uks_kunjungan.'
WHERE NOT EXISTS (
  SELECT 1 FROM app_migration WHERE migration_key='PART65_UKS_KUNJUNGAN_QR'
);
