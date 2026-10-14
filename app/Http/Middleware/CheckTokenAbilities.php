<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Enforces the abilities of personal access tokens on every API request made with a token.
 *
 * This runs in the API group, before the route's auth:sanctum guard has resolved the user. The token
 * user is therefore resolved here through the sanctum guard. Browser sessions are not tokens and are not
 * restricted by this middleware.
 *
 * Default deny: a token may reach an API resource only if the resource is mapped below and the token has
 * the matching ability. The exceptions are the small set in ALWAYS_ALLOWED and self-revocation.
 */
class CheckTokenAbilities
{
    /** Resource => ability per HTTP read/write. Writes need the write ability. */
    private const RESOURCE_ABILITY_MAP = [
        'scans' => ['read' => 'scans:read', 'write' => 'scans:write'],
        'findings' => ['read' => 'findings:read', 'write' => 'findings:write'],
        'reports' => ['read' => 'reports:read', 'write' => 'reports:write'],
        'projects' => ['read' => 'projects:read', 'write' => 'projects:write'],
        'repositories' => ['read' => 'repositories:read', 'write' => 'repositories:write'],
        'notifications' => ['read' => 'notifications:read', 'write' => 'notifications:write'],
        'agent' => ['read' => 'agent:read', 'write' => 'agent:write'],
    ];

    /** Read-only endpoints every valid token may use. They expose no other user's data. */
    private const ALWAYS_ALLOWED_GET = [
        'api/auth/me',
        'api/system/status',
        'api/users/preferences',
    ];

    public function handle(Request $request, Closure $next)
    {
        $token = Auth::guard('sanctum')->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $this->tokenMayAccess($request, $token)) {
            abort(403, 'This token does not have permission for this endpoint.');
        }

        return $next($request);
    }

    private function tokenMayAccess(Request $request, PersonalAccessToken $token): bool
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        // A token cannot mint another token. That would let a restricted token gain full abilities.
        if ($path === 'api/auth/tokens' && $method === 'POST') {
            return false;
        }

        // A token may always revoke itself or another token of its owner.
        if (str_starts_with($path, 'api/auth/tokens/') && $method === 'DELETE') {
            return true;
        }

        if (in_array($method, ['GET', 'HEAD'], true) && in_array($path, self::ALWAYS_ALLOWED_GET, true)) {
            return true;
        }

        $segments = explode('/', $path);
        $resource = $segments[1] ?? null;

        if ($resource === null || ! isset(self::RESOURCE_ABILITY_MAP[$resource])) {
            return false;
        }

        $kind = in_array($method, ['GET', 'HEAD'], true) ? 'read' : 'write';

        return $token->can(self::RESOURCE_ABILITY_MAP[$resource][$kind]);
    }
}
