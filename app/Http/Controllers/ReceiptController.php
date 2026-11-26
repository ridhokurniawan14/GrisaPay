<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\PaymentPrint;
use App\Services\ReceiptBuilder;
use App\Support\ReceiptLogo;

class ReceiptController extends Controller
{
    /** Halaman cetak struk. Dibuka lewat URL bertanda tangan sekali-pakai yang dibuat saat mencatat cetakan. */
    public function show(PaymentPrint $print)
    {
        abort_unless(auth()->user()?->can(PermissionName::PaymentsPrint->value), 403);

        return view('receipts.show', [
            'lines' => ReceiptBuilder::lines($print->load('payment')),
            'logo' => ReceiptLogo::dataUri(),
            'cols' => (int) config('grisapay.receipt.cols', 40),
            'fontPt' => (float) config('grisapay.receipt.font_pt', 8),
            'paperMm' => (int) config('grisapay.receipt.paper_mm', 76),
            'title' => $print->payment->receipt_no.' · '.$print->label(),
        ]);
    }
}
