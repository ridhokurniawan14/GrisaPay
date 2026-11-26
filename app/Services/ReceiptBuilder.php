<?php

namespace App\Services;

use App\Models\InstitutionSetting;
use App\Models\PaymentPrint;
use App\Support\ReportBrand;
use Throwable;

/** Menyusun baris teks struk untuk satu kali cetak (memakai isi yang dibekukan, bukan tagihan saat ini). */
final class ReceiptBuilder
{
    /** @return list<string> */
    public static function lines(PaymentPrint $print): array
    {
        $payment = $print->payment->loadMissing(['student', 'creator', 'verifier', 'academicYear']);
        $snapshot = ReceiptSnapshot::ensure($payment);
        $brand = ReportBrand::get();

        try {
            $footer = (string) (InstitutionSetting::current()->receipt_footer ?? '');
        } catch (Throwable) {
            $footer = '';
        }

        $classroom = $payment->student->enrollments()
            ->where('academic_year_id', $payment->academic_year_id)
            ->with('classroom')
            ->first()?->classroom?->name ?? '-';

        return ReceiptFormatter::lines([
            'name' => $brand['name'],
            'address' => $brand['address'],
            'footer' => $footer,
            'receipt_no' => (string) $payment->receipt_no,
            'paid_at' => $payment->paid_at->format('d/m/Y H:i').' WIB',
            'student' => $payment->student->name,
            'nisn' => (string) ($payment->student->nisn ?? '-'),
            'classroom' => $classroom,
            'rows' => $snapshot['rows'],
            'total_paid' => $snapshot['total_paid'],
            'total_remaining' => $snapshot['total_remaining'],
            'method' => $payment->methodText(),
            'payer' => (string) ($payment->payer_name ?? ''),
            'cashier' => (string) ($payment->verifier?->name ?? $payment->creator?->name ?? ''),
            'copy' => $print->copy_no,
        ], (int) config('grisapay.receipt.cols', 40));
    }
}
