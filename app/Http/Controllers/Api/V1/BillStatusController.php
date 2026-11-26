<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Services\BillAccess;
use App\Services\GrisaKadBills;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API baca-saja tagihan untuk GrisaKAD. Scope: bills:read. Tidak ada endpoint tulis. */
class BillStatusController extends Controller
{
    public function __construct(private readonly GrisaKadBills $bills, private readonly BillAccess $access)
    {
    }

    /** GET /api/v1/students/{ref}/bills   (ref = UUID GrisaID atau NISN) */
    public function student(Request $request, string $ref): JsonResponse
    {
        $student = $this->bills->resolve($ref);

        // 404 yang sama untuk "tidak ada" dan "tidak berhak" agar keberadaan siswa tidak bocor.
        abort_if($student === null || ! $this->access->student($request->user('api'), $student), 404, 'Siswa tidak ditemukan.');

        return response()->json(['data' => $this->bills->forStudent($student)]);
    }

    /** GET /api/v1/classes/{uuid}/bills   (uuid = ID kelas di GrisaID) */
    public function classroom(Request $request, string $uuid): JsonResponse
    {
        $year = AcademicYear::current();
        $class = Classroom::query()->where('grisaid_id', strtolower($uuid))->first();

        abort_if($year === null || $class === null || ! $this->access->classroom($request->user('api'), $class->id), 404, 'Kelas tidak ditemukan.');

        return response()->json(['data' => [
            'class' => ['id' => $class->grisaid_id, 'name' => $class->name],
            'academic_year' => ['code' => $year->code, 'name' => $year->name],
            'students' => $this->bills->forClassroom($class->id, $year),
        ]]);
    }
}
