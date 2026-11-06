-- PART26: fondasi Tugas Tambahan + histori Ekstrakurikuler per Tapel
-- Aman untuk MariaDB 10.11. Tidak menghapus data lama.

INSERT INTO jenis_tugas (kode,nama,kategori,aktif,keterangan) VALUES
('KOORDINATOR_EKSTRA','Koordinator Ekstrakurikuler','Kesiswaan',1,'Koordinator kegiatan ekstrakurikuler berdasarkan Tapel dan kegiatan.'),
('GURU_PIKET','Guru Piket','Kesiswaan',1,'Guru yang ditugaskan melakukan operasional piket.'),
('KOORDINATOR','Koordinator','Kesiswaan',1,'Tugas koordinasi umum yang dapat dikembangkan kemudian.')
ON DUPLICATE KEY UPDATE nama=VALUES(nama), kategori=VALUES(kategori), aktif=VALUES(aktif), keterangan=VALUES(keterangan);

-- GURU_BK bukan lagi sumber penetapan tugas tambahan; role users.guru_bk tetap utuh.
-- Record lama tidak dihapus agar histori tetap aman.
UPDATE jenis_tugas SET aktif=0, keterangan='Tidak digunakan untuk penugasan baru. Guru BK ditentukan melalui users.role=guru_bk. Record lama dipertahankan untuk histori.' WHERE kode='GURU_BK';

ALTER TABLE ekstra_jadwal ADD COLUMN tahun_pelajaran_id BIGINT UNSIGNED NULL AFTER ekstrakurikuler_id;

CREATE INDEX idx_ekstra_jadwal_tapel ON ekstra_jadwal (tahun_pelajaran_id);
