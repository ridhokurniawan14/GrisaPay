<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\BillAccess;
use App\Services\GrisaKadBills;
use App\Support\BillActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API baca-saja tagihan untuk GrisaKAD. Tidak ada endpoint tulis. 404 yang sama untuk "tidak ada" dan "tidak berhak". */
class BillStatusController extends Controller
{
    public function __construct(private readonly GrisaKadBills $bills, private readonly BillAccess $access)
    {
    }

    private function actor(Request $request): BillActor
    {
        return $request->attributes->get('bill_actor');
    }

    /** GET /api/v1/students/{ref}/bills  (UUID GrisaID atau NISN) */
    public function student(Request $request, string $ref): JsonResponse
    {
        $student = $this->bills->resolve($ref);

        abort_if($student === null || ! $this->access->student($this->actor($request), $student), 404, 'Siswa tidak ditemukan.');

        return response()->json(['data' => $this->bills->forStudent($student)]);
    }

    /** GET /api/v1/students/{ref}/payments — riwayat pembayaran (hak akses sama dengan tagihan siswa). */
    public function studentPayments(Request $request, string $ref): JsonResponse
    {
        $student = $this->bills->resolve($ref);

        abort_if($student === null || ! $this->access->student($this->actor($request), $student), 404, 'Siswa tidak ditemukan.');

        return response()->json(['data' => $this->bills->payments($student)]);
    }

    /** GET /api/v1/me/payments — riwayat pembayaran siswa yang sedang masuk. */
    public function myPayments(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $student = $actor->type === 'student' ? Student::query()->where('grisaid_id', $actor->studentUuid)->first() : null;

        abort_if($student === null, 404, 'Akun ini bukan akun siswa.');

        return response()->json(['data' => $this->bills->payments($student)]);
    }

    /** GET /api/v1/classes/{uuid}/bills */
    public function classroom(Request $request, string $uuid): JsonResponse
    {
        $class = Classroom::query()->where('grisaid_id', strtolower($uuid))->first();
        $year = AcademicYear::current();

        abort_if($year === null || $class === null || ! $this->access->classroom($this->actor($request), $class), 404, 'Kelas tidak ditemukan.');

        return response()->json(['data' => $this->classPayload($class, $year)]);
    }

    /** GET /api/v1/me/bills — tagihan siswa yang sedang masuk. */
    public function myBills(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $student = $actor->type === 'student' ? Student::query()->where('grisaid_id', $actor->studentUuid)->first() : null;

        abort_if($student === null, 404, 'Akun ini bukan akun siswa.');

        return response()->json(['data' => $this->bills->forStudent($student)]);
    }

    /** GET /api/v1/me/classes — status tagihan kelas yang diwalikan guru yang sedang masuk. */
    public function myClasses(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $year = AcademicYear::current();

        abort_if($actor->type !== 'teacher' || $year === null, 404, 'Akun ini bukan akun wali kelas.');

        $classes = Classroom::query()->where('academic_year_id', $year->id)->whereIn('grisaid_id', $actor->classroomUuids)->orderBy('name')->get();

        return response()->json(['data' => $classes->map(fn (Classroom $c) => $this->classPayload($c, $year))->values()]);
    }

    /** @return array<string, mixed> */
    private function classPayload(Classroom $class, AcademicYear $year): array
    {
        return [
            'class' => ['id' => $class->grisaid_id, 'name' => $class->name],
            'academic_year' => ['code' => $year->code, 'name' => $year->name],
            'students' => $this->bills->forClassroom($class->id, $year),
        ];
    }
}
