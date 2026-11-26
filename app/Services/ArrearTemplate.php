<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Berkas contoh untuk impor tunggakan: judul kolom, contoh isi, dan lembar petunjuk. */
final class ArrearTemplate
{
    public static function download(): StreamedResponse
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setTitle('Tunggakan');

        $ws->fromArray([['NISN', 'Tahun Ajaran', 'Kategori', 'Keterangan', 'Jumlah']], null, 'A1');
        $ws->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
        ]);

        $examples = [
            ['0117790181', '2025/2026', 'Personal', 'Kekurangan semester genap', 500000],
            ['0117790181', '2025/2026', 'PSM', '', 250000],
            ['0088816037', '2024/2025', '', 'Tunggakan lama', 1200000],
        ];

        foreach ($examples as $i => $row) {
            $r = $i + 2;
            $ws->setCellValueExplicit("A{$r}", $row[0], DataType::TYPE_STRING);
            $ws->setCellValue("B{$r}", $row[1]);
            $ws->setCellValue("C{$r}", $row[2]);
            $ws->setCellValue("D{$r}", $row[3]);
            $ws->setCellValue("E{$r}", $row[4]);
            $ws->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0');
        }

        foreach (['A' => 16, 'B' => 14, 'C' => 16, 'D' => 34, 'E' => 16] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        $help = $book->createSheet();
        $help->setTitle('Petunjuk');
        $help->fromArray([
            ['Petunjuk pengisian'],
            ['1. Satu baris = satu tunggakan. Satu siswa boleh punya banyak baris.'],
            ['2. NISN wajib. Siswa (termasuk alumni) harus sudah ada di data siswa GrisaPay (sinkron dari GrisaID).'],
            ['3. Tahun Ajaran wajib, format 2025/2026. Tahun ajaran itu harus sudah ada (sinkron dari GrisaID).'],
            ['4. Kategori boleh kosong. Bila diisi: Personal, Insidental, Pengembangan, atau PSM. Kosong = masuk sebagai Tunggakan umum.'],
            ['5. Keterangan boleh kosong (mis. "Kekurangan semester genap").'],
            ['6. Jumlah wajib, angka rupiah tanpa desimal (boleh 500000 atau 500.000).'],
            ['7. Di struk dan portal, semua tunggakan tahun sebelum tahun ajaran aktif tampil sebagai "Tunggakan Sebelumnya".'],
            ['8. Mengunggah berkas yang sama dua kali aman: baris yang sama persis dilewati.'],
            ['9. Hapus 3 baris contoh sebelum mengisi data asli.'],
        ]);
        $help->getColumnDimension('A')->setWidth(110);
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
        }, 'template-tunggakan.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
