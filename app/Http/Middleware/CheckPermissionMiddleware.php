<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Authorization\AuthorizationException;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckPermissionMiddleware
 *
 * Verifies that the authenticated user's role includes the required permission.
 * Resolves the user from the authenticated guard; never trusts header values.
 *
 * Usage: Route::middleware('permission:users.view')
 */
final class CheckPermissionMiddleware
{
    public function __construct(private readonly StatefulGuard $guard) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $this->guard->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
