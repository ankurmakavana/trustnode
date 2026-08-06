<?php

declare(strict_types=1);

use App\Http\Controllers\Asset\AssetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Scan\ScanController;
use App\Http\Controllers\Target\TargetController;
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

    // Dashboard Endpoints
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Asset Management Endpoints
    Route::get('/assets/{asset}/activity', [AssetController::class, 'activityLogs']);
    Route::apiResource('/assets', AssetController::class);

    // Target Management Endpoints
    Route::get('/targets/{target}/activity', [TargetController::class, 'activityLogs']);
    Route::apiResource('/targets', TargetController::class);

    // Scan Management Endpoints
    Route::get('/scans/{scan}/activity', [ScanController::class, 'activityLogs']);
    Route::apiResource('/scans', ScanController::class);
});
