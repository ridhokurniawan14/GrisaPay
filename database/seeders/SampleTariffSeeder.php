<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeComponent;
use Illuminate\Database\Seeder;

/**
 * HANYA UNTUK UJI LOKAL. Angka di bawah adalah contoh, bukan tarif sekolah.
 * Nanti tarif diisi lewat halaman Tarif; seeder ini bisa dibuang.
 */
class SampleTariffSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::current();

        if ($year === null) {
            $this->command?->error('Tahun ajaran aktif belum ada. Jalankan grisapay:sync-grisaid dulu.');

            return;
        }

        $cat = FeeCategory::query()->pluck('id', 'code');

        // [tingkat, kode kategori, nama komponen, nominal, urutan]
        $rows = [
            [10, 'pengembangan', 'Uang Gedung', 1000000, 1],
            [10, 'insidental', 'Kegiatan Sekolah', 300000, 1],
            [10, 'insidental', 'Seragam', 400000, 2],
            [10, 'psm', 'PSM', 500000, 1],

            [11, 'personal', 'Biaya Personal', 600000, 1],
            [11, 'insidental', 'Kegiatan Sekolah', 300000, 1],
            [11, 'psm', 'PSM', 500000, 1],

            [12, 'personal', 'Biaya Personal', 600000, 1],
            [12, 'insidental', 'Kegiatan Sekolah', 300000, 1],
            [12, 'insidental', 'Ujian & Kelulusan', 350000, 2],
            [12, 'psm', 'PSM', 500000, 1],
        ];

        foreach ($rows as [$grade, $code, $name, $amount, $order]) {
            FeeComponent::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'grade' => $grade, 'fee_category_id' => $cat[$code], 'name' => $name],
                ['amount' => $amount, 'sort_order' => $order],
            );
        }

        $this->command?->info('Tarif contoh TA '.$year->code.' terisi ('.count($rows).' komponen).');
    }
}
