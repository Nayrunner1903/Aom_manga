<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:jwt-login')->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:jwt-register')->name('auth.register');

    Route::middleware(['jwt.api', 'throttle:jwt-api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::apiResource('transactions', TransactionController::class)->whereNumber('transaction');
        Route::apiResource('categories', CategoryController::class)->whereNumber('category');
        Route::get('summary', SummaryController::class)->name('summary');
    });
});
