-- PART58.4 - Semester Ganjil/Genap wajib per Tahun Pelajaran + periode tugas dihapus dari logika
-- Jalankan SATU KALI setelah PART58.3.
SET @db := DATABASE();

-- Pastikan setiap Tahun Pelajaran mempunyai dua semester.
INSERT INTO semester (tahun_pelajaran_id,nama,aktif,tanggal_mulai,tanggal_selesai)
SELECT tp.id,'Ganjil',IF(tp.aktif=1 AND NOT EXISTS(SELECT 1 FROM semester s2 WHERE s2.tahun_pelajaran_id=tp.id AND s2.aktif=1),1,0),
       CASE WHEN tp.tanggal_mulai IS NOT NULL THEN tp.tanggal_mulai ELSE CONCAT(CAST(LEFT(tp.nama,4) AS UNSIGNED),'-07-01') END,
       CASE WHEN LEFT(tp.nama,4) REGEXP '^[0-9]{4}$' THEN CONCAT(CAST(LEFT(tp.nama,4) AS UNSIGNED),'-12-31') ELSE NULL END
FROM tahun_pelajaran tp
WHERE NOT EXISTS (SELECT 1 FROM semester s WHERE s.tahun_pelajaran_id=tp.id AND s.nama='Ganjil');

INSERT INTO semester (tahun_pelajaran_id,nama,aktif,tanggal_mulai,tanggal_selesai)
SELECT tp.id,'Genap',0,
       CASE WHEN tp.nama REGEXP '^[0-9]{4}/[0-9]{4}$' THEN CONCAT(CAST(SUBSTRING(tp.nama,6,4) AS UNSIGNED),'-01-01') ELSE NULL END,
       CASE WHEN tp.tanggal_selesai IS NOT NULL THEN tp.tanggal_selesai ELSE CASE WHEN tp.nama REGEXP '^[0-9]{4}/[0-9]{4}$' THEN CONCAT(CAST(SUBSTRING(tp.nama,6,4) AS UNSIGNED),'-06-30') ELSE NULL END END
FROM tahun_pelajaran tp
WHERE NOT EXISTS (SELECT 1 FROM semester s WHERE s.tahun_pelajaran_id=tp.id AND s.nama='Genap');

-- Penugasan lama mengikuti semester, bukan lagi tanggal mulai/selesai.
UPDATE tugas_guru tg SET tg.mulai=NULL,tg.selesai=NULL WHERE tg.mulai IS NOT NULL OR tg.selesai IS NOT NULL;

-- Satu semester aktif untuk Tahun Pelajaran aktif; bila tanggal hari ini berada pada semester tertentu, gunakan semester tersebut.
UPDATE semester s SET s.aktif=0 WHERE s.tahun_pelajaran_id=(SELECT id FROM tahun_pelajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1);
UPDATE semester s
JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id
SET s.aktif=1
WHERE tp.aktif=1
  AND (CURRENT_DATE BETWEEN COALESCE(s.tanggal_mulai,'1000-01-01') AND COALESCE(s.tanggal_selesai,'9999-12-31'))
ORDER BY s.id
LIMIT 1;

-- Jika tanggal semester belum terisi, Ganjil menjadi semester aktif default.
UPDATE semester s
JOIN tahun_pelajaran tp ON tp.id=s.tahun_pelajaran_id
SET s.aktif=1
WHERE tp.aktif=1 AND s.nama='Ganjil' AND NOT EXISTS (SELECT 1 FROM semester sx WHERE sx.tahun_pelajaran_id=tp.id AND sx.aktif=1);

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'PART58_4_SEMESTER_TANPA_PERIODE_TUGAS','Semester Ganjil/Genap wajib per Tapel dan periode tanggal tugas tidak lagi digunakan'
WHERE NOT EXISTS (SELECT 1 FROM app_migration WHERE migration_key='PART58_4_SEMESTER_TANPA_PERIODE_TUGAS');
