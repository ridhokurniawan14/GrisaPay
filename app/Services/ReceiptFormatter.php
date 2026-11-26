<?php

namespace App\Services;

/**
 * Menyusun isi struk sebagai baris teks lebar tetap (untuk printer dot matrix yang mencetak teks).
 * Murni PHP tanpa database, sehingga mudah diuji.
 */
final class ReceiptFormatter
{
    /** Awalan penanda baris tebal (dibaca halaman cetak, lalu dihapus). */
    public const BOLD = "\x01";

    /**
     * @param array{
     *   name: string, address: string, footer: string, receipt_no: string, paid_at: string,
     *   student: string, nisn: string, classroom: string,
     *   rows: list<array{label: string, paid: int, remaining: int}>,
     *   total_paid: int, total_remaining: int, method: string, payer: string, cashier: string, copy: int
     * } $d
     * @return list<string>
     */
    public static function lines(array $d, int $cols = 40): array
    {
        $cols = max(32, $cols);
        $out = [];
        $eq = str_repeat('=', $cols);
        $dash = str_repeat('-', $cols);

        foreach (self::wrap($d['name'], $cols) as $l) {
            $out[] = self::bold(self::center($l, $cols));
        }

        foreach (self::wrap($d['address'], $cols) as $l) {
            $out[] = self::center($l, $cols);
        }

        $out[] = $eq;
        $out[] = self::bold(self::center('KWITANSI PEMBAYARAN', $cols));
        $out[] = $dash;

        foreach ([
            ['No', $d['receipt_no'], true], ['Tgl', $d['paid_at'], false], ['Nama', $d['student'], true],
            ['NISN', $d['nisn'], false], ['Kelas', $d['classroom'], false],
        ] as [$label, $value, $strong]) {
            foreach (self::field($label, $value, $cols) as $line) {
                $out[] = $strong ? self::bold($line) : $line;
            }
        }

        $out[] = $dash;

        $labelW = $cols - 24;
        $out[] = self::pad('Kategori', $labelW).' '.self::padLeft('Bayar', 11).' '.self::padLeft('Sisa', 11);

        foreach ($d['rows'] as $row) {
            $label = str_replace('Sebelumnya', 'Sblm.', $row['label']);
            $out[] = self::pad(self::cut($label, $labelW), $labelW).' '
                .self::padLeft(self::money($row['paid']), 11).' '
                .self::padLeft(self::money($row['remaining']), 11);
        }

        $out[] = $dash;
        $out[] = self::bold(self::pair('TOTAL DIBAYAR', 'Rp '.self::money($d['total_paid']), $cols));
        $out[] = self::bold(self::pair('SISA BELUM TERBAYAR', 'Rp '.self::money($d['total_remaining']), $cols));
        $out[] = $dash;

        foreach ([['Metode', $d['method']], ['Penyetor', $d['payer']], ['Petugas', $d['cashier']]] as [$label, $value]) {
            if ($value !== '') {
                array_push($out, ...self::field($label, $value, $cols));
            }
        }

        $out[] = $eq;

        if ($d['footer'] !== '') {
            foreach (self::wrap($d['footer'], $cols) as $l) {
                $out[] = self::center($l, $cols);
            }
        }

        if ($d['copy'] >= 1) {
            $out[] = '';
            $out[] = self::bold(self::center('** CETAK ULANG KE-'.$d['copy'].' **', $cols));
        }

        return $out;
    }

    private static function bold(string $line): string
    {
        return self::BOLD.$line;
    }

    public static function money(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /** @return list<string> */
    private static function field(string $label, string $value, int $cols): array
    {
        $head = self::pad($label, 8).': ';
        $width = $cols - 10;
        $parts = self::wrap($value === '' ? '-' : $value, $width);
        $lines = [];

        foreach ($parts as $i => $part) {
            $lines[] = ($i === 0 ? $head : str_repeat(' ', 10)).$part;
        }

        return $lines;
    }

    private static function pair(string $left, string $right, int $cols): string
    {
        $space = max(1, $cols - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $space).$right;
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return [];
        }

        $lines = [];
        $current = '';

        foreach (explode(' ', $text) as $word) {
            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }

            if ($current === '') {
                $current = $word;
            } elseif (mb_strlen($current) + 1 + mb_strlen($word) <= $width) {
                $current .= ' '.$word;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function center(string $text, int $cols): string
    {
        $len = mb_strlen($text);

        return $len >= $cols ? $text : str_repeat(' ', intdiv($cols - $len, 2)).$text;
    }

    private static function pad(string $text, int $width): string
    {
        return $text.str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    private static function padLeft(string $text, int $width): string
    {
        return str_repeat(' ', max(0, $width - mb_strlen($text))).$text;
    }

    private static function cut(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, $width);
    }
}
