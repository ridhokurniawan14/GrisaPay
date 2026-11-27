<?php

namespace App\Exports;

use App\Models\FeeCategory;
use App\Models\FeeComponent;
use App\Models\Payment;
use App\Services\CashRecap;
use Illuminate\Support\Collection;

/**
 * Buku penerimaan: satu baris per pembayaran sah, kolom uang dipecah per rincian tarif.
 * Kolom DINAMIS — dibentuk dari tarif yang ada (kategori & nama rincian), bukan daftar tetap:
 *  - Tunggakan: dipecah menurut kelas siswa SAAT INI (Alumni, XI, XII; X hanya bila ada)
 *  - Rincian tarif: satu kolom per nama rincian (nama sama di beberapa tingkat = satu kolom), urut menurut kategori
 * Jumlah semua kolom pecahan selalu sama dengan kolom Jumlah.
 */
final class ReceiptLedgerReport
{
    private const ARREAR_LABELS = [0 => 'Tunggakan Alumni', 10 => 'Tunggakan Kelas X', 11 => 'Tunggakan Kelas XI', 12 => 'Tunggakan Kelas XII'];

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<string>  $subtitle
     */
    public static function build(Collection $payments, array $subtitle, string $stamp): ReportTable
    {
        $sah = $payments->where('status', Payment::PAID)->values();
        $place = CashRecap::placement($sah);

        // Tunggakan = item kategori tunggakan, atau item dari tagihan tahun ajaran lain daripada tahun pembayaran.
        $isArrear = fn (Payment $p, $a): bool => ($a->billItem?->category?->code === FeeCategory::CARRYOVER)
            || ($a->billItem?->bill && (int) $a->billItem->bill->academic_year_id !== (int) $p->academic_year_id);

        $rowCols = [];   // kunci kolom => ['label' => ..., 'order' => [...]]
        $arrearGrades = [0 => true, 11 => true, 12 => true];

        foreach ($sah as $p) {
            foreach ($p->allocations as $a) {
                if ($isArrear($p, $a)) {
                    $arrearGrades[$place[$p->id]['grade']] = true;
                }
            }
        }

        // Kolom tarif: dari tarif tahun-tahun yang terlibat + nama yang muncul di data (mis. tarif yang sudah dihapus).
        $years = $sah->pluck('academic_year_id')->unique()->filter()->all();
        $cats = FeeCategory::query()->get()->keyBy('id');

        $tariffCols = [];
        $add = function (int $catId, string $name, int $sort) use (&$tariffCols, $cats): void {
            $key = 'f'.$catId.'_'.md5(mb_strtolower(trim($name)));
            $cat = $cats->get($catId);
            $tariffCols[$key] ??= [
                'label' => ($cat && mb_strtolower($cat->receipt_label) !== mb_strtolower($name) ? $cat->receipt_label.' — ' : '').$name,
                'order' => [(int) ($cat->sort_order ?? 999), $sort, mb_strtolower($name)],
            ];
            $tariffCols[$key]['order'][1] = min($tariffCols[$key]['order'][1], $sort);
        };

        FeeComponent::query()->whereIn('academic_year_id', $years)->get()
            ->each(fn (FeeComponent $c) => $add((int) $c->fee_category_id, $c->name, (int) $c->sort_order));

        foreach ($sah as $p) {
            foreach ($p->allocations as $a) {
                if (! $isArrear($p, $a) && $a->billItem) {
                    $add((int) $a->billItem->fee_category_id, (string) $a->billItem->name, (int) $a->billItem->sort_order);
                }
            }
        }

        uasort($tariffCols, fn (array $x, array $y): int => $x['order'] <=> $y['order']);

        // Susunan kolom
        $columns = [
            ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text', 'width' => 12],
            ['key' => 'receipt_no', 'label' => 'No. kwitansi', 'type' => 'text', 'width' => 16],
            ['key' => 'name', 'label' => 'Keterangan (siswa)', 'type' => 'text', 'width' => 30],
            ['key' => 'class', 'label' => 'Kelas', 'type' => 'text', 'width' => 14],
            ['key' => 'method', 'label' => 'Metode', 'type' => 'text', 'width' => 10],
            ['key' => 'total', 'label' => 'Jumlah', 'type' => 'money', 'width' => 15],
        ];

        $moneyKeys = ['total'];
        $arrearKey = fn (int $g): string => 'arrear_'.$g;

        foreach ([0, 10, 11, 12] as $g) {
            if (isset($arrearGrades[$g])) {
                $columns[] = ['key' => $arrearKey($g), 'label' => self::ARREAR_LABELS[$g], 'type' => 'money', 'width' => 14];
                $moneyKeys[] = $arrearKey($g);
            }
        }

        foreach ($tariffCols as $key => $c) {
            $columns[] = ['key' => $key, 'label' => $c['label'], 'type' => 'money', 'width' => 14];
            $moneyKeys[] = $key;
        }

        // Selisih bila ada pembayaran yang belum teralokasi penuh (jarang) agar total kolom tetap cocok.
        $columns[] = ['key' => 'other', 'label' => 'Lainnya', 'type' => 'money', 'width' => 12];
        $moneyKeys[] = 'other';

        // Baris
        $rows = [];

        foreach ($sah as $i => $p) {
            $row = [
                'no' => $i + 1,
                'date' => $p->paid_at->format('d/m/Y'),
                'receipt_no' => (string) $p->receipt_no,
                'name' => (string) ($p->student?->name ?? '-'),
                'class' => (string) $place[$p->id]['class'],
                'method' => Payment::METHODS[$p->method] ?? (string) $p->method,
                'total' => (int) $p->amount,
                'other' => 0,
            ];

            $allocated = 0;

            foreach ($p->allocations as $a) {
                $amount = (int) $a->amount;
                $allocated += $amount;
                $key = $isArrear($p, $a)
                    ? $arrearKey($place[$p->id]['grade'])
                    : 'f'.(int) ($a->billItem?->fee_category_id ?? 0).'_'.md5(mb_strtolower(trim((string) ($a->billItem?->name ?? ''))));

                if (! array_key_exists($key, $row) && ! in_array($key, array_column($columns, 'key'), true)) {
                    $key = 'other';
                }

                $row[$key] = ($row[$key] ?? 0) + $amount;
            }

            $row['other'] += (int) $p->amount - $allocated;
            $rows[] = $row;
        }

        // Kolom "Lainnya" disembunyikan bila tidak terpakai sama sekali.
        if (array_sum(array_column($rows, 'other')) === 0) {
            $columns = array_values(array_filter($columns, fn (array $c): bool => $c['key'] !== 'other'));
            $moneyKeys = array_values(array_diff($moneyKeys, ['other']));
        }

        return new ReportTable('Buku Penerimaan', 'buku-penerimaan-'.$stamp, $subtitle, $columns, $rows, $moneyKeys);
    }
}
