<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DTO\Auth\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Authentication\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {}

    /**
     * Authenticate session credentials and return profile payload.
     */
    public function login(LoginRequest $request): UserResource
    {
        $loginData = new LoginData(
            email: $request->validated('email'),
            password: $request->validated('password'),
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent() ?? 'Unknown'
        );

        $result = $this->authService->login($loginData);

        return (new UserResource($result->user))
            ->additional([
                'meta' => [
                    'session_id' => $result->sessionId,
                    'message' => $result->message,
                ],
            ]);
    }

    /**
     * Return the currently authenticated user, or {"authenticated": false}.
     * Always returns HTTP 200 — never 401 — so the SPA can check session
     * status without producing console errors on unauthenticated page loads.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = auth('sanctum')->user() ?? $request->user();

        if (! $user) {
            return response()->json(['authenticated' => false]);
        }

        $user->load('role');

        return response()->json([
            'authenticated' => true,
            'data' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
                'role' => $user->role ? [
                    'name' => $user->role->name,
                    'slug' => $user->role->slug,
                ] : null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Terminate the active authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
