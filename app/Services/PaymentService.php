<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentVoid;
use App\Models\Student;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alur pembayaran:
 *  tunai            -> langsung sah (alokasi + nomor kwitansi)
 *  transfer / QRIS  -> menunggu -> (verifikasi bendahara) sah | ditolak
 *  sah              -> (ajukan void -> ACC bendahara) void
 * Uang selalu dibagi ke tagihan TERTUA dulu. Kelebihan bayar ditolak (tidak ada saldo/titipan).
 */
final class PaymentService
{
    public function record(array $data, User $by): Payment
    {
        $amount = (int) ($data['amount'] ?? 0);

        if ($amount < 1) {
            throw new DomainException('Jumlah pembayaran harus lebih dari 0.');
        }

        if (! isset(Payment::METHODS[$data['method'] ?? ''])) {
            throw new DomainException('Metode pembayaran tidak dikenal.');
        }

        return DB::transaction(function () use ($data, $amount, $by): Payment {
            $student = Student::query()->lockForUpdate()->findOrFail($data['student_id']);
            $focus = $data['focus'] ?? null;

            $this->assertFits($student, $focus, $amount);

            $payment = Payment::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => AcademicYear::current()?->id ?? throw new DomainException('Tahun ajaran aktif belum ada.'),
                'paid_at' => now(),
                'method' => $data['method'],
                'amount' => $amount,
                'focus' => $focus ?: null,
                'reference' => $this->clean($data['reference'] ?? null),
                'payer_name' => $this->clean($data['payer_name'] ?? null),
                'note' => $this->clean($data['note'] ?? null),
                'status' => Payment::PENDING,
                'created_by' => $by->id,
            ]);

            if ($payment->method === Payment::CASH) {
                $this->finalize($payment, $by);
                $this->log($payment, $by, 'Pembayaran tunai dicatat');
            } else {
                $this->log($payment, $by, 'Pembayaran dicatat, menunggu verifikasi');
            }

            return $payment->refresh();
        });
    }

    public function verify(Payment $payment, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $by): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== Payment::PENDING) {
                throw new DomainException('Pembayaran ini sudah diputuskan.');
            }

            Student::query()->lockForUpdate()->findOrFail($locked->student_id);
            $this->finalize($locked, $by);
            $this->log($locked, $by, 'Pembayaran diverifikasi');

            return $locked->refresh();
        });
    }

    public function reject(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== Payment::PENDING) {
                throw new DomainException('Pembayaran ini sudah diputuskan.');
            }

            $locked->update([
                'status' => Payment::REJECTED,
                'reject_reason' => $reason,
                'verified_by' => $by->id,
                'verified_at' => now(),
            ]);
            $this->log($locked, $by, 'Pembayaran ditolak', ['alasan' => $reason]);

            return $locked;
        });
    }

    public function requestVoid(Payment $payment, User $by, string $reason): PaymentVoid
    {
        return DB::transaction(function () use ($payment, $by, $reason): PaymentVoid {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== Payment::PAID) {
                throw new DomainException('Hanya pembayaran berstatus Sah yang bisa di-void.');
            }

            if ($locked->voids()->where('status', PaymentVoid::REQUESTED)->exists()) {
                throw new DomainException('Sudah ada pengajuan void yang menunggu persetujuan.');
            }

            $void = $locked->voids()->create(['reason' => $reason, 'status' => PaymentVoid::REQUESTED, 'requested_by' => $by->id]);
            $this->log($locked, $by, 'Void diajukan', ['alasan' => $reason]);

            return $void;
        });
    }

    public function approveVoid(PaymentVoid $void, User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($void, $by, $note): void {
            $lockedVoid = PaymentVoid::query()->lockForUpdate()->findOrFail($void->id);

            if ($lockedVoid->status !== PaymentVoid::REQUESTED) {
                throw new DomainException('Pengajuan void ini sudah diputuskan.');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($lockedVoid->payment_id);

            if ($payment->status !== Payment::PAID) {
                throw new DomainException('Pembayaran ini tidak lagi berstatus Sah.');
            }

            // Alokasi tetap tersimpan sebagai jejak; yang dihitung sebagai "dibayar" hanya pembayaran berstatus Sah.
            $payment->update(['status' => Payment::VOID]);
            $lockedVoid->update(['status' => PaymentVoid::APPROVED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->log($payment, $by, 'Void disetujui', ['alasan_void' => $lockedVoid->reason]);
        });
    }

    public function rejectVoid(PaymentVoid $void, User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($void, $by, $note): void {
            $lockedVoid = PaymentVoid::query()->lockForUpdate()->findOrFail($void->id);

            if ($lockedVoid->status !== PaymentVoid::REQUESTED) {
                throw new DomainException('Pengajuan void ini sudah diputuskan.');
            }

            $lockedVoid->update(['status' => PaymentVoid::REJECTED, 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->log($lockedVoid->payment, $by, 'Void ditolak');
        });
    }

    /** Total sisa yang masih bisa dibayar (sesuai fokus). */
    public function outstandingTotal(Student $student, ?string $focus = null): int
    {
        return (int) array_sum($this->outstanding($student, $focus));
    }

    /**
     * Sisa per item tagihan, urut tertua dulu: tahun ajaran lama -> kategori -> urutan rincian.
     *
     * @return array<int, int> id item => sisa
     */
    public function outstanding(Student $student, ?string $focus = null): array
    {
        $current = AcademicYear::current();

        $items = BillItem::query()
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->join('academic_years', 'academic_years.id', '=', 'bills.academic_year_id')
            ->join('fee_categories', 'fee_categories.id', '=', 'bill_items.fee_category_id')
            ->where('bills.student_id', $student->id)
            ->select('bill_items.*', 'academic_years.start_date as ay_start', 'bills.academic_year_id as ay_id')
            ->orderBy('academic_years.start_date')
            ->orderBy('fee_categories.sort_order')
            ->orderBy('bill_items.sort_order')
            ->orderBy('bill_items.id')
            ->get();

        if ($focus === 'carry' && $current !== null) {
            $items = $items->filter(fn ($i): bool => $i->ay_start < $current->start_date->format('Y-m-d'));
        } elseif (is_string($focus) && str_starts_with($focus, 'cat:') && $current !== null) {
            $categoryId = (int) substr($focus, 4);
            $items = $items->filter(fn ($i): bool => (int) $i->ay_id === $current->id && (int) $i->fee_category_id === $categoryId);
        }

        $paid = BillSummary::paidByItem($items->pluck('id')->all());
        $result = [];

        foreach ($items as $item) {
            $remaining = max(0, (int) $item->amount - (int) $item->discount_amount - (int) ($paid[$item->id] ?? 0));

            if ($remaining > 0) {
                $result[$item->id] = $remaining;
            }
        }

        return $result;
    }

    private function assertFits(Student $student, ?string $focus, int $amount): void
    {
        $total = $this->outstandingTotal($student, $focus ?: null);

        if ($total === 0) {
            throw new DomainException('Siswa ini tidak punya tagihan yang belum terbayar'.($focus ? ' untuk pilihan tersebut.' : '.'));
        }

        if ($amount > $total) {
            throw new DomainException('Jumlah melebihi sisa tagihan (Rp '.number_format($total, 0, ',', '.').'). Kelebihan bayar tidak diterima.');
        }
    }

    /** Membagi uang ke item, memberi nomor kwitansi, dan menjadikan pembayaran Sah. Dipanggil di dalam transaksi. */
    private function finalize(Payment $payment, User $by): void
    {
        $student = Student::query()->findOrFail($payment->student_id);
        $plan = PaymentAllocator::allocate($this->outstanding($student, $payment->focus), $payment->amount);

        if ($plan['leftover'] > 0 || $plan['allocations'] === []) {
            throw new DomainException('Jumlah melebihi sisa tagihan saat ini (tagihan berubah sejak dicatat).');
        }

        foreach ($plan['allocations'] as $itemId => $amount) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'bill_item_id' => $itemId, 'amount' => $amount]);
        }

        $payment->update([
            'receipt_no' => $this->nextReceiptNumber(),
            'status' => Payment::PAID,
            'verified_by' => $by->id,
            'verified_at' => now(),
        ]);
    }

    /** Berurutan per tahun kalender, tanpa lompatan: dikunci selama transaksi. */
    private function nextReceiptNumber(): string
    {
        $year = (int) now()->format('Y');

        DB::table('receipt_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $row = DB::table('receipt_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = (int) $row->last_number + 1;

        DB::table('receipt_sequences')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('KW/%d/%05d', $year, $next);
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $extra */
    private function log(Payment $payment, User $by, string $message, array $extra = []): void
    {
        $payment->loadMissing('student');

        activity('pembayaran')
            ->causedBy($by)
            ->performedOn($payment)
            ->withProperties([
                'no_kwitansi' => $payment->receipt_no ?? '(belum ada)',
                'siswa' => $payment->student->name,
                'metode' => $payment->methodText(),
                'jumlah' => 'Rp '.number_format($payment->amount, 0, ',', '.'),
            ] + $extra)
            ->log($message);
    }
}
