-- PART 13 - UKS V2: monitoring stok dan kedaluwarsa
-- Database: absensi_sekolah_v2

ALTER TABLE uks_obat
  ADD COLUMN IF NOT EXISTS tanggal_kadaluarsa DATE NULL AFTER lokasi;

CREATE INDEX IF NOT EXISTS idx_uks_obat_kadaluarsa ON uks_obat(tanggal_kadaluarsa);
