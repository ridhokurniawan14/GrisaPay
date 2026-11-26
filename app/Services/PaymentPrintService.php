<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentPrint;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Mencatat setiap cetak struk. Cetak pertama = asli; berikutnya = "cetak ulang ke-N". */
final class PaymentPrintService
{
    public function record(Payment $payment, User $by): PaymentPrint
    {
        return DB::transaction(function () use ($payment, $by): PaymentPrint {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== Payment::PAID) {
                throw new DomainException('Struk hanya bisa dicetak untuk pembayaran yang sah.');
            }

            $copy = PaymentPrint::query()->where('payment_id', $locked->id)->count();

            $print = PaymentPrint::query()->create([
                'payment_id' => $locked->id,
                'copy_no' => $copy,
                'printed_by' => $by->id,
                'printed_at' => now(),
            ]);

            $locked->loadMissing('student');

            activity('pembayaran')
                ->causedBy($by)
                ->performedOn($locked)
                ->withProperties([
                    'no_kwitansi' => $locked->receipt_no,
                    'siswa' => $locked->student->name,
                    'cetakan' => $print->label(),
                ])
                ->log($copy === 0 ? 'Struk dicetak' : "Struk dicetak ulang (ke-{$copy})");

            return $print;
        });
    }
}
