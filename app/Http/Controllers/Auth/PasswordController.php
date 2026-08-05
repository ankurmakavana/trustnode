<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DTO\Auth\ChangePasswordData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Authentication\AuthenticationService;
use App\Services\Authentication\PasswordResetService;
use Illuminate\Http\JsonResponse;

final class PasswordController extends Controller
{
    public function __construct(
        private AuthenticationService $authService,
        private PasswordResetService $passwordResetService
    ) {}

    /**
     * Update current authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $changeData = new ChangePasswordData(
            currentPassword: $request->validated('current_password'),
            newPassword: $request->validated('new_password')
        );

        $this->authService->changePassword($user, $changeData);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Dispatch recovery link to the target user email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $token = $this->passwordResetService->sendResetLink($request->validated('email'));

        return response()->json([
            'message' => 'Password reset link generated successfully.',
            'meta' => [
                'token' => $token, // Returned for API convenience
            ],
        ]);
    }

    /**
     * Commit the password change using a valid reset token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            email: $request->validated('email'),
            token: $request->validated('token'),
            password: $request->validated('password')
        );

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }
}
