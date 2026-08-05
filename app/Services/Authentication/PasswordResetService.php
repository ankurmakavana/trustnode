<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Exceptions\Auth\AuthenticationFailedException;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        protected Hasher $hasher
    ) {}

    /**
     * Issue a password reset token and dispatch standard recovery hook.
     *
     * @throws AuthenticationFailedException
     */
    public function sendResetLink(string $email): string
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw new AuthenticationFailedException('User with this email does not exist.');
        }

        // Return a mock/generated token for recovery verification
        return Password::broker()->createToken($user);
    }

    /**
     * Validate the token and update user credentials.
     *
     * @throws AuthenticationFailedException
     */
    public function resetPassword(string $email, string $token, string $password): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw new AuthenticationFailedException('User with this email does not exist.');
        }

        if (! Password::broker()->tokenExists($user, $token)) {
            throw new AuthenticationFailedException('Invalid or expired reset token.');
        }

        $user->update([
            'password' => $this->hasher->make($password),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ]);

        Password::broker()->deleteToken($user);
    }
}
