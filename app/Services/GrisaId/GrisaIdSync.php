<?php

namespace App\Services\GrisaId;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin data master dari GrisaID ke database lokal GrisaPay, sehingga transaksi kasir hanya membaca
 * salinan lokal dan tetap jalan walau GrisaID sedang mati.
 *
 * Sinkronisasi PENUH (bukan inkremental): untuk ±1.300 siswa hanya belasan permintaan, dan inkremental
 * lewat updated_since akan melewatkan perubahan kelas (kelas berubah di tabel penempatan, bukan di siswa).
 * Aman diulang: semua penulisan berupa upsert. Jaringan diselesaikan dulu, baru ditulis dalam transaksi.
 */
final class GrisaIdSync
{
    private const CHUNK = 300;

    public function __construct(private readonly GrisaIdClient $client)
    {
    }

    /** @return array<string, int> jumlah baris salinan lokal setelah sinkronisasi */
    public function run(): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $years = $this->client->academicYears();

        DB::transaction(function () use ($years, $now): void {
            AcademicYear::upsert(
                array_map(fn (array $year): array => GrisaIdMapper::academicYear($year, $now), $years),
                ['grisaid_id'],
                ['code', 'name', 'start_date', 'end_date', 'is_active', 'updated_at'],
            );
        });

        foreach ($years as $year) {
            $code = (string) $year['code'];

            // Seluruh data diambil dulu (jaringan), baru ditulis. Siswa dari semua status, termasuk yang
            // sudah dihapus di GrisaID, karena riwayat tagihan mengacu ke mereka.
            $classes = $this->client->classes($code);
            $students = iterator_to_array($this->client->students([
                'academic_year' => $code,
                'with_trashed' => 1,
                'include' => 'guardians',
            ]), false);

            $this->writeYear((string) $year['id'], $classes, $students, $now);
        }

        return [
            'tahun_ajaran' => AcademicYear::query()->count(),
            'kelas' => Classroom::query()->count(),
            'siswa' => Student::query()->count(),
            'penempatan_kelas' => StudentEnrollment::query()->count(),
            'wali' => StudentGuardian::query()->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $classes
     * @param  list<array<string, mixed>>  $students
     */
    private function writeYear(string $yearGrisaId, array $classes, array $students, string $now): void
    {
        DB::transaction(function () use ($yearGrisaId, $classes, $students, $now): void {
            $yearId = (int) AcademicYear::query()->where('grisaid_id', $yearGrisaId)->value('id');

            // 1. Kelas
            $classRows = GrisaIdMapper::classroomRows($classes, $students, $yearId, $now);

            foreach (array_chunk(array_values($classRows), self::CHUNK) as $chunk) {
                Classroom::upsert($chunk, ['grisaid_id'], ['academic_year_id', 'grade', 'name', 'group_no', 'major_code', 'major_name', 'updated_at']);
            }

            $classroomIds = $classRows === []
                ? []
                : Classroom::query()->whereIn('grisaid_id', array_keys($classRows))->pluck('id', 'grisaid_id')->all();

            // 2. Siswa
            $studentRows = array_map(fn (array $student): array => GrisaIdMapper::student($student, $now), $students);

            foreach (array_chunk($studentRows, self::CHUNK) as $chunk) {
                Student::upsert($chunk, ['grisaid_id'], ['nisn', 'nis', 'name', 'gender', 'phone', 'entry_year', 'status', 'grisaid_updated_at', 'grisaid_deleted_at', 'updated_at']);
            }

            $studentIds = [];

            foreach (array_chunk(array_column($studentRows, 'grisaid_id'), self::CHUNK) as $chunk) {
                $studentIds += Student::query()->whereIn('grisaid_id', $chunk)->pluck('id', 'grisaid_id')->all();
            }

            // 3. Penempatan kelas pada tahun ajaran ini
            $enrollmentRows = GrisaIdMapper::enrollmentRows($students, $studentIds, $classroomIds, $yearId, $now);

            foreach (array_chunk($enrollmentRows, self::CHUNK) as $chunk) {
                StudentEnrollment::upsert($chunk, ['student_id', 'academic_year_id'], ['classroom_id', 'status', 'updated_at']);
            }

            // Salinan harus sama persis dengan GrisaID: penempatan yang sudah tidak ada dihapus.
            // Dilewati bila GrisaID tidak mengembalikan siswa sama sekali (tidak bisa dibedakan dari kekeliruan).
            if ($students !== []) {
                $keep = array_column($enrollmentRows, 'student_id');

                StudentEnrollment::query()
                    ->where('academic_year_id', $yearId)
                    ->when($keep !== [], fn ($query) => $query->whereNotIn('student_id', $keep))
                    ->delete();
            }

            // 4. Wali (termasuk nomor WA). Hanya siswa yang datanya memuat "guardians" yang diganti.
            [$replace, $guardianRows] = GrisaIdMapper::guardianPlan($students, $studentIds, $now);

            foreach (array_chunk($replace, self::CHUNK) as $chunk) {
                StudentGuardian::query()->whereIn('student_id', $chunk)->delete();
            }

            foreach (array_chunk($guardianRows, self::CHUNK) as $chunk) {
                StudentGuardian::insert($chunk);
            }
        });
    }
}
