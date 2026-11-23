<?php

namespace App\Exports;

use App\Support\ReportBrand;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menulis ReportTable menjadi .xlsx berformat: kop, header berwarna tema, zebra, total, siap cetak A4. */
final class ExcelReport
{
    private const MONEY = '#,##0;-#,##0;"-"';

    private const STATUS_COLORS = [
        'Disetujui' => ['DCFCE7', '166534'],
        'Ditolak' => ['FEE2E2', '991B1B'],
        'Dicabut' => ['E5E7EB', '374151'],
        'Diajukan' => ['FEF3C7', '92400E'],
    ];

    /** @param array{generated_by: string, generated_at: string} $meta */
    public static function download(ReportTable $report, array $meta): StreamedResponse
    {
        $brand = ReportBrand::get();
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $report->title) ?: 'Laporan', 0, 31));

        $cols = $report->columns;
        $n = count($cols);
        $last = Coordinate::stringFromColumnIndex($n);
        $primary = 'FF'.strtoupper(ltrim($brand['color'], '#'));
        $onPrimary = 'FF'.strtoupper(ltrim(ReportBrand::textOn($brand['color']), '#'));

        // Kop
        $row = 1;
        $ws->setCellValue("A{$row}", $brand['name']);
        $ws->mergeCells("A{$row}:{$last}{$row}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($brand['address'] !== '') {
            $row++;
            $ws->setCellValue("A{$row}", $brand['address']);
            $ws->mergeCells("A{$row}:{$last}{$row}");
            $ws->getStyle("A{$row}")->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row += 2;
        $ws->setCellValue("A{$row}", $report->title);
        $ws->mergeCells("A{$row}:{$last}{$row}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($report->subtitle as $line) {
            $row++;
            $ws->setCellValue("A{$row}", $line);
            $ws->mergeCells("A{$row}:{$last}{$row}");
            $ws->getStyle("A{$row}")->getFont()->setSize(10)->getColor()->setARGB('FF4B5563');
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Header tabel
        $row += 2;
        $headerRow = $row;

        foreach ($cols as $i => $col) {
            $coord = Coordinate::stringFromColumnIndex($i + 1).$row;
            $label = $col['label'].(($col['type'] ?? 'text') === 'money' ? ' (Rp)' : '');
            $ws->setCellValue($coord, $label);
            $ws->getColumnDimensionByColumn($i + 1)->setWidth((float) ($col['width'] ?? 16) + 4);
        }

        $headerRange = "A{$row}:{$last}{$row}";
        $ws->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => $onPrimary]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $primary]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension($row)->setRowHeight(34);

        // Isi
        $firstData = $row + 1;

        foreach ($report->rows as $index => $data) {
            $row++;

            foreach ($cols as $i => $col) {
                $coord = Coordinate::stringFromColumnIndex($i + 1).$row;
                $value = $data[$col['key']] ?? null;
                $type = $col['type'] ?? 'text';

                if ($type === 'text') {
                    $ws->setCellValueExplicit($coord, (string) ($value ?? ''), DataType::TYPE_STRING); // NISN "0117..." tetap utuh
                } else {
                    $ws->setCellValueExplicit($coord, (int) ($value ?? 0), DataType::TYPE_NUMERIC);
                }

                if ($type === 'money') {
                    $ws->getStyle($coord)->getNumberFormat()->setFormatCode(self::MONEY);
                }

                $align = $col['align'] ?? match ($type) {
                    'money' => 'right',
                    'int' => 'center',
                    default => 'left',
                };
                $ws->getStyle($coord)->getAlignment()->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);

                if ($report->statusKey === $col['key'] && isset(self::STATUS_COLORS[(string) $value])) {
                    [$bg, $fg] = self::STATUS_COLORS[(string) $value];
                    $ws->getStyle($coord)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$bg);
                    $ws->getStyle($coord)->getFont()->setBold(true)->getColor()->setARGB('FF'.$fg);
                    $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }

            if ($index % 2 === 1) {
                foreach ($cols as $i => $col) {
                    if ($report->statusKey === $col['key']) {
                        continue;
                    }

                    $ws->getStyle(Coordinate::stringFromColumnIndex($i + 1).$row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
                }
            }
        }

        $lastData = $row;

        // Total
        if ($report->totalKeys !== [] && $report->rows !== []) {
            $row++;
            $totals = $report->totals();
            $ws->setCellValue("A{$row}", 'TOTAL');

            foreach ($cols as $i => $col) {
                if (isset($totals[$col['key']])) {
                    $coord = Coordinate::stringFromColumnIndex($i + 1).$row;
                    $ws->setCellValueExplicit($coord, $totals[$col['key']], DataType::TYPE_NUMERIC);
                    $ws->getStyle($coord)->getNumberFormat()->setFormatCode(self::MONEY);
                    $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            $ws->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
        }

        // Garis tipis seluruh tabel
        $ws->getStyle("A{$headerRow}:{$last}{$row}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');

        if ($report->rows !== []) {
            $ws->setAutoFilter("A{$headerRow}:{$last}{$lastData}");
        } else {
            $row++;
            $ws->setCellValue("A{$row}", 'Tidak ada data untuk filter ini.');
        }

        $ws->freezePane('A'.($headerRow + 1));

        // Catatan cetak
        $row += 2;
        $ws->setCellValue("A{$row}", "Dicetak oleh {$meta['generated_by']} pada {$meta['generated_at']}");
        $ws->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF6B7280');

        // Siap cetak: A4 landscape, lebar muat 1 halaman, header berulang
        $setup = $ws->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $setup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $ws->getHeaderFooter()->setOddFooter('&L&8'.$brand['name'].'&R&8Halaman &P dari &N');
        $ws->setShowGridlines(false);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $report->filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
