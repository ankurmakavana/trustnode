<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\DTO\Auth\AuthResult;
use App\DTO\Auth\ChangePasswordData;
use App\DTO\Auth\LoginData;
use App\Enums\UserStatus;
use App\Events\Auth\PasswordChanged;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\AuthenticationFailedException;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;

class AuthenticationService
{
    public function __construct(
        protected SessionService $sessionService,
        protected Hasher $hasher,
        protected Dispatcher $dispatcher,
        protected Config $config
    ) {}

    /**
     * Authenticate user credentials, initiate a session, and return AuthResult.
     *
     * @throws AuthenticationFailedException
     * @throws AccountSuspendedException
     */
    public function login(LoginData $data): AuthResult
    {
        $user = User::where('email', $data->email)->first();

        if (! $user) {
            throw new AuthenticationFailedException;
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw new AccountSuspendedException;
        }

        if (! $this->hasher->check($data->password, $user->password)) {
            throw new AuthenticationFailedException;
        }

        $sessionId = $this->sessionService->startSession($user, $data->ipAddress, $data->userAgent);

        $this->dispatcher->dispatch(new UserLoggedIn($user));

        return new AuthResult($user, $sessionId, 'Login successful.');
    }

    /**
     * Log out the authenticated user.
     */
    public function logout(User $user): void
    {
        $this->sessionService->terminateSession();
        $this->dispatcher->dispatch(new UserLoggedOut($user));
    }

    /**
     * Change password for the user, update security tracking fields, and invalidate other sessions if configured.
     *
     * @throws AuthenticationFailedException
     */
    public function changePassword(User $user, ChangePasswordData $data): void
    {
        if (! $this->hasher->check($data->currentPassword, $user->password)) {
            throw new AuthenticationFailedException('Current password does not match.');
        }

        $user->update([
            'password' => $this->hasher->make($data->newPassword),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ]);

        if ($this->config->get('trustnode.security.auth.session.single_session_only', false)) {
            $this->sessionService->invalidateOtherSessions($user);
        }

        $this->dispatcher->dispatch(new PasswordChanged($user));
    }
}
