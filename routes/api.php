<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BillStatusController;
use App\Http\Middleware\ResolveBillActor;
use App\Http\Controllers\Api\V1\BrandingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::prefix('v1')->group(function () {
    // Publik (nama & logo sekolah untuk halaman login GrisaKAD)
    Route::get('branding', BrandingController::class)->middleware('throttle:30,1');

    Route::middleware([ResolveBillActor::class, 'throttle:60,1'])->group(function () {
        Route::get('students/{ref}/bills', [BillStatusController::class, 'student']);
        Route::get('students/{ref}/payments', [BillStatusController::class, 'studentPayments']);
        Route::get('classes/{uuid}/bills', [BillStatusController::class, 'classroom']);
        Route::get('me/bills', [BillStatusController::class, 'myBills']);         // siswa
        Route::get('me/payments', [BillStatusController::class, 'myPayments']);   // siswa
        Route::get('me/classes', [BillStatusController::class, 'myClasses']);     // wali kelas
    });
});
