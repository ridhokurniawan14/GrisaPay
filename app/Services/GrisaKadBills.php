<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentEnrollment;

/**
 * Bentuk data tagihan untuk GrisaKAD (hanya-baca): status + ringkasan per kategori, TANPA rincian item/transaksi.
 * Siswa diacu lewat UUID GrisaID (utama) atau NISN.
 */
final class GrisaKadBills
{
    /** Cari siswa dari UUID GrisaID atau NISN (10 digit). Null bila tidak ada / NISN ganda. */
    public function resolve(string $ref): ?Student
    {
        $ref = trim($ref);

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ref) === 1) {
            return Student::query()->where('grisaid_id', strtolower($ref))->first();
        }

        if (preg_match('/^\d{10}$/', $ref) === 1) {
            $found = Student::query()->where('nisn', $ref)->limit(2)->get();

            return $found->count() === 1 ? $found->first() : null;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function forStudent(Student $student, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        if ($year === null) {
            return ['student' => $this->identity($student, null), 'academic_year' => null, 'status' => 'tidak_ada_tagihan', 'categories' => [], 'total' => $this->totals(0, 0, 0, 0)];
        }

        $summary = BillSummary::forStudent($student, $year);
        $codes = FeeCategory::query()->pluck('code', 'receipt_label')->all();

        $categories = [];
        foreach ($summary['rows'] as $row) {
            $categories[] = [
                'code' => $codes[$row['label']] ?? ($row['label'] === 'Tunggakan Sebelumnya' ? FeeCategory::CARRYOVER : 'lainnya'),
                'label' => $row['label'],
                'status' => $this->status($row['bill'] - $row['discount'], $row['paid'], $row['remaining']),
                ...$this->amounts($row),
            ];
        }

        $t = $summary['total'];

        return [
            'student' => $this->identity($student, $year),
            'academic_year' => ['code' => $year->code, 'name' => $year->name],
            'status' => $categories === [] ? 'tidak_ada_tagihan' : $this->status($t['bill'] - $t['discount'], $t['paid'], $t['remaining']),
            'categories' => $categories,
            'total' => $this->amounts($t),
            'as_of' => now()->toIso8601String(),
        ];
    }

    /** Status lunas/belum seluruh siswa satu kelas (tanpa kategori), untuk wali kelas. @return list<array<string, mixed>> */
    public function forClassroom(int $classroomId, AcademicYear $year): array
    {
        return StudentEnrollment::query()
            ->where('classroom_id', $classroomId)->where('academic_year_id', $year->id)->where('status', 'aktif')
            ->with('student')->get()
            ->sortBy(fn ($e) => $e->student->name)
            ->map(function ($e) use ($year): array {
                $d = $this->forStudent($e->student, $year);

                return ['student' => $d['student'], 'status' => $d['status'], 'total' => $d['total']];
            })->values()->all();
    }

    /** @return array<string, mixed> */
    private function identity(Student $s, ?AcademicYear $year): array
    {
        $class = $year ? StudentEnrollment::query()->where('student_id', $s->id)->where('academic_year_id', $year->id)->with('classroom')->first()?->classroom : null;

        return ['id' => $s->grisaid_id, 'nisn' => $s->nisn, 'name' => $s->name, 'class' => $class?->name];
    }

    private function status(int $net, int $paid, int $remaining): string
    {
        return match (true) {
            $remaining <= 0 => 'lunas',
            $paid > 0 => 'sebagian',
            default => 'belum',
        };
    }

    /** @return array{bill: int, discount: int, paid: int, remaining: int} */
    private function amounts(array $r): array
    {
        return $this->totals($r['bill'], $r['discount'], $r['paid'], $r['remaining']);
    }

    /** @return array{bill: int, discount: int, paid: int, remaining: int} */
    private function totals(int $bill, int $discount, int $paid, int $remaining): array
    {
        return ['bill' => $bill, 'discount' => $discount, 'paid' => $paid, 'remaining' => $remaining];
    }
}
