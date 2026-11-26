<?php

namespace App\Services;

/** Membaca nominal rupiah dari sel Excel: 1500000, "1.500.000", "Rp 1.500.000,00", "1,500,000". */
final class AmountParser
{
    public static function parse(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (! is_string($value)) {
            return null;
        }

        $s = trim(str_ireplace(['rp', ' ', "\xC2\xA0"], '', $value));

        if ($s === '' || ! preg_match('/^[\d.,]+$/', $s)) {
            return null;
        }

        if (preg_match('/^\d+$/', $s)) {
            return (int) $s;
        }

        // 1.500.000,00 atau 1,500,000.00 -> buang desimal di belakang, lalu pemisah ribuan
        if (preg_match('/^(\d{1,3}(?:[.,]\d{3})+)[.,]\d{1,2}$/', $s, $m)) {
            return (int) preg_replace('/\D/', '', $m[1]);
        }

        // 1.500.000 atau 1,500,000
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $s)) {
            return (int) preg_replace('/\D/', '', $s);
        }

        // 1500,50 atau 1500.5
        if (preg_match('/^(\d+)[.,]\d{1,2}$/', $s, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
