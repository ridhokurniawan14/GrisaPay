<?php

namespace Database\Seeders;

use App\Models\FeeCategory;
use Illuminate\Database\Seeder;

class FeeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'tunggakan', 'name' => 'Tunggakan Sebelumnya', 'receipt_label' => 'Tunggakan Sebelumnya', 'sort_order' => 10, 'is_system' => true],
            ['code' => 'pengembangan', 'name' => 'Uang Pengembangan (Uang Gedung)', 'receipt_label' => 'Pengembangan', 'sort_order' => 20, 'is_system' => false],
            ['code' => 'personal', 'name' => 'Biaya Personal', 'receipt_label' => 'Personal', 'sort_order' => 30, 'is_system' => false],
            ['code' => 'insidental', 'name' => 'Biaya Insidental', 'receipt_label' => 'Insidental', 'sort_order' => 40, 'is_system' => false],
            ['code' => 'psm', 'name' => 'PSM', 'receipt_label' => 'PSM', 'sort_order' => 50, 'is_system' => false],
        ];

        foreach ($rows as $row) {
            // Nama/label/urutan tidak menimpa perubahan admin; hanya buat bila belum ada.
            FeeCategory::query()->firstOrCreate(['code' => $row['code']], $row);
        }
    }
}
