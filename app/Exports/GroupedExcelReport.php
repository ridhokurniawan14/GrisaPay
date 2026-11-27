<?php

namespace App\Exports;

use App\Support\ReportBrand;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menulis GroupedReport ke .xlsx: kop, header dua baris (sel digabung), isi per tanggal, baris total, siap cetak. */
final class GroupedExcelReport
{
    private const MONEY = '#,##0;-#,##0;"-"';

    /** @param array{generated_by: string, generated_at: string} $meta */
    public static function download(GroupedReport $report, array $meta): StreamedResponse
    {
        $brand = ReportBrand::get();
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $report->title) ?: 'Laporan', 0, 31));

        $cols = $report->columns();
        $n = count($cols);
        $last = Coordinate::stringFromColumnIndex($n);
        $primary = 'FF'.strtoupper(ltrim($brand['color'], '#'));
        $onPrimary = 'FF'.strtoupper(ltrim(ReportBrand::textOn($brand['color']), '#'));
        $center = Alignment::HORIZONTAL_CENTER;

        // Kop
        $row = 1;
        $ws->setCellValue("A{$row}", $brand['name']);
        $ws->mergeCells("A{$row}:{$last}{$row}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal($center);

        if ($brand['address'] !== '') {
            $row++;
            $ws->setCellValue("A{$row}", $brand['address']);
            $ws->mergeCells("A{$row}:{$last}{$row}");
            $ws->getStyle("A{$row}")->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal($center);
        }

        $row += 2;
        $ws->setCellValue("A{$row}", $report->title);
        $ws->mergeCells("A{$row}:{$last}{$row}");
        $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
        $ws->getStyle("A{$row}")->getAlignment()->setHorizontal($center);

        foreach ($report->subtitle as $line) {
            $row++;
            $ws->setCellValue("A{$row}", $line);
            $ws->mergeCells("A{$row}:{$last}{$row}");
            $ws->getStyle("A{$row}")->getFont()->setSize(10)->getColor()->setARGB('FF4B5563');
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal($center);
        }

        // Header dua baris
        $row += 2;
        $h1 = $row;
        $h2 = $row + 1;
        $i = 0;

        $put = function (int $col, int $r, string $text) use ($ws): void {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($col).$r, mb_strtoupper($text));
        };

        foreach ($report->lead as $c) {
            $i++;
            $L = Coordinate::stringFromColumnIndex($i);
            $put($i, $h1, $c['label']);
            $ws->mergeCells("{$L}{$h1}:{$L}{$h2}");
            $ws->getColumnDimensionByColumn($i)->setWidth((float) ($c['width'] ?? 14) + 2);
        }

        foreach ($report->groups as $g) {
            $count = count($g['columns']);
            $from = $i + 1;

            if ($count === 1) {
                $i++;
                $L = Coordinate::stringFromColumnIndex($i);
                $put($i, $h1, $g['label']);
                $ws->mergeCells("{$L}{$h1}:{$L}{$h2}");
                $ws->getColumnDimensionByColumn($i)->setWidth((float) ($g['columns'][0]['width'] ?? 14) + 2);

                continue;
            }

            $put($from, $h1, $g['label']);
            $ws->mergeCells(Coordinate::stringFromColumnIndex($from).$h1.':'.Coordinate::stringFromColumnIndex($from + $count - 1).$h1);

            foreach ($g['columns'] as $c) {
                $i++;
                $put($i, $h2, $c['label']);
                $ws->getColumnDimensionByColumn($i)->setWidth((float) ($c['width'] ?? 14) + 2);
            }
        }

        $ws->getStyle("A{$h1}:{$last}{$h2}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => $onPrimary]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $primary]],
            'alignment' => ['horizontal' => $center, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $ws->getRowDimension($h1)->setRowHeight(24);
        $ws->getRowDimension($h2)->setRowHeight(32);

        // Isi
        $row = $h2;
        $firstData = $row + 1;

        foreach ($report->rows as $data) {
            $row++;

            foreach ($cols as $k => $c) {
                $coord = Coordinate::stringFromColumnIndex($k + 1).$row;
                $v = $data[$c['key']] ?? null;

                if (($c['type'] ?? 'text') === 'text') {
                    $ws->setCellValueExplicit($coord, (string) ($v ?? ''), DataType::TYPE_STRING);
                } else {
                    $ws->setCellValueExplicit($coord, (int) ($v ?? 0), DataType::TYPE_NUMERIC);
                }
            }
        }

        $lastData = $row;

        if ($lastData >= $firstData) {
            foreach ($cols as $k => $c) {
                $L = Coordinate::stringFromColumnIndex($k + 1);
                $type = $c['type'] ?? 'text';
                $style = $ws->getStyle("{$L}{$firstData}:{$L}{$lastData}");

                if ($type === 'money') {
                    $style->getNumberFormat()->setFormatCode(self::MONEY);
                }

                $style->getAlignment()
                    ->setHorizontal($c['align'] ?? match ($type) { 'money' => 'right', 'int' => 'center', default => 'left' })
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $zebra = new Conditional();
            $zebra->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW()-'.$firstData.',2)=1');
            $zebra->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
            $zebra->getStyle()->getFill()->getEndColor()->setARGB('FFF3F4F6');
            $ws->getStyle("A{$firstData}:{$last}{$lastData}")->setConditionalStyles([$zebra]);
        }

        // Total
        if ($report->rows !== []) {
            $row++;
            $totals = $report->totals();
            $ws->setCellValue("A{$row}", 'TOTAL');

            foreach ($cols as $k => $c) {
                if (isset($totals[$c['key']])) {
                    $coord = Coordinate::stringFromColumnIndex($k + 1).$row;
                    $ws->setCellValueExplicit($coord, $totals[$c['key']], DataType::TYPE_NUMERIC);
                    $ws->getStyle($coord)->getNumberFormat()->setFormatCode(self::MONEY);
                    $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }

            $ws->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
        } else {
            $row++;
            $ws->setCellValue("A{$row}", 'Tidak ada data untuk filter ini.');
        }

        $ws->getStyle("A{$firstData}:{$last}{$row}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');

        // Baris tanggal & keterangan tetap terlihat saat digeser ke kanan.
        $ws->freezePane('C'.($h2 + 1));

        // Tanda tangan
        if ($report->signers !== []) {
            $row += 2;
            $count = count($report->signers);
            $seg = max(1, intdiv($n, $count));
            $city = trim($brand['city'].', '.($meta['generated_date'] ?? ''), ', ');
            $dateRow = $row;
            $roleRow = $row + 1;
            $nameRow = $row + 5;
            $nipRow = $row + 6;

            foreach (array_values($report->signers) as $k => $sg) {
                $a = Coordinate::stringFromColumnIndex(1 + $k * $seg);
                $b = Coordinate::stringFromColumnIndex($k === $count - 1 ? $n : ($k + 1) * $seg);

                if ($k === $count - 1) {
                    $ws->setCellValue("{$a}{$dateRow}", $city);
                }

                $ws->setCellValue("{$a}{$roleRow}", $sg['role']);
                $ws->setCellValue("{$a}{$nameRow}", $sg['name'] !== '' ? $sg['name'] : '............................');

                if (($sg['nip'] ?? '') !== '') {
                    $ws->setCellValue("{$a}{$nipRow}", 'NIP. '.$sg['nip']);
                }

                foreach ([$dateRow, $roleRow, $nameRow, $nipRow] as $r) {
                    $ws->mergeCells("{$a}{$r}:{$b}{$r}");
                }

                $ws->getStyle("{$a}{$dateRow}:{$b}{$nipRow}")->getAlignment()->setHorizontal($center);
                $ws->getStyle("{$a}{$nameRow}")->getFont()->setBold(true)->setUnderline(true);
            }

            $row = $nipRow;
        }

        $row += 2;
        $ws->setCellValue("A{$row}", "Dicetak oleh {$meta['generated_by']} pada {$meta['generated_at']}");
        $ws->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF6B7280');

        $setup = $ws->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $setup->setRowsToRepeatAtTopByStartAndEnd($h1, $h2);
        $ws->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4);
        $ws->getHeaderFooter()->setOddFooter('&L&8'.$brand['name'].'&R&8Halaman &P dari &N');
        $ws->setShowGridlines(false);

        return response()->streamDownload(function () use ($ss): void {
            (new Xlsx($ss))->save('php://output');
        }, $report->filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
