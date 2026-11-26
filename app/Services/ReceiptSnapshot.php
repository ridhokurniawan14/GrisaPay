<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\Payment;

/**
 * Isi struk per KATEGORI: berapa dibayar kali ini dan berapa sisanya setelah pembayaran ini.
 * Dibekukan saat pembayaran sah agar cetak ulang selalu sama dengan cetakan pertama.
 */
final class ReceiptSnapshot
{
    public const CARRY = 'Tunggakan Sebelumnya';

    /** @return array{rows: list<array{label: string, paid: int, remaining: int}>, total_paid: int, total_remaining: int} */
    public static function build(Payment $payment): array
    {
        $payment->loadMissing(['student', 'academicYear', 'allocations.billItem.category', 'allocations.billItem.bill.academicYear']);

        /** @var AcademicYear $year */
        $year = $payment->academicYear;
        $start = $year->start_date->format('Y-m-d');

        $paid = [];

        foreach ($payment->allocations as $allocation) {
            $item = $allocation->billItem;
            $isCarry = substr((string) $item->bill->academicYear->start_date, 0, 10) < $start;
            $label = $isCarry ? self::CARRY : (string) $item->category->receipt_label;
            $paid[$label] = ($paid[$label] ?? 0) + (int) $allocation->amount;
        }

        $remaining = [];
        $summary = BillSummary::forStudent($payment->student, $year);

        foreach ($summary['rows'] as $row) {
            $remaining[$row['label']] = ($remaining[$row['label']] ?? 0) + $row['remaining'];
        }

        $order = FeeCategory::query()->orderBy('sort_order')->pluck('sort_order', 'receipt_label')->all();
        $labels = array_values(array_unique(array_merge(array_keys($paid), array_keys($remaining))));

        usort($labels, function (string $a, string $b) use ($order): int {
            $rank = fn (string $l): int => $l === self::CARRY ? -1 : ($order[$l] ?? 999);

            return $rank($a) <=> $rank($b);
        });

        $rows = [];

        foreach ($labels as $label) {
            $row = ['label' => $label, 'paid' => (int) ($paid[$label] ?? 0), 'remaining' => (int) ($remaining[$label] ?? 0)];

            if ($row['paid'] > 0 || $row['remaining'] > 0) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'total_paid' => (int) array_sum($paid),
            'total_remaining' => (int) array_sum($remaining),
        ];
    }

    /** Snapshot tersimpan; pembayaran lama tanpa snapshot dibuatkan sekali lalu disimpan. */
    public static function ensure(Payment $payment): array
    {
        if (is_array($payment->receipt_snapshot)) {
            return $payment->receipt_snapshot;
        }

        $snapshot = self::build($payment);
        $payment->update(['receipt_snapshot' => $snapshot]);

        return $snapshot;
    }
}
