<?php

return [
    // Struk printer dot matrix (Epson TM-U220, kertas 76 mm). Sesuaikan bila hasil cetak terpotong atau terlalu kecil.
    'receipt' => [
        'cols' => (int) env('RECEIPT_COLS', 40),         // jumlah karakter per baris
        'font_pt' => (float) env('RECEIPT_FONT_PT', 8),  // ukuran huruf di halaman cetak
        'paper_mm' => (int) env('RECEIPT_PAPER_MM', 76), // lebar kertas
    ],
    'pg_bin' => env('PG_BIN_PATH'),            // folder bin PostgreSQL (berisi pg_dump & pg_restore); kosong = pakai PATH
    'backup_keep' => 14,
];
