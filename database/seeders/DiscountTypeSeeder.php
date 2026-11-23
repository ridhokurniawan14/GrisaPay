<?php

namespace Database\Seeders;

use App\Models\DiscountType;
use Illuminate\Database\Seeder;

class DiscountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Prestasi Juara 1', 'Prestasi Juara 2', 'Prestasi Juara 3', 'Tidak Mampu', 'Yatim Piatu', 'Lainnya'];

        foreach ($types as $i => $name) {
            // Nilai bawaan dikosongkan: bendahara/admin mengisinya sendiri di halaman Jenis Diskon.
            DiscountType::query()->firstOrCreate(['name' => $name], ['sort_order' => ($i + 1) * 10]);
        }
    }
}
