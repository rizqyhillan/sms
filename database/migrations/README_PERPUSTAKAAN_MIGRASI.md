# README Migrasi Perpustakaan — PART84

Dokumen ini adalah panduan resmi urutan migrasi modul **Perpustakaan** pada APK Sekolah.

## 1. Baseline

Baseline aplikasi: **PART83**.

PART84 hanya menambahkan dokumentasi migrasi dan backup/restore. Tidak mengubah alur operasional, tabel, query, atau logika modul Perpustakaan.

## 2. Instalasi baru (fresh database)

Gunakan urutan berikut:

1. Buat database `absensi_sekolah_v2`.
2. Import schema dasar:
   - `absensi_sekolah_v2.sql`
3. Pastikan import schema dasar selesai tanpa error.
4. Jalankan migrasi Perpustakaan:
   - `database/migrations/PART76_PERPUSTAKAAN_EKSEMPLAR_UNIK.sql`
5. Setelah PART76 selesai, jalankan:
   - `database/migrations/PART79_PERPUSTAKAAN_REKONSILIASI_TRANSAKSI_LAMA.sql`
6. Login ke aplikasi dan lakukan pemeriksaan dasar modul Perpustakaan.

### Urutan wajib

```text
absensi_sekolah_v2.sql
        ↓
PART76_PERPUSTAKAAN_EKSEMPLAR_UNIK.sql
        ↓
PART79_PERPUSTAKAAN_REKONSILIASI_TRANSAKSI_LAMA.sql
        ↓
Aplikasi siap digunakan
```

**Jangan menjalankan PART79 sebelum PART76**, karena PART79 menggunakan tabel dan kolom `perpustakaan_eksemplar`/`eksemplar_id` yang disiapkan oleh PART76.

## 3. Database hasil backup penuh

Jika backup dibuat dari database yang sudah lengkap dan backup tersebut berisi **schema + data** seluruh database, maka:

- cukup restore dump database;
- tidak perlu menjalankan PART76 lagi;
- tidak perlu menjalankan PART79 lagi;
- jangan menjalankan migrasi ulang hanya karena proses restore selesai.

Alasannya: struktur tabel dan data hasil migrasi sudah berada di dalam dump backup.

## 4. Backup yang disarankan

Sebelum melakukan migrasi pada database yang sudah berjalan, buat backup:

### Backup database

Contoh:

```bash
mysqldump -u root -p --routines --triggers --events absensi_sekolah_v2 > backup_absensi_sekolah_v2.sql
```

Jika environment tidak menggunakan password root, sesuaikan perintahnya.

### Backup file aplikasi/uploads

Simpan juga salinan folder aplikasi beserta folder upload yang digunakan aplikasi.

Database backup dan backup file aplikasi/uploads sebaiknya disimpan sebagai satu paket tanggal/waktu yang sama agar mudah dipulihkan sebagai satu versi sistem.

## 5. Checklist restore

### A. Sebelum restore

- [ ] Pastikan backup yang dipilih adalah backup yang benar.
- [ ] Simpan safety backup database saat ini.
- [ ] Simpan safety backup folder upload saat ini.
- [ ] Pastikan aplikasi tidak sedang melakukan transaksi penting.

### B. Restore database

- [ ] Restore file dump database.
- [ ] Pastikan proses restore selesai tanpa error.
- [ ] Pastikan tabel `perpustakaan_buku` tersedia.
- [ ] Pastikan tabel `perpustakaan_eksemplar` tersedia.
- [ ] Pastikan `perpustakaan_detail_peminjaman.eksemplar_id` tersedia.
- [ ] Pastikan data peminjaman dan data eksemplar tetap ada.

### C. Restore file

- [ ] Restore folder upload dari backup pasangan yang sama.
- [ ] Pastikan logo sekolah dan file pendukung masih tersedia.
- [ ] Pastikan permission folder sesuai environment server.

### D. Verifikasi aplikasi

- [ ] Login berhasil.
- [ ] Data Buku dapat dibuka.
- [ ] Data Eksemplar/QR dapat dibuka.
- [ ] Pengunjung dapat dibuka.
- [ ] Peminjaman dapat dibuka.
- [ ] Pengembalian dapat dibuka.
- [ ] Laporan Perpustakaan dapat dibuka.
- [ ] Data transaksi lama tetap terbaca.
- [ ] Tidak ada error PHP/database pada halaman yang diuji.

## 6. Verifikasi instalasi baru

Setelah fresh install, pemeriksaan minimum:

```sql
SHOW TABLES LIKE 'perpustakaan_%';

SHOW COLUMNS FROM perpustakaan_detail_peminjaman LIKE 'eksemplar_id';

SELECT COUNT(*) AS total_eksemplar
FROM perpustakaan_eksemplar;
```

Jika database memiliki data buku, periksa juga:

```sql
SELECT
    b.id,
    b.kode_buku,
    b.stok,
    COUNT(e.id) AS total_eksemplar,
    SUM(e.status='tersedia') AS tersedia
FROM perpustakaan_buku b
LEFT JOIN perpustakaan_eksemplar e ON e.buku_id=b.id
GROUP BY b.id, b.kode_buku, b.stok;
```

Nilai `stok` pada baseline ini mengikuti jumlah eksemplar dengan status `tersedia`.

## 7. Catatan migrasi lama

Folder `database/archive/migrations/` berisi migrasi lama/obsolete dan **bukan bagian dari urutan migrasi Perpustakaan PART76 → PART79**.

Jangan menjalankan file archive secara acak pada database final.

## 8. Ringkasan keputusan

| Kondisi | Tindakan |
|---|---|
| Fresh install | Base SQL → PART76 → PART79 |
| Restore full backup schema + data | Restore saja, tidak perlu migrasi ulang |
| Sebelum migrasi pada database aktif | Backup database + uploads |
| PART79 | Hanya setelah PART76 |
| Migration archive | Jangan dijalankan sebagai migrasi Perpustakaan final |

---

**PART84 — Dokumentasi finalisasi migrasi & backup/restore Perpustakaan**
