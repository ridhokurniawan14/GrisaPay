<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\FeeComponent;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;

/**
 * Membuat tagihan untuk siswa yang punya kelas di tahun ajaran tertentu.
 * Aman diulang: tagihan yang sudah ada tidak diubah; hanya komponen BARU (belum ada di tagihan) yang ditambahkan.
 * Nominal item yang sudah tertagih tidak pernah ditimpa.
 */
final class BillGenerator
{
    /** @return array{bills_created: int, items_added: int, students_without_tariff: int} */
    public function generate(AcademicYear $year): array
    {
        $components = FeeComponent::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('grade');

        $stats = ['bills_created' => 0, 'items_added' => 0, 'students_without_tariff' => 0];

        StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('status', 'aktif')
            ->with('classroom')
            ->orderBy('id')
            ->chunkById(200, function ($enrollments) use ($year, $components, &$stats): void {
                DB::transaction(function () use ($enrollments, $year, $components, &$stats): void {
                    foreach ($enrollments as $enrollment) {
                        $classroom = $enrollment->classroom;
                        $planned = $components->get($classroom->grade);

                        if ($planned === null || $planned->isEmpty()) {
                            $stats['students_without_tariff']++;

                            continue;
                        }

                        $bill = Bill::query()->firstOrCreate(
                            ['student_id' => $enrollment->student_id, 'academic_year_id' => $year->id],
                            ['grade' => $classroom->grade, 'classroom_name' => $classroom->name],
                        );

                        if ($bill->wasRecentlyCreated) {
                            $stats['bills_created']++;
                        }

                        $existing = BillItem::query()->where('bill_id', $bill->id)->pluck('fee_component_id')->all();

                        foreach ($planned as $component) {
                            if (in_array($component->id, $existing, true)) {
                                continue;
                            }

                            BillItem::query()->create([
                                'bill_id' => $bill->id,
                                'fee_category_id' => $component->fee_category_id,
                                'fee_component_id' => $component->id,
                                'name' => $component->name,
                                'amount' => $component->amount,
                                'sort_order' => $component->sort_order,
                            ]);
                            $stats['items_added']++;
                        }
                    }
                });
            });

        return $stats;
    }
}
