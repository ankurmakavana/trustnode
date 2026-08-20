<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TokenController extends Controller
{
    private const ALLOWED_ABILITIES = [
        'scans:read',
        'scans:write',
        'findings:read',
        'reports:read',
        'reports:write',
    ];

    /**
     * Create a new personal access token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'in:' . implode(',', self::ALLOWED_ABILITIES)],
        ]);

        $abilities = $validated['abilities'] ?? [];

        /** @var \App\Models\User $user */
        $user = $request->user();

        $token = $user->createToken($validated['name'], $abilities);

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => 'Token created successfully. Store it safely as it will not be shown again.',
        ], 201);
    }

    /**
     * Revoke a personal access token by ID.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->tokens()->where('id', $id)->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}
