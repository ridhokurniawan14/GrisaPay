<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FeeCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;

/**
 * Ringkasan tagihan siswa per KATEGORI (bentuk yang tampil di struk dan portal), plus rincian untuk admin.
 * "Tunggakan sebelumnya" = sisa tagihan tahun-tahun ajaran sebelumnya; dihitung, tidak disalin.
 */
final class BillSummary
{
    /**
     * @return array{
     *   rows: list<array{label: string, bill: int, discount: int, paid: int, remaining: int, items: list<array{name: string, amount: int, discount: int, paid: int, remaining: int}>}>,
     *   total: array{bill: int, discount: int, paid: int, remaining: int}
     * }
     */
    public static function forStudent(Student $student, AcademicYear $year): array
    {
        $categories = FeeCategory::query()->orderBy('sort_order')->get()->keyBy('id');

        $current = Bill::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->with('items')
            ->first();

        $previous = Bill::query()
            ->where('student_id', $student->id)
            ->whereHas('academicYear', fn ($q) => $q->where('start_date', '<', $year->start_date))
            ->with(['items', 'academicYear'])
            ->get();

        $paid = self::paidByItem(
            collect([$current])->filter()->flatMap->items->merge($previous->flatMap->items)->pluck('id')->all()
        );

        $rows = [];

        // Tunggakan sebelumnya: hanya sisa yang masih ada.
        $carry = ['label' => 'Tunggakan Sebelumnya', 'bill' => 0, 'discount' => 0, 'paid' => 0, 'remaining' => 0, 'items' => []];

        foreach ($previous as $bill) {
            foreach ($bill->items as $item) {
                $line = self::line($item, $paid, "{$item->name} (TA {$bill->academicYear->code})");

                if ($line['remaining'] > 0) {
                    $carry['bill'] += $line['amount'];
                    $carry['discount'] += $line['discount'];
                    $carry['paid'] += $line['paid'];
                    $carry['remaining'] += $line['remaining'];
                    $carry['items'][] = $line;
                }
            }
        }

        if ($carry['remaining'] > 0) {
            $rows[] = $carry;
        }

        // Tagihan tahun berjalan, dikelompokkan per kategori mengikuti urutan kategori.
        $byCategory = [];

        foreach ($current?->items ?? [] as $item) {
            $line = self::line($item, $paid, $item->name);
            $row = $byCategory[$item->fee_category_id] ??= [
                'label' => $categories[$item->fee_category_id]->receipt_label ?? 'Lainnya',
                'bill' => 0, 'discount' => 0, 'paid' => 0, 'remaining' => 0, 'items' => [],
            ];
            $row['bill'] += $line['amount'];
            $row['discount'] += $line['discount'];
            $row['paid'] += $line['paid'];
            $row['remaining'] += $line['remaining'];
            $row['items'][] = $line;
            $byCategory[$item->fee_category_id] = $row;
        }

        uksort($byCategory, fn ($a, $b) => ($categories[$a]->sort_order ?? 999) <=> ($categories[$b]->sort_order ?? 999));
        $rows = array_merge($rows, array_values($byCategory));

        $total = ['bill' => 0, 'discount' => 0, 'paid' => 0, 'remaining' => 0];

        foreach ($rows as $row) {
            foreach ($total as $key => $_) {
                $total[$key] += $row[$key];
            }
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Jumlah yang sudah dibayar per item tagihan: hanya pembayaran berstatus Sah (void dan menunggu tidak dihitung).
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    public static function paidByItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', Payment::PAID)
            ->whereIn('payment_allocations.bill_item_id', $itemIds)
            ->groupBy('payment_allocations.bill_item_id')
            ->selectRaw('payment_allocations.bill_item_id as item_id, SUM(payment_allocations.amount) as total')
            ->pluck('total', 'item_id')
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    /** @param array<int, int> $paid */
    private static function line(BillItem $item, array $paid, string $name): array
    {
        $amount = (int) $item->amount;
        $discount = min((int) $item->discount_amount, $amount);
        $paidAmount = (int) ($paid[$item->id] ?? 0);

        return [
            'name' => $name,
            'amount' => $amount,
            'discount' => $discount,
            'paid' => $paidAmount,
            'remaining' => max(0, $amount - $discount - $paidAmount),
        ];
    }
}
