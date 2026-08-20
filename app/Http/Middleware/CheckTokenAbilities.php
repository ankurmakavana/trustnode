<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenAbilities
{
    private const RESOURCE_ABILITY_MAP = [
        'scans' => [
            'GET' => 'scans:read',
            'POST' => 'scans:write',
            'PUT' => 'scans:write',
            'PATCH' => 'scans:write',
            'DELETE' => 'scans:write',
        ],
        'findings' => [
            'GET' => 'findings:read',
        ],
        'reports' => [
            'GET' => 'reports:read',
            'POST' => 'reports:write',
            'PUT' => 'reports:write',
            'PATCH' => 'reports:write',
            'DELETE' => 'reports:write',
        ],
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If the user authenticated via a Sanctum Token
        if ($user && $user->currentAccessToken() instanceof PersonalAccessToken) {
            $path = $request->path();
            $method = $request->method();
            
            // Extract the base resource from path (e.g. api/scans/123 -> scans)
            $segments = explode('/', trim($path, '/'));
            $resource = $segments[1] ?? null; // segments[0] is 'api'

            if ($resource && isset(self::RESOURCE_ABILITY_MAP[$resource])) {
                $requiredAbility = self::RESOURCE_ABILITY_MAP[$resource][$method] ?? null;
                
                if ($requiredAbility && !$user->tokenCan($requiredAbility)) {
                    abort(403, "Token missing required ability: {$requiredAbility}");
                }
            }
        }

        return $next($request);
    }
}
