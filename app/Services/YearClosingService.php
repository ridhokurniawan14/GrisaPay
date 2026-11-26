<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\User;
use App\Models\YearClosing;
use DomainException;
use Illuminate\Support\Facades\DB;

final class YearClosingService
{
    /**
     * Daftar cek sebelum tutup buku.
     * blocking = harus beres dulu; info = hanya diketahui (terbawa).
     *
     * @return array{blocking: array<int, string>, info: array<int, string>, summary: array<string, int>}
     */
    public function checklist(AcademicYear $year): array
    {
        $blocking = [];
        $info = [];

        $pending = Payment::query()->where('academic_year_id', $year->id)->where('status', Payment::PENDING)->count();
        if ($pending > 0) {
            $blocking[] = "{$pending} pembayaran masih menunggu verifikasi (transfer/QRIS).";
        }

        $open = CashShift::query()->where('status', CashShift::OPEN)->count();
        if ($open > 0) {
            $blocking[] = "{$open} shift kas masih terbuka — tutup dulu.";
        }

        $summary = $this->summary($year);
        if ($summary['outstanding'] > 0) {
            $info[] = 'Sisa tunggakan Rp '.number_format($summary['outstanding'], 0, ',', '.')." ({$summary['students_owing']} siswa) tetap bisa dibayar setelah tutup buku.";
        }

        return ['blocking' => $blocking, 'info' => $info, 'summary' => $summary];
    }

    /** @return array{bills: int, billed: int, paid: int, outstanding: int, students_owing: int} */
    public function summary(AcademicYear $year): array
    {
        $bills = Bill::query()->where('academic_year_id', $year->id)->with('items')->get();
        $billed = 0;
        $paid = 0;
        $owing = 0;

        $paidByItem = DB::table('payment_allocations as a')
            ->join('payments as p', 'p.id', '=', 'a.payment_id')
            ->join('bill_items as i', 'i.id', '=', 'a.bill_item_id')
            ->join('bills as b', 'b.id', '=', 'i.bill_id')
            ->where('b.academic_year_id', $year->id)
            ->where('p.status', Payment::PAID)
            ->groupBy('b.id')
            ->selectRaw('b.id as bill_id, SUM(a.amount) as paid')
            ->pluck('paid', 'bill_id');

        foreach ($bills as $bill) {
            $due = (int) $bill->items->sum(fn ($i) => max(0, $i->amount - $i->discount_amount));
            $p = (int) ($paidByItem[$bill->id] ?? 0);
            $billed += $due;
            $paid += $p;
            $owing += ($due - $p) > 0 ? 1 : 0;
        }

        return ['bills' => $bills->count(), 'billed' => $billed, 'paid' => $paid, 'outstanding' => max(0, $billed - $paid), 'students_owing' => $owing];
    }

    public function close(AcademicYear $year, User $by, ?string $note = null): YearClosing
    {
        if ($year->is_active) {
            throw new DomainException('Tahun ajaran yang masih aktif tidak bisa ditutup buku. Jadikan tahun berikutnya aktif lebih dulu (lewat GrisaID, lalu sinkron).');
        }

        $check = $this->checklist($year);
        if ($check['blocking'] !== []) {
            throw new DomainException('Belum bisa ditutup: '.implode(' ', $check['blocking']));
        }

        $closing = DB::transaction(fn () => YearClosing::query()->updateOrCreate(
            ['academic_year_id' => $year->id],
            ['closed_at' => now(), 'closed_by' => $by->id, 'note' => $note, 'snapshot' => $check['summary'],
                'reopened_at' => null, 'reopened_by' => null, 'reopen_reason' => null],
        ));

        YearLock::flush();
        activity('tutup_buku')->performedOn($year)->causedBy($by)->withProperties($check['summary'])
            ->log("Tutup buku tahun ajaran {$year->name}");

        return $closing;
    }

    public function reopen(AcademicYear $year, User $by, string $reason): void
    {
        $closing = YearClosing::query()->where('academic_year_id', $year->id)->whereNotNull('closed_at')->first()
            ?? throw new DomainException('Tahun ajaran ini tidak dalam keadaan ditutup.');

        $closing->update(['closed_at' => null, 'reopened_at' => now(), 'reopened_by' => $by->id, 'reopen_reason' => $reason]);

        YearLock::flush();
        activity('tutup_buku')->performedOn($year)->causedBy($by)->withProperties(['alasan' => $reason])
            ->log("Buka kembali tutup buku tahun ajaran {$year->name}");
    }
}
