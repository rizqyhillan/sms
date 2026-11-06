<?php
declare(strict_types=1);

/**
 * PART58.2 - Registry terpusat untuk role + tugas tambahan.
 *
 * File ini sengaja menjadi satu-satunya tempat untuk mendefinisikan:
 * - kode tugas yang memberikan fungsi tertentu;
 * - target data tugas (kelas/ekstrakurikuler/dll);
 * - alias kode tugas yang memiliki fungsi sama.
 *
 * Penambahan jenis tugas baru di tabel jenis_tugas tidak otomatis membuka
 * akses apa pun. Kode baru harus dipetakan ke capability di sini terlebih
 * dahulu. Prinsip fail-closed ini mencegah tugas baru memperoleh akses
 * secara tidak sengaja.
 */
function task_registry(): array
{
    static $registry = null;
    if ($registry !== null) return $registry;

    $registry = [
        'WALI_KELAS' => [
            'nama' => 'Wali Kelas',
            'capabilities' => ['wali_kelas'],
            'target_type' => 'kelas',
        ],

        // Dua kode lama/baru tetap diperlakukan sebagai fungsi piket yang sama.
        'PETUGAS_PIKET' => [
            'nama' => 'Petugas Piket',
            'capabilities' => ['piket'],
            'target_type' => null,
        ],
        'GURU_PIKET' => [
            'nama' => 'Guru Piket',
            'capabilities' => ['piket'],
            'target_type' => null,
        ],

        // Semua variasi pembina ekstra memakai capability yang sama.
        'PEMBINA_EKSTRA' => [
            'nama' => 'Pembina Ekstrakurikuler',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],
        'KOORDINATOR_EKSTRA' => [
            'nama' => 'Koordinator Ekstrakurikuler',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],
        'PEMBINA_PRAMUKA' => [
            'nama' => 'Pembina Pramuka',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],
        'PEMBINA_PMR' => [
            'nama' => 'Pembina PMR',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],
        'PEMBINA_BASKET' => [
            'nama' => 'Pembina Basket',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],
        'PEMBINA_FUTSAL' => [
            'nama' => 'Pembina Futsal',
            'capabilities' => ['ekstrakurikuler'],
            'target_type' => 'ekstrakurikuler',
        ],

        'PETUGAS_UKS' => [
            'nama' => 'Petugas UKS',
            'capabilities' => ['uks'],
            'target_type' => null,
        ],
        'PETUGAS_PERPUSTAKAAN' => [
            'nama' => 'Petugas Perpustakaan',
            'capabilities' => ['perpustakaan'],
            'target_type' => null,
        ],
        'PEMBINA_OSIS' => [
            'nama' => 'Pembina OSIS',
            'capabilities' => ['osis'],
            'target_type' => null,
        ],
        'KOORDINATOR_KEGIATAN' => [
            'nama' => 'Koordinator Kegiatan',
            'capabilities' => ['koordinator_kegiatan'],
            'target_type' => null,
        ],
        'KOORDINATOR' => [
            'nama' => 'Koordinator',
            'capabilities' => ['koordinator'],
            'target_type' => null,
        ],
        'KOORDINATOR_LAB' => [
            'nama' => 'Koordinator Laboratorium',
            'capabilities' => ['laboratorium'],
            'target_type' => null,
        ],
        'KEPALA_LAB' => [
            'nama' => 'Kepala Laboratorium',
            'capabilities' => ['laboratorium'],
            'target_type' => null,
        ],

        // GURU_BK tetap dikontrol oleh role guru_bk, bukan penugasan baru.
        // TUGAS_LAIN sengaja tidak mempunyai capability.
    ];

    return $registry;
}

function task_registry_entry(string $kode): ?array
{
    $kode = strtoupper(trim($kode));
    return task_registry()[$kode] ?? null;
}

function task_registry_capability_codes(string $capability): array
{
    $capability = strtolower(trim($capability));
    $out = [];
    foreach (task_registry() as $kode => $entry) {
        if (in_array($capability, $entry['capabilities'] ?? [], true)) {
            $out[] = $kode;
        }
    }
    return $out;
}

function task_registry_capabilities(): array
{
    $out = [];
    foreach (task_registry() as $entry) {
        foreach (($entry['capabilities'] ?? []) as $capability) {
            $out[$capability] = true;
        }
    }
    return array_keys($out);
}
