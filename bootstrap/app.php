<?php

use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\AuthenticationFailedException;
use App\Exceptions\Authorization\AuthorizationException;
use App\Http\Middleware\CheckPermissionMiddleware;
use App\Http\Middleware\CheckRoleMiddleware;
use App\Providers\AuthServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        AuthServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermissionMiddleware::class,
            'role' => CheckRoleMiddleware::class,
        ]);

        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON 401 for all unauthenticated requests
        $exceptions->render(function (AuthenticationException $e) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        });

        $exceptions->render(function (AuthenticationFailedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        });

        $exceptions->render(function (AccountSuspendedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        });
    })->create();
