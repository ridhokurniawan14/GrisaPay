<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\BillActor;

/**
 * Siapa boleh melihat tagihan siapa:
 *  - staf  : semua
 *  - siswa : hanya dirinya
 *  - guru  : hanya siswa di kelas yang dia walikan (tahun ajaran aktif)
 */
final class BillAccess
{
    public function student(BillActor $actor, Student $student): bool
    {
        return match ($actor->type) {
            'staff' => true,
            'student' => $actor->studentUuid !== null && $actor->studentUuid === $student->grisaid_id,
            'teacher' => $this->inHomeroom($actor, $student),
            default => false,
        };
    }

    public function classroom(BillActor $actor, Classroom $classroom): bool
    {
        return $actor->isStaff() || ($actor->type === 'teacher' && in_array($classroom->grisaid_id, $actor->classroomUuids, true));
    }

    private function inHomeroom(BillActor $actor, Student $student): bool
    {
        $year = AcademicYear::current();

        if ($year === null || $actor->classroomUuids === []) {
            return false;
        }

        return StudentEnrollment::query()
            ->where('student_id', $student->id)->where('academic_year_id', $year->id)
            ->whereHas('classroom', fn($q) => $q->whereIn('grisaid_id', $actor->classroomUuids))
            ->exists();
    }
}
