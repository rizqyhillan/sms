<?php
declare(strict_types=1);

/**
 * Identitas sekolah terpusat.
 * Semua modul menggunakan helper ini agar logo/nama/alamat tidak hard-code.
 */
function school_profile(PDO $pdo): array {
    static $profile = null;
    if ($profile !== null) return $profile;
    try {
        $profile = $pdo->query('SELECT * FROM profil_sekolah ORDER BY id LIMIT 1')->fetch() ?: [];
    } catch (Throwable $e) {
        $profile = [];
    }
    $profile['nama_sekolah'] = $profile['nama_sekolah'] ?? (defined('NAMA_SEKOLAH') ? NAMA_SEKOLAH : 'Sekolah');
    return $profile;
}
function school_asset_url(?string $path): string {
    if (!$path) return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}
function school_logo_url(PDO $pdo): string { return school_asset_url(school_profile($pdo)['logo'] ?? null); }
function school_photo_url(PDO $pdo): string { return school_asset_url(school_profile($pdo)['foto'] ?? null); }
