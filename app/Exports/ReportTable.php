<?php

namespace App\Exports;

/**
 * Bentuk data laporan yang netral format: dipakai bersama oleh penulis Excel dan PDF.
 *
 * Kolom: ['key' => string, 'label' => string, 'type' => 'text'|'int'|'money', 'width' => int (lebar Excel),
 *         'pdf' => bool (tampil di PDF; default true), 'align' => 'left'|'center'|'right' (opsional)]
 */
final class ReportTable
{
    /**
     * @param  list<string>  $subtitle  baris keterangan di bawah judul (filter, periode, dst.)
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $totalKeys  kolom uang/angka yang dijumlahkan di baris Total
     */
    public function __construct(
        public readonly string $title,
        public readonly string $filename,
        public readonly array $subtitle,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $totalKeys = [],
        public readonly ?string $statusKey = null,
    ) {
    }

    /** @return array<string, int> */
    public function totals(): array
    {
        $totals = [];

        foreach ($this->totalKeys as $key) {
            $totals[$key] = (int) array_sum(array_column($this->rows, $key));
        }

        return $totals;
    }

    /** @return list<array<string, mixed>> */
    public function pdfColumns(): array
    {
        return array_values(array_filter($this->columns, fn (array $c): bool => ($c['pdf'] ?? true) === true));
    }
}
