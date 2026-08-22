<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Api\Admin\LicenseController;
use App\Http\Controllers\Api\Admin\ReleaseController as AdminReleaseController;
use App\Http\Controllers\Api\V1\InstallationController;
use App\Http\Controllers\Api\V1\ReleaseController as V1ReleaseController;
use App\Http\Middleware\AuthenticateInstallation;

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{uuid}', [CustomerController::class, 'show']);
    Route::patch('/customers/{uuid}', [CustomerController::class, 'update']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{uuid}', [ProductController::class, 'show']);
    Route::patch('/products/{uuid}', [ProductController::class, 'update']);

    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{uuid}', [PlanController::class, 'show']);
    Route::patch('/plans/{uuid}', [PlanController::class, 'update']);

    Route::post('/licenses', [LicenseController::class, 'store']);
    Route::get('/licenses', [LicenseController::class, 'index']);
    Route::get('/licenses/{uuid}', [LicenseController::class, 'show']);
    Route::post('/licenses/{uuid}/suspend', [LicenseController::class, 'suspend']);
    Route::post('/licenses/{uuid}/revoke', [LicenseController::class, 'revoke']);

    Route::get('/releases', [AdminReleaseController::class, 'index']);
    Route::post('/releases', [AdminReleaseController::class, 'store']);
    Route::patch('/releases/{release}', [AdminReleaseController::class, 'update']);
});

Route::prefix('v1/licenses')->group(function () {
    Route::middleware('throttle:10,1')->post('/activate', [InstallationController::class, 'activate']);
    
    Route::middleware([AuthenticateInstallation::class, 'throttle:60,1'])->group(function() {
        Route::post('/validate', [InstallationController::class, 'validateLicense']);
        Route::get('/current', [InstallationController::class, 'current']);
    });
    
    Route::middleware([AuthenticateInstallation::class, 'throttle:10,1'])->group(function() {
        Route::post('/deactivate', [InstallationController::class, 'deactivate']);
    });
});

Route::prefix('v1/releases')->group(function () {
    Route::middleware([AuthenticateInstallation::class, 'throttle:60,1'])->group(function() {
        Route::get('/latest', [V1ReleaseController::class, 'latest']);
    });
    
    Route::get('/{release}/download', [V1ReleaseController::class, 'download'])->name('api.v1.releases.download');
});
