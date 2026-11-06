-- PART 18: Snapshot histori tahun pelajaran + kelas pada transaksi UKS dan Pengunjung Perpustakaan
-- Jalankan sekali pada database absensi_sekolah_v2.
ALTER TABLE uks_kunjungan
  ADD COLUMN tahun_pelajaran_id BIGINT UNSIGNED NULL AFTER siswa_id,
  ADD COLUMN kelas_id BIGINT UNSIGNED NULL AFTER tahun_pelajaran_id,
  ADD KEY idx_uks_kunjungan_tp_kelas (tahun_pelajaran_id, kelas_id),
  ADD CONSTRAINT fk_uks_kunjungan_tp FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_uks_kunjungan_kelas FOREIGN KEY (kelas_id) REFERENCES master_kelas(id) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE perpustakaan_kunjungan
  ADD COLUMN tahun_pelajaran_id BIGINT UNSIGNED NULL AFTER siswa_id,
  ADD COLUMN kelas_id BIGINT UNSIGNED NULL AFTER tahun_pelajaran_id,
  ADD KEY idx_perpus_kunjungan_tp_kelas (tahun_pelajaran_id, kelas_id),
  ADD CONSTRAINT fk_perpus_kunjungan_tp FOREIGN KEY (tahun_pelajaran_id) REFERENCES tahun_pelajaran(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_perpus_kunjungan_kelas FOREIGN KEY (kelas_id) REFERENCES master_kelas(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Backfill transaksi lama berdasarkan tanggal transaksi dan rentang tahun pelajaran.
UPDATE uks_kunjungan uk
JOIN siswa_kelas sk ON sk.siswa_id = uk.siswa_id
JOIN tahun_pelajaran tp ON tp.id = sk.tahun_pelajaran_id
SET uk.tahun_pelajaran_id = sk.tahun_pelajaran_id,
    uk.kelas_id = sk.kelas_id
WHERE uk.tahun_pelajaran_id IS NULL
  AND uk.tanggal BETWEEN COALESCE(tp.tanggal_mulai, '1000-01-01') AND COALESCE(tp.tanggal_selesai, '9999-12-31');

UPDATE perpustakaan_kunjungan pk
JOIN siswa_kelas sk ON sk.siswa_id = pk.siswa_id
JOIN tahun_pelajaran tp ON tp.id = sk.tahun_pelajaran_id
SET pk.tahun_pelajaran_id = sk.tahun_pelajaran_id,
    pk.kelas_id = sk.kelas_id
WHERE pk.tahun_pelajaran_id IS NULL
  AND DATE(pk.waktu_kunjungan) BETWEEN COALESCE(tp.tanggal_mulai, '1000-01-01') AND COALESCE(tp.tanggal_selesai, '9999-12-31');

-- Verifikasi sebelum membuat kolom NOT NULL di masa depan:
-- SELECT COUNT(*) AS uks_belum_terpetakan FROM uks_kunjungan WHERE tahun_pelajaran_id IS NULL OR kelas_id IS NULL;
-- SELECT COUNT(*) AS perpus_belum_terpetakan FROM perpustakaan_kunjungan WHERE tahun_pelajaran_id IS NULL OR kelas_id IS NULL;
