-- Batch 2: lifecycle sesi otomatis DRAFT -> OPEN -> CLOSED.
-- Tidak diperlukan perubahan data manual. Kode aplikasi menghitung lifecycle
-- berdasarkan waktu PHP timezone Asia/Jakarta setiap kali sesi diminta.
-- Migration ini hanya mencatat bahwa fondasi lifecycle Batch 2 telah dipasang.

INSERT INTO app_migration (migration_key,keterangan)
SELECT 'BATCH2_SESSION_LIFECYCLE',
       'Lifecycle sesi otomatis DRAFT sebelum jam mulai, OPEN saat jadwal berlangsung, CLOSED setelah jam selesai; WIB dan tanpa cron.'
WHERE NOT EXISTS (
    SELECT 1 FROM app_migration WHERE migration_key='BATCH2_SESSION_LIFECYCLE'
);
