<?php

namespace App\Services;

/**
 * Membaca baris lembar kerja (array dua dimensi) menjadi baris tunggakan yang rapi + daftar kesalahan.
 * Murni PHP; pembacaan berkas Excel dilakukan di ArrearImporter.
 */
final class ArrearRowParser
{
    private const ALIASES = [
        'nisn' => ['nisn'],
        'year' => ['tahun ajaran', 'tahun_ajaran', 'tahunajaran', 'ta', 'tahun'],
        'category' => ['kategori', 'category'],
        'note' => ['keterangan', 'ket', 'catatan'],
        'amount' => ['jumlah', 'nominal', 'tunggakan', 'kekurangan', 'sisa'],
    ];

    /**
     * @param  list<list<mixed>>  $sheet
     * @return array{rows: list<array{line: int, nisn: string, year: string, category: ?string, note: ?string, amount: int}>, errors: list<string>}
     */
    public static function parse(array $sheet): array
    {
        $headerAt = null;
        $map = [];

        foreach (array_slice($sheet, 0, 15, true) as $i => $row) {
            $found = self::mapHeader($row);

            if (isset($found['nisn'], $found['year'], $found['amount'])) {
                $headerAt = $i;
                $map = $found;
                break;
            }
        }

        if ($headerAt === null) {
            return ['rows' => [], 'errors' => ['Baris judul tidak ditemukan. Kolom wajib: NISN, Tahun Ajaran, Jumlah.']];
        }

        $rows = [];
        $errors = [];

        foreach ($sheet as $i => $row) {
            if ($i <= $headerAt) {
                continue;
            }

            $line = $i + 1;
            $cell = fn (string $key): mixed => isset($map[$key]) ? ($row[$map[$key]] ?? null) : null;

            if (self::blank($row)) {
                continue;
            }

            $nisn = self::nisn($cell('nisn'));
            $year = self::year($cell('year'));
            $amount = AmountParser::parse($cell('amount'));

            if ($nisn === '') {
                $errors[] = "Baris {$line}: NISN kosong";

                continue;
            }

            if ($year === null) {
                $errors[] = "Baris {$line}: tahun ajaran tidak valid (contoh 2025/2026)";

                continue;
            }

            if ($amount === null || $amount < 1) {
                $errors[] = "Baris {$line}: jumlah tidak valid atau 0";

                continue;
            }

            $category = trim((string) $cell('category'));
            $note = trim((string) $cell('note'));

            $rows[] = [
                'line' => $line,
                'nisn' => $nisn,
                'year' => $year,
                'category' => $category === '' ? null : $category,
                'note' => $note === '' ? null : $note,
                'amount' => $amount,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** @return array<string, int> */
    private static function mapHeader(array $row): array
    {
        $found = [];

        foreach ($row as $col => $value) {
            $label = mb_strtolower(trim((string) $value));

            foreach (self::ALIASES as $key => $aliases) {
                if (! isset($found[$key]) && in_array($label, $aliases, true)) {
                    $found[$key] = (int) $col;
                }
            }
        }

        return $found;
    }

    private static function blank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** NISN yang tersimpan sebagai angka di Excel kehilangan nol di depan: lengkapi sampai 10 digit. */
    private static function nisn(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $s = (string) (int) $value;

            return strlen($s) < 10 ? str_pad($s, 10, '0', STR_PAD_LEFT) : $s;
        }

        $s = trim((string) $value);

        return preg_match('/^\d{1,9}$/', $s) ? str_pad($s, 10, '0', STR_PAD_LEFT) : $s;
    }

    private static function year(mixed $value): ?string
    {
        if (preg_match('/^\s*(\d{4})\s*[\/\-]\s*(\d{4})\s*$/', (string) $value, $m) && (int) $m[2] === (int) $m[1] + 1) {
            return $m[1].'/'.$m[2];
        }

        return null;
    }
}
