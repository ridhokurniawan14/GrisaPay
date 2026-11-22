<?php

namespace App\Support;

/**
 * Warna tema dari database disuntikkan ke CSS panel, jadi HARUS dipastikan berformat #RRGGBB
 * sebelum dipakai. Nilai yang tidak valid diganti warna bawaan.
 */
final class ThemeColor
{
    public const DEFAULT = '#6366f1';

    // \z (bukan $) agar baris baru di ujung teks tidak ikut lolos.
    public const PATTERN = '/^#[0-9a-fA-F]{6}\z/';

    public static function sanitize(mixed $value): string
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1
            ? strtolower($value)
            : self::DEFAULT;
    }
}
