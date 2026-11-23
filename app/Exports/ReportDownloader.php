<?php

namespace App\Exports;

use Symfony\Component\HttpFoundation\Response;

/** Pintu tunggal unduhan laporan: membuat metadata, mencatat log aktivitas, lalu memilih penulis Excel/PDF. */
final class ReportDownloader
{
    public static function respond(ReportTable $report, string $format): Response
    {
        $meta = [
            'generated_by' => (string) (auth()->user()?->name ?? '-'),
            'generated_at' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'generated_date' => now()->locale('id')->translatedFormat('d F Y'),
        ];

        activity('ekspor')
            ->causedBy(auth()->user())
            ->withProperties([
                'laporan' => $report->title,
                'format' => $format === 'pdf' ? 'PDF' : 'Excel',
                'jumlah_baris' => count($report->rows),
                'filter' => $report->subtitle,
            ])
            ->log('Unduh laporan');

        return $format === 'pdf'
            ? PdfReport::download($report, $meta)
            : ExcelReport::download($report, $meta);
    }
}
