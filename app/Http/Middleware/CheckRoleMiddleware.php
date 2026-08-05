<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Authorization\AuthorizationException;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRoleMiddleware
 *
 * Verifies that the authenticated user holds one of the required role slugs.
 * Supports multiple roles via pipe-separated string: 'role:administrator|manager'
 *
 * Usage: Route::middleware('role:administrator')
 *        Route::middleware('role:administrator|manager')
 */
final class CheckRoleMiddleware
{
    public function __construct(private readonly StatefulGuard $guard) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $this->guard->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        throw new AuthorizationException;
    }
}
