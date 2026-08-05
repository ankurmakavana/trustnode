<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/password/forgot', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');

    Route::post('/password/reset', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:3,1');

    Route::get('/invitation/{token}', [InvitationController::class, 'show']);
    Route::post('/invitation/{token}', [InvitationController::class, 'accept'])
        ->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/password/change', [PasswordController::class, 'changePassword']);
});
