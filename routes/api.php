<?php

declare(strict_types=1);

use App\Http\Controllers\Asset\AssetController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Compliance\ComplianceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finding\FindingController;
use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Integration\IntegrationController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\Repository\RepositoryController;
use App\Http\Controllers\Risk\RiskController;
use App\Http\Controllers\Scan\ScanController;
use App\Http\Controllers\Target\TargetController;
use Illuminate\Support\Facades\Route;

// Public auth-status check — always 200, never 401
Route::get('/auth/me', [AuthController::class, 'me']);

Route::get('/system/status', function () {
    return response()->json([
        'version' => config('app.version', '1.0.0'),
        'application' => 'Healthy',
        'database' => \Illuminate\Support\Facades\DB::connection()->getPdo() ? 'Connected' : 'Error',
        'queue' => 'Available',
        'license' => \App\Facades\LicenseGate::status()['status'] ?? 'Community'
    ]);
});

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

use App\Http\Controllers\Auth\TokenController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/tokens', [TokenController::class, 'store']);
    Route::delete('/auth/tokens/{token}', [TokenController::class, 'destroy']);
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
    Route::post('/scans/{scan}/report', [ScanController::class, 'generateReport']);
    Route::get('/scans/{scan}/report/status', [ScanController::class, 'reportStatus']);
    Route::get('/scans/{scan}/report/download', [ScanController::class, 'downloadReport'])->name('scans.report.download');
    Route::apiResource('/scans', ScanController::class);

    // Finding Management Endpoints
    Route::get('/findings/{finding}/activity', [FindingController::class, 'activityLogs']);
    Route::apiResource('/findings', FindingController::class);

    // Risk Management Endpoints
    Route::post('/risks/{risk}/treatment', [RiskController::class, 'addTreatment']);
    Route::post('/risks/{risk}/accept', [RiskController::class, 'accept']);
    Route::apiResource('/risks', RiskController::class);

    // Report Management Endpoints
    Route::post('/reports/{report}/duplicate', [ReportController::class, 'duplicate']);
    Route::post('/reports/{report}/archive', [ReportController::class, 'archive']);
    Route::apiResource('/reports', ReportController::class);

    // Compliance Management Endpoints
    Route::get('/compliance/stats', [ComplianceController::class, 'stats']);
    Route::get('/compliance/{code}', [ComplianceController::class, 'show']);
    Route::get('/compliance', [ComplianceController::class, 'index']);

    // Git Repository Management Endpoints
    Route::post('/repositories/validate-access', [RepositoryController::class, 'validateAccess']);
    Route::post('/repositories/{repository}/scan', [RepositoryController::class, 'scan']);
    Route::get('/repositories/{repository}/scans', [RepositoryController::class, 'scans']);
    Route::apiResource('/repositories', RepositoryController::class);

    // Integration Management Endpoints
    Route::get('/integrations/stats', [IntegrationController::class, 'stats']);
    Route::get('/integrations/connector/{code}', [IntegrationController::class, 'byConnector']);
    Route::post('/integrations/{integration}/validate', [IntegrationController::class, 'validateConnection']);
    Route::post('/integrations/{integration}/import', [ImportController::class, 'triggerConnectionImport']);
    Route::post('/integrations/{integration}/disconnect', [IntegrationController::class, 'disconnect']);
    Route::apiResource('/integrations', IntegrationController::class);

    // Import Engine Endpoints
    Route::post('/imports/upload', [ImportController::class, 'upload']);
    Route::post('/imports/preview', [ImportController::class, 'preview']);
    Route::get('/imports/history', [ImportController::class, 'history']);
    Route::get('/imports', [ImportController::class, 'index']);
    Route::get('/imports/{job}', [ImportController::class, 'show']);
    Route::delete('/imports/{job}', [ImportController::class, 'destroy']);

    // Notification Management Endpoints
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Notification\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/mark-read', [\App\Http\Controllers\Notification\NotificationController::class, 'markRead']);
    Route::apiResource('/notifications', \App\Http\Controllers\Notification\NotificationController::class)->only(['index', 'show', 'destroy']);

    // Settings Management Endpoints
    Route::get('/settings/mail', [SettingController::class, 'getMailSettings']);
    Route::put('/settings/mail', [SettingController::class, 'updateMailSettings']);
    Route::post('/settings/test-email', [SettingController::class, 'testEmail']);

    // User Preferences
    Route::get('/users/preferences', [UserPreferenceController::class, 'index']);
    Route::put('/users/preferences', [UserPreferenceController::class, 'update']);
});
