<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth', 'signed'])
    ->get('/struk/{print}', [ReceiptController::class, 'show'])
    ->name('receipt.show');
