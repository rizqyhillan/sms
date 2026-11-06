-- PART 11 migration
ALTER TABLE siswa_kelas ADD COLUMN nomor_urut INT UNSIGNED NULL AFTER kelas_id;
CREATE INDEX idx_siswa_kelas_nomor ON siswa_kelas (tahun_pelajaran_id, kelas_id, nomor_urut);
CREATE TABLE IF NOT EXISTS profil_sekolah (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, nama_sekolah VARCHAR(200) NOT NULL, npsn VARCHAR(30) NULL, nss VARCHAR(30) NULL, jenjang VARCHAR(50) NULL, status_sekolah VARCHAR(50) NULL, alamat TEXT NULL, desa VARCHAR(100) NULL, kecamatan VARCHAR(100) NULL, kabupaten VARCHAR(100) NULL, provinsi VARCHAR(100) NULL, kode_pos VARCHAR(10) NULL, telepon VARCHAR(50) NULL, email VARCHAR(150) NULL, website VARCHAR(200) NULL, logo VARCHAR(255) NULL, foto VARCHAR(255) NULL, nama_kepala VARCHAR(200) NULL, nip_kepala VARCHAR(50) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO profil_sekolah (nama_sekolah) SELECT 'SMP NEGERI 1 PLAOSAN' WHERE NOT EXISTS (SELECT 1 FROM profil_sekolah);

-- Isi nomor urut untuk penempatan aktif yang sudah ada, per tahun + kelas.
SET @rn:=0; SET @last_t:=NULL; SET @last_k:=NULL;
UPDATE siswa_kelas sk JOIN (SELECT id, (@rn:=IF(@last_t=tahun_pelajaran_id AND @last_k=kelas_id,@rn+1,1)) AS rn, (@last_t:=tahun_pelajaran_id) t, (@last_k:=kelas_id) k FROM siswa_kelas WHERE status='aktif' ORDER BY tahun_pelajaran_id,kelas_id,id) x ON x.id=sk.id SET sk.nomor_urut=x.rn;
