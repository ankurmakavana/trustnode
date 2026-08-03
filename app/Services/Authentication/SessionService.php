<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\SessionManager;

class SessionService
{
    public function __construct(
        protected StatefulGuard $guard,
        protected SessionManager $session
    ) {}

    /**
     * Start user session, update login logs, and return the new session ID.
     */
    public function startSession(User $user, string $ipAddress, string $userAgent): string
    {
        $this->guard->login($user);
        $this->session->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        return $this->session->getId();
    }

    /**
     * Terminate the current session, log out the user, and rotate CSRF tokens.
     */
    public function terminateSession(): void
    {
        $this->guard->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }

    /**
     * Invalidate all other active sessions for the user.
     */
    public function invalidateOtherSessions(User $user): void
    {
        // TODO: Implement other active session invalidations (e.g., using Redis or database session tables).
    }
}
