-- V12: fondasi status siswa untuk siklus tahunan.
-- Jalankan sekali pada database absensi_sekolah_v2.
ALTER TABLE siswa
  MODIFY status ENUM('aktif','lulus','pindah','keluar','nonaktif') NOT NULL DEFAULT 'aktif';

ALTER TABLE siswa_kelas
  MODIFY status ENUM('aktif','lulus','pindah','keluar') NOT NULL DEFAULT 'aktif';
