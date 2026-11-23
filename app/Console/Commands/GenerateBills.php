<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Services\BillGenerator;
use Illuminate\Console\Command;

class GenerateBills extends Command
{
    protected $signature = 'grisapay:generate-bills {year? : Kode tahun ajaran, mis. 2026/2027 (default: yang aktif)}';

    protected $description = 'Buat tagihan siswa dari tarif tahun ajaran (aman diulang)';

    public function handle(BillGenerator $generator): int
    {
        $code = $this->argument('year');
        $year = $code ? AcademicYear::query()->where('code', $code)->first() : AcademicYear::current();

        if ($year === null) {
            $this->error('Tahun ajaran tidak ditemukan. Jalankan grisapay:sync-grisaid dulu.');

            return self::FAILURE;
        }

        $stats = $generator->generate($year);

        $this->table(['Tahun ajaran', 'Tagihan baru', 'Item ditambah', 'Siswa tanpa tarif'], [[
            $year->code, $stats['bills_created'], $stats['items_added'], $stats['students_without_tariff'],
        ]]);

        if ($stats['students_without_tariff'] > 0) {
            $this->warn('Ada siswa yang tingkatnya belum punya tarif. Isi tarif tingkat tersebut lalu jalankan ulang.');
        }

        return self::SUCCESS;
    }
}
