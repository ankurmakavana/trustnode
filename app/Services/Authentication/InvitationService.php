<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Enums\UserStatus;
use App\Exceptions\Auth\AuthenticationFailedException;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Hashing\Hasher;

class InvitationService
{
    public function __construct(
        protected Hasher $hasher
    ) {}

    /**
     * Locate an active invitation by token.
     *
     * @throws AuthenticationFailedException
     */
    public function getInvitationByToken(string $token): UserInvitation
    {
        $hash = hash('sha256', $token);
        $invitation = UserInvitation::where('token_hash', $hash)->first();

        if (! $invitation) {
            throw new AuthenticationFailedException('Invitation not found.');
        }

        if ($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at?->isPast()) {
            throw new AuthenticationFailedException('This invitation is no longer active.');
        }

        return $invitation;
    }

    /**
     * Accept invitation, create user profile, and flag the token as claimed.
     *
     * @throws AuthenticationFailedException
     */
    public function acceptInvitation(string $token, string $name, string $password, string $ipAddress): User
    {
        $invitation = $this->getInvitationByToken($token);

        $user = User::create([
            'name' => $name,
            'email' => $invitation->email,
            'password' => $this->hasher->make($password),
            'role_id' => $invitation->role_id,
            'status' => UserStatus::ACTIVE,
        ]);

        $invitation->update([
            'accepted_at' => now(),
            'accepted_by_ip' => $ipAddress,
        ]);

        return $user;
    }
}
