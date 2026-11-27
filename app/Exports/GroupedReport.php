<?php

namespace App\Exports;

use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan berkepala dua tingkat (kategori digabung, rincian di bawahnya).
 *
 * lead  : kolom tetap di kiri  ['key','label','type' => text|int|money,'width','align']
 * groups: kolom uang bergrup   [['label' => 'PERSONAL', 'columns' => [['key','label','width'], ...]], ...]
 *         grup berkolom 1 dirender satu kolom (header menyatu dua baris); grup berkolom >1 = header gabungan + sub-header.
 */
final class GroupedReport
{
    /**
     * @param  list<string>  $subtitle
     * @param  list<array<string, mixed>>  $lead
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{role: string, name: string, nip: string}>  $signers  blok tanda tangan, kiri ke kanan
     */
    public function __construct(
        public readonly string $title,
        public readonly string $filename,
        public readonly array $subtitle,
        public readonly array $lead,
        public readonly array $groups,
        public readonly array $rows,
        public readonly array $signers = [],
    ) {
    }

    /** @return list<array<string, mixed>> semua kolom data berurutan (kiri ke kanan) */
    public function columns(): array
    {
        $cols = $this->lead;

        foreach ($this->groups as $g) {
            foreach ($g['columns'] as $c) {
                $cols[] = $c + ['type' => 'money'];
            }
        }

        return $cols;
    }

    /** @return array<string, int> jumlah tiap kolom uang */
    public function totals(): array
    {
        $totals = [];

        foreach ($this->columns() as $c) {
            if (($c['type'] ?? 'text') === 'money') {
                $totals[$c['key']] = (int) array_sum(array_column($this->rows, $c['key']));
            }
        }

        return $totals;
    }

    public function download(string $format): Response
    {
        @ini_set('memory_limit', '768M');
        @set_time_limit(300);

        $meta = [
            'generated_by' => (string) (auth()->user()?->name ?? '-'),
            'generated_at' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'generated_date' => now()->locale('id')->translatedFormat('d F Y'),
        ];

        activity('ekspor')
            ->causedBy(auth()->user())
            ->withProperties([
                'laporan' => $this->title,
                'format' => $format === 'pdf' ? 'PDF' : 'Excel',
                'jumlah_baris' => count($this->rows),
                'filter' => $this->subtitle,
            ])
            ->log('Unduh laporan');

        return $format === 'pdf'
            ? GroupedPdfReport::download($this, $meta)
            : GroupedExcelReport::download($this, $meta);
    }
}
