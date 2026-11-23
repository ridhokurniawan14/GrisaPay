<?php

namespace App\Exports;

use App\Support\ReportBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menulis ReportTable menjadi PDF A4 landscape: kop, tabel zebra, total, tanda tangan bendahara, nomor halaman. */
final class PdfReport
{
    /** @param array{generated_by: string, generated_at: string} $meta */
    public static function download(ReportTable $report, array $meta): StreamedResponse
    {
        set_time_limit(180);
        ini_set('memory_limit', '512M');

        $brand = ReportBrand::get();

        $pdf = Pdf::loadView('exports.table', [
            'report' => $report,
            'brand' => $brand,
            'meta' => $meta,
            'onPrimary' => ReportBrand::textOn($brand['color']),
        ])->setPaper('a4', 'landscape');

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(
            $canvas->get_width() - 140,
            $canvas->get_height() - 24,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}',
            $font,
            7,
            [0.42, 0.45, 0.5],
        );

        $binary = $pdf->output();

        // streamDownload (bukan response biasa): Livewire menolak badan biner dan mengira itu JSON ("Malformed UTF-8").
        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $report->filename.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
