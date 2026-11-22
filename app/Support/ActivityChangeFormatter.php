<?php

namespace App\Support;

/**
 * Mengubah kolom attribute_changes milik Spatie Activitylog v5
 * (['attributes' => nilai baru, 'old' => nilai lama]) menjadi baris yang mudah dibaca.
 */
final class ActivityChangeFormatter
{
    private const LABELS = [
        'name' => 'Nama',
        'email' => 'Email',
        'is_active' => 'Akun aktif',
        'short_name' => 'Nama singkat',
        'npsn' => 'NPSN',
        'address' => 'Alamat',
        'city' => 'Kota',
        'phone' => 'Telepon',
        'website' => 'Website',
        'logo_path' => 'Logo',
        'primary_color' => 'Warna tema',
        'principal_name' => 'Nama kepala sekolah',
        'principal_nip' => 'NIP kepala sekolah',
        'principal_signature_path' => 'Tanda tangan kepala sekolah',
        'treasurer_name' => 'Nama bendahara',
        'treasurer_nip' => 'NIP bendahara',
        'treasurer_signature_path' => 'Tanda tangan bendahara',
        'bank_name' => 'Nama bank',
        'bank_account_no' => 'No. rekening',
        'bank_account_name' => 'Atas nama rekening',
        'qris_image_path' => 'Gambar QRIS',
        'receipt_footer' => 'Teks kaki kwitansi',
    ];

    /** @return list<string> */
    public static function lines(mixed $changes): array
    {
        if (is_object($changes) && method_exists($changes, 'toArray')) {
            $changes = $changes->toArray();
        }

        $changes = is_array($changes) ? $changes : [];
        $new = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];
        $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];

        $lines = [];

        foreach ($new as $key => $value) {
            $label = self::LABELS[$key] ?? (string) $key;

            $lines[] = array_key_exists($key, $old)
                ? "{$label}: " . self::show($old[$key]) . ' → ' . self::show($value)
                : "{$label}: " . self::show($value);
        }

        foreach (array_diff_key($old, $new) as $key => $value) {
            $lines[] = (self::LABELS[$key] ?? (string) $key) . ': ' . self::show($value) . ' → (dihapus)';
        }

        return $lines;
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '(kosong)',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => mb_strimwidth((string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 60, '…'),
            default => mb_strimwidth((string) $value, 0, 60, '…'),
        };
    }
}
