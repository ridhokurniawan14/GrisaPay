<?php

namespace App\Exports;

use App\Models\Bill;
use App\Models\FeeCategory;
use App\Services\BillSummary;
use Illuminate\Support\Collection;

final class BillReportBuilder
{
    private const CARRY = 'Tunggakan Sebelumnya';

    /**
     * Satu baris per siswa: tagihan tiap kategori tahun berjalan, diskon, dibayar, sisa, dan tunggakan tahun lalu.
     *
     * @param  Collection<int, Bill>  $bills
     * @param  list<string>  $subtitle
     */
    public static function build(Collection $bills, array $subtitle): ReportTable
    {
        set_time_limit(180);

        $bills->loadMissing(['student', 'academicYear']);

        $categories = FeeCategory::query()
            ->where('is_active', true)
            ->where('code', '!=', FeeCategory::CARRYOVER)
            ->orderBy('sort_order')
            ->get();

        $rows = [];

        foreach ($bills->values() as $i => $bill) {
            $summary = BillSummary::forStudent($bill->student, $bill->academicYear);
            $byLabel = collect($summary['rows'])->keyBy('label');

            $carry = (int) ($byLabel[self::CARRY]['remaining'] ?? 0);
            $current = collect($summary['rows'])->reject(fn (array $r): bool => $r['label'] === self::CARRY);

            $row = [
                'no' => $i + 1,
                'student' => $bill->student->name,
                'nisn' => $bill->student->nisn,
                'classroom' => $bill->classroom_name,
            ];

            foreach ($categories as $category) {
                $row['cat_'.$category->id] = (int) ($byLabel[$category->receipt_label]['bill'] ?? 0);
            }

            $row['bill'] = (int) $current->sum('bill');
            $row['discount'] = (int) $current->sum('discount');
            $row['paid'] = (int) $current->sum('paid');
            $row['remaining'] = (int) $current->sum('remaining');
            $row['carry'] = $carry;
            $row['total_due'] = $row['remaining'] + $carry;

            $rows[] = $row;
        }

        $columns = [
            ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
            ['key' => 'student', 'label' => 'Nama siswa', 'width' => 30],
            ['key' => 'nisn', 'label' => 'NISN', 'width' => 13],
            ['key' => 'classroom', 'label' => 'Kelas', 'width' => 14],
        ];

        $totalKeys = ['bill', 'discount', 'paid', 'remaining', 'carry', 'total_due'];

        foreach ($categories as $category) {
            $columns[] = ['key' => 'cat_'.$category->id, 'label' => $category->receipt_label, 'type' => 'money', 'width' => 15];
            $totalKeys[] = 'cat_'.$category->id;
        }

        array_push(
            $columns,
            ['key' => 'bill', 'label' => 'Total tagihan', 'type' => 'money', 'width' => 16],
            ['key' => 'discount', 'label' => 'Diskon', 'type' => 'money', 'width' => 14],
            ['key' => 'paid', 'label' => 'Dibayar', 'type' => 'money', 'width' => 14],
            ['key' => 'remaining', 'label' => 'Sisa tahun ini', 'type' => 'money', 'width' => 16],
            ['key' => 'carry', 'label' => 'Tunggakan sebelumnya', 'type' => 'money', 'width' => 18],
            ['key' => 'total_due', 'label' => 'Total belum terbayar', 'type' => 'money', 'width' => 18],
        );

        return new ReportTable(
            title: 'Laporan Tagihan Siswa',
            filename: 'tagihan-siswa-'.now()->format('Ymd-His'),
            subtitle: array_merge($subtitle, [count($rows).' siswa']),
            columns: $columns,
            rows: $rows,
            totalKeys: $totalKeys,
        );
    }
}
