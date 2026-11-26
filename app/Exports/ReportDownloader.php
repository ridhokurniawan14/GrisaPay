<?php

namespace App\Exports;

use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\Response;

/** Pintu tunggal unduhan laporan: membuat metadata, mencatat log aktivitas, lalu memilih penulis Excel/PDF. */
final class ReportDownloader
{
    public static function respond(ReportTable $report, string $format): ?Response
    {
        // Laporan besar (ribuan baris) butuh lebih banyak memori dan waktu dari batas bawaan.
        @ini_set('memory_limit', '768M');
        @set_time_limit(300);

        // PDF dengan ribuan baris menghabiskan memori. Arahkan ke Excel atau persempit filter.
        $maxPdfRows = (int) config('grisapay.pdf_max_rows', 600);

        if ($format === 'pdf' && count($report->rows) > $maxPdfRows) {
            Notification::make()
                ->warning()
                ->title('Terlalu banyak baris untuk PDF')
                ->body(count($report->rows).' baris (maksimal '.$maxPdfRows.'). Persempit filter (mis. per kelas) atau unduh dalam format Excel.')
                ->duration(10000)
                ->send();

            return null;
        }

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
