<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BillStatusController;
use Laravel\Passport\Http\Middleware\CheckToken;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::prefix('v1')->middleware(['auth:api', CheckToken::class . ':bills:read', 'throttle:60,1'])->group(function () {
    Route::get('students/{ref}/bills', [BillStatusController::class, 'student']);
    Route::get('classes/{uuid}/bills', [BillStatusController::class, 'classroom']);
});
