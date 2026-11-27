<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BillStatusController;
use App\Http\Middleware\ResolveBillActor;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::prefix('v1')->middleware([ResolveBillActor::class, 'throttle:60,1'])->group(function () {
    Route::get('students/{ref}/bills', [BillStatusController::class, 'student']);
    Route::get('classes/{uuid}/bills', [BillStatusController::class, 'classroom']);
    Route::get('me/bills', [BillStatusController::class, 'myBills']);      // siswa
    Route::get('me/classes', [BillStatusController::class, 'myClasses']);  // wali kelas
});
