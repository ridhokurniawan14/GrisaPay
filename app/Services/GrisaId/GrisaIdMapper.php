<?php

namespace App\Services\GrisaId;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Mengubah respons API GrisaID menjadi baris database lokal. Murni PHP (tanpa database/HTTP),
 * sehingga mudah diuji. Semua baris untuk satu tabel memiliki kunci yang sama (syarat upsert massal).
 */
final class GrisaIdMapper
{
    /** @param array<string, mixed> $year */
    public static function academicYear(array $year, string $now): array
    {
        return [
            'grisaid_id' => (string) $year['id'],
            'code' => (string) $year['code'],
            'name' => (string) ($year['name'] ?? 'Tahun Ajaran '.$year['code']),
            'start_date' => (string) $year['start_date'],
            'end_date' => (string) $year['end_date'],
            'is_active' => (bool) ($year['is_active'] ?? false),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @param array<string, mixed> $classroom */
    public static function classroom(array $classroom, int $academicYearId, string $now): array
    {
        return [
            'grisaid_id' => (string) $classroom['id'],
            'academic_year_id' => $academicYearId,
            'grade' => (int) $classroom['grade'],
            'name' => (string) $classroom['name'],
            'group_no' => isset($classroom['group_no']) ? (int) $classroom['group_no'] : null,
            'major_code' => self::nullable($classroom['major']['code'] ?? null),
            'major_name' => self::nullable($classroom['major']['name'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Kelas satu tahun ajaran: dari daftar kelas (termasuk kelas kosong) DAN dari penempatan siswa
     * (jaga-jaga bila kelas baru dibuat di antara dua permintaan). Hasil dikunci dengan UUID kelas.
     *
     * @param  list<array<string, mixed>>  $classes
     * @param  list<array<string, mixed>>  $students
     * @return array<string, array<string, mixed>>
     */
    public static function classroomRows(array $classes, array $students, int $academicYearId, string $now): array
    {
        $rows = [];

        foreach ($classes as $classroom) {
            $rows[(string) $classroom['id']] = self::classroom($classroom, $academicYearId, $now);
        }

        foreach ($students as $student) {
            $classroom = $student['classroom'] ?? null;

            if (is_array($classroom) && isset($classroom['id'])) {
                $rows[(string) $classroom['id']] ??= self::classroom($classroom, $academicYearId, $now);
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $student */
    public static function student(array $student, string $now): array
    {
        return [
            'grisaid_id' => (string) $student['id'],
            'nisn' => self::nullable($student['nisn'] ?? null),
            'nis' => self::nullable($student['nis'] ?? null),
            'name' => (string) $student['name'],
            'gender' => (string) $student['gender'],
            'phone' => self::nullable($student['phone'] ?? null),
            'entry_year' => (int) $student['entry_year'],
            'status' => (string) $student['status'],
            'grisaid_updated_at' => self::time($student['updated_at'] ?? null),
            'grisaid_deleted_at' => self::time($student['deleted_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Penempatan kelas siswa pada satu tahun ajaran.
     *
     * @param  list<array<string, mixed>>  $students
     * @param  array<string, int>  $studentIds  UUID siswa => id lokal
     * @param  array<string, int>  $classroomIds  UUID kelas => id lokal
     * @return list<array<string, mixed>>
     */
    public static function enrollmentRows(array $students, array $studentIds, array $classroomIds, int $academicYearId, string $now): array
    {
        $rows = [];

        foreach ($students as $student) {
            $classroom = $student['classroom'] ?? null;

            if (! is_array($classroom) || ! isset($classroom['id'])) {
                continue; // siswa ini tidak terdaftar di tahun ajaran tersebut
            }

            $studentId = $studentIds[(string) $student['id']] ?? null;
            $classroomId = $classroomIds[(string) $classroom['id']] ?? null;

            if ($studentId === null || $classroomId === null) {
                continue;
            }

            $rows[] = [
                'student_id' => $studentId,
                'classroom_id' => $classroomId,
                'academic_year_id' => $academicYearId,
                'status' => (string) ($classroom['enrollment_status'] ?? 'aktif'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Wali siswa. Siswa yang datanya TIDAK memuat kunci "guardians" (permintaan tanpa include) tidak
     * disentuh, supaya data wali lama tidak terhapus karena permintaan yang tidak lengkap.
     *
     * @param  list<array<string, mixed>>  $students
     * @param  array<string, int>  $studentIds
     * @return array{0: list<int>, 1: list<array<string, mixed>>} [id siswa yang wali-nya diganti, baris wali baru]
     */
    public static function guardianPlan(array $students, array $studentIds, string $now): array
    {
        $replace = [];
        $rows = [];

        foreach ($students as $student) {
            if (! array_key_exists('guardians', $student)) {
                continue;
            }

            $studentId = $studentIds[(string) $student['id']] ?? null;

            if ($studentId === null) {
                continue;
            }

            $replace[] = $studentId;
            $byRelation = [];

            foreach ((array) $student['guardians'] as $guardian) {
                $relation = (string) ($guardian['relation'] ?? '');

                if ($relation === '') {
                    continue;
                }

                // Dikunci per hubungan: satu siswa hanya boleh punya satu ayah/ibu/wali (aturan unik tabel).
                $byRelation[$relation] = [
                    'student_id' => $studentId,
                    'relation' => $relation,
                    'name' => self::nullable($guardian['name'] ?? null),
                    'occupation' => self::nullable($guardian['occupation'] ?? null),
                    'whatsapp' => self::nullable($guardian['whatsapp'] ?? null),
                    'is_primary_contact' => (bool) ($guardian['is_primary_contact'] ?? false),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            array_push($rows, ...array_values($byRelation));
        }

        return [$replace, $rows];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /** ISO 8601 dari API -> "Y-m-d H:i:s" pada zona waktu aplikasi. Nilai kosong atau tidak valid -> null. */
    private static function time(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
