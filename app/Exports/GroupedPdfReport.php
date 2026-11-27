<?php

namespace App\Exports;

use App\Support\ReportBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menulis GroupedReport ke PDF A4 landscape. */
final class GroupedPdfReport
{
    /** @param array{generated_by: string, generated_at: string, generated_date: string} $meta */
    public static function download(GroupedReport $report, array $meta): StreamedResponse
    {
        set_time_limit(180);
        ini_set('memory_limit', '512M');

        $brand = ReportBrand::get();

        $pdf = Pdf::loadView('exports.grouped', [
            'report' => $report,
            'brand' => $brand,
            'meta' => $meta,
            'onPrimary' => ReportBrand::textOn($brand['color']),
        ])->setPaper('a4', 'landscape');

        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 140, $canvas->get_height() - 24, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7, [0.42, 0.45, 0.5]);

        $binary = $pdf->output();

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $report->filename.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
