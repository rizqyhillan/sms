-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Okt 2026 pada 07.35
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sms`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `metode_awal` enum('QR','MANUAL') NOT NULL DEFAULT 'QR',
  `sumber_terakhir` enum('QR','VERIFIKASI','KOREKSI') NOT NULL DEFAULT 'QR',
  `status` enum('H','T','I','S','A') NOT NULL DEFAULT 'H',
  `status_verifikasi` enum('belum','valid','dikoreksi') NOT NULL DEFAULT 'belum',
  `diverifikasi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `diverifikasi_at` datetime DEFAULT NULL,
  `waktu_scan` datetime DEFAULT NULL,
  `waktu_pulang` datetime DEFAULT NULL,
  `pulang_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `pulang_at` datetime DEFAULT NULL,
  `scan_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `sesi_id`, `siswa_id`, `metode_awal`, `sumber_terakhir`, `status`, `status_verifikasi`, `diverifikasi_oleh`, `diverifikasi_at`, `waktu_scan`, `waktu_pulang`, `pulang_oleh`, `pulang_at`, `scan_oleh`, `keterangan`, `created_at`, `updated_at`) VALUES
(42, 49, 49, 'QR', 'QR', 'H', 'belum', NULL, NULL, '2026-10-09 05:54:21', NULL, NULL, NULL, 1, NULL, '2026-10-08 22:54:21', '2026-10-08 22:54:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_perubahan`
--

CREATE TABLE `absensi_perubahan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `absensi_id` bigint(20) UNSIGNED NOT NULL,
  `status_lama` enum('H','T','I','S','A') DEFAULT NULL,
  `status_baru` enum('H','T','I','S','A') NOT NULL,
  `alasan` varchar(255) DEFAULT NULL,
  `diubah_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `diubah_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_sholat`
--

CREATE TABLE `absensi_sholat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('SUDAH','BERHALANGAN') NOT NULL DEFAULT 'SUDAH',
  `waktu_scan` datetime DEFAULT NULL,
  `metode` enum('QR','MANUAL') NOT NULL DEFAULT 'QR',
  `dicatat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `absensi_sholat`
--
DELIMITER $$
CREATE TRIGGER `trg_absensi_sholat_berhalangan_insert` BEFORE INSERT ON `absensi_sholat` FOR EACH ROW BEGIN
  DECLARE v_jk CHAR(1);
  IF NEW.status = 'BERHALANGAN' THEN
    SELECT jk INTO v_jk FROM siswa WHERE id = NEW.siswa_id LIMIT 1;
    IF v_jk <> 'P' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status BERHALANGAN hanya dapat diberikan kepada siswi';
    END IF;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_absensi_sholat_berhalangan_update` BEFORE UPDATE ON `absensi_sholat` FOR EACH ROW BEGIN
  DECLARE v_jk CHAR(1);
  IF NEW.status = 'BERHALANGAN' THEN
    SELECT jk INTO v_jk FROM siswa WHERE id = NEW.siswa_id LIMIT 1;
    IF v_jk <> 'P' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status BERHALANGAN hanya dapat diberikan kepada siswi';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_sholat_perubahan`
--

CREATE TABLE `absensi_sholat_perubahan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `absensi_sholat_id` bigint(20) UNSIGNED NOT NULL,
  `status_lama` enum('SUDAH','BERHALANGAN') DEFAULT NULL,
  `status_baru` enum('SUDAH','BERHALANGAN') NOT NULL,
  `alasan` varchar(255) DEFAULT NULL,
  `diubah_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `diubah_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `app_migration`
--

CREATE TABLE `app_migration` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `migration_key` varchar(150) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `app_migration`
--

INSERT INTO `app_migration` (`id`, `migration_key`, `applied_at`, `keterangan`) VALUES
(1, 'V15_SISWA_ABSENSI_SHOLAT_FINAL', '2026-09-25 21:58:06', 'Fondasi siswa/tapel/kelas, verifikasi kehadiran, koreksi, dan absensi sholat'),
(2, 'PART25_IZIN_SISWA', '2026-09-26 07:49:19', 'Modul izin pulang lebih awal/keluar sementara'),
(3, 'PART58_3_SEMESTER_JADWAL_TUGAS', '2026-09-26 14:19:05', 'Fondasi semester dan jadwal mingguan tugas tambahan'),
(4, 'PART58_4_SEMESTER_TANPA_PERIODE_TUGAS', '2026-09-26 14:30:50', 'Semester Ganjil/Genap wajib per Tapel dan periode tanggal tugas tidak lagi digunakan'),
(5, 'BATCH2_SESSION_LIFECYCLE', '2026-09-28 17:32:00', 'Lifecycle sesi otomatis DRAFT sebelum jam mulai, OPEN saat jadwal berlangsung, CLOSED setelah jam selesai; WIB dan tanpa cron.'),
(6, 'BATCH2_HOTFIX1_SESSION_LIFECYCLE', '2026-09-28 17:36:51', 'Menambahkan status_sesi, kunci konteks sesi, dan index lifecycle untuk Batch 2.'),
(7, 'PART59_UKS_PETUGAS_SISWA', '2026-09-29 20:41:41', 'Fondasi jadwal dan absensi petugas UKS siswa yang berdiri sendiri dari absensi kehadiran dan sholat.'),
(8, 'PART65_UKS_KUNJUNGAN_QR', '2026-09-29 21:29:12', 'Menambahkan metode QR/USB/MANUAL pada kunjungan UKS.'),
(9, 'PART66_UKS_LAYANAN_ABSENSI_PETUGAS', '2026-09-29 21:55:33', 'Menyelaraskan layanan UKS dengan alur antrian BK dan memperluas absensi petugas UKS untuk guru dan siswa.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `aksi` varchar(100) NOT NULL,
  `modul` varchar(100) NOT NULL,
  `referensi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `aksi`, `modul`, `referensi_id`, `keterangan`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 11:59:25'),
(2, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 12:02:52'),
(3, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 12:03:10'),
(4, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 12:09:27'),
(5, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 13:10:24'),
(6, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 16:22:42'),
(7, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-13 22:10:11'),
(8, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 00:28:17'),
(9, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 10:59:04'),
(10, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 13:53:01'),
(11, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 13:53:28'),
(12, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 13:53:31'),
(13, 1, 'CREATE_SESSION', 'absensi', 1, 'Membuat sesi Absen Masuk Pagi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 14:36:04'),
(14, 1, 'CREATE_SESSION', 'absensi', 2, 'Membuat sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 14:37:00'),
(15, 1, 'ACTIVATE_SESSION', 'absensi', 1, 'Mengaktifkan sesi Absen Masuk Pagi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 14:37:34'),
(16, 1, 'CLOSE_SESSION', 'absensi', 1, 'Menutup sesi Absen Masuk Pagi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-14 14:38:10'),
(17, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:05:56'),
(18, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:38:49'),
(19, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 2, 'Mengubah jadwal hari 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:39:09'),
(20, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 3, 'Mengubah jadwal hari 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:39:29'),
(21, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 4, 'Mengubah jadwal hari 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:39:46'),
(22, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 4, 'Mengubah jadwal hari 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:39:59'),
(23, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 01:40:33'),
(24, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 02:18:37'),
(25, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-16 02:30:01'),
(26, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-16 02:31:57'),
(27, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 03:49:59'),
(28, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 03:50:02'),
(29, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 03:50:05'),
(30, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-16 04:28:16'),
(31, 1, 'IMPORT', 'siswa', 0, 'Impor 10 siswa dari CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 04:48:58'),
(32, 1, 'SCAN', 'absensi', 1, 'Scan siswa 1001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 04:51:57'),
(33, 1, 'ACTIVATE_SESSION', 'sholat', 2, 'Mengaktifkan sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 04:52:44'),
(34, 1, 'SCAN', 'absensi', 2, 'Scan siswa 1001 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 04:53:03'),
(35, 1, 'DELETE', 'siswa', 8, 'Menghapus siswa BAGAS (NIS 1708)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(36, 1, 'DELETE', 'siswa', 7, 'Menghapus siswa ERIK (NIS 1607)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(37, 1, 'DELETE', 'siswa', 2, 'Menghapus siswa JOKO (NIS 1102)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(38, 1, 'DELETE', 'siswa', 3, 'Menghapus siswa LALA (NIS 1203)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(39, 1, 'DELETE', 'siswa', 4, 'Menghapus siswa LIA (NIS 1304)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(40, 1, 'DELETE', 'siswa', 6, 'Menghapus siswa MITA (NIS 1506)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(41, 1, 'DELETE', 'siswa', 5, 'Menghapus siswa PANDU (NIS 1405)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(42, 1, 'DELETE', 'siswa', 9, 'Menghapus siswa RUDI (NIS 1809)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(43, 1, 'DELETE', 'siswa', 10, 'Menghapus siswa WINDA (NIS 1910)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:29:48'),
(44, 1, 'CREATE', 'kelas', 10, 'Menambahkan kelas 9D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:32:22'),
(45, 1, 'IMPORT', 'siswa', 0, 'Impor 9 siswa dari CSV; ditempatkan 8, belum ditempatkan 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:32:34'),
(46, 1, 'CLASS_ASSIGN', 'siswa', 1, 'Penempatan/perpindahan kelas, siswa_kelas #16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:33:15'),
(47, 1, 'CLASS_ASSIGN', 'siswa', 26, 'Penempatan/perpindahan kelas, siswa_kelas #17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 05:33:52'),
(48, 1, 'DELETE_SESSION', 'absensi', 1, 'Menghapus sesi kehadiran Absen Masuk Pagi pada 2026-09-14 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:03:46'),
(49, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:39:27'),
(50, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:40:04'),
(51, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:52:48'),
(52, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:52:50'),
(53, 1, 'CREATE_JADWAL_SHOLAT', 'pengaturan_absensi', 1, 'Menyimpan jadwal sholat.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:54:15'),
(54, 1, 'CREATE_JADWAL_SHOLAT', 'pengaturan_absensi', 2, 'Menyimpan jadwal sholat.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 06:54:44'),
(55, 1, 'DELETE_JENIS_SHOLAT', 'pengaturan_absensi', 2, 'Menghapus jenis sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:01:51'),
(56, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 1, 'Menyimpan jadwal sholat: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:20:59'),
(57, 1, 'ACTIVATE_JENIS_SHOLAT', 'pengaturan_absensi', 1, 'Mengaktifkan jenis sholat: Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:21:10'),
(58, 1, 'ACTIVATE_JENIS_SHOLAT', 'pengaturan_absensi', 6, 'Mengaktifkan jenis sholat: Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:21:46'),
(59, 1, 'CREATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menyimpan jadwal sholat: Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:23:14'),
(60, 1, 'CLOSE_SESSION', 'sholat', 6, 'Menutup sesi Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:31:54'),
(61, 1, 'CLOSE_SESSION', 'sholat', 5, 'Menutup sesi Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:32:01'),
(62, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menyimpan jadwal sholat: Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:33:06'),
(63, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menyimpan jadwal sholat: Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:37:30'),
(64, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menyimpan jadwal sholat: Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:40:49'),
(65, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menyimpan jadwal sholat: Sholat Ashar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-16 07:41:23'),
(66, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 03:18:01'),
(67, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-19 03:18:33'),
(68, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 00:50:51'),
(69, 1, 'SCAN', 'absensi', 3, 'Scan siswa 1001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 00:53:33'),
(70, 1, 'UPDATE', 'kelas', 1, 'Mengubah kelas 7A', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 01:36:24'),
(71, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 04:28:25'),
(72, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:16:26'),
(73, 1, 'IMPORT', 'guru', 0, 'Impor 5 guru dari CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:19:09'),
(74, 1, 'CREATE_USER', 'pengguna', 2, 'Membuat akun guru4 dengan role guru_bk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:21:18'),
(75, 1, 'CREATE_USER', 'pengguna', 3, 'Membuat akun guru dengan role guru', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:22:19'),
(76, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:22:50'),
(77, 2, 'LOGIN', 'AUTH', 2, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:23:00'),
(78, 2, 'LOGOUT', 'AUTH', 2, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:23:55'),
(79, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:24:50'),
(80, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:25:15'),
(81, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:25:19'),
(82, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:26:00'),
(83, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-22 10:26:07'),
(84, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-23 01:23:13'),
(85, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 13:54:15'),
(86, 1, 'CREATE', 'tahun_pelajaran', 2, 'Menambahkan tahun pelajaran 2027/2028', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:10:53'),
(87, 1, 'CLASS_ASSIGN', 'siswa', 1, 'Penempatan/perpindahan kelas, siswa_kelas #18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:19:17'),
(88, 1, 'CLASS_ASSIGN', 'siswa', 1, 'Penempatan/perpindahan kelas, siswa_kelas #19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:20:40'),
(89, 1, 'STATUS_lulus', 'siswa', 24, 'Status siswa diubah menjadi LULUS (#riwayat 1)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:30:47'),
(90, 1, 'STATUS_pindah', 'siswa', 18, 'Status siswa diubah menjadi PINDAH (#riwayat 2)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:31:47'),
(91, 1, 'STATUS_keluar', 'siswa', 1, 'Status siswa diubah menjadi KELUAR (#riwayat 3)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-24 14:32:15'),
(92, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 00:57:04'),
(93, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 00:57:46'),
(94, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:15:19'),
(95, 1, 'IMPORT', 'siswa', 0, 'Impor 5 siswa dari CSV; ditempatkan 5, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:27:45'),
(96, 1, 'CREATE', 'tahun_pelajaran', 4, 'Menambahkan tahun pelajaran 2025/2026', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:29:12'),
(97, 1, 'CREATE', 'tahun_pelajaran', 5, 'Menambahkan tahun pelajaran 2024/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:30:01'),
(98, 1, 'ACTIVATE', 'tahun_pelajaran', 5, 'Mengaktifkan tahun pelajaran 2024/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:30:30'),
(99, 1, 'UPDATE', 'kelas', 1, 'Menonaktifkan kelas 7A', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:33:14'),
(100, 1, 'UPDATE', 'kelas', 1, 'Mengaktifkan kelas 7A', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:33:59'),
(101, 1, 'ACTIVATE', 'tahun_pelajaran', 1, 'Mengaktifkan tahun pelajaran 2026/2027', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:35:37'),
(102, 1, 'CLASS_ASSIGN', 'siswa', 36, 'Penempatan/perpindahan kelas, siswa_kelas #32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:36:20'),
(103, 1, 'CLASS_ASSIGN', 'siswa', 37, 'Penempatan/perpindahan kelas, siswa_kelas #33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:36:45'),
(104, 1, 'CLASS_ASSIGN', 'siswa', 35, 'Penempatan/perpindahan kelas, siswa_kelas #34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:37:05'),
(105, 1, 'CLASS_ASSIGN', 'siswa', 34, 'Penempatan/perpindahan kelas, siswa_kelas #35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:37:20'),
(106, 1, 'CLASS_ASSIGN', 'siswa', 38, 'Penempatan/perpindahan kelas, siswa_kelas #36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:37:33'),
(107, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:38:54'),
(108, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:39:00'),
(109, 1, 'ACTIVATE', 'tahun_pelajaran', 5, 'Mengaktifkan tahun pelajaran 2024/2025', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:39:29'),
(110, 1, 'PROMOTION', 'tahun_pelajaran', 4, 'Proses kenaikan kelas dari 2024/2025 ke 2025/2026; berhasil 5 siswa; dilewati 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:43:37'),
(111, 1, 'ACTIVATE', 'tahun_pelajaran', 4, 'Mengaktifkan tahun pelajaran 2025/2026', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:44:29'),
(112, 1, 'ACTIVATE', 'tahun_pelajaran', 1, 'Mengaktifkan tahun pelajaran 2026/2027', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:45:43'),
(113, 1, 'STATUS_lulus', 'siswa', 22, 'Status siswa diubah menjadi LULUS (#riwayat 4)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:46:36'),
(114, 1, 'ACTIVATE', 'tahun_pelajaran', 4, 'Mengaktifkan tahun pelajaran 2025/2026', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:47:11'),
(115, 1, 'ACTIVATE', 'tahun_pelajaran', 1, 'Mengaktifkan tahun pelajaran 2026/2027', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:49:35'),
(116, 1, 'STATUS_lulus', 'siswa', 23, 'Status siswa diubah menjadi LULUS (#riwayat 5)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 01:50:18'),
(117, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 13:29:02'),
(118, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 22:13:05'),
(119, 1, 'VERIFY_STATUS', 'absensi', 4, 'Verifikasi guru piket: status H. Alasan: Verifikasi guru piket.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-25 23:05:37'),
(120, 1, 'VERIFY_STATUS', 'absensi', 4, 'Verifikasi guru piket: status I. Alasan: ada kepentingan keluarga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 00:16:34'),
(121, 1, 'VERIFY_STATUS', 'absensi', 4, 'Verifikasi guru piket: status I. Alasan: ada kepentingan keluarga', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 00:22:32'),
(122, 1, 'VERIFY_STATUS', 'absensi', 4, 'Verifikasi guru piket: status S. Alasan: Kurang enak badan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 00:32:34'),
(123, 1, 'VERIFY_STATUS', 'absensi', 5, 'Verifikasi guru piket: status I. Alasan: Dijemput orang tua/wali', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 00:32:47'),
(124, 1, 'VERIFY_STATUS', 'absensi', 5, 'Verifikasi guru piket: status I. Alasan: Dijemput orang tua/wali', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 00:49:26'),
(125, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:23:40'),
(126, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:24:20'),
(127, 1, 'UPDATE_USER', 'pengguna', 3, 'Mengubah akun guru1 dengan role guru', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:24:59'),
(128, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:25:05'),
(129, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:25:13'),
(130, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:26:20'),
(131, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:26:29'),
(132, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:35:14'),
(133, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:35:17'),
(134, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:39:33'),
(135, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 01:39:41'),
(136, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:03:03'),
(137, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:03:05'),
(138, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:03:17'),
(139, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:03:26'),
(140, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:13:01'),
(141, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:13:04'),
(142, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:13:07'),
(143, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:13:32'),
(144, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:13:41'),
(145, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:17:49'),
(146, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:17:50'),
(147, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:17:58'),
(148, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 02:18:06'),
(149, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:05:01'),
(150, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:05:04'),
(151, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:05:22'),
(152, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:06:32'),
(153, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:06:33'),
(154, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:06:47'),
(155, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 04:07:00'),
(156, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 05:44:17'),
(157, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 05:44:18'),
(158, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 05:46:31'),
(159, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 05:46:39'),
(160, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 06:13:03'),
(161, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 06:13:05'),
(162, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:19:32'),
(163, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:19:41'),
(164, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:44:23'),
(165, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:44:25'),
(166, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:47:51'),
(167, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 07:47:58'),
(168, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:12:49'),
(169, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:12:51'),
(170, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:14:50'),
(171, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:17:17'),
(172, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:17:48'),
(173, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 08:17:59'),
(174, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 11:08:31'),
(175, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 11:08:33'),
(176, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 11:09:18'),
(177, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 11:09:26'),
(178, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:32:34'),
(179, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:32:37'),
(180, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:33:10'),
(181, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:33:21'),
(182, 1, 'CREATE_BACKUP', 'pemeliharaan', NULL, 'Membuat backup backup_20260926_203414.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:34:15'),
(183, 1, 'RESET_DATA', 'pemeliharaan', NULL, 'Reset kelompok: absensi, izin, bk, ekstra, perpus, uks; safety backup: backup_20260926_203440.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:34:40'),
(184, 1, 'DELETE_STUDENT', 'pemeliharaan', 36, 'Menghapus siswa AMANDA (NIS 1201); safety backup: backup_20260926_203537.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:35:37'),
(185, 1, 'DELETE_STUDENT', 'pemeliharaan', 1, 'Menghapus siswa ANDI (NIS 1001); safety backup: backup_20260926_203555.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:35:56'),
(186, 1, 'DELETE_STUDENT', 'pemeliharaan', 24, 'Menghapus siswa BAGAS (NIS 1708); safety backup: backup_20260926_203609.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:36:09'),
(187, 1, 'DELETE_STUDENT', 'pemeliharaan', 37, 'Menghapus siswa BELLA (NIS 1202); safety backup: backup_20260926_203631.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:36:31'),
(188, 1, 'DELETE_STUDENT', 'pemeliharaan', 35, 'Menghapus siswa DIDIK (NIS 1200); safety backup: backup_20260926_203639.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:36:39'),
(189, 1, 'DELETE_STUDENT', 'pemeliharaan', 23, 'Menghapus siswa ERIK (NIS 1607); safety backup: backup_20260926_203647.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:36:48'),
(190, 1, 'DELETE_STUDENT', 'pemeliharaan', 18, 'Menghapus siswa JOKO (NIS 1102); safety backup: backup_20260926_203656.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:36:57'),
(191, 1, 'DELETE_STUDENT', 'pemeliharaan', 19, 'Menghapus siswa LALA (NIS 1203); safety backup: backup_20260926_203704.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:05'),
(192, 1, 'DELETE_STUDENT', 'pemeliharaan', 20, 'Menghapus siswa LIA (NIS 1304); safety backup: backup_20260926_203711.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:12'),
(193, 1, 'DELETE_STUDENT', 'pemeliharaan', 22, 'Menghapus siswa MITA (NIS 1506); safety backup: backup_20260926_203721.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:21'),
(194, 1, 'DELETE_STUDENT', 'pemeliharaan', 21, 'Menghapus siswa PANDU (NIS 1405); safety backup: backup_20260926_203728.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:29'),
(195, 1, 'DELETE_STUDENT', 'pemeliharaan', 34, 'Menghapus siswa RAISA (NIS 1199); safety backup: backup_20260926_203736.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:37'),
(196, 1, 'DELETE_STUDENT', 'pemeliharaan', 25, 'Menghapus siswa RUDI (NIS 1809); safety backup: backup_20260926_203744.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:44'),
(197, 1, 'DELETE_STUDENT', 'pemeliharaan', 26, 'Menghapus siswa WINDA (NIS 1910); safety backup: backup_20260926_203752.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:52'),
(198, 1, 'DELETE_STUDENT', 'pemeliharaan', 38, 'Menghapus siswa ZAENAL (NIS 1208); safety backup: backup_20260926_203759.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:37:59'),
(199, 1, 'DELETE_KELAS', 'kelas', 9, 'Menghapus kelas 9C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:33'),
(200, 1, 'DELETE_KELAS', 'kelas', 8, 'Menghapus kelas 9B', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:37'),
(201, 1, 'DELETE_KELAS', 'kelas', 10, 'Menghapus kelas 9D', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:45'),
(202, 1, 'DELETE_KELAS', 'kelas', 6, 'Menghapus kelas 8C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:48'),
(203, 1, 'DELETE_KELAS', 'kelas', 5, 'Menghapus kelas 8B', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:51'),
(204, 1, 'DELETE_KELAS', 'kelas', 3, 'Menghapus kelas 7C', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:55'),
(205, 1, 'DELETE_KELAS', 'kelas', 2, 'Menghapus kelas 7B', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:38:58'),
(206, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:56:36'),
(207, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:56:37'),
(208, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:57:28'),
(209, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 13:57:37'),
(210, 1, 'DELETE', 'tahun_pelajaran', 2, 'Menghapus tahun pelajaran 2027/2028 yang belum memiliki transaksi/riwayat.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 14:06:50'),
(211, 1, 'DELETE', 'tahun_pelajaran', 4, 'Menghapus tahun pelajaran 2025/2026 yang belum memiliki transaksi/riwayat.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 14:07:26'),
(212, 1, 'DELETE', 'tahun_pelajaran', 5, 'Menghapus tahun pelajaran 2024/2025 yang belum memiliki transaksi/riwayat.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 14:07:29'),
(213, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 21:44:48'),
(214, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '192.168.1.3', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', '2026-09-26 21:50:05'),
(215, 1, 'CREATE_JENIS_TUGAS', 'jenis_tugas', 18, 'Menambahkan jenis tugas manual: Pembina Tari [PEMBINA_TARI]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 22:25:01'),
(216, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '192.168.1.3', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', '2026-09-26 22:45:06'),
(217, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:07:06'),
(218, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:07:09'),
(219, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:20:12'),
(220, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:20:26'),
(221, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:21:06'),
(222, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:21:07'),
(223, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:31:09'),
(224, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:31:20'),
(225, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:42:19'),
(226, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:42:27'),
(227, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:44:56'),
(228, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:44:57'),
(229, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:51:36'),
(230, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-26 23:51:43'),
(231, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 00:26:38'),
(232, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 01:41:32'),
(233, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '192.168.1.2', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', '2026-09-27 01:50:15'),
(234, 1, 'IMPORT', 'siswa', 0, 'Impor 2 siswa dari XLSX; ditempatkan 2, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:12:21'),
(235, 1, 'IMPORT', 'guru', 0, 'Impor 2 guru dari XLSX', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:13:08');
INSERT INTO `audit_log` (`id`, `user_id`, `aksi`, `modul`, `referensi_id`, `keterangan`, `ip_address`, `user_agent`, `created_at`) VALUES
(236, 1, 'DELETE', 'guru', 6, 'Menghapus guru GURU UJI IMPORT 01 (NIP UJI-GURU-260927-01)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:13:33'),
(237, 1, 'DELETE', 'guru', 7, 'Menghapus guru GURU UJI IMPORT 02 (NIP UJI-GURU-260927-02)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:13:33'),
(238, 1, 'RESET_DATA', 'pemeliharaan', NULL, 'Reset kelompok: absensi, izin, bk, ekstra, perpus; safety backup: backup_20260927_091814.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:18:14'),
(239, 1, 'DELETE_STUDENT', 'pemeliharaan', 39, 'Penghapusan siswa SISWA UJI IMPORT 01 (NIS UJI-SIS-260927-01); safety backup: backup_20260927_091835.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:18:35'),
(240, 1, 'DELETE_STUDENT', 'pemeliharaan', 40, 'Penghapusan siswa SISWA UJI IMPORT 02 (NIS UJI-SIS-260927-02); safety backup: backup_20260927_091847.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:18:47'),
(241, 1, 'IMPORT', 'siswa', 0, 'Impor 2 siswa dari XLSX; ditempatkan 2, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:41:21'),
(242, 1, 'UPDATE', 'siswa', 41, 'Mengubah data siswa SISWA UJI IMPORT 01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:42:09'),
(243, 1, 'UPDATE', 'siswa', 42, 'Mengubah data siswa SISWA UJI IMPORT 02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:42:22'),
(244, 1, 'DELETE_STUDENT', 'pemeliharaan', 41, 'Penghapusan siswa SISWA UJI IMPORT 01 (NIS 260927-01); safety backup: backup_20260927_094318.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:43:18'),
(245, 1, 'DELETE_STUDENT', 'pemeliharaan', 42, 'Penghapusan siswa SISWA UJI IMPORT 02 (NIS 260927-02); safety backup: backup_20260927_094326.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:43:27'),
(246, 1, 'IMPORT', 'siswa', 0, 'Impor 2 siswa dari XLSX; ditempatkan 2, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:44:05'),
(247, 1, 'UPDATE', 'siswa', 43, 'Mengubah data siswa SISWA UJI IMPORT 01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:51:51'),
(248, 1, 'DELETE_STUDENT', 'pemeliharaan', 43, 'Penghapusan siswa SISWA UJI IMPORT 01 (NIS UJI-NISN-260927-01); safety backup: backup_20260927_095224.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:52:24'),
(249, 1, 'DELETE_STUDENT', 'pemeliharaan', 44, 'Penghapusan siswa SISWA UJI IMPORT 02 (NIS 260927-02); safety backup: backup_20260927_095233.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:52:33'),
(250, 1, 'IMPORT', 'siswa', 0, 'Impor 2 siswa dari XLSX; ditempatkan 2, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 02:53:44'),
(251, 1, 'DELETE_STUDENT', 'pemeliharaan', 45, 'Penghapusan siswa FARID (NIS 10001); safety backup: backup_20260927_100346.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:03:46'),
(252, 1, 'DELETE_STUDENT', 'pemeliharaan', 46, 'Penghapusan siswa LIA (NIS 10002); safety backup: backup_20260927_100354.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:03:54'),
(253, 1, 'IMPORT', 'siswa', 0, 'Impor 2 siswa dari XLSX; ditempatkan 2, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:04:08'),
(254, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0', '2026-09-27 03:11:58'),
(255, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:46:52'),
(256, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:46:54'),
(257, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:49:40'),
(258, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:49:47'),
(259, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:52:08'),
(260, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:52:10'),
(261, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:52:20'),
(262, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 03:52:37'),
(263, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 04:06:18'),
(264, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 04:06:19'),
(265, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 04:06:24'),
(266, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 04:06:57'),
(267, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 09:22:58'),
(268, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 09:23:51'),
(269, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 09:23:59'),
(270, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 10:14:53'),
(271, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 10:14:54'),
(272, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 10:15:27'),
(273, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-27 10:15:37'),
(274, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:09:56'),
(275, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:09:56'),
(276, 1, 'CREATE', 'kelas', 11, 'Menambahkan kelas 7B', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:12:30'),
(277, 1, 'IMPORT', 'siswa', 0, 'Impor 3 siswa dari XLSX; ditempatkan 3, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:12:46'),
(278, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:25:10'),
(279, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:26:35'),
(280, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:31:50'),
(281, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:44:06'),
(282, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 1, 'Menyimpan jadwal sholat: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:45:00'),
(283, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:45:07'),
(284, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:45:11'),
(285, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:46:03'),
(286, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:46:11'),
(287, 1, 'UPDATE_PERMISSION', 'pengguna', 3, 'Memperbarui hak akses akun guru1 (1 hak)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:48:39'),
(288, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:48:41'),
(289, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:48:42'),
(290, 3, 'SCAN', 'absensi', 6, 'Scan siswa 10001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:50:02'),
(291, 3, 'SCAN', 'absensi', 7, 'Scan siswa 10002 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:50:20'),
(292, 3, 'SCAN', 'absensi', 8, 'Scan siswa 10004 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:50:31'),
(293, 3, 'SCAN', 'absensi', 9, 'Scan siswa 10005 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:50:36'),
(294, 3, 'SCAN', 'absensi', 10, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:50:40'),
(295, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:52:36'),
(296, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 06:52:43'),
(297, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 09:14:14'),
(298, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 09:14:27'),
(299, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 09:14:40'),
(300, 1, 'CREATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 10:06:18'),
(301, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 10:28:58'),
(302, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '192.168.1.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-28 10:45:22'),
(303, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:12:34'),
(304, 1, 'DEACTIVATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menonaktifkan jadwal sholat semester 1: Sholat Ashar karena sudah memiliki histori sesi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:12:42'),
(305, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:13:07'),
(306, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:18:31'),
(307, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:18:52'),
(308, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:19:16'),
(309, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:23:52'),
(310, 1, 'DELETE_SESSION', 'absensi', 20, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:24:28'),
(311, 1, 'DELETE_SESSION', 'absensi', 23, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:24:31'),
(312, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:25:42'),
(313, 1, 'DELETE_SESSION', 'absensi', 24, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:25:53'),
(314, 1, 'SCAN', 'absensi', 11, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:27:22'),
(315, 1, 'DELETE_SESSION', 'absensi', 25, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:28:13'),
(316, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:28:35'),
(317, 1, 'DELETE_SESSION', 'absensi', 26, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:28:47'),
(318, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:30:27'),
(319, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:30:54'),
(320, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:31:47'),
(321, 1, 'SCAN_SHOLAT', 'absensi', 1, 'Scan sholat 10003 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:32:09'),
(322, 1, 'DELETE_SESSION', 'absensi', 27, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:34:31'),
(323, 1, 'DELETE_RECORD', 'sholat', 1, 'Menghapus record sholat BAGAS pada sesi Sholat Dzuhur tanggal 2026-09-28 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:47:53'),
(324, 1, 'DELETE_SESSION', 'absensi', 28, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:48:12'),
(325, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:48:56'),
(326, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:49:27'),
(327, 1, 'SCAN', 'absensi', 12, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:50:29'),
(328, 1, 'SCAN_SHOLAT', 'absensi', 2, 'Scan sholat 10003 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 11:53:34'),
(329, 1, 'DELETE_RECORD', 'sholat', 2, 'Menghapus record sholat BAGAS pada sesi Sholat Dzuhur tanggal 2026-09-28 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:01:57'),
(330, 1, 'DELETE_SESSION', 'absensi', 29, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:02:21'),
(331, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:22:06'),
(332, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:23:14'),
(333, 1, 'DEACTIVATE_JADWAL_SHOLAT', 'pengaturan_absensi', 3, 'Menonaktifkan jadwal sholat semester 1: Sholat Ashar karena sudah memiliki histori sesi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:23:52'),
(334, 1, 'SCAN', 'absensi', 13, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:25:44'),
(335, 1, 'SCAN', 'absensi', 14, 'Scan siswa 10005 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:25:53'),
(336, 1, 'SCAN_SHOLAT', 'absensi', 3, 'Scan sholat 10005 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:28:16'),
(337, 1, 'SCAN_SHOLAT', 'absensi', 4, 'Scan sholat 10003 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 12:28:22'),
(338, 1, 'visit_create', 'bk', 1, 'Mencatat kunjungan BK siswa 10005', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 13:03:47'),
(339, 1, 'layanan_queue_create', 'bk', 1, 'Siswa masuk antrian Layanan BK: 10005', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 13:51:07'),
(340, 1, 'layanan_start', 'bk', 1, 'Memulai layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 13:51:50'),
(341, 1, 'layanan_queue_create', 'bk', 2, 'Siswa masuk antrian Layanan BK: 10003', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 14:33:16'),
(342, 1, 'layanan_finish', 'bk', 1, 'Menyelesaikan layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36', '2026-09-28 14:44:46'),
(343, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:45:35'),
(344, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:45:54'),
(345, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:46:12'),
(346, 1, 'layanan_queue_create', 'bk', 3, 'Siswa masuk antrian Layanan BK: 10005', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:56:37'),
(347, 1, 'layanan_start', 'bk', 3, 'Memulai layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:57:07'),
(348, 1, 'layanan_finish', 'bk', 3, 'Menyelesaikan layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 09:57:44'),
(349, 1, 'create', 'pembinaan', 1, 'Membuat pembinaan untuk siswa ID 48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 10:12:48'),
(350, 1, 'STATUS_CHANGE', 'pembinaan', 1, 'Status pembinaan #1 berubah dari proses menjadi selesai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 10:13:18'),
(351, 1, 'DELETE_SESSION', 'absensi', 30, 'Menghapus sesi kehadiran Absensi Kehadiran pada 2026-09-28 beserta transaksi terkait', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:27:03'),
(352, 1, 'VERIFY_STATUS', 'absensi', 15, 'Verifikasi guru piket: status H. Alasan: Verifikasi guru piket.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:30:40'),
(353, 1, 'DELETE_RECORD', 'absensi', 15, 'Menghapus record kehadiran FARID pada sesi Absensi Kehadiran tanggal 2026-09-29 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:30:56'),
(354, 1, 'VERIFY_STATUS', 'absensi', 16, 'Verifikasi guru piket: status A. Alasan: Verifikasi guru piket.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:31:08'),
(355, 1, 'DELETE_RECORD', 'absensi', 16, 'Menghapus record kehadiran FARID pada sesi Absensi Kehadiran tanggal 2026-09-29 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:31:16'),
(356, 1, 'VERIFY_STATUS', 'absensi', 17, 'Verifikasi guru piket: status H. Alasan: Verifikasi guru piket.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:35:09'),
(357, 1, 'DELETE_RECORD', 'absensi', 17, 'Menghapus record kehadiran LIA pada sesi Absensi Kehadiran tanggal 2026-09-29 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 11:35:17'),
(358, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:03:12'),
(359, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:03:14'),
(360, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:03:40'),
(361, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:03:48'),
(362, 1, 'layanan_queue_create', 'bk', 4, 'Siswa masuk antrian Layanan BK: 10003', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:13:12'),
(363, 1, 'layanan_cancel', 'bk', 4, 'Membatalkan antrian/layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 12:13:20'),
(364, 1, 'CREATE_PETUGAS_UKS', 'uks', 4, 'Menambahkan Petugas UKS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 13:42:30'),
(365, 1, 'SCAN_KUNJUNGAN_UKS', 'uks', 1, 'Scan kunjungan UKS siswa 10005 melalui QR.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 14:29:30'),
(366, 1, 'CANCEL_LAYANAN_UKS', 'uks', 1, 'Membatalkan antrian layanan UKS untuk 10005.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 14:55:56'),
(367, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 2, 'Siswa masuk antrian layanan UKS 10005 melalui QR.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 14:56:02'),
(368, 1, 'START_LAYANAN_UKS', 'uks', 2, 'Memulai layanan UKS untuk 10005.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 14:57:31'),
(369, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 3, 'Siswa masuk antrian layanan UKS 10003 melalui QR.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 15:07:35'),
(370, 1, 'CANCEL_LAYANAN_UKS', 'uks', 3, 'Membatalkan antrian layanan UKS untuk 10003.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 15:07:43'),
(371, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 4, 'Siswa masuk antrian layanan UKS 10003 melalui QR.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 15:07:47'),
(372, 1, 'CANCEL_LAYANAN_UKS', 'uks', 4, 'Membatalkan antrian layanan UKS untuk 10003.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-29 15:08:03'),
(373, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:00:59'),
(374, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 3, 'Mengubah jadwal semester 1 hari 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:02:25'),
(375, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:02:52'),
(376, 1, 'SCAN', 'absensi', 18, 'Scan siswa 10005 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:05:26'),
(377, 1, 'SCAN', 'absensi', 19, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:05:32'),
(378, 1, 'SCAN', 'absensi', 20, 'Scan siswa 10001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:07:12'),
(379, 1, 'SCAN_SHOLAT', 'absensi', 5, 'Scan sholat 10001 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:08:17'),
(380, 1, 'SCAN_SHOLAT', 'absensi', 6, 'Scan sholat 10005 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:08:21'),
(381, 1, 'SCAN_SHOLAT', 'absensi', 7, 'Scan sholat 10003 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:08:27'),
(382, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:19:18'),
(383, 1, 'DELETE_RECORD', 'absensi', 18, 'Menghapus record kehadiran HENDRIK pada sesi Absensi Kehadiran tanggal 2026-09-30 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:19:36'),
(384, 1, 'DELETE_RECORD', 'absensi', 19, 'Menghapus record kehadiran BAGAS pada sesi Absensi Kehadiran tanggal 2026-09-30 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:19:39'),
(385, 1, 'DELETE_RECORD', 'absensi', 20, 'Menghapus record kehadiran FARID pada sesi Absensi Kehadiran tanggal 2026-09-30 untuk pengujian/koreksi.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:19:43'),
(386, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 02:24:47'),
(387, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 04:45:03'),
(388, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 04:46:04'),
(389, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 22:39:48'),
(390, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 22:45:39'),
(391, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 22:46:04'),
(392, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-30 22:46:49'),
(393, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 00:59:48'),
(394, 1, 'SCAN', 'absensi', 21, 'Scan siswa 10002 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 01:30:36'),
(395, 1, 'CREATE', 'perpustakaan', 1, 'Mencatat kunjungan perpustakaan: LIA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 01:51:18'),
(396, 1, 'CREATE', 'perpustakaan', 2, 'Mencatat kunjungan perpustakaan: LIA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 01:56:50'),
(397, 1, 'CREATE', 'perpustakaan', 3, 'Mencatat kunjungan perpustakaan: LIA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 01:59:09'),
(398, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 02:46:43'),
(399, 1, 'CREATE', 'perpustakaan', 1, 'Menambah kategori perpustakaan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 02:47:43'),
(400, 1, 'CREATE', 'perpustakaan', 1, 'Menambah buku perpustakaan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 02:48:48'),
(401, 1, 'CREATE', 'perpustakaan', 1, 'Membuat transaksi peminjaman PJ-20261001-094942-881', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 02:49:42'),
(402, 1, 'UPDATE', 'perpustakaan', 1, 'Mengubah buku perpustakaan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 03:24:20'),
(403, 1, 'CREATE', 'perpustakaan', 2, 'Membuat transaksi peminjaman PJ-20261001-102504-175', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 03:25:04'),
(404, 1, 'UPDATE', 'perpustakaan', 2, 'Memproses pengembalian buku', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 03:26:26'),
(405, 1, 'UPDATE', 'perpustakaan', 1, 'Memproses pengembalian buku', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 03:26:31'),
(406, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 06:27:58'),
(407, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 08:48:27'),
(408, 1, 'UPDATE', 'perpustakaan', 1, 'Memperbarui status eksemplar | PERUBAHAN={\"before\":[],\"after\":{\"changes\":[{\"kode\":\"001-0001\",\"before_status\":\"tersedia\",\"after_status\":\"tersedia\",\"kondisi\":\"baik\"},{\"kode\":\"001-0002\",\"before_status\":\"tersedia\",\"after_status\":\"rusak\",\"kondisi\":\"baik\"}]}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 08:59:15'),
(409, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 10:01:12'),
(410, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 10:01:18'),
(411, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 10:03:02'),
(412, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:36:03'),
(413, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:36:07'),
(414, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:37:00'),
(415, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:37:09'),
(416, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:43:34'),
(417, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:43:36'),
(418, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:45:46'),
(419, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 11:45:53'),
(420, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 12:58:20'),
(421, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 12:58:22'),
(422, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 12:58:24'),
(423, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 13:53:51'),
(424, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 13:53:57'),
(425, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:27:34'),
(426, 1, 'CREATE_BACKUP', 'pemeliharaan', NULL, 'Membuat backup backup_20261001_213636.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:36:37'),
(427, 1, 'CREATE_BACKUP', 'pemeliharaan', NULL, 'Membuat backup backup_20261001_214355.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:43:55'),
(428, 1, 'CREATE_BACKUP', 'pemeliharaan', NULL, 'Membuat backup backup_20261001_214923.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:49:23'),
(429, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:53:23'),
(430, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:53:25'),
(431, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:53:37'),
(432, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:53:45'),
(433, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:09:28'),
(434, 1, 'MULAI_SESI_TESTING', 'pemeliharaan', NULL, 'Lingkungan pengujian dibersihkan; tabel: 30; baris dihapus: 47; safety backup: backup_20261002_052052.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:20:52'),
(435, 1, 'MULAI_SESI_TESTING', 'pemeliharaan', NULL, 'Lingkungan pengujian dibersihkan; tabel: 30; baris dihapus: 0; safety backup: backup_20261002_052336.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:23:36'),
(436, 1, 'MULAI_SESI_TESTING', 'pemeliharaan', NULL, 'Lingkungan pengujian dibersihkan; tabel: 30; baris dihapus: 10; safety backup: backup_20261002_052628.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:26:29'),
(437, 1, 'DESTRUCTIVE_REAUTH_FAILED', 'pemeliharaan', NULL, 'Verifikasi ulang Administrator untuk operasi data sensitif gagal (percobaan 1).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:51:57'),
(438, 1, 'DESTRUCTIVE_REAUTH_SUCCESS', 'pemeliharaan', NULL, 'Verifikasi ulang Administrator berhasil; operasi data sensitif dibuka selama 300 detik.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 22:52:14'),
(439, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 01:32:04'),
(440, 1, 'IMPORT', 'siswa', 0, 'Impor 5 siswa dari XLSX; ditempatkan 5, belum ditempatkan 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 01:41:48'),
(441, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 01:49:21'),
(442, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 03:46:41'),
(443, 1, 'UPDATE', 'perpustakaan', 1, 'Memperbarui status eksemplar | PERUBAHAN={\"before\":[],\"after\":{\"changes\":[{\"kode\":\"001-0001\",\"before_status\":\"tersedia\",\"after_status\":\"tersedia\",\"kondisi\":\"baik\"},{\"kode\":\"001-0002\",\"before_status\":\"rusak\",\"after_status\":\"tersedia\",\"kondisi\":\"baik\"}]}}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 03:54:14'),
(444, 1, 'CREATE_JENIS', 'pelanggaran', 1, 'Menambahkan jenis pelanggaran Bolos', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 03:55:32'),
(445, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:09:27'),
(446, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:09:50'),
(447, 1, 'SCAN', 'absensi', 22, 'Scan siswa 10005 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:28'),
(448, 1, 'SCAN', 'absensi', 23, 'Scan siswa 10004 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:31'),
(449, 1, 'SCAN', 'absensi', 24, 'Scan siswa 10009 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:36'),
(450, 1, 'SCAN', 'absensi', 25, 'Scan siswa 10010 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:43'),
(451, 1, 'SCAN', 'absensi', 26, 'Scan siswa 10007 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:45'),
(452, 1, 'SCAN', 'absensi', 27, 'Scan siswa 10006 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:48'),
(453, 1, 'SCAN', 'absensi', 28, 'Scan siswa 10001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:50');
INSERT INTO `audit_log` (`id`, `user_id`, `aksi`, `modul`, `referensi_id`, `keterangan`, `ip_address`, `user_agent`, `created_at`) VALUES
(454, 1, 'SCAN', 'absensi', 29, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:10:52'),
(455, 1, 'SCAN', 'absensi', 30, 'Scan siswa 10002 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:11:32'),
(456, 1, 'SCAN', 'absensi', 31, 'Scan siswa 10008 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:11:51'),
(457, 1, 'SCAN_SHOLAT', 'absensi', 8, 'Scan sholat 10001 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:15:59'),
(458, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:18:07'),
(459, 1, 'CREATE', 'perpustakaan', 4, 'Mencatat kunjungan perpustakaan: JOHAN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:20:33'),
(460, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 5, 'Siswa masuk antrian layanan UKS 10010 melalui USB.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:21:51'),
(461, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 6, 'Siswa masuk antrian layanan UKS 10001 melalui QR.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:22:21'),
(462, 1, 'layanan_queue_create', 'bk', 5, 'Siswa masuk antrian Layanan BK: 10002', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:22:41'),
(463, 1, 'layanan_queue_create', 'bk', 6, 'Siswa masuk antrian Layanan BK: 10001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:22:47'),
(464, 1, 'layanan_queue_create', 'bk', 7, 'Siswa masuk antrian Layanan BK: 10003', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:23:01'),
(465, 1, 'DESTRUCTIVE_REAUTH_SUCCESS', 'pemeliharaan', NULL, 'Verifikasi ulang Administrator berhasil; operasi data sensitif dibuka selama 300 detik.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:27:28'),
(466, 1, 'MULAI_SESI_TESTING', 'pemeliharaan', NULL, 'Lingkungan pengujian dibersihkan; tabel: 30; baris dihapus: 34; safety backup: backup_20261002_112742.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:27:42'),
(467, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:28:51'),
(468, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:29:14'),
(469, 1, 'SCAN', 'absensi', 32, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:12'),
(470, 1, 'SCAN', 'absensi', 33, 'Scan siswa 10002 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:14'),
(471, 1, 'SCAN', 'absensi', 34, 'Scan siswa 10001 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:16'),
(472, 1, 'SCAN', 'absensi', 35, 'Scan siswa 10010 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:18'),
(473, 1, 'SCAN', 'absensi', 36, 'Scan siswa 10009 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:21'),
(474, 1, 'SCAN', 'absensi', 37, 'Scan siswa 10008 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:23'),
(475, 1, 'SCAN', 'absensi', 38, 'Scan siswa 10007 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:25'),
(476, 1, 'SCAN', 'absensi', 39, 'Scan siswa 10006 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:28'),
(477, 1, 'SCAN', 'absensi', 40, 'Scan siswa 10005 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:30'),
(478, 1, 'SCAN', 'absensi', 41, 'Scan siswa 10004 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:30:56'),
(479, 1, 'CREATE', 'perpustakaan', 5, 'Mencatat kunjungan perpustakaan: HENDRIK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:32:53'),
(480, 1, 'SCAN_SHOLAT', 'absensi', 9, 'Scan sholat 10004 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:35:13'),
(481, 1, 'SCAN_SHOLAT', 'absensi', 10, 'Scan sholat 10005 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:35:16'),
(482, 1, 'SCAN_SHOLAT', 'absensi', 11, 'Scan sholat 10006 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:35:18'),
(483, 1, 'SCAN_SHOLAT', 'absensi', 12, 'Scan sholat 10007 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:35:21'),
(484, 1, 'SCAN_SHOLAT', 'absensi', 13, 'Scan sholat 10008 pada sesi Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:35:23'),
(485, 1, 'layanan_queue_create', 'bk', 8, 'Siswa masuk antrian Layanan BK: 10008', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:10'),
(486, 1, 'layanan_queue_create', 'bk', 9, 'Siswa masuk antrian Layanan BK: 10007', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:12'),
(487, 1, 'layanan_queue_create', 'bk', 10, 'Siswa masuk antrian Layanan BK: 10006', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:14'),
(488, 1, 'layanan_queue_create', 'bk', 11, 'Siswa masuk antrian Layanan BK: 10005', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:17'),
(489, 1, 'layanan_start', 'bk', 8, 'Memulai layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:27'),
(490, 1, 'layanan_start', 'bk', 9, 'Memulai layanan BK', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:40:36'),
(491, 1, 'CREATE', 'pelanggaran', 1, 'Mencatat pelanggaran siswa #47 dengan poin +5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:41:11'),
(492, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 7, 'Siswa masuk antrian layanan UKS 10009 melalui USB.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:41:37'),
(493, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 8, 'Siswa masuk antrian layanan UKS 10010 melalui USB.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:41:40'),
(494, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:42:56'),
(495, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:42:57'),
(496, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 04:45:51'),
(497, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 09:11:39'),
(498, 1, 'DESTRUCTIVE_REAUTH_SUCCESS', 'pemeliharaan', NULL, 'Verifikasi ulang Administrator berhasil; operasi data sensitif dibuka selama 300 detik.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 09:12:51'),
(499, 1, 'MULAI_SESI_TESTING', 'pemeliharaan', NULL, 'Lingkungan pengujian dibersihkan; tabel: 30; baris dihapus: 40; safety backup: backup_20261002_161308.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 09:13:08'),
(500, 1, 'CREATE', 'perpustakaan', 6, 'Mencatat kunjungan perpustakaan: BRANDON', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 09:39:54'),
(501, 1, 'CREATE', 'perpustakaan', 7, 'Mencatat kunjungan perpustakaan: FARID', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:00:32'),
(502, 1, 'CREATE', 'perpustakaan', 8, 'Mencatat kunjungan perpustakaan: ISABEL', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:00:41'),
(503, 1, 'CREATE', 'perpustakaan', 9, 'Mencatat kunjungan perpustakaan: JOHAN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:08:55'),
(504, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 9, 'Siswa masuk antrian layanan UKS 10010 melalui USB.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:11:00'),
(505, 1, 'QUEUE_KUNJUNGAN_UKS', 'uks', 10, 'Siswa masuk antrian layanan UKS 10008 melalui USB.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:11:05'),
(506, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:03:03'),
(507, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:03:04'),
(508, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:03:48'),
(509, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:03:54'),
(510, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:26:36'),
(511, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:26:37'),
(512, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:29:24'),
(513, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:29:31'),
(514, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:43:38'),
(515, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:43:40'),
(516, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:43:51'),
(517, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 11:43:58'),
(518, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:22:01'),
(519, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:22:02'),
(520, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:22:18'),
(521, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:22:28'),
(522, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:24:38'),
(523, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:24:39'),
(524, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:25:13'),
(525, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 16:25:21'),
(526, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:07:16'),
(527, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:07:17'),
(528, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:07:36'),
(529, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:07:41'),
(530, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:08:09'),
(531, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:08:19'),
(532, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:16:19'),
(533, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 17:16:20'),
(534, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:26:35'),
(535, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:26:50'),
(536, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:26:58'),
(537, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:31:36'),
(538, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:31:37'),
(539, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:33:34'),
(540, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 00:33:42'),
(541, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 03:09:00'),
(542, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 03:09:04'),
(543, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 03:09:11'),
(544, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 04:58:27'),
(545, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 04:58:28'),
(546, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:01:43'),
(547, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:01:50'),
(548, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:06:56'),
(549, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:06:57'),
(550, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:08:28'),
(551, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:08:35'),
(552, 1, 'DELETE_BACKUP', 'pemeliharaan', NULL, 'Menghapus file backup backup_20261001_214923.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:13:43'),
(553, 1, 'DELETE_BACKUP', 'pemeliharaan', NULL, 'Menghapus file backup backup_20261002_052052.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:13:46'),
(554, 1, 'DELETE_BACKUP', 'pemeliharaan', NULL, 'Menghapus file backup backup_20261002_052336.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:13:49'),
(555, 1, 'DELETE_BACKUP', 'pemeliharaan', NULL, 'Menghapus file backup backup_20261002_052628.zip', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:13:51'),
(556, 1, 'CREATE_USER', 'pengguna', 4, 'Membuat akun 10001 dengan role siswa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:49:02'),
(557, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:49:15'),
(558, 4, 'LOGIN', 'AUTH', 4, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:49:22'),
(559, 4, 'LOGOUT', 'AUTH', 4, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:50:07'),
(560, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:50:14'),
(561, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:50:19'),
(562, 1, 'CREATE_USER_SISWA', 'pengguna', 5, 'Membuat akun siswa 10008 dengan password awal NIS + *', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:54:11'),
(563, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:54:30'),
(564, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: 10001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:54:37'),
(565, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: 10001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:54:43'),
(566, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: 10001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:54:48'),
(567, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: 10002', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:55:07'),
(568, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:55:17'),
(569, 1, 'RESET_PASSWORD_SISWA', 'pengguna', 4, 'Reset password akun siswa 10001 ke password awal NIS + *', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:55:28'),
(570, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:55:33'),
(571, 4, 'LOGIN', 'AUTH', 4, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 05:55:40'),
(572, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 10:56:59'),
(573, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 10:58:03'),
(574, 4, 'LOGIN', 'AUTH', 4, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 10:58:15'),
(575, 4, 'LOGOUT', 'AUTH', 4, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 10:59:25'),
(576, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 10:59:37'),
(577, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 23:01:44'),
(578, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 23:01:50'),
(579, 1, 'UPDATE_AKUN_KEPALA_SEKOLAH', 'pengguna', 8, 'Pembaruan identitas/status akun Kepala Sekolah: Kepala Sekolah. Username tetap kepsek.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 23:27:03'),
(580, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 23:27:11'),
(581, 8, 'LOGIN', 'AUTH', 8, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-03 23:27:22'),
(582, 8, 'LOGOUT', 'AUTH', 8, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 00:18:46'),
(583, 8, 'LOGIN', 'AUTH', 8, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 01:55:34'),
(584, 8, 'LOGOUT', 'AUTH', 8, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 02:36:35'),
(585, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 02:36:43'),
(586, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 02:37:46'),
(587, 8, 'LOGIN', 'AUTH', 8, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 02:37:47'),
(588, 8, 'LOGOUT', 'AUTH', 8, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 03:03:58'),
(589, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 03:04:06'),
(590, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 04:09:06'),
(591, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:40:54'),
(592, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:44:10'),
(593, 4, 'LOGIN', 'AUTH', 4, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:44:25'),
(594, 4, 'LOGOUT', 'AUTH', 4, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:45:53'),
(595, 8, 'LOGIN', 'AUTH', 8, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:45:57'),
(596, 8, 'LOGOUT', 'AUTH', 8, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:46:18'),
(597, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 08:46:27'),
(598, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 09:57:36'),
(599, 1, 'UPDATE_JENIS', 'pelanggaran', 1, 'Memperbarui jenis pelanggaran Hamil atau menghamili', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:24:54'),
(600, 1, 'CREATE_JENIS', 'pelanggaran', 2, 'Menambahkan jenis pelanggaran Membawa sajam, mengedarkan, menyalah gunakan narkoba dan minuman keras', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:25:33'),
(601, 1, 'CREATE_JENIS', 'pelanggaran', 3, 'Menambahkan jenis pelanggaran Menganiaya guru dan karyawan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:26:16'),
(602, 1, 'CREATE_JENIS', 'pelanggaran', 4, 'Menambahkan jenis pelanggaran Bentuk pelanggaran apapun yang kasusnya sampai pada yang berwajib', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:27:00'),
(603, 1, 'CREATE_JENIS', 'pelanggaran', 5, 'Menambahkan jenis pelanggaran Berkelahi atau tawuran baik di dalam lingkungan sekolah ataupun diluar lingkungan sekolah selama masih mengenakan seragam sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:27:34'),
(604, 1, 'UPDATE_JENIS', 'pelanggaran', 5, 'Memperbarui jenis pelanggaran Berkelahi atau tawuran baik di dalam lingkungan sekolah ataupun diluar lingkungan sekolah selama masih mengenakan seragam sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:28:34'),
(605, 1, 'UPDATE_JENIS', 'pelanggaran', 5, 'Memperbarui jenis pelanggaran Berkelahi atau tawuran baik di dalam lingkungan sekolah ataupun diluar lingkungan sekolah selama masih mengenakan seragam sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:29:22'),
(606, 1, 'CREATE_JENIS', 'pelanggaran', 6, 'Menambahkan jenis pelanggaran Membawa gambar porno baik berbentuk print out maupun file di HP', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:29:43'),
(607, 1, 'CREATE_JENIS', 'pelanggaran', 7, 'Menambahkan jenis pelanggaran Melakukan pencurian', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:30:00'),
(608, 1, 'CREATE_JENIS', 'pelanggaran', 8, 'Menambahkan jenis pelanggaran Praktek preman, pemerasan, mengompas atau melakukan pemalakan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:30:25'),
(609, 1, 'UPDATE_JENIS', 'pelanggaran', 7, 'Memperbarui jenis pelanggaran Melakukan pencurian', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:30:42'),
(610, 1, 'UPDATE_JENIS', 'pelanggaran', 6, 'Memperbarui jenis pelanggaran Membawa gambar porno baik berbentuk print out maupun file di HP', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:30:56'),
(611, 1, 'CREATE_JENIS', 'pelanggaran', 9, 'Menambahkan jenis pelanggaran Pacaran di lingkungan sekolah dengan melakukan tindakan tidak senonoh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:31:16'),
(612, 1, 'CREATE_JENIS', 'pelanggaran', 10, 'Menambahkan jenis pelanggaran Pelecehan, menentang, mengumpat, maupun berkata kasar terhadap teman, guru dan karyawan', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:31:45'),
(613, 1, 'CREATE_JENIS', 'pelanggaran', 11, 'Menambahkan jenis pelanggaran Pengrusakan sarana dan prasarana sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:32:06'),
(614, 1, 'CREATE_JENIS', 'pelanggaran', 12, 'Menambahkan jenis pelanggaran Melakukan pemalsuan tanda tangan Kepala Sekolah, Guru dan Karyawan serta pemalsuan terhadap nilai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:32:26'),
(615, 1, 'CREATE_JENIS', 'pelanggaran', 13, 'Menambahkan jenis pelanggaran Bermain judi di lingkungan sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:32:51'),
(616, 1, 'CREATE_JENIS', 'pelanggaran', 14, 'Menambahkan jenis pelanggaran Memegang anggota tubuh pada bagian-bagian organ vital atau melakukan tindakan pelecehan terhadap lawan jenis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:33:12'),
(617, 1, 'CREATE_JENIS', 'pelanggaran', 15, 'Menambahkan jenis pelanggaran Merokok baik di lingkungan sekolah maupun di luar sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:33:37'),
(618, 1, 'CREATE_JENIS', 'pelanggaran', 16, 'Menambahkan jenis pelanggaran Melompat jendela ataupun pagar sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:34:05'),
(619, 1, 'CREATE_JENIS', 'pelanggaran', 17, 'Menambahkan jenis pelanggaran Bertato, beranting, bertindik ataupun rambut dimodel kliwir bagi peserta didik putra', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:34:26'),
(620, 1, 'CREATE_JENIS', 'pelanggaran', 18, 'Menambahkan jenis pelanggaran Rambut disemir dengan warna selain hitam', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:35:01'),
(621, 1, 'CREATE_JENIS', 'pelanggaran', 19, 'Menambahkan jenis pelanggaran Pacaran di lingkungan sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:35:25'),
(622, 1, 'CREATE_JENIS', 'pelanggaran', 20, 'Menambahkan jenis pelanggaran Membawa HP ke sekolah tanpa seizin wali kelas atau guru mapel', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:36:16'),
(623, 1, 'CREATE_JENIS', 'pelanggaran', 21, 'Menambahkan jenis pelanggaran Melakukan corat-coret di bangunan sekolah atau yang bukan pada tempatnya', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:37:02'),
(624, 1, 'UPDATE_JENIS', 'pelanggaran', 12, 'Memperbarui jenis pelanggaran Melakukan pemalsuan tanda tangan Kepala Sekolah, Guru dan Karyawan serta pemalsuan terhadap nilai', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:39:23'),
(625, 1, 'UPDATE_JENIS', 'pelanggaran', 13, 'Memperbarui jenis pelanggaran Bermain judi di lingkungan sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:39:41'),
(626, 1, 'UPDATE_JENIS', 'pelanggaran', 14, 'Memperbarui jenis pelanggaran Memegang anggota tubuh pada bagian-bagian organ vital atau melakukan tindakan pelecehan terhadap lawan jenis', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:40:18'),
(627, 1, 'UPDATE_JENIS', 'pelanggaran', 11, 'Memperbarui jenis pelanggaran Pengrusakan sarana dan prasarana sekolah', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:40:54'),
(628, 1, 'CREATE_JENIS', 'pelanggaran', 23, 'Menambahkan jenis pelanggaran Meninggalkan sekolah tanpa ijin (membolos), alpha lebih dari 3 hari', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:41:33'),
(629, 1, 'CREATE_JENIS', 'pelanggaran', 24, 'Menambahkan jenis pelanggaran Makan dan minum di ruang kelas ketika KBM berlangsung, berkata jorok (misuh)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:41:54'),
(630, 1, 'CREATE_JENIS', 'pelanggaran', 25, 'Menambahkan jenis pelanggaran Tidak mengikuti upacara tanpa ijin atau tanpa keterangan yang jelas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:42:14'),
(631, 1, 'CREATE_JENIS', 'pelanggaran', 26, 'Menambahkan jenis pelanggaran Tidak memakai topi saat mengikuti upacara bendera', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:42:43'),
(632, 1, 'CREATE_JENIS', 'pelanggaran', 27, 'Menambahkan jenis pelanggaran Tidak memakai seragam atau identitas sekolah secara lengkap dan benar', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:43:02'),
(633, 1, 'CREATE_JENIS', 'pelanggaran', 28, 'Menambahkan jenis pelanggaran Berambut panjang atau gondrong bagi peserta didik putra', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:43:22'),
(634, 1, 'CREATE_JENIS', 'pelanggaran', 29, 'Menambahkan jenis pelanggaran Memakai rok mini atau di atas lutut bagi peserta didik putri', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:43:38'),
(635, 1, 'CREATE_JENIS', 'pelanggaran', 30, 'Menambahkan jenis pelanggaran Tidak membawa pulang buku pelajaran dan meninggalkannya di sekolah dengan sengaja', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:43:58'),
(636, 1, 'CREATE_JENIS', 'pelanggaran', 31, 'Menambahkan jenis pelanggaran Bersolek dengan berlebihan misal memakai lipstik dan shadow', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:44:16'),
(637, 1, 'CREATE_JENIS', 'pelanggaran', 32, 'Menambahkan jenis pelanggaran Datang terlambat di sekolah dengan alasan yang tidak tepat, meninggalkan kelas tanpa ijin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:44:57'),
(638, 1, 'CREATE_JENIS', 'pelanggaran', 33, 'Menambahkan jenis pelanggaran Memakai kalung maupun gelang bagi semua peserta didik', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:45:14'),
(639, 1, 'CREATE_JENIS', 'pelanggaran', 34, 'Menambahkan jenis pelanggaran Memelihara kuku panjang, membawa peralatan make up kecuali sisir', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:45:35'),
(640, 1, 'CREATE_JENIS', 'pelanggaran', 35, 'Menambahkan jenis pelanggaran Memakai seragam sekolah tapi baju tidak dimasukkan ke dalam celana/rok', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:46:04'),
(641, 1, 'CREATE_JENIS', 'pelanggaran', 36, 'Menambahkan jenis pelanggaran Membuang sampah tidak pada tempatnya', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 10:46:23'),
(642, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:29:21'),
(643, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:29:25'),
(644, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:36:32'),
(645, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:36:39'),
(646, 1, 'CREATE', 'pelanggaran', 2, 'Mencatat pelanggaran siswa #47 dengan poin +25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:41:31'),
(647, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:41:39'),
(648, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:41:41'),
(649, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:43:29'),
(650, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:43:36'),
(651, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:50:51'),
(652, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:50:52'),
(653, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:54:10'),
(654, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 11:54:17'),
(655, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:02:42'),
(656, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:02:43'),
(657, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:04:12'),
(658, NULL, 'LOGIN_FAILED', 'AUTH', NULL, 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:04:20'),
(659, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:04:26'),
(660, 1, 'UPDATE_PERMISSION', 'pengguna', 2, 'Memperbarui hak akses akun guru4 (1 hak)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:06:40'),
(661, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:06:44'),
(662, 2, 'LOGIN', 'AUTH', 2, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:06:52'),
(663, 2, 'LOGOUT', 'AUTH', 2, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:07:33'),
(664, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:07:44'),
(665, 1, 'UPDATE_PERMISSION', 'pengguna', 2, 'Memperbarui hak akses akun guru4 (5 hak)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:08:19'),
(666, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:08:20'),
(667, 2, 'LOGIN', 'AUTH', 2, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:08:22'),
(668, 2, 'LOGOUT', 'AUTH', 2, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:08:43'),
(669, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:08:51'),
(670, 1, 'UPDATE_PERMISSION', 'pengguna', 3, 'Memperbarui hak akses akun guru1 (3 hak)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:10:16'),
(671, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:10:19'),
(672, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:10:22'),
(673, 3, 'LOGOUT', 'AUTH', 3, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:12:55'),
(674, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:13:02'),
(675, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:28:55'),
(676, 3, 'LOGIN', 'AUTH', 3, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-04 12:28:56');
INSERT INTO `audit_log` (`id`, `user_id`, `aksi`, `modul`, `referensi_id`, `keterangan`, `ip_address`, `user_agent`, `created_at`) VALUES
(677, 8, 'LOGIN', 'AUTH', 8, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-06 01:48:28'),
(678, 8, 'LOGOUT', 'AUTH', 8, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-06 01:48:48'),
(679, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-07 02:34:37'),
(680, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-07 02:57:38'),
(681, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-07 02:57:40'),
(682, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 03:57:09'),
(683, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:15:22'),
(684, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 1, 'Mengubah jadwal semester 1 hari 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:16:58'),
(685, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 1, 'Mengubah jenis sholat: DH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:13'),
(686, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 1, 'Mengubah jenis sholat: DZUHUR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:24'),
(687, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 6, 'Mengubah jenis sholat: ASHAR', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:32'),
(688, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 3, 'Mengubah jenis sholat: MAGHRIB', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:40'),
(689, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 4, 'Mengubah jenis sholat: ISYA\'', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:50'),
(690, 1, 'UPDATE_JENIS_SHOLAT', 'pengaturan_absensi', 5, 'Mengubah jenis sholat: SUBUH', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:18:57'),
(691, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:22:12'),
(692, 1, 'UPDATE_JADWAL_SHOLAT', 'pengaturan_absensi', 4, 'Menyimpan jadwal sholat semester 1: Sholat Dzuhur', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:25:44'),
(693, 1, 'LOGOUT', 'AUTH', 1, 'User logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 05:26:18'),
(694, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 22:36:55'),
(695, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 22:39:53'),
(696, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 22:53:11'),
(697, 1, 'SCAN', 'absensi', 42, 'Scan siswa 10003 pada sesi Absensi Kehadiran', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-08 22:54:21'),
(698, 1, 'LOGIN', 'AUTH', 1, 'User berhasil login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-09 04:40:50'),
(699, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-09 04:48:32'),
(700, 1, 'UPDATE_JADWAL_KEHADIRAN', 'pengaturan_absensi', 5, 'Mengubah jadwal semester 1 hari 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-09 04:49:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bk_kunjungan`
--

CREATE TABLE `bk_kunjungan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `kelas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu_kunjungan` time NOT NULL,
  `jenis_kunjungan` enum('konseling','konsultasi','rujukan','administratif','lainnya') NOT NULL DEFAULT 'konseling',
  `keterangan_singkat` varchar(255) DEFAULT NULL,
  `metode` enum('QR','USB','MANUAL') NOT NULL DEFAULT 'QR',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `bk_layanan`
--

CREATE TABLE `bk_layanan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `kelas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `nomor_antrian` int(10) UNSIGNED NOT NULL,
  `waktu_masuk` datetime NOT NULL,
  `status` enum('menunggu','dilayani','selesai','batal') NOT NULL DEFAULT 'menunggu',
  `jenis_layanan` enum('konseling','konsultasi','rujukan','administratif','lainnya') DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `topik` varchar(190) DEFAULT NULL,
  `permasalahan` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `kesepakatan` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `melibatkan_ortu` tinyint(1) NOT NULL DEFAULT 0,
  `metode_masuk` enum('QR','USB','MANUAL') NOT NULL DEFAULT 'QR',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `catatan_bk`
--

CREATE TABLE `catatan_bk` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `jenis` varchar(100) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status` enum('baru','proses','selesai') NOT NULL DEFAULT 'baru',
  `guru_bk_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstrakurikuler`
--

CREATE TABLE `ekstrakurikuler` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `pembina_id` bigint(20) UNSIGNED DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ekstrakurikuler`
--

INSERT INTO `ekstrakurikuler` (`id`, `kode`, `nama`, `pembina_id`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'PRAMUKA', 'Pramuka', NULL, 1, NULL, '2026-09-26 01:21:10', '2026-09-26 01:21:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_absensi`
--

CREATE TABLE `ekstra_absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('H','T','I','S','A') NOT NULL DEFAULT 'H',
  `waktu_scan` datetime DEFAULT NULL,
  `scan_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `metode` enum('QR','MANUAL') NOT NULL DEFAULT 'QR',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ekstra_absensi`
--

INSERT INTO `ekstra_absensi` (`id`, `sesi_id`, `siswa_id`, `status`, `waktu_scan`, `scan_oleh`, `metode`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, 5, 54, 'H', '2026-10-02 17:00:10', 1, 'QR', NULL, '2026-10-02 10:00:10', '2026-10-02 10:00:10'),
(3, 5, 47, 'H', '2026-10-02 17:00:13', 1, 'QR', NULL, '2026-10-02 10:00:13', '2026-10-02 10:00:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_anggota`
--

CREATE TABLE `ekstra_anggota` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ekstrakurikuler_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `tanggal_keluar` date DEFAULT NULL,
  `status` enum('aktif','nonaktif','keluar') NOT NULL DEFAULT 'aktif',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ekstra_anggota`
--

INSERT INTO `ekstra_anggota` (`id`, `ekstrakurikuler_id`, `siswa_id`, `tahun_pelajaran_id`, `tanggal_masuk`, `tanggal_keluar`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(5, 1, 47, 1, '2026-10-02', NULL, 'aktif', NULL, '2026-10-02 09:40:57', '2026-10-02 09:40:57'),
(6, 1, 54, 1, '2026-10-02', NULL, 'aktif', NULL, '2026-10-02 09:40:57', '2026-10-02 09:40:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_dokumentasi`
--

CREATE TABLE `ekstra_dokumentasi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_jadwal`
--

CREATE TABLE `ekstra_jadwal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ekstrakurikuler_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `tempat` varchar(150) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ekstra_jadwal`
--

INSERT INTO `ekstra_jadwal` (`id`, `ekstrakurikuler_id`, `tahun_pelajaran_id`, `hari`, `jam_mulai`, `jam_selesai`, `tempat`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(3, 1, 1, 'Jumat', '17:00:00', '17:05:00', 'Lapangan', 1, NULL, '2026-10-01 10:35:34', '2026-10-02 09:58:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_jurnal`
--

CREATE TABLE `ekstra_jurnal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `tujuan` text DEFAULT NULL,
  `materi` text DEFAULT NULL,
  `kegiatan` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `kendala` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_jurnal_kendala`
--

CREATE TABLE `ekstra_jurnal_kendala` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED NOT NULL,
  `kendala` text NOT NULL,
  `tindakan` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_pembina_absensi`
--

CREATE TABLE `ekstra_pembina_absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ekstrakurikuler_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `guru_id` bigint(20) UNSIGNED NOT NULL,
  `sesi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `status` enum('H','I','S','A') NOT NULL DEFAULT 'H',
  `waktu` time DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `dicatat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_penilaian`
--

CREATE TABLE `ekstra_penilaian` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ekstra_anggota_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `keaktifan` decimal(5,2) DEFAULT NULL,
  `kedisiplinan` decimal(5,2) DEFAULT NULL,
  `keterampilan` decimal(5,2) DEFAULT NULL,
  `kerjasama` decimal(5,2) DEFAULT NULL,
  `prestasi` decimal(5,2) DEFAULT NULL,
  `nilai_akhir` decimal(5,2) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `dinilai_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ekstra_sesi`
--

CREATE TABLE `ekstra_sesi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ekstrakurikuler_id` bigint(20) UNSIGNED NOT NULL,
  `jadwal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `materi` varchar(255) DEFAULT NULL,
  `status` enum('draft','aktif','ditutup','dibatalkan') NOT NULL DEFAULT 'draft',
  `dibuka_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `dibuka_at` datetime DEFAULT NULL,
  `ditutup_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `ditutup_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ekstra_sesi`
--

INSERT INTO `ekstra_sesi` (`id`, `ekstrakurikuler_id`, `jadwal_id`, `tahun_pelajaran_id`, `semester_id`, `tanggal`, `jam_mulai`, `jam_selesai`, `materi`, `status`, `dibuka_oleh`, `dibuka_at`, `ditutup_oleh`, `ditutup_at`, `created_at`, `updated_at`) VALUES
(5, 1, 3, 1, 1, '2026-10-02', '17:00:00', '17:05:00', NULL, 'ditutup', 1, '2026-10-02 17:00:03', 1, '2026-10-02 17:06:44', '2026-10-02 09:41:09', '2026-10-02 10:06:44');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `jk` enum('L','P') DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `kode_qr` varchar(100) NOT NULL,
  `mapel` varchar(100) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `nama`, `nip`, `jk`, `no_hp`, `kode_qr`, `mapel`, `status`, `created_at`, `updated_at`) VALUES
(1, 'guru1', '19800101001', 'L', '81234567890', 'GURU-FC9094097B5C', 'Matematika', 'aktif', '2026-09-22 10:19:09', '2026-09-22 10:19:09'),
(2, 'guru2', '19800101002', 'P', '81234567891', 'GURU-B007F85A29D4', 'Bahasa Indonesia', 'aktif', '2026-09-22 10:19:09', '2026-09-22 10:19:09'),
(3, 'guru3', '19800101003', 'L', '81234567892', 'GURU-EA271B99B363', 'IPA', 'aktif', '2026-09-22 10:19:09', '2026-09-22 10:19:09'),
(4, 'guru4', '19800101004', 'P', '81234567893', 'GURU-84AB407DED44', 'BK', 'aktif', '2026-09-22 10:19:09', '2026-09-22 10:19:09'),
(5, 'guru5', '19800101005', 'L', '81234567894', 'GURU-FD32C5D40D66', 'IPS', 'aktif', '2026-09-22 10:19:09', '2026-09-22 10:19:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hak_akses`
--

CREATE TABLE `hak_akses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `modul` varchar(100) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `hak_akses`
--

INSERT INTO `hak_akses` (`id`, `kode`, `nama`, `modul`, `aktif`, `created_at`) VALUES
(1, 'dashboard', 'Dashboard', 'Dashboard', 1, '2026-09-13 11:25:22'),
(2, 'master_data', 'Master Data', 'Master Data', 1, '2026-09-13 11:25:22'),
(3, 'siswa', 'Data Siswa', 'Siswa', 1, '2026-09-13 11:25:22'),
(4, 'guru', 'Data Guru', 'Guru', 1, '2026-09-13 11:25:22'),
(5, 'kelas', 'Data Kelas', 'Kelas', 1, '2026-09-13 11:25:22'),
(6, 'absensi', 'Absensi', 'Absensi', 1, '2026-09-13 11:25:22'),
(7, 'sholat', 'Absensi Sholat', 'Sholat', 1, '2026-09-13 11:25:22'),
(8, 'ekstrakurikuler', 'Ekstrakurikuler', 'Ekstrakurikuler', 1, '2026-09-13 11:25:22'),
(9, 'bk', 'Bimbingan Konseling', 'BK', 1, '2026-09-13 11:25:22'),
(10, 'pelanggaran', 'Pelanggaran', 'Kesiswaan', 1, '2026-09-13 11:25:22'),
(11, 'pembinaan', 'Pembinaan', 'Kesiswaan', 1, '2026-09-13 11:25:22'),
(12, 'home_visit', 'Home Visit', 'Kesiswaan', 1, '2026-09-13 11:25:22'),
(13, 'perpustakaan', 'Perpustakaan', 'Perpustakaan', 1, '2026-09-13 11:25:22'),
(14, 'uks', 'UKS', 'UKS', 1, '2026-09-13 11:25:22'),
(15, 'laporan', 'Laporan', 'Laporan', 1, '2026-09-13 11:25:22'),
(16, 'tugas_guru', 'Tugas Guru', 'Pengguna', 1, '2026-09-13 11:25:22'),
(17, 'pengguna', 'Pengguna', 'Pengguna', 1, '2026-09-13 11:25:22'),
(18, 'audit_log', 'Audit Log', 'Sistem', 1, '2026-09-13 11:25:22'),
(19, 'pengaturan', 'Pengaturan', 'Sistem', 1, '2026-09-13 11:25:22'),
(20, 'izin_siswa', 'Izin Siswa', 'Kesiswaan', 1, '2026-09-26 00:49:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `home_visit`
--

CREATE TABLE `home_visit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal_rencana` date DEFAULT NULL,
  `tanggal_pelaksanaan` date DEFAULT NULL,
  `waktu` time DEFAULT NULL,
  `alasan` text DEFAULT NULL,
  `tujuan` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status` enum('direncanakan','dilaksanakan','ditunda','dibatalkan') NOT NULL DEFAULT 'direncanakan',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `izin_siswa`
--

CREATE TABLE `izin_siswa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `kelas_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('PULANG_LEBIH_AWAL','KELUAR_SEMENTARA') NOT NULL DEFAULT 'PULANG_LEBIH_AWAL',
  `jam_pengajuan` datetime NOT NULL DEFAULT current_timestamp(),
  `jam_keluar` datetime DEFAULT NULL,
  `alasan` varchar(150) NOT NULL,
  `keterangan` varchar(500) DEFAULT NULL,
  `status` enum('DIAJUKAN','DISETUJUI','DITOLAK','SELESAI') NOT NULL DEFAULT 'DIAJUKAN',
  `diajukan_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `disetujui_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `disetujui_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kehadiran`
--

CREATE TABLE `jadwal_kehadiran` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `hari` tinyint(3) UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
  `aktif` tinyint(1) NOT NULL DEFAULT 0,
  `jam_mulai` time NOT NULL DEFAULT '06:30:00',
  `jam_selesai` time NOT NULL DEFAULT '09:00:00',
  `batas_terlambat` time DEFAULT NULL,
  `nama_sesi` varchar(150) NOT NULL DEFAULT 'Absensi Kehadiran',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_kehadiran`
--

INSERT INTO `jadwal_kehadiran` (`id`, `tahun_pelajaran_id`, `semester_id`, `hari`, `aktif`, `jam_mulai`, `jam_selesai`, `batas_terlambat`, `nama_sesi`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, '06:30:00', '14:30:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-10-08 05:16:58'),
(2, 1, 1, 2, 1, '06:30:00', '14:30:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-09-28 10:05:02'),
(3, 1, 1, 3, 1, '09:05:00', '14:30:00', '09:06:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-09-30 02:02:25'),
(4, 1, 1, 4, 1, '06:30:00', '14:30:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-09-28 10:05:02'),
(5, 1, 1, 5, 1, '05:54:00', '11:15:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-10-09 04:49:38'),
(6, 1, 1, 6, 0, '06:30:00', '09:00:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-09-28 10:05:02'),
(7, 1, 1, 7, 0, '06:30:00', '09:00:00', '07:00:00', 'Absensi Kehadiran', '2026-09-16 01:37:08', '2026-09-28 10:05:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_sholat`
--

CREATE TABLE `jadwal_sholat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `jenis_sholat_id` bigint(20) UNSIGNED NOT NULL,
  `hari` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Tidak digunakan lagi; jadwal berlaku pada semua hari efektif sekolah',
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `nama_sesi` varchar(150) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_sholat`
--

INSERT INTO `jadwal_sholat` (`id`, `tahun_pelajaran_id`, `semester_id`, `jenis_sholat_id`, `hari`, `jam_mulai`, `jam_selesai`, `nama_sesi`, `aktif`, `created_at`, `updated_at`) VALUES
(3, 1, 1, 6, NULL, '14:30:00', '16:30:00', 'Sholat Ashar', 0, '2026-09-16 07:23:14', '2026-09-28 11:12:42'),
(4, 1, 1, 1, NULL, '11:35:00', '13:00:00', 'Sholat Dzuhur', 1, '2026-09-28 10:06:18', '2026-10-08 05:22:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_sholat_arsip_batch11`
--

CREATE TABLE `jadwal_sholat_arsip_batch11` (
  `arsip_id` bigint(20) UNSIGNED NOT NULL,
  `jadwal_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `jenis_sholat_id` bigint(20) UNSIGNED NOT NULL,
  `hari` tinyint(3) UNSIGNED DEFAULT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `nama_sesi` varchar(150) NOT NULL,
  `aktif` tinyint(1) NOT NULL,
  `alasan` varchar(255) NOT NULL,
  `diarsipkan_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_sholat_arsip_batch11`
--

INSERT INTO `jadwal_sholat_arsip_batch11` (`arsip_id`, `jadwal_id`, `tahun_pelajaran_id`, `semester_id`, `jenis_sholat_id`, `hari`, `jam_mulai`, `jam_selesai`, `nama_sesi`, `aktif`, `alasan`, `diarsipkan_at`) VALUES
(1, 1, 1, 1, 1, 1, '13:50:00', '14:15:00', 'Sholat Dzuhur', 1, 'Duplikat jadwal sholat pada semester+jenis; konfigurasi terbaru dipertahankan.', '2026-09-28 17:15:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_absensi`
--

CREATE TABLE `jenis_absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` enum('kehadiran','sholat') NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_absensi`
--

INSERT INTO `jenis_absensi` (`id`, `kode`, `nama`, `kategori`, `aktif`, `urutan`, `created_at`) VALUES
(1, 'MASUK', 'Absen Masuk', 'kehadiran', 1, 1, '2026-09-13 11:27:24'),
(2, 'PULANG', 'Absen Pulang', 'kehadiran', 0, 2, '2026-09-13 11:27:24'),
(3, 'SHOLAT', 'Absensi Sholat', 'sholat', 1, 3, '2026-09-13 11:27:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pelanggaran`
--

CREATE TABLE `jenis_pelanggaran` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `poin` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_pelanggaran`
--

INSERT INTO `jenis_pelanggaran` (`id`, `kode`, `nama`, `kategori`, `poin`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, '101', 'Hamil atau menghamili', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 100, 1, NULL, '2026-10-02 03:55:32', '2026-10-04 10:24:54'),
(2, '102', 'Membawa sajam, mengedarkan, menyalah gunakan narkoba dan minuman keras', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 100, 1, NULL, '2026-10-04 10:25:33', '2026-10-04 10:25:33'),
(3, '103', 'Menganiaya guru dan karyawan', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 100, 1, NULL, '2026-10-04 10:26:16', '2026-10-04 10:26:16'),
(4, '104', 'Bentuk pelanggaran apapun yang kasusnya sampai pada yang berwajib', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 100, 1, NULL, '2026-10-04 10:27:00', '2026-10-04 10:27:00'),
(5, '201', 'Berkelahi atau tawuran baik di dalam lingkungan sekolah ataupun diluar lingkungan sekolah selama masih mengenakan seragam sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 75, 1, NULL, '2026-10-04 10:27:34', '2026-10-04 10:29:22'),
(6, '202', 'Membawa gambar porno baik berbentuk print out maupun file di HP', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 75, 1, NULL, '2026-10-04 10:29:43', '2026-10-04 10:30:56'),
(7, '203', 'Melakukan pencurian', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 75, 1, NULL, '2026-10-04 10:30:00', '2026-10-04 10:30:42'),
(8, '204', 'Praktek preman, pemerasan, mengompas atau melakukan pemalakan', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 75, 1, NULL, '2026-10-04 10:30:25', '2026-10-04 10:30:25'),
(9, '205', 'Pacaran di lingkungan sekolah dengan melakukan tindakan tidak senonoh', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 75, 1, NULL, '2026-10-04 10:31:16', '2026-10-04 10:31:16'),
(10, '301', 'Pelecehan, menentang, mengumpat, maupun berkata kasar terhadap teman, guru dan karyawan', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 50, 1, NULL, '2026-10-04 10:31:45', '2026-10-04 10:31:45'),
(11, '302', 'Pengrusakan sarana dan prasarana sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 50, 1, NULL, '2026-10-04 10:32:06', '2026-10-04 10:40:54'),
(12, '303', 'Melakukan pemalsuan tanda tangan Kepala Sekolah, Guru dan Karyawan serta pemalsuan terhadap nilai', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 50, 1, NULL, '2026-10-04 10:32:26', '2026-10-04 10:39:23'),
(13, '304', 'Bermain judi di lingkungan sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 50, 1, NULL, '2026-10-04 10:32:51', '2026-10-04 10:39:41'),
(14, '305', 'Memegang anggota tubuh pada bagian-bagian organ vital atau melakukan tindakan pelecehan terhadap lawan jenis', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 50, 1, NULL, '2026-10-04 10:33:12', '2026-10-04 10:40:18'),
(15, '401', 'Merokok baik di lingkungan sekolah maupun di luar sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:33:37', '2026-10-04 10:33:37'),
(16, '402', 'Melompat jendela ataupun pagar sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:34:05', '2026-10-04 10:34:05'),
(17, '403', 'Bertato, beranting, bertindik ataupun rambut dimodel kliwir bagi peserta didik putra', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:34:26', '2026-10-04 10:34:26'),
(18, '404', 'Rambut disemir dengan warna selain hitam', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:35:01', '2026-10-04 10:35:01'),
(19, '405', 'Pacaran di lingkungan sekolah', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:35:25', '2026-10-04 10:35:25'),
(20, '406', 'Membawa HP ke sekolah tanpa seizin wali kelas atau guru mapel', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 25, 1, NULL, '2026-10-04 10:36:16', '2026-10-04 10:36:16'),
(21, '501', 'Melakukan corat-coret di bangunan sekolah atau yang bukan pada tempatnya', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 15, 1, NULL, '2026-10-04 10:37:02', '2026-10-04 10:37:02'),
(23, '502', 'Meninggalkan sekolah tanpa ijin (membolos), alpha lebih dari 3 hari', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 15, 1, NULL, '2026-10-04 10:41:33', '2026-10-04 10:41:33'),
(24, '503', 'Makan dan minum di ruang kelas ketika KBM berlangsung, berkata jorok (misuh)', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 15, 1, NULL, '2026-10-04 10:41:54', '2026-10-04 10:41:54'),
(25, '504', 'Tidak mengikuti upacara tanpa ijin atau tanpa keterangan yang jelas', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 15, 1, NULL, '2026-10-04 10:42:14', '2026-10-04 10:42:14'),
(26, '601', 'Tidak memakai topi saat mengikuti upacara bendera', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:42:43', '2026-10-04 10:42:43'),
(27, '602', 'Tidak memakai seragam atau identitas sekolah secara lengkap dan benar', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:43:02', '2026-10-04 10:43:02'),
(28, '603', 'Berambut panjang atau gondrong bagi peserta didik putra', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:43:22', '2026-10-04 10:43:22'),
(29, '604', 'Memakai rok mini atau di atas lutut bagi peserta didik putri', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:43:38', '2026-10-04 10:43:38'),
(30, '605', 'Tidak membawa pulang buku pelajaran dan meninggalkannya di sekolah dengan sengaja', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:43:58', '2026-10-04 10:43:58'),
(31, '606', 'Bersolek dengan berlebihan misal memakai lipstik dan shadow', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 10, 1, NULL, '2026-10-04 10:44:16', '2026-10-04 10:44:16'),
(32, '701', 'Datang terlambat di sekolah dengan alasan yang tidak tepat, meninggalkan kelas tanpa ijin', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 5, 1, NULL, '2026-10-04 10:44:57', '2026-10-04 10:44:57'),
(33, '702', 'Memakai kalung maupun gelang bagi semua peserta didik', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 5, 1, NULL, '2026-10-04 10:45:14', '2026-10-04 10:45:14'),
(34, '703', 'Memelihara kuku panjang, membawa peralatan make up kecuali sisir', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 5, 1, NULL, '2026-10-04 10:45:35', '2026-10-04 10:45:35'),
(35, '704', 'Memakai seragam sekolah tapi baju tidak dimasukkan ke dalam celana/rok', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 5, 1, NULL, '2026-10-04 10:46:04', '2026-10-04 10:46:04'),
(36, '705', 'Membuang sampah tidak pada tempatnya', 'KELAKUAN, KERAJINAN DAN KERAPIAN', 5, 1, NULL, '2026-10-04 10:46:23', '2026-10-04 10:46:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_sholat`
--

CREATE TABLE `jenis_sholat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(50) NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_sholat`
--

INSERT INTO `jenis_sholat` (`id`, `nama`, `urutan`, `aktif`, `created_at`) VALUES
(1, 'DZUHUR', 1, 1, '2026-09-13 11:27:24'),
(3, 'MAGHRIB', 3, 0, '2026-09-13 11:27:24'),
(4, 'ISYA\'', 4, 0, '2026-09-13 11:27:24'),
(5, 'SUBUH', 5, 0, '2026-09-13 11:27:24'),
(6, 'ASHAR', 2, 1, '2026-09-16 07:02:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_tugas`
--

CREATE TABLE `jenis_tugas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_tugas`
--

INSERT INTO `jenis_tugas` (`id`, `kode`, `nama`, `kategori`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'WALI_KELAS', 'Wali Kelas', 'Kesiswaan', 1, 'Penanggung jawab utama kelas', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(2, 'GURU_BK', 'Guru BK', 'Kesiswaan', 0, 'Tidak digunakan untuk penugasan baru. Guru BK ditentukan melalui users.role=guru_bk. Record lama dipertahankan untuk histori.', '2026-09-13 11:25:22', '2026-09-26 01:15:19'),
(3, 'PEMBINA_EKSTRA', 'Pembina Ekstrakurikuler', 'Kesiswaan', 1, 'Pembina kegiatan ekstrakurikuler', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(4, 'KOORDINATOR_LAB', 'Koordinator Laboratorium', 'Sarpras', 1, 'Koordinator laboratorium', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(5, 'KEPALA_LAB', 'Kepala Laboratorium', 'Sarpras', 1, 'Penanggung jawab laboratorium', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(6, 'PEMBINA_OSIS', 'Pembina OSIS', 'Kesiswaan', 1, 'Pembina organisasi siswa', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(7, 'PEMBINA_PRAMUKA', 'Pembina Pramuka', 'Kesiswaan', 1, 'Pembina kegiatan Pramuka', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(8, 'PEMBINA_PMR', 'Pembina PMR', 'Kesiswaan', 1, 'Pembina kegiatan PMR', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(9, 'PEMBINA_BASKET', 'Pembina Basket', 'Kesiswaan', 1, 'Pembina kegiatan Basket', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(10, 'PEMBINA_FUTSAL', 'Pembina Futsal', 'Kesiswaan', 1, 'Pembina kegiatan Futsal', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(11, 'KOORDINATOR_KEGIATAN', 'Koordinator Kegiatan', 'Kesiswaan', 1, 'Koordinator kegiatan sekolah', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(12, 'TUGAS_LAIN', 'Tugas Lain', 'Lainnya', 1, 'Tugas tambahan lain yang dapat ditambahkan admin', '2026-09-13 11:25:22', '2026-09-13 11:25:22'),
(13, 'PETUGAS_UKS', 'Petugas UKS', 'Layanan', 1, 'Penanggung jawab operasional UKS dan stok obat', '2026-09-22 05:58:28', '2026-09-22 05:58:28'),
(14, 'PETUGAS_PIKET', 'Petugas Piket', 'Kesiswaan', 1, 'Guru yang bertugas melakukan verifikasi kehadiran siswa di kelas', '2026-09-25 14:58:05', '2026-09-25 14:58:05'),
(15, 'KOORDINATOR_EKSTRA', 'Koordinator Ekstrakurikuler', 'Kesiswaan', 1, 'Koordinator kegiatan ekstrakurikuler berdasarkan Tapel dan kegiatan.', '2026-09-26 01:15:19', '2026-09-26 01:15:19'),
(16, 'GURU_PIKET', 'Guru Piket', 'Kesiswaan', 1, 'Guru yang ditugaskan melakukan operasional piket.', '2026-09-26 01:15:19', '2026-09-26 01:15:19'),
(17, 'KOORDINATOR', 'Koordinator', 'Kesiswaan', 1, 'Tugas koordinasi umum yang dapat dikembangkan kemudian.', '2026-09-26 01:15:19', '2026-09-26 01:15:19'),
(18, 'PEMBINA_TARI', 'Pembina Tari', 'Kesiswaan', 1, 'Pembina kegiatan Tari', '2026-09-26 22:25:01', '2026-09-26 22:25:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kalender_kehadiran`
--

CREATE TABLE `kalender_kehadiran` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('libur','masuk_khusus') NOT NULL,
  `nama` varchar(150) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `konsultasi_bk`
--

CREATE TABLE `konsultasi_bk` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `jenis` enum('siswa','orang_tua','wali_kelas','guru','lainnya') NOT NULL DEFAULT 'siswa',
  `topik` varchar(190) NOT NULL,
  `permasalahan` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `kesepakatan` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status` enum('baru','proses','selesai','dibatalkan') NOT NULL DEFAULT 'baru',
  `melibatkan_ortu` tinyint(1) NOT NULL DEFAULT 0,
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_kelas`
--

CREATE TABLE `master_kelas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(20) NOT NULL,
  `tingkat` tinyint(3) UNSIGNED NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `master_kelas`
--

INSERT INTO `master_kelas` (`id`, `nama`, `tingkat`, `urutan`, `aktif`, `created_at`, `updated_at`) VALUES
(1, '7A', 7, 1, 1, '2026-09-13 11:25:21', '2026-09-25 01:33:59'),
(4, '8A', 8, 4, 1, '2026-09-13 11:25:21', '2026-09-13 11:25:21'),
(7, '9A', 9, 7, 1, '2026-09-13 11:25:21', '2026-09-13 11:25:21'),
(11, '7B', 7, 2, 1, '2026-09-28 06:12:30', '2026-09-28 06:12:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orang_tua`
--

CREATE TABLE `orang_tua` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `orang_tua_siswa`
--

CREATE TABLE `orang_tua_siswa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `orang_tua_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `hubungan` enum('ayah','ibu','wali','lainnya') NOT NULL DEFAULT 'wali',
  `utama` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggaran_poin_penyesuaian`
--

CREATE TABLE `pelanggaran_poin_penyesuaian` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `delta_poin` int(11) NOT NULL,
  `alasan` text NOT NULL,
  `tindakan` text DEFAULT NULL,
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggaran_siswa`
--

CREATE TABLE `pelanggaran_siswa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jenis_pelanggaran_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `kronologi` text DEFAULT NULL,
  `poin` int(11) NOT NULL DEFAULT 0,
  `tindakan` text DEFAULT NULL,
  `status` enum('tercatat','ditindaklanjuti','selesai') NOT NULL DEFAULT 'tercatat',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggaran_siswa`
--

INSERT INTO `pelanggaran_siswa` (`id`, `siswa_id`, `tahun_pelajaran_id`, `jenis_pelanggaran_id`, `tanggal`, `waktu`, `kronologi`, `poin`, `tindakan`, `status`, `petugas_id`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, 47, 1, 17, '2026-10-04', NULL, '', 25, NULL, 'tercatat', NULL, NULL, '2026-10-04 11:41:31', '2026-10-04 11:41:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembinaan`
--

CREATE TABLE `pembinaan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` varchar(100) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status` enum('direncanakan','dilaksanakan','selesai','dibatalkan') NOT NULL DEFAULT 'direncanakan',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembinaan_siswa`
--

CREATE TABLE `pembinaan_siswa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `pelanggaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `jenis` enum('teguran','konsultasi','pembinaan_individu','pembinaan_ortu','perjanjian_perilaku','lainnya') NOT NULL DEFAULT 'pembinaan_individu',
  `masalah` text NOT NULL,
  `tindakan` text DEFAULT NULL,
  `hasil` text DEFAULT NULL,
  `tindak_lanjut` text DEFAULT NULL,
  `status` enum('direncanakan','proses','selesai','dibatalkan') NOT NULL DEFAULT 'proses',
  `melibatkan_ortu` tinyint(1) NOT NULL DEFAULT 0,
  `rekomendasi_pengurangan_poin` int(11) NOT NULL DEFAULT 0,
  `rujukan_bk_id` bigint(20) UNSIGNED DEFAULT NULL,
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunci` varchar(100) NOT NULL,
  `nilai` text DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `kunci`, `nilai`, `keterangan`, `updated_at`) VALUES
(1, 'nama_sekolah', 'SMP NEGERI 1 PLAOSAN', 'Nama resmi sekolah', '2026-09-13 11:35:24'),
(2, 'absen_pulang_aktif', '1', '0 = tidak aktif, 1 = aktif', '2026-10-08 23:14:15'),
(3, 'durasi_display', '3', 'Durasi tampilan hasil scan dalam detik', '2026-09-13 11:35:24'),
(4, 'batas_terlambat_default', '07:15:00', 'Batas waktu default untuk menentukan keterlambatan', '2026-09-13 11:35:24'),
(5, 'batas_finalisasi_kehadiran', '17:00', 'Batas finalisasi otomatis kehadiran harian (WIB).', '2026-10-08 23:14:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_anggota`
--

CREATE TABLE `perpustakaan_anggota` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode_anggota` varchar(50) NOT NULL,
  `siswa_id` bigint(20) UNSIGNED DEFAULT NULL,
  `guru_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal_daftar` date NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perpustakaan_anggota`
--

INSERT INTO `perpustakaan_anggota` (`id`, `kode_anggota`, `siswa_id`, `guru_id`, `tanggal_daftar`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(67, 'LIB-S-47', 47, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(68, 'LIB-S-48', 48, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(69, 'LIB-S-49', 49, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(70, 'LIB-S-50', 50, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(71, 'LIB-S-51', 51, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(72, 'LIB-S-52', 52, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(73, 'LIB-S-53', 53, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(74, 'LIB-S-54', 54, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(75, 'LIB-S-55', 55, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(76, 'LIB-S-56', 56, NULL, '2026-10-02', 'aktif', 'Anggota otomatis dari data siswa', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(82, 'LIB-G-1', NULL, 1, '2026-10-02', 'aktif', 'Anggota otomatis dari data guru', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(83, 'LIB-G-2', NULL, 2, '2026-10-02', 'aktif', 'Anggota otomatis dari data guru', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(84, 'LIB-G-3', NULL, 3, '2026-10-02', 'aktif', 'Anggota otomatis dari data guru', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(85, 'LIB-G-4', NULL, 4, '2026-10-02', 'aktif', 'Anggota otomatis dari data guru', '2026-10-02 09:18:51', '2026-10-02 09:18:51'),
(86, 'LIB-G-5', NULL, 5, '2026-10-02', 'aktif', 'Anggota otomatis dari data guru', '2026-10-02 09:18:51', '2026-10-02 09:18:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_buku`
--

CREATE TABLE `perpustakaan_buku` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode_buku` varchar(50) NOT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `penulis` varchar(255) DEFAULT NULL,
  `penerbit` varchar(255) DEFAULT NULL,
  `tahun_terbit` year(4) DEFAULT NULL,
  `kategori_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stok` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lokasi` varchar(100) DEFAULT NULL,
  `kondisi` enum('baik','rusak_ringan','rusak_berat') NOT NULL DEFAULT 'baik',
  `status` enum('tersedia','nonaktif') NOT NULL DEFAULT 'tersedia',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perpustakaan_buku`
--

INSERT INTO `perpustakaan_buku` (`id`, `kode_buku`, `isbn`, `judul`, `penulis`, `penerbit`, `tahun_terbit`, `kategori_id`, `stok`, `lokasi`, `kondisi`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, '001', '001', 'senangnya belajar', NULL, NULL, NULL, 1, 2, NULL, 'baik', 'tersedia', NULL, '2026-10-01 02:48:48', '2026-10-02 03:54:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_detail_peminjaman`
--

CREATE TABLE `perpustakaan_detail_peminjaman` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `peminjaman_id` bigint(20) UNSIGNED NOT NULL,
  `buku_id` bigint(20) UNSIGNED NOT NULL,
  `eksemplar_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jumlah` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `tanggal_kembali` date DEFAULT NULL,
  `kondisi_kembali` enum('baik','rusak_ringan','rusak_berat','hilang') DEFAULT NULL,
  `denda` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('dipinjam','kembali','hilang') NOT NULL DEFAULT 'dipinjam',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_eksemplar`
--

CREATE TABLE `perpustakaan_eksemplar` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `buku_id` bigint(20) UNSIGNED NOT NULL,
  `kode_eksemplar` varchar(80) NOT NULL,
  `kondisi` enum('baik','rusak_ringan','rusak_berat') NOT NULL DEFAULT 'baik',
  `status` enum('tersedia','dipinjam','rusak','hilang','nonaktif') NOT NULL DEFAULT 'tersedia',
  `lokasi` varchar(100) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perpustakaan_eksemplar`
--

INSERT INTO `perpustakaan_eksemplar` (`id`, `buku_id`, `kode_eksemplar`, `kondisi`, `status`, `lokasi`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 1, '001-0001', 'baik', 'tersedia', NULL, NULL, '2026-10-01 03:53:18', '2026-10-01 03:53:18'),
(2, 1, '001-0002', 'baik', 'tersedia', NULL, NULL, '2026-10-01 03:53:18', '2026-10-02 03:54:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_kategori`
--

CREATE TABLE `perpustakaan_kategori` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perpustakaan_kategori`
--

INSERT INTO `perpustakaan_kategori` (`id`, `kode`, `nama`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, '101', 'Buku cerita anak', 1, '', '2026-10-01 02:47:43', '2026-10-01 02:47:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_kunjungan`
--

CREATE TABLE `perpustakaan_kunjungan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `kelas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `waktu_kunjungan` datetime NOT NULL DEFAULT current_timestamp(),
  `metode` enum('kamera','usb','manual') NOT NULL DEFAULT 'manual',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `perpustakaan_kunjungan`
--

INSERT INTO `perpustakaan_kunjungan` (`id`, `siswa_id`, `tahun_pelajaran_id`, `kelas_id`, `waktu_kunjungan`, `metode`, `petugas_id`, `keterangan`, `ip_address`, `user_agent`, `created_at`) VALUES
(6, 55, 1, 11, '2026-10-02 16:39:54', 'usb', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 09:39:54'),
(7, 47, 1, 1, '2026-10-02 17:00:32', 'usb', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:00:32'),
(8, 54, 1, 1, '2026-10-02 17:00:41', 'usb', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:00:41'),
(9, 56, 1, 11, '2026-10-02 17:08:55', 'usb', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-02 10:08:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `perpustakaan_peminjaman`
--

CREATE TABLE `perpustakaan_peminjaman` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nomor_transaksi` varchar(50) NOT NULL,
  `anggota_id` bigint(20) UNSIGNED NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_jatuh_tempo` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` enum('dipinjam','sebagian_kembali','selesai','terlambat','dibatalkan') NOT NULL DEFAULT 'dipinjam',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `profil_sekolah`
--

CREATE TABLE `profil_sekolah` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama_sekolah` varchar(200) NOT NULL,
  `npsn` varchar(30) DEFAULT NULL,
  `nss` varchar(30) DEFAULT NULL,
  `jenjang` varchar(50) DEFAULT NULL,
  `status_sekolah` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `desa` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kabupaten` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `nama_kepala` varchar(200) DEFAULT NULL,
  `nip_kepala` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `profil_sekolah`
--

INSERT INTO `profil_sekolah` (`id`, `nama_sekolah`, `npsn`, `nss`, `jenjang`, `status_sekolah`, `alamat`, `desa`, `kecamatan`, `kabupaten`, `provinsi`, `kode_pos`, `telepon`, `email`, `website`, `logo`, `foto`, `nama_kepala`, `nip_kepala`, `created_at`, `updated_at`) VALUES
(1, 'SMP NEGERI 1 PLAOSAN', '20509370', '', 'SMP', 'NEGERI', 'Jl. Raya Sarangan Plaosan', 'Plaosan', 'Plaosan', 'Magetan', 'Jawa Timur', '63361', '0351-888077', 'smpn1plaosan@gmail.com', 'www.smpn1plaosan.sch.id', 'uploads/profil/logo_20260916135147_9efaad.png', 'uploads/profil/foto_20261001195448_18abed854d46b8a3.jpg', 'Suparti, S.Pd.,M.Pd', '197208161997022004', '2026-09-16 06:46:30', '2026-10-01 12:54:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `semester`
--

CREATE TABLE `semester` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `nama` enum('Ganjil','Genap') NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 0,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `semester`
--

INSERT INTO `semester` (`id`, `tahun_pelajaran_id`, `nama`, `aktif`, `tanggal_mulai`, `tanggal_selesai`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ganjil', 1, '2026-07-01', '2026-12-31', '2026-09-13 11:25:21', '2026-09-26 07:30:50'),
(2, 1, 'Genap', 0, '2027-01-01', '2027-06-30', '2026-09-13 11:25:21', '2026-09-13 11:25:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sesi_absensi`
--

CREATE TABLE `sesi_absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `jenis_absensi_id` bigint(20) UNSIGNED NOT NULL,
  `jenis_sholat_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jenis_sesi_key` bigint(20) UNSIGNED GENERATED ALWAYS AS (coalesce(`jenis_sholat_id`,0)) STORED,
  `tanggal` date NOT NULL,
  `nama_sesi` varchar(150) NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `batas_terlambat` time DEFAULT NULL,
  `status` enum('draft','aktif','ditutup') NOT NULL DEFAULT 'draft',
  `status_sesi` enum('DRAFT','OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `verifikasi_status` enum('belum','proses','selesai','terkunci') NOT NULL DEFAULT 'belum',
  `diverifikasi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `diverifikasi_at` datetime DEFAULT NULL,
  `dikunci_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `dikunci_at` datetime DEFAULT NULL,
  `dibuka_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `dibuka_at` datetime DEFAULT NULL,
  `ditutup_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `ditutup_at` datetime DEFAULT NULL,
  `finalisasi_at` datetime DEFAULT NULL,
  `finalisasi_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `finalisasi_mode` enum('MANUAL','AUTO') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sesi_absensi`
--

INSERT INTO `sesi_absensi` (`id`, `tahun_pelajaran_id`, `semester_id`, `jenis_absensi_id`, `jenis_sholat_id`, `tanggal`, `nama_sesi`, `jam_mulai`, `jam_selesai`, `batas_terlambat`, `status`, `status_sesi`, `verifikasi_status`, `diverifikasi_oleh`, `diverifikasi_at`, `dikunci_oleh`, `dikunci_at`, `dibuka_oleh`, `dibuka_at`, `ditutup_oleh`, `ditutup_at`, `finalisasi_at`, `finalisasi_oleh`, `finalisasi_mode`, `created_at`, `updated_at`) VALUES
(43, 1, 1, 1, NULL, '2026-10-02', 'Absensi Kehadiran', '11:30:00', '11:45:00', '11:31:00', 'ditutup', 'CLOSED', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-02 09:13:43', '2026-10-02 09:13:43'),
(44, 1, 1, 3, 1, '2026-10-02', 'Sholat Dzuhur', '11:35:00', '11:38:00', NULL, 'ditutup', 'CLOSED', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-02 09:13:43', '2026-10-02 09:13:43'),
(45, 1, 1, 1, NULL, '2026-10-07', 'Absensi Kehadiran', '09:05:00', '14:30:00', '09:06:00', 'ditutup', 'CLOSED', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-07 02:34:37', '2026-10-08 05:16:08'),
(46, 1, 1, 3, 1, '2026-10-07', 'Sholat Dzuhur', '11:35:00', '11:38:00', NULL, 'draft', 'DRAFT', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-07 02:34:37', '2026-10-07 02:34:37'),
(47, 1, 1, 1, NULL, '2026-10-08', 'Absensi Kehadiran', '06:30:00', '14:30:00', '07:00:00', 'aktif', 'OPEN', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-08 03:57:09', '2026-10-08 03:57:09'),
(48, 1, 1, 3, 1, '2026-10-08', 'Sholat Dzuhur', '11:35:00', '13:00:00', NULL, 'aktif', 'OPEN', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-08 03:57:09', '2026-10-08 05:22:45'),
(49, 1, 1, 1, NULL, '2026-10-09', 'Absensi Kehadiran', '05:54:00', '11:15:00', '07:00:00', 'ditutup', 'CLOSED', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-08 22:36:55', '2026-10-09 04:40:50'),
(50, 1, 1, 3, 1, '2026-10-09', 'Sholat Dzuhur', '11:35:00', '13:00:00', NULL, 'aktif', 'OPEN', 'belum', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-08 22:36:55', '2026-10-09 04:40:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nis` varchar(30) NOT NULL,
  `nisn` varchar(30) DEFAULT NULL,
  `nama` varchar(150) NOT NULL,
  `jk` enum('L','P') DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `kode_qr` varchar(50) NOT NULL,
  `status` enum('aktif','lulus','pindah','keluar','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `nis`, `nisn`, `nama`, `jk`, `no_hp`, `kode_qr`, `status`, `created_at`, `updated_at`) VALUES
(47, '10001', '1111100001', 'FARID', 'L', '081230000001', 'SIS-F6BDC09147FC', 'aktif', '2026-09-27 03:04:08', '2026-09-27 03:04:08'),
(48, '10002', '1111100002', 'LIA', 'P', '081230000002', 'SIS-E7B238A6CBE3', 'aktif', '2026-09-27 03:04:08', '2026-09-27 03:04:08'),
(49, '10003', '1111100003', 'BAGAS', 'L', '081230000003', 'SIS-EB9343EE2DF9', 'aktif', '2026-09-28 06:12:46', '2026-09-28 06:12:46'),
(50, '10004', '1111100004', 'DIANA', 'P', '081230000004', 'SIS-AE75400C70FE', 'aktif', '2026-09-28 06:12:46', '2026-09-28 06:12:46'),
(51, '10005', '1111100005', 'HENDRIK', 'L', '081230000003', 'SIS-0234EB0D8FDA', 'aktif', '2026-09-28 06:12:46', '2026-09-28 06:12:46'),
(52, '10006', '1111100006', 'KEVIN', 'L', NULL, 'SIS-AFD6ADEC5337', 'aktif', '2026-10-02 01:41:48', '2026-10-02 01:41:48'),
(53, '10007', '1111100007', 'SAFIRA', 'P', NULL, 'SIS-D9B1DC6F41B1', 'aktif', '2026-10-02 01:41:48', '2026-10-02 01:41:48'),
(54, '10008', '1111100008', 'ISABEL', 'P', NULL, 'SIS-07B8068B3439', 'aktif', '2026-10-02 01:41:48', '2026-10-02 01:41:48'),
(55, '10009', '1111100009', 'BRANDON', 'L', NULL, 'SIS-2FA9E834433A', 'aktif', '2026-10-02 01:41:48', '2026-10-02 01:41:48'),
(56, '10010', '1111100010', 'JOHAN', 'L', NULL, 'SIS-0D7795809D4C', 'aktif', '2026-10-02 01:41:48', '2026-10-02 01:41:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa_kelas`
--

CREATE TABLE `siswa_kelas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `kelas_id` bigint(20) UNSIGNED NOT NULL,
  `nomor_urut` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('aktif','lulus','pindah','keluar') NOT NULL DEFAULT 'aktif',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `active_marker` bigint(20) UNSIGNED GENERATED ALWAYS AS (case when `status` = 'aktif' then 0 else NULL end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `siswa_kelas`
--

INSERT INTO `siswa_kelas` (`id`, `siswa_id`, `tahun_pelajaran_id`, `kelas_id`, `nomor_urut`, `status`, `keterangan`, `created_at`) VALUES
(50, 47, 1, 1, NULL, 'aktif', 'Import XLSX', '2026-09-27 03:04:08'),
(51, 48, 1, 1, NULL, 'aktif', 'Import XLSX', '2026-09-27 03:04:08'),
(52, 49, 1, 11, NULL, 'aktif', 'Import XLSX', '2026-09-28 06:12:46'),
(53, 50, 1, 11, NULL, 'aktif', 'Import XLSX', '2026-09-28 06:12:46'),
(54, 51, 1, 11, NULL, 'aktif', 'Import XLSX', '2026-09-28 06:12:46'),
(55, 52, 1, 1, NULL, 'aktif', 'Import XLSX', '2026-10-02 01:41:48'),
(56, 53, 1, 1, NULL, 'aktif', 'Import XLSX', '2026-10-02 01:41:48'),
(57, 54, 1, 1, NULL, 'aktif', 'Import XLSX', '2026-10-02 01:41:48'),
(58, 55, 1, 11, NULL, 'aktif', 'Import XLSX', '2026-10-02 01:41:48'),
(59, 56, 1, 11, NULL, 'aktif', 'Import XLSX', '2026-10-02 01:41:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa_status_riwayat`
--

CREATE TABLE `siswa_status_riwayat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('lulus','pindah','keluar') NOT NULL,
  `tanggal_status` date NOT NULL,
  `tujuan` varchar(255) DEFAULT NULL,
  `keterangan` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tahun_pelajaran`
--

CREATE TABLE `tahun_pelajaran` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(20) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 0,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tahun_pelajaran`
--

INSERT INTO `tahun_pelajaran` (`id`, `nama`, `aktif`, `tanggal_mulai`, `tanggal_selesai`, `created_at`, `updated_at`) VALUES
(1, '2026/2027', 1, '2026-07-01', '2027-06-30', '2026-09-13 11:25:21', '2026-09-25 01:49:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tugas_guru`
--

CREATE TABLE `tugas_guru` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `guru_id` bigint(20) UNSIGNED NOT NULL,
  `jenis_tugas_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED DEFAULT NULL,
  `target_tipe` varchar(30) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `mulai` date DEFAULT NULL,
  `selesai` date DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tugas_guru`
--

INSERT INTO `tugas_guru` (`id`, `guru_id`, `jenis_tugas_id`, `tahun_pelajaran_id`, `semester_id`, `target_tipe`, `target_id`, `mulai`, `selesai`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, 1, 16, 1, 1, NULL, NULL, NULL, NULL, 1, NULL, '2026-09-26 01:22:43', '2026-09-26 07:19:05'),
(3, 1, 1, 1, 1, 'kelas', 1, NULL, NULL, 1, NULL, '2026-09-26 01:22:59', '2026-09-26 07:19:05'),
(4, 1, 13, 1, 1, NULL, NULL, NULL, NULL, 1, NULL, '2026-09-29 13:42:30', '2026-09-29 13:42:30'),
(5, 1, 7, 1, 1, 'ekstrakurikuler', 1, NULL, NULL, 1, NULL, '2026-10-01 11:43:21', '2026-10-01 11:43:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tugas_guru_jadwal`
--

CREATE TABLE `tugas_guru_jadwal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tugas_guru_id` bigint(20) UNSIGNED NOT NULL,
  `hari` tinyint(3) UNSIGNED NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tugas_guru_jadwal`
--

INSERT INTO `tugas_guru_jadwal` (`id`, `tugas_guru_id`, `hari`, `jam_mulai`, `jam_selesai`, `aktif`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, 2, 1, NULL, NULL, 1, NULL, '2026-10-04 10:17:01', '2026-10-04 10:17:01'),
(3, 2, 2, NULL, NULL, 1, NULL, '2026-10-04 10:17:01', '2026-10-04 10:17:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_kunjungan`
--

CREATE TABLE `uks_kunjungan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tahun_pelajaran_id` bigint(20) UNSIGNED DEFAULT NULL,
  `kelas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `nomor_antrian` int(10) UNSIGNED DEFAULT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `waktu_selesai` time DEFAULT NULL,
  `metode` enum('QR','USB','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `petugas_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('baru','ditangani','selesai','batal') NOT NULL DEFAULT 'baru',
  `jenis_layanan` varchar(50) DEFAULT NULL,
  `keluhan` text DEFAULT NULL,
  `hasil_pemeriksaan` text DEFAULT NULL,
  `tindakan` text DEFAULT NULL,
  `obat` text DEFAULT NULL,
  `rujukan` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `uks_kunjungan`
--

INSERT INTO `uks_kunjungan` (`id`, `siswa_id`, `tahun_pelajaran_id`, `kelas_id`, `tanggal`, `waktu`, `nomor_antrian`, `waktu_mulai`, `waktu_selesai`, `metode`, `petugas_id`, `status`, `jenis_layanan`, `keluhan`, `hasil_pemeriksaan`, `tindakan`, `obat`, `rujukan`, `catatan`, `created_at`, `updated_at`) VALUES
(9, 56, 1, 11, '2026-10-02', '17:11:00', 1, NULL, NULL, 'USB', NULL, 'baru', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-02 10:11:00', '2026-10-02 10:11:00'),
(10, 54, 1, 1, '2026-10-02', '17:11:05', 2, NULL, NULL, 'USB', NULL, 'baru', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-02 10:11:05', '2026-10-02 10:11:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_kunjungan_obat`
--

CREATE TABLE `uks_kunjungan_obat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `obat_id` bigint(20) UNSIGNED NOT NULL,
  `jumlah` int(10) UNSIGNED NOT NULL,
  `aturan_pakai` varchar(255) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_obat`
--

CREATE TABLE `uks_obat` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `stok_minimum` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stok_saat_ini` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lokasi` varchar(150) DEFAULT NULL,
  `tanggal_kadaluarsa` date DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_petugas_absensi`
--

CREATE TABLE `uks_petugas_absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `petugas_jadwal_id` bigint(20) UNSIGNED DEFAULT NULL,
  `petugas_tipe` enum('siswa','guru') NOT NULL DEFAULT 'siswa',
  `guru_id` bigint(20) UNSIGNED DEFAULT NULL,
  `siswa_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_keluar` time DEFAULT NULL,
  `status` enum('hadir','terlambat','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
  `metode` enum('qr','manual') NOT NULL DEFAULT 'qr',
  `catatan` varchar(255) DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `petugas_kunci` varchar(60) GENERATED ALWAYS AS (concat(`petugas_tipe`,':',if(`petugas_tipe` = 'guru',coalesce(`guru_id`,0),coalesce(`siswa_id`,0)))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_petugas_jadwal`
--

CREATE TABLE `uks_petugas_jadwal` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `siswa_kelas_id` bigint(20) UNSIGNED NOT NULL,
  `semester_id` bigint(20) UNSIGNED NOT NULL,
  `hari` tinyint(3) UNSIGNED NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_rujukan`
--

CREATE TABLE `uks_rujukan` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED NOT NULL,
  `siswa_id` bigint(20) UNSIGNED NOT NULL,
  `tujuan_jenis` enum('puskesmas','rumah_sakit','klinik','lainnya') NOT NULL DEFAULT 'puskesmas',
  `fasilitas_nama` varchar(200) NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time DEFAULT NULL,
  `pendamping_guru_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pendamping_nama` varchar(150) DEFAULT NULL,
  `alasan` text NOT NULL,
  `cara_berangkat` enum('diantar_sekolah','diantar_orangtua','datang_sendiri_dengan_persetujuan','lainnya') NOT NULL DEFAULT 'diantar_orangtua',
  `status` enum('direncanakan','dirujuk','selesai','tidak_jadi') NOT NULL DEFAULT 'direncanakan',
  `hasil_tindak_lanjut` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uks_stok_transaksi`
--

CREATE TABLE `uks_stok_transaksi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `obat_id` bigint(20) UNSIGNED NOT NULL,
  `jenis` enum('masuk','keluar','koreksi') NOT NULL,
  `jumlah` int(10) UNSIGNED NOT NULL,
  `stok_sebelum` int(10) UNSIGNED NOT NULL,
  `stok_sesudah` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `kunjungan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `role` enum('admin','kepala_sekolah','guru','guru_bk','siswa','ortu') NOT NULL,
  `guru_id` bigint(20) UNSIGNED DEFAULT NULL,
  `siswa_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`, `guru_id`, `siswa_id`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$9HIQq/mnCMK62616WT5V2e/ScZvi8obzez9Zbtn2QUcz/.MOv0CWK', 'Administrator', 'admin', NULL, NULL, 'aktif', '2026-10-09 11:40:50', '2026-09-13 11:57:15', '2026-10-09 04:40:50'),
(2, 'guru4', '$2y$10$Iun1TH/DZjpjl1FyyAmX4ODPywB.KRLg.RT0682naf8jqDHL0L0.a', 'Guru empat', 'guru_bk', 4, NULL, 'aktif', '2026-10-04 19:08:22', '2026-09-22 10:21:18', '2026-10-04 12:08:22'),
(3, 'guru1', '$2y$10$eHw0oa4AF0tul9DF4V/rfumwtw6LXqizsuQoZqPmJPxXOZO6/Jq62', 'Guru Satu', 'guru', 1, NULL, 'aktif', '2026-10-04 19:28:56', '2026-09-22 10:22:19', '2026-10-04 12:28:56'),
(4, '10001', '$2y$10$Awbw6BLPx7/2t6ILc2R9m.FEWJg1zrMB7/Pq1pdp12MX3mhF.q7sC', 'FARID', 'siswa', NULL, 47, 'aktif', '2026-10-04 15:44:25', '2026-10-03 05:49:02', '2026-10-04 08:44:25'),
(5, '10008', '$2y$10$zgs0LFz6uPOhOxohLXb06.w6/Zk/OXxp/jiyMGcVTn8SINu7k3zZm', 'ISABEL', 'siswa', NULL, 54, 'aktif', NULL, '2026-10-03 05:54:11', '2026-10-03 05:54:11'),
(8, 'kepsek', '$2y$10$NjuCf/rD2dIBtc90ZG2f4u1mManXb6vuigLTzYEj/MExI1OUSGxOO', 'Kepala Sekolah', 'kepala_sekolah', NULL, NULL, 'aktif', '2026-10-06 08:48:28', '2026-10-03 23:26:46', '2026-10-06 01:48:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_hak_akses`
--

CREATE TABLE `user_hak_akses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `hak_akses_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_hak_akses`
--

INSERT INTO `user_hak_akses` (`id`, `user_id`, `hak_akses_id`, `created_at`) VALUES
(1, 1, 1, '2026-09-13 12:12:54'),
(2, 1, 2, '2026-09-13 12:12:54'),
(3, 1, 3, '2026-09-13 12:12:54'),
(4, 1, 4, '2026-09-13 12:12:54'),
(5, 1, 5, '2026-09-13 12:12:54'),
(6, 1, 6, '2026-09-13 12:12:54'),
(7, 1, 7, '2026-09-13 12:12:54'),
(8, 1, 8, '2026-09-13 12:12:54'),
(9, 1, 9, '2026-09-13 12:12:54'),
(10, 1, 10, '2026-09-13 12:12:54'),
(11, 1, 11, '2026-09-13 12:12:54'),
(12, 1, 12, '2026-09-13 12:12:54'),
(13, 1, 13, '2026-09-13 12:12:54'),
(14, 1, 14, '2026-09-13 12:12:54'),
(15, 1, 15, '2026-09-13 12:12:54'),
(16, 1, 16, '2026-09-13 12:12:54'),
(17, 1, 17, '2026-09-13 12:12:54'),
(18, 1, 18, '2026-09-13 12:12:54'),
(19, 1, 19, '2026-09-13 12:12:54'),
(23, 8, 1, '2026-10-03 23:26:46'),
(24, 8, 15, '2026-10-03 23:26:46'),
(26, 2, 9, '2026-10-04 12:08:19'),
(27, 2, 12, '2026-10-04 12:08:19'),
(28, 2, 20, '2026-10-04 12:08:19'),
(29, 2, 10, '2026-10-04 12:08:19'),
(30, 2, 11, '2026-10-04 12:08:19'),
(31, 3, 6, '2026-10-04 12:10:16'),
(32, 3, 5, '2026-10-04 12:10:16'),
(33, 3, 15, '2026-10-04 12:10:16');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_absensi_sesi_siswa` (`sesi_id`,`siswa_id`),
  ADD KEY `idx_absensi_siswa` (`siswa_id`),
  ADD KEY `idx_absensi_status` (`status`),
  ADD KEY `idx_absensi_scan` (`waktu_scan`),
  ADD KEY `fk_absensi_scan_oleh` (`scan_oleh`),
  ADD KEY `idx_absensi_verifikasi` (`status_verifikasi`,`siswa_id`),
  ADD KEY `fk_absensi_verifikasi_user_final` (`diverifikasi_oleh`),
  ADD KEY `idx_absensi_pulang` (`sesi_id`,`siswa_id`,`waktu_pulang`),
  ADD KEY `fk_absensi_pulang_oleh` (`pulang_oleh`);

--
-- Indeks untuk tabel `absensi_perubahan`
--
ALTER TABLE `absensi_perubahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_absensi_perubahan_absensi` (`absensi_id`),
  ADD KEY `idx_absensi_perubahan_user` (`diubah_oleh`);

--
-- Indeks untuk tabel `absensi_sholat`
--
ALTER TABLE `absensi_sholat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_absensi_sholat_sesi_siswa` (`sesi_id`,`siswa_id`),
  ADD KEY `idx_absensi_sholat_siswa` (`siswa_id`),
  ADD KEY `idx_absensi_sholat_status` (`status`),
  ADD KEY `idx_absensi_sholat_waktu` (`waktu_scan`),
  ADD KEY `idx_absensi_sholat_petugas` (`dicatat_oleh`);

--
-- Indeks untuk tabel `absensi_sholat_perubahan`
--
ALTER TABLE `absensi_sholat_perubahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asp_perubahan_absensi` (`absensi_sholat_id`),
  ADD KEY `idx_asp_perubahan_user` (`diubah_oleh`);

--
-- Indeks untuk tabel `app_migration`
--
ALTER TABLE `app_migration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_app_migration_key` (`migration_key`);

--
-- Indeks untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_modul` (`modul`),
  ADD KEY `idx_audit_aksi` (`aksi`),
  ADD KEY `idx_audit_referensi` (`referensi_id`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `idx_audit_modul_aksi_created` (`modul`,`aksi`,`created_at`);

--
-- Indeks untuk tabel `bk_kunjungan`
--
ALTER TABLE `bk_kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bk_kunj_siswa` (`siswa_id`),
  ADD KEY `idx_bk_kunj_tapel` (`tahun_pelajaran_id`),
  ADD KEY `idx_bk_kunj_tanggal` (`tanggal`),
  ADD KEY `idx_bk_kunj_petugas` (`petugas_id`),
  ADD KEY `fk_bk_kunj_kelas` (`kelas_id`),
  ADD KEY `fk_bk_kunj_created` (`created_by`);

--
-- Indeks untuk tabel `bk_layanan`
--
ALTER TABLE `bk_layanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bk_layanan_nomor_hari` (`tanggal`,`nomor_antrian`),
  ADD KEY `idx_bk_layanan_tanggal_status` (`tanggal`,`status`),
  ADD KEY `idx_bk_layanan_siswa_tanggal` (`siswa_id`,`tanggal`),
  ADD KEY `idx_bk_layanan_tapel` (`tahun_pelajaran_id`),
  ADD KEY `idx_bk_layanan_petugas` (`petugas_id`),
  ADD KEY `fk_bk_layanan_kelas` (`kelas_id`),
  ADD KEY `fk_bk_layanan_created` (`created_by`);

--
-- Indeks untuk tabel `catatan_bk`
--
ALTER TABLE `catatan_bk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_catatan_bk_siswa` (`siswa_id`),
  ADD KEY `idx_catatan_bk_tanggal` (`tanggal`),
  ADD KEY `idx_catatan_bk_status` (`status`),
  ADD KEY `idx_catatan_bk_guru` (`guru_bk_id`);

--
-- Indeks untuk tabel `ekstrakurikuler`
--
ALTER TABLE `ekstrakurikuler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_kode` (`kode`),
  ADD UNIQUE KEY `uk_ekstra_nama` (`nama`),
  ADD KEY `idx_ekstra_pembina` (`pembina_id`),
  ADD KEY `idx_ekstra_aktif` (`aktif`);

--
-- Indeks untuk tabel `ekstra_absensi`
--
ALTER TABLE `ekstra_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_absensi_sesi_siswa` (`sesi_id`,`siswa_id`),
  ADD KEY `idx_ekstra_absensi_siswa` (`siswa_id`),
  ADD KEY `idx_ekstra_absensi_status` (`status`),
  ADD KEY `idx_ekstra_absensi_scan` (`waktu_scan`),
  ADD KEY `fk_ekstra_absensi_scan` (`scan_oleh`);

--
-- Indeks untuk tabel `ekstra_anggota`
--
ALTER TABLE `ekstra_anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_anggota` (`ekstrakurikuler_id`,`siswa_id`,`tahun_pelajaran_id`),
  ADD KEY `idx_ekstra_anggota_siswa` (`siswa_id`),
  ADD KEY `idx_ekstra_anggota_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_ekstra_anggota_status` (`status`);

--
-- Indeks untuk tabel `ekstra_dokumentasi`
--
ALTER TABLE `ekstra_dokumentasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_dokumentasi_sesi` (`sesi_id`),
  ADD KEY `idx_ekstra_dokumentasi_user_b2` (`uploaded_by`);

--
-- Indeks untuk tabel `ekstra_jadwal`
--
ALTER TABLE `ekstra_jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ekstra_jadwal_ekstra` (`ekstrakurikuler_id`),
  ADD KEY `idx_ekstra_jadwal_hari` (`hari`),
  ADD KEY `idx_ekstra_jadwal_aktif` (`aktif`),
  ADD KEY `idx_ekstra_jadwal_tapel` (`tahun_pelajaran_id`);

--
-- Indeks untuk tabel `ekstra_jurnal`
--
ALTER TABLE `ekstra_jurnal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_jurnal_sesi` (`sesi_id`),
  ADD KEY `idx_ekstra_jurnal_user` (`dibuat_oleh`);

--
-- Indeks untuk tabel `ekstra_jurnal_kendala`
--
ALTER TABLE `ekstra_jurnal_kendala`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_kendala_sesi` (`sesi_id`),
  ADD KEY `idx_ekstra_kendala_user_b2` (`dibuat_oleh`);

--
-- Indeks untuk tabel `ekstra_pembina_absensi`
--
ALTER TABLE `ekstra_pembina_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_epa_sesi_guru_b1` (`sesi_id`,`guru_id`),
  ADD KEY `idx_epa_extra_tapel_tanggal` (`ekstrakurikuler_id`,`tahun_pelajaran_id`,`tanggal`),
  ADD KEY `fk_epa_guru_b2` (`guru_id`),
  ADD KEY `fk_epa_user_b2` (`dicatat_oleh`),
  ADD KEY `idx_epa_sesi_b1` (`sesi_id`),
  ADD KEY `idx_epa_sesi_status_b2` (`sesi_id`,`status`);

--
-- Indeks untuk tabel `ekstra_penilaian`
--
ALTER TABLE `ekstra_penilaian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ekstra_penilaian` (`ekstra_anggota_id`,`semester_id`),
  ADD KEY `idx_ekstra_penilaian_semester` (`semester_id`),
  ADD KEY `idx_ekstra_penilaian_user` (`dinilai_oleh`);

--
-- Indeks untuk tabel `ekstra_sesi`
--
ALTER TABLE `ekstra_sesi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ekstra_sesi_ekstra` (`ekstrakurikuler_id`),
  ADD KEY `idx_ekstra_sesi_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_ekstra_sesi_semester` (`semester_id`),
  ADD KEY `idx_ekstra_sesi_tanggal` (`tanggal`),
  ADD KEY `idx_ekstra_sesi_status` (`status`),
  ADD KEY `fk_ekstra_sesi_dibuka` (`dibuka_oleh`),
  ADD KEY `fk_ekstra_sesi_ditutup` (`ditutup_oleh`),
  ADD KEY `idx_ekstra_sesi_jadwal_b1` (`jadwal_id`),
  ADD KEY `idx_ekstra_sesi_jadwal_tgl_b2` (`jadwal_id`,`tanggal`,`status`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_guru_qr` (`kode_qr`),
  ADD UNIQUE KEY `uk_guru_nip` (`nip`),
  ADD KEY `idx_guru_nama` (`nama`),
  ADD KEY `idx_guru_status` (`status`);

--
-- Indeks untuk tabel `hak_akses`
--
ALTER TABLE `hak_akses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_hak_akses_kode` (`kode`),
  ADD KEY `idx_hak_akses_modul` (`modul`),
  ADD KEY `idx_hak_akses_aktif` (`aktif`);

--
-- Indeks untuk tabel `home_visit`
--
ALTER TABLE `home_visit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home_visit_siswa` (`siswa_id`),
  ADD KEY `idx_home_visit_rencana` (`tanggal_rencana`),
  ADD KEY `idx_home_visit_pelaksanaan` (`tanggal_pelaksanaan`),
  ADD KEY `idx_home_visit_status` (`status`),
  ADD KEY `idx_home_visit_petugas` (`petugas_id`),
  ADD KEY `idx_home_visit47_tapel` (`tahun_pelajaran_id`);

--
-- Indeks untuk tabel `izin_siswa`
--
ALTER TABLE `izin_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_izin_siswa` (`siswa_id`,`tanggal`),
  ADD KEY `idx_izin_tapel_kelas` (`tahun_pelajaran_id`,`kelas_id`,`tanggal`),
  ADD KEY `idx_izin_status` (`status`,`tanggal`),
  ADD KEY `fk_izin_siswa_kelas` (`kelas_id`),
  ADD KEY `fk_izin_siswa_pengaju` (`diajukan_oleh`),
  ADD KEY `fk_izin_siswa_penyetuju` (`disetujui_oleh`);

--
-- Indeks untuk tabel `jadwal_kehadiran`
--
ALTER TABLE `jadwal_kehadiran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jadwal_kehadiran_tahun_hari` (`tahun_pelajaran_id`,`hari`),
  ADD UNIQUE KEY `uk_jadwal_kehadiran_semester_hari_b1` (`semester_id`,`hari`);

--
-- Indeks untuk tabel `jadwal_sholat`
--
ALTER TABLE `jadwal_sholat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jadwal_sholat_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_jadwal_sholat_jenis` (`jenis_sholat_id`),
  ADD KEY `idx_jadwal_sholat_aktif` (`aktif`),
  ADD KEY `fk_jadwal_sholat_semester_b1` (`semester_id`);

--
-- Indeks untuk tabel `jadwal_sholat_arsip_batch11`
--
ALTER TABLE `jadwal_sholat_arsip_batch11`
  ADD PRIMARY KEY (`arsip_id`),
  ADD KEY `idx_arsip_jadwal` (`jadwal_id`),
  ADD KEY `idx_arsip_konteks` (`semester_id`,`jenis_sholat_id`);

--
-- Indeks untuk tabel `jenis_absensi`
--
ALTER TABLE `jenis_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jenis_absensi_kode` (`kode`),
  ADD KEY `idx_jenis_absensi_kategori` (`kategori`),
  ADD KEY `idx_jenis_absensi_aktif` (`aktif`);

--
-- Indeks untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jenis_pelanggaran_kode` (`kode`),
  ADD UNIQUE KEY `uk_jenis_pelanggaran_nama` (`nama`),
  ADD KEY `idx_jenis_pelanggaran_kategori` (`kategori`),
  ADD KEY `idx_jenis_pelanggaran_aktif` (`aktif`);

--
-- Indeks untuk tabel `jenis_sholat`
--
ALTER TABLE `jenis_sholat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jenis_sholat_nama` (`nama`),
  ADD KEY `idx_jenis_sholat_aktif` (`aktif`);

--
-- Indeks untuk tabel `jenis_tugas`
--
ALTER TABLE `jenis_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_jenis_tugas_kode` (`kode`),
  ADD UNIQUE KEY `uk_jenis_tugas_nama` (`nama`),
  ADD KEY `idx_jenis_tugas_aktif` (`aktif`);

--
-- Indeks untuk tabel `kalender_kehadiran`
--
ALTER TABLE `kalender_kehadiran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kalender_kehadiran_tahun_tanggal` (`tahun_pelajaran_id`,`tanggal`),
  ADD KEY `idx_kalender_kehadiran_tanggal` (`tanggal`),
  ADD KEY `fk_kalender_kehadiran_user` (`created_by`);

--
-- Indeks untuk tabel `konsultasi_bk`
--
ALTER TABLE `konsultasi_bk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kons50_siswa` (`siswa_id`),
  ADD KEY `idx_kons50_tapel` (`tahun_pelajaran_id`),
  ADD KEY `idx_kons50_tanggal` (`tanggal`),
  ADD KEY `idx_kons50_status` (`status`),
  ADD KEY `fk_kons50_petugas` (`petugas_id`),
  ADD KEY `fk_kons50_created` (`created_by`);

--
-- Indeks untuk tabel `master_kelas`
--
ALTER TABLE `master_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_master_kelas_nama` (`nama`),
  ADD KEY `idx_master_kelas_tingkat` (`tingkat`),
  ADD KEY `idx_master_kelas_aktif` (`aktif`);

--
-- Indeks untuk tabel `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ortu52_user` (`user_id`);

--
-- Indeks untuk tabel `orang_tua_siswa`
--
ALTER TABLE `orang_tua_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ortu52_anak` (`orang_tua_id`,`siswa_id`),
  ADD KEY `idx_ortu52_siswa` (`siswa_id`);

--
-- Indeks untuk tabel `pelanggaran_poin_penyesuaian`
--
ALTER TABLE `pelanggaran_poin_penyesuaian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_penyesuaian_siswa` (`siswa_id`),
  ADD KEY `idx_penyesuaian_tapel` (`tahun_pelajaran_id`),
  ADD KEY `idx_penyesuaian_tanggal` (`tanggal`),
  ADD KEY `idx_penyesuaian_petugas` (`petugas_id`),
  ADD KEY `fk_penyesuaian_created_by` (`created_by`);

--
-- Indeks untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pelanggaran_siswa` (`siswa_id`),
  ADD KEY `idx_pelanggaran_jenis` (`jenis_pelanggaran_id`),
  ADD KEY `idx_pelanggaran_tanggal` (`tanggal`),
  ADD KEY `idx_pelanggaran_status` (`status`),
  ADD KEY `idx_pelanggaran_petugas` (`petugas_id`),
  ADD KEY `idx_pelanggaran_tapel` (`tahun_pelajaran_id`);

--
-- Indeks untuk tabel `pembinaan`
--
ALTER TABLE `pembinaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pembinaan_siswa` (`siswa_id`),
  ADD KEY `idx_pembinaan_tanggal` (`tanggal`),
  ADD KEY `idx_pembinaan_status` (`status`),
  ADD KEY `idx_pembinaan_petugas` (`petugas_id`);

--
-- Indeks untuk tabel `pembinaan_siswa`
--
ALTER TABLE `pembinaan_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pembinaan46_siswa` (`siswa_id`),
  ADD KEY `idx_pembinaan46_tapel` (`tahun_pelajaran_id`),
  ADD KEY `idx_pembinaan46_pelanggaran` (`pelanggaran_id`),
  ADD KEY `idx_pembinaan46_tanggal` (`tanggal`),
  ADD KEY `idx_pembinaan46_status` (`status`),
  ADD KEY `idx_pembinaan46_petugas` (`petugas_id`),
  ADD KEY `fk_pembinaan46_bk` (`rujukan_bk_id`),
  ADD KEY `fk_pembinaan46_created_by` (`created_by`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pengaturan_kunci` (`kunci`);

--
-- Indeks untuk tabel `perpustakaan_anggota`
--
ALTER TABLE `perpustakaan_anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_perpus_anggota_kode` (`kode_anggota`),
  ADD UNIQUE KEY `uk_perpus_anggota_siswa` (`siswa_id`),
  ADD UNIQUE KEY `uk_perpus_anggota_guru` (`guru_id`),
  ADD KEY `idx_perpus_anggota_status` (`status`);

--
-- Indeks untuk tabel `perpustakaan_buku`
--
ALTER TABLE `perpustakaan_buku`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_perpus_buku_kode` (`kode_buku`),
  ADD KEY `idx_perpus_buku_judul` (`judul`),
  ADD KEY `idx_perpus_buku_isbn` (`isbn`),
  ADD KEY `idx_perpus_buku_kategori` (`kategori_id`),
  ADD KEY `idx_perpus_buku_status` (`status`);

--
-- Indeks untuk tabel `perpustakaan_detail_peminjaman`
--
ALTER TABLE `perpustakaan_detail_peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perpus_detail_peminjaman` (`peminjaman_id`),
  ADD KEY `idx_perpus_detail_buku` (`buku_id`),
  ADD KEY `idx_perpus_detail_status` (`status`),
  ADD KEY `idx_perpus_detail_eksemplar` (`eksemplar_id`);

--
-- Indeks untuk tabel `perpustakaan_eksemplar`
--
ALTER TABLE `perpustakaan_eksemplar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_perpus_eksemplar_kode` (`kode_eksemplar`),
  ADD KEY `idx_perpus_eksemplar_buku` (`buku_id`),
  ADD KEY `idx_perpus_eksemplar_status` (`status`);

--
-- Indeks untuk tabel `perpustakaan_kategori`
--
ALTER TABLE `perpustakaan_kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_perpus_kategori_kode` (`kode`),
  ADD UNIQUE KEY `uk_perpus_kategori_nama` (`nama`),
  ADD KEY `idx_perpus_kategori_aktif` (`aktif`);

--
-- Indeks untuk tabel `perpustakaan_kunjungan`
--
ALTER TABLE `perpustakaan_kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_perpus_kunjungan_siswa_waktu` (`siswa_id`,`waktu_kunjungan`),
  ADD KEY `idx_perpus_kunjungan_waktu` (`waktu_kunjungan`),
  ADD KEY `idx_perpus_kunjungan_petugas` (`petugas_id`),
  ADD KEY `idx_perpus_kunjungan_tp_kelas` (`tahun_pelajaran_id`,`kelas_id`),
  ADD KEY `fk_perpus_kunjungan_kelas` (`kelas_id`);

--
-- Indeks untuk tabel `perpustakaan_peminjaman`
--
ALTER TABLE `perpustakaan_peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_perpus_peminjaman_nomor` (`nomor_transaksi`),
  ADD KEY `idx_perpus_peminjaman_anggota` (`anggota_id`),
  ADD KEY `idx_perpus_peminjaman_tanggal` (`tanggal_pinjam`),
  ADD KEY `idx_perpus_peminjaman_jatuh_tempo` (`tanggal_jatuh_tempo`),
  ADD KEY `idx_perpus_peminjaman_status` (`status`),
  ADD KEY `idx_perpus_peminjaman_petugas` (`petugas_id`);

--
-- Indeks untuk tabel `profil_sekolah`
--
ALTER TABLE `profil_sekolah`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_semester_tahun_nama` (`tahun_pelajaran_id`,`nama`),
  ADD KEY `idx_semester_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_semester_aktif` (`aktif`);

--
-- Indeks untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sesi_absensi_konteks_b1` (`tahun_pelajaran_id`,`semester_id`,`jenis_absensi_id`,`jenis_sesi_key`,`tanggal`),
  ADD KEY `idx_sesi_tanggal` (`tanggal`),
  ADD KEY `idx_sesi_status` (`status`),
  ADD KEY `idx_sesi_jenis` (`jenis_absensi_id`),
  ADD KEY `idx_sesi_sholat` (`jenis_sholat_id`),
  ADD KEY `idx_sesi_tahun_semester` (`tahun_pelajaran_id`,`semester_id`),
  ADD KEY `fk_sesi_semester` (`semester_id`),
  ADD KEY `fk_sesi_dibuka_oleh` (`dibuka_oleh`),
  ADD KEY `fk_sesi_ditutup_oleh` (`ditutup_oleh`),
  ADD KEY `idx_sesi_verifikasi` (`verifikasi_status`,`tanggal`),
  ADD KEY `fk_sesi_verifikasi_user_final` (`diverifikasi_oleh`),
  ADD KEY `fk_sesi_dikunci_user_final` (`dikunci_oleh`),
  ADD KEY `idx_sesi_absensi_status_sesi_b1` (`status_sesi`,`tanggal`,`jam_mulai`,`jam_selesai`),
  ADD KEY `fk_sesi_finalisasi_oleh` (`finalisasi_oleh`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_siswa_nis` (`nis`),
  ADD UNIQUE KEY `uk_siswa_kode_qr` (`kode_qr`),
  ADD UNIQUE KEY `uk_siswa_nisn` (`nisn`),
  ADD KEY `idx_siswa_nama` (`nama`),
  ADD KEY `idx_siswa_status` (`status`);

--
-- Indeks untuk tabel `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_siswa_kelas_satu_aktif` (`siswa_id`,`tahun_pelajaran_id`,`active_marker`),
  ADD KEY `idx_siswa_kelas_siswa` (`siswa_id`),
  ADD KEY `idx_siswa_kelas_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_siswa_kelas_kelas` (`kelas_id`),
  ADD KEY `idx_siswa_kelas_nomor` (`tahun_pelajaran_id`,`kelas_id`,`nomor_urut`);

--
-- Indeks untuk tabel `siswa_status_riwayat`
--
ALTER TABLE `siswa_status_riwayat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ssr_siswa` (`siswa_id`),
  ADD KEY `idx_ssr_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_ssr_status` (`status`),
  ADD KEY `fk_ssr_user` (`created_by`);

--
-- Indeks untuk tabel `tahun_pelajaran`
--
ALTER TABLE `tahun_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tahun_pelajaran_nama` (`nama`),
  ADD KEY `idx_tahun_pelajaran_aktif` (`aktif`);

--
-- Indeks untuk tabel `tugas_guru`
--
ALTER TABLE `tugas_guru`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tugas_guru_guru` (`guru_id`),
  ADD KEY `idx_tugas_guru_jenis` (`jenis_tugas_id`),
  ADD KEY `idx_tugas_guru_tahun` (`tahun_pelajaran_id`),
  ADD KEY `idx_tugas_guru_target` (`target_tipe`,`target_id`),
  ADD KEY `idx_tugas_guru_aktif` (`aktif`),
  ADD KEY `idx_p58_3_tg_semester_aktif` (`semester_id`,`aktif`,`mulai`,`selesai`,`guru_id`);

--
-- Indeks untuk tabel `tugas_guru_jadwal`
--
ALTER TABLE `tugas_guru_jadwal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_p58_3_tgj_hari` (`tugas_guru_id`,`hari`),
  ADD KEY `idx_p58_3_tgj_tugas` (`tugas_guru_id`);

--
-- Indeks untuk tabel `uks_kunjungan`
--
ALTER TABLE `uks_kunjungan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_uks_kunjungan_antrian_harian` (`tanggal`,`nomor_antrian`),
  ADD KEY `idx_uks_siswa` (`siswa_id`),
  ADD KEY `idx_uks_tanggal` (`tanggal`),
  ADD KEY `idx_uks_petugas` (`petugas_id`),
  ADD KEY `idx_uks_kunjungan_tp_kelas` (`tahun_pelajaran_id`,`kelas_id`),
  ADD KEY `fk_uks_kunjungan_kelas` (`kelas_id`),
  ADD KEY `idx_uks_kunjungan_status_tanggal` (`tanggal`,`status`,`nomor_antrian`);

--
-- Indeks untuk tabel `uks_kunjungan_obat`
--
ALTER TABLE `uks_kunjungan_obat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uks_kunjungan_obat_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_uks_kunjungan_obat_obat` (`obat_id`);

--
-- Indeks untuk tabel `uks_obat`
--
ALTER TABLE `uks_obat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_uks_obat_kode` (`kode`),
  ADD KEY `idx_uks_obat_aktif` (`aktif`),
  ADD KEY `idx_uks_obat_stok` (`stok_saat_ini`,`stok_minimum`),
  ADD KEY `idx_uks_obat_kadaluarsa` (`tanggal_kadaluarsa`);

--
-- Indeks untuk tabel `uks_petugas_absensi`
--
ALTER TABLE `uks_petugas_absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_p59_uks_absensi_jadwal_tanggal` (`petugas_jadwal_id`,`tanggal`),
  ADD UNIQUE KEY `uk_uks_absensi_petugas_tanggal` (`petugas_kunci`,`tanggal`),
  ADD KEY `idx_p59_uks_absensi_tanggal` (`tanggal`,`status`),
  ADD KEY `idx_p59_uks_absensi_verifikator` (`verified_by`),
  ADD KEY `idx_uks_absensi_guru_tanggal` (`guru_id`,`tanggal`),
  ADD KEY `idx_uks_absensi_siswa_tanggal` (`siswa_id`,`tanggal`);

--
-- Indeks untuk tabel `uks_petugas_jadwal`
--
ALTER TABLE `uks_petugas_jadwal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_p59_uks_petugas_hari` (`siswa_kelas_id`,`semester_id`,`hari`),
  ADD KEY `idx_p59_uks_petugas_siswa_kelas` (`siswa_kelas_id`),
  ADD KEY `idx_p59_uks_petugas_semester` (`semester_id`,`aktif`,`hari`),
  ADD KEY `idx_p59_uks_petugas_hari_aktif` (`semester_id`,`hari`,`aktif`,`jam_mulai`,`jam_selesai`);

--
-- Indeks untuk tabel `uks_rujukan`
--
ALTER TABLE `uks_rujukan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_uks_rujukan_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_uks_rujukan_siswa` (`siswa_id`),
  ADD KEY `idx_uks_rujukan_tanggal` (`tanggal`),
  ADD KEY `idx_uks_rujukan_tujuan` (`tujuan_jenis`),
  ADD KEY `idx_uks_rujukan_status` (`status`),
  ADD KEY `fk_uks_rujukan_guru` (`pendamping_guru_id`),
  ADD KEY `fk_uks_rujukan_user` (`created_by`);

--
-- Indeks untuk tabel `uks_stok_transaksi`
--
ALTER TABLE `uks_stok_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uks_stok_obat` (`obat_id`),
  ADD KEY `idx_uks_stok_tanggal` (`tanggal`),
  ADD KEY `idx_uks_stok_kunjungan` (`kunjungan_id`),
  ADD KEY `idx_uks_stok_user` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_username` (`username`),
  ADD UNIQUE KEY `uk_users_guru` (`guru_id`),
  ADD UNIQUE KEY `uk_users_siswa` (`siswa_id`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indeks untuk tabel `user_hak_akses`
--
ALTER TABLE `user_hak_akses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_hak_akses` (`user_id`,`hak_akses_id`),
  ADD KEY `idx_user_hak_user` (`user_id`),
  ADD KEY `idx_user_hak_akses` (`hak_akses_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT untuk tabel `absensi_perubahan`
--
ALTER TABLE `absensi_perubahan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `absensi_sholat`
--
ALTER TABLE `absensi_sholat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `absensi_sholat_perubahan`
--
ALTER TABLE `absensi_sholat_perubahan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `app_migration`
--
ALTER TABLE `app_migration`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=701;

--
-- AUTO_INCREMENT untuk tabel `bk_kunjungan`
--
ALTER TABLE `bk_kunjungan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `bk_layanan`
--
ALTER TABLE `bk_layanan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `catatan_bk`
--
ALTER TABLE `catatan_bk`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstrakurikuler`
--
ALTER TABLE `ekstrakurikuler`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `ekstra_absensi`
--
ALTER TABLE `ekstra_absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `ekstra_anggota`
--
ALTER TABLE `ekstra_anggota`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `ekstra_dokumentasi`
--
ALTER TABLE `ekstra_dokumentasi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstra_jadwal`
--
ALTER TABLE `ekstra_jadwal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `ekstra_jurnal`
--
ALTER TABLE `ekstra_jurnal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstra_jurnal_kendala`
--
ALTER TABLE `ekstra_jurnal_kendala`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstra_pembina_absensi`
--
ALTER TABLE `ekstra_pembina_absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstra_penilaian`
--
ALTER TABLE `ekstra_penilaian`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ekstra_sesi`
--
ALTER TABLE `ekstra_sesi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `hak_akses`
--
ALTER TABLE `hak_akses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `home_visit`
--
ALTER TABLE `home_visit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `izin_siswa`
--
ALTER TABLE `izin_siswa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kehadiran`
--
ALTER TABLE `jadwal_kehadiran`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `jadwal_sholat`
--
ALTER TABLE `jadwal_sholat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `jadwal_sholat_arsip_batch11`
--
ALTER TABLE `jadwal_sholat_arsip_batch11`
  MODIFY `arsip_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `jenis_absensi`
--
ALTER TABLE `jenis_absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT untuk tabel `jenis_sholat`
--
ALTER TABLE `jenis_sholat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `jenis_tugas`
--
ALTER TABLE `jenis_tugas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `kalender_kehadiran`
--
ALTER TABLE `kalender_kehadiran`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `konsultasi_bk`
--
ALTER TABLE `konsultasi_bk`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `master_kelas`
--
ALTER TABLE `master_kelas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `orang_tua`
--
ALTER TABLE `orang_tua`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `orang_tua_siswa`
--
ALTER TABLE `orang_tua_siswa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pelanggaran_poin_penyesuaian`
--
ALTER TABLE `pelanggaran_poin_penyesuaian`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pembinaan`
--
ALTER TABLE `pembinaan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembinaan_siswa`
--
ALTER TABLE `pembinaan_siswa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_anggota`
--
ALTER TABLE `perpustakaan_anggota`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_buku`
--
ALTER TABLE `perpustakaan_buku`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_detail_peminjaman`
--
ALTER TABLE `perpustakaan_detail_peminjaman`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_eksemplar`
--
ALTER TABLE `perpustakaan_eksemplar`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_kategori`
--
ALTER TABLE `perpustakaan_kategori`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_kunjungan`
--
ALTER TABLE `perpustakaan_kunjungan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `perpustakaan_peminjaman`
--
ALTER TABLE `perpustakaan_peminjaman`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `profil_sekolah`
--
ALTER TABLE `profil_sekolah`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `semester`
--
ALTER TABLE `semester`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT untuk tabel `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT untuk tabel `siswa_status_riwayat`
--
ALTER TABLE `siswa_status_riwayat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tahun_pelajaran`
--
ALTER TABLE `tahun_pelajaran`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tugas_guru`
--
ALTER TABLE `tugas_guru`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tugas_guru_jadwal`
--
ALTER TABLE `tugas_guru_jadwal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `uks_kunjungan`
--
ALTER TABLE `uks_kunjungan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `uks_kunjungan_obat`
--
ALTER TABLE `uks_kunjungan_obat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `uks_obat`
--
ALTER TABLE `uks_obat`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `uks_petugas_absensi`
--
ALTER TABLE `uks_petugas_absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `uks_petugas_jadwal`
--
ALTER TABLE `uks_petugas_jadwal`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `uks_rujukan`
--
ALTER TABLE `uks_rujukan`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `uks_stok_transaksi`
--
ALTER TABLE `uks_stok_transaksi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `user_hak_akses`
--
ALTER TABLE `user_hak_akses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_pulang_oleh` FOREIGN KEY (`pulang_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_scan_oleh` FOREIGN KEY (`scan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `sesi_absensi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_verifikasi_user_final` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_perubahan`
--
ALTER TABLE `absensi_perubahan`
  ADD CONSTRAINT `fk_absensi_perubahan_absensi` FOREIGN KEY (`absensi_id`) REFERENCES `absensi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_perubahan_user` FOREIGN KEY (`diubah_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_sholat`
--
ALTER TABLE `absensi_sholat`
  ADD CONSTRAINT `fk_absensi_sholat_sesi_final` FOREIGN KEY (`sesi_id`) REFERENCES `sesi_absensi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_sholat_siswa_final` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_sholat_user_final` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_sholat_perubahan`
--
ALTER TABLE `absensi_sholat_perubahan`
  ADD CONSTRAINT `fk_asp_perubahan_absensi_final` FOREIGN KEY (`absensi_sholat_id`) REFERENCES `absensi_sholat` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asp_perubahan_user_final` FOREIGN KEY (`diubah_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bk_kunjungan`
--
ALTER TABLE `bk_kunjungan`
  ADD CONSTRAINT `fk_bk_kunj_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_kunj_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_kunj_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_kunj_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_kunj_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bk_layanan`
--
ALTER TABLE `bk_layanan`
  ADD CONSTRAINT `fk_bk_layanan_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_layanan_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_layanan_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_layanan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bk_layanan_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `catatan_bk`
--
ALTER TABLE `catatan_bk`
  ADD CONSTRAINT `fk_catatan_bk_guru` FOREIGN KEY (`guru_bk_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_catatan_bk_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstrakurikuler`
--
ALTER TABLE `ekstrakurikuler`
  ADD CONSTRAINT `fk_ekstra_pembina` FOREIGN KEY (`pembina_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_absensi`
--
ALTER TABLE `ekstra_absensi`
  ADD CONSTRAINT `fk_ekstra_absensi_scan` FOREIGN KEY (`scan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_absensi_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `ekstra_sesi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_anggota`
--
ALTER TABLE `ekstra_anggota`
  ADD CONSTRAINT `fk_ekstra_anggota_ekstra` FOREIGN KEY (`ekstrakurikuler_id`) REFERENCES `ekstrakurikuler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_anggota_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_anggota_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_dokumentasi`
--
ALTER TABLE `ekstra_dokumentasi`
  ADD CONSTRAINT `fk_ekstra_dokumentasi_sesi_b2` FOREIGN KEY (`sesi_id`) REFERENCES `ekstra_sesi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_dokumentasi_user_b2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_jadwal`
--
ALTER TABLE `ekstra_jadwal`
  ADD CONSTRAINT `fk_ekstra_jadwal_ekstra` FOREIGN KEY (`ekstrakurikuler_id`) REFERENCES `ekstrakurikuler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_jadwal_tahun_b2` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_jurnal`
--
ALTER TABLE `ekstra_jurnal`
  ADD CONSTRAINT `fk_ekstra_jurnal_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `ekstra_sesi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_jurnal_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_jurnal_kendala`
--
ALTER TABLE `ekstra_jurnal_kendala`
  ADD CONSTRAINT `fk_ekstra_kendala_sesi_b2` FOREIGN KEY (`sesi_id`) REFERENCES `ekstra_sesi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_kendala_user_b2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_pembina_absensi`
--
ALTER TABLE `ekstra_pembina_absensi`
  ADD CONSTRAINT `fk_epa_guru_b2` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_epa_sesi_b1` FOREIGN KEY (`sesi_id`) REFERENCES `ekstra_sesi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_epa_user_b2` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_penilaian`
--
ALTER TABLE `ekstra_penilaian`
  ADD CONSTRAINT `fk_ekstra_penilaian_anggota` FOREIGN KEY (`ekstra_anggota_id`) REFERENCES `ekstra_anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_penilaian_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_penilaian_user` FOREIGN KEY (`dinilai_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ekstra_sesi`
--
ALTER TABLE `ekstra_sesi`
  ADD CONSTRAINT `fk_ekstra_sesi_dibuka` FOREIGN KEY (`dibuka_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_sesi_ditutup` FOREIGN KEY (`ditutup_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_sesi_ekstra` FOREIGN KEY (`ekstrakurikuler_id`) REFERENCES `ekstrakurikuler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_sesi_jadwal_b1` FOREIGN KEY (`jadwal_id`) REFERENCES `ekstra_jadwal` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_sesi_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ekstra_sesi_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `home_visit`
--
ALTER TABLE `home_visit`
  ADD CONSTRAINT `fk_home_visit47_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_home_visit_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_home_visit_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `izin_siswa`
--
ALTER TABLE `izin_siswa`
  ADD CONSTRAINT `fk_izin_siswa_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_izin_siswa_pengaju` FOREIGN KEY (`diajukan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_izin_siswa_penyetuju` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_izin_siswa_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_izin_siswa_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_kehadiran`
--
ALTER TABLE `jadwal_kehadiran`
  ADD CONSTRAINT `fk_jadwal_kehadiran_semester_b1` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_kehadiran_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_sholat`
--
ALTER TABLE `jadwal_sholat`
  ADD CONSTRAINT `fk_jadwal_sholat_jenis` FOREIGN KEY (`jenis_sholat_id`) REFERENCES `jenis_sholat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_sholat_semester_b1` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jadwal_sholat_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kalender_kehadiran`
--
ALTER TABLE `kalender_kehadiran`
  ADD CONSTRAINT `fk_kalender_kehadiran_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kalender_kehadiran_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `konsultasi_bk`
--
ALTER TABLE `konsultasi_bk`
  ADD CONSTRAINT `fk_kons50_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kons50_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kons50_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kons50_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD CONSTRAINT `fk_ortu52_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orang_tua_siswa`
--
ALTER TABLE `orang_tua_siswa`
  ADD CONSTRAINT `fk_ortu52_ot` FOREIGN KEY (`orang_tua_id`) REFERENCES `orang_tua` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ortu52_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggaran_poin_penyesuaian`
--
ALTER TABLE `pelanggaran_poin_penyesuaian`
  ADD CONSTRAINT `fk_penyesuaian_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penyesuaian_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penyesuaian_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penyesuaian_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggaran_siswa`
--
ALTER TABLE `pelanggaran_siswa`
  ADD CONSTRAINT `fk_pelanggaran_jenis` FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pelanggaran_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pelanggaran_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembinaan`
--
ALTER TABLE `pembinaan`
  ADD CONSTRAINT `fk_pembinaan_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembinaan_siswa`
--
ALTER TABLE `pembinaan_siswa`
  ADD CONSTRAINT `fk_pembinaan46_bk` FOREIGN KEY (`rujukan_bk_id`) REFERENCES `catatan_bk` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan46_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan46_pelanggaran` FOREIGN KEY (`pelanggaran_id`) REFERENCES `pelanggaran_siswa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan46_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan46_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembinaan46_tapel` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_anggota`
--
ALTER TABLE `perpustakaan_anggota`
  ADD CONSTRAINT `fk_perpus_anggota_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_anggota_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_buku`
--
ALTER TABLE `perpustakaan_buku`
  ADD CONSTRAINT `fk_perpus_buku_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `perpustakaan_kategori` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_detail_peminjaman`
--
ALTER TABLE `perpustakaan_detail_peminjaman`
  ADD CONSTRAINT `fk_perpus_detail_buku` FOREIGN KEY (`buku_id`) REFERENCES `perpustakaan_buku` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_detail_eksemplar` FOREIGN KEY (`eksemplar_id`) REFERENCES `perpustakaan_eksemplar` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_detail_peminjaman` FOREIGN KEY (`peminjaman_id`) REFERENCES `perpustakaan_peminjaman` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_eksemplar`
--
ALTER TABLE `perpustakaan_eksemplar`
  ADD CONSTRAINT `fk_perpus_eksemplar_buku` FOREIGN KEY (`buku_id`) REFERENCES `perpustakaan_buku` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_kunjungan`
--
ALTER TABLE `perpustakaan_kunjungan`
  ADD CONSTRAINT `fk_perpus_kunjungan_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_kunjungan_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_kunjungan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_kunjungan_tp` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `perpustakaan_peminjaman`
--
ALTER TABLE `perpustakaan_peminjaman`
  ADD CONSTRAINT `fk_perpus_peminjaman_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `perpustakaan_anggota` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perpus_peminjaman_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `semester`
--
ALTER TABLE `semester`
  ADD CONSTRAINT `fk_semester_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `sesi_absensi`
--
ALTER TABLE `sesi_absensi`
  ADD CONSTRAINT `fk_sesi_dibuka_oleh` FOREIGN KEY (`dibuka_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_dikunci_user_final` FOREIGN KEY (`dikunci_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_ditutup_oleh` FOREIGN KEY (`ditutup_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_finalisasi_oleh` FOREIGN KEY (`finalisasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_jenis_absensi` FOREIGN KEY (`jenis_absensi_id`) REFERENCES `jenis_absensi` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_jenis_sholat` FOREIGN KEY (`jenis_sholat_id`) REFERENCES `jenis_sholat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesi_verifikasi_user_final` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa_kelas`
--
ALTER TABLE `siswa_kelas`
  ADD CONSTRAINT `fk_siswa_kelas_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_siswa_kelas_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_siswa_kelas_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa_status_riwayat`
--
ALTER TABLE `siswa_status_riwayat`
  ADD CONSTRAINT `fk_ssr_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ssr_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ssr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tugas_guru`
--
ALTER TABLE `tugas_guru`
  ADD CONSTRAINT `fk_p58_3_tugas_guru_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_guru_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_guru_jenis` FOREIGN KEY (`jenis_tugas_id`) REFERENCES `jenis_tugas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tugas_guru_tahun` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tugas_guru_jadwal`
--
ALTER TABLE `tugas_guru_jadwal`
  ADD CONSTRAINT `fk_p58_3_tgj_tugas` FOREIGN KEY (`tugas_guru_id`) REFERENCES `tugas_guru` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_kunjungan`
--
ALTER TABLE `uks_kunjungan`
  ADD CONSTRAINT `fk_uks_kunjungan_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `master_kelas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_kunjungan_tp` FOREIGN KEY (`tahun_pelajaran_id`) REFERENCES `tahun_pelajaran` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_petugas` FOREIGN KEY (`petugas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_kunjungan_obat`
--
ALTER TABLE `uks_kunjungan_obat`
  ADD CONSTRAINT `fk_uks_kunjungan_obat_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `uks_kunjungan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_kunjungan_obat_obat` FOREIGN KEY (`obat_id`) REFERENCES `uks_obat` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_petugas_absensi`
--
ALTER TABLE `uks_petugas_absensi`
  ADD CONSTRAINT `fk_p59_uks_absensi_jadwal` FOREIGN KEY (`petugas_jadwal_id`) REFERENCES `uks_petugas_jadwal` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_p59_uks_absensi_verifikator` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_absensi_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_petugas_jadwal`
--
ALTER TABLE `uks_petugas_jadwal`
  ADD CONSTRAINT `fk_p59_uks_petugas_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_p59_uks_petugas_siswa_kelas` FOREIGN KEY (`siswa_kelas_id`) REFERENCES `siswa_kelas` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_rujukan`
--
ALTER TABLE `uks_rujukan`
  ADD CONSTRAINT `fk_uks_rujukan_guru` FOREIGN KEY (`pendamping_guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_rujukan_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `uks_kunjungan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_rujukan_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_rujukan_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `uks_stok_transaksi`
--
ALTER TABLE `uks_stok_transaksi`
  ADD CONSTRAINT `fk_uks_stok_kunjungan` FOREIGN KEY (`kunjungan_id`) REFERENCES `uks_kunjungan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_stok_obat` FOREIGN KEY (`obat_id`) REFERENCES `uks_obat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uks_stok_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_hak_akses`
--
ALTER TABLE `user_hak_akses`
  ADD CONSTRAINT `fk_user_hak_akses` FOREIGN KEY (`hak_akses_id`) REFERENCES `hak_akses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_hak_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
