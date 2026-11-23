<?php

namespace App\Exports;

use App\Models\Discount;
use Illuminate\Support\Collection;

final class DiscountReportBuilder
{
    /**
     * @param  Collection<int, Discount>  $discounts
     * @param  list<string>  $subtitle
     */
    public static function build(Collection $discounts, array $subtitle): ReportTable
    {
        $discounts->loadMissing(['bill.student', 'type', 'category', 'billItem', 'requester', 'decider']);

        $rows = [];

        foreach ($discounts->values() as $i => $d) {
            $rows[] = [
                'no' => $i + 1,
                'requested_at' => $d->created_at?->format('d/m/Y H:i'),
                'student' => $d->bill->student->name,
                'nisn' => $d->bill->student->nisn,
                'classroom' => $d->bill->classroom_name,
                'type' => $d->type->name,
                'scope' => $d->scopeText(),
                'value' => $d->valueText(),
                'granted' => $d->status === Discount::APPROVED ? $d->granted_amount : 0,
                'status' => ucfirst($d->status),
                'requester' => $d->requester?->name,
                'decider' => $d->decider?->name,
                'decided_at' => $d->decided_at?->format('d/m/Y H:i'),
                'reason' => $d->reason,
                'note' => $d->decision_note,
            ];
        }

        return new ReportTable(
            title: 'Laporan Diskon Siswa',
            filename: 'diskon-siswa-'.now()->format('Ymd-His'),
            subtitle: array_merge($subtitle, [count($rows).' pengajuan']),
            columns: [
                ['key' => 'no', 'label' => 'No', 'type' => 'int', 'width' => 5],
                ['key' => 'requested_at', 'label' => 'Diajukan', 'width' => 16, 'align' => 'center'],
                ['key' => 'student', 'label' => 'Siswa', 'width' => 28],
                ['key' => 'nisn', 'label' => 'NISN', 'width' => 13, 'pdf' => false],
                ['key' => 'classroom', 'label' => 'Kelas', 'width' => 14],
                ['key' => 'type', 'label' => 'Jenis diskon', 'width' => 20],
                ['key' => 'scope', 'label' => 'Cakupan', 'width' => 22],
                ['key' => 'value', 'label' => 'Nilai', 'width' => 11, 'align' => 'center'],
                ['key' => 'granted', 'label' => 'Potongan', 'type' => 'money', 'width' => 15],
                ['key' => 'status', 'label' => 'Status', 'width' => 13],
                ['key' => 'requester', 'label' => 'Pengaju', 'width' => 18],
                ['key' => 'decider', 'label' => 'Diputuskan oleh', 'width' => 18],
                ['key' => 'decided_at', 'label' => 'Tgl keputusan', 'width' => 16, 'align' => 'center', 'pdf' => false],
                ['key' => 'reason', 'label' => 'Alasan / bukti', 'width' => 40],
                ['key' => 'note', 'label' => 'Catatan keputusan', 'width' => 30, 'pdf' => false],
            ],
            rows: $rows,
            totalKeys: ['granted'],
            statusKey: 'status',
        );
    }
}
