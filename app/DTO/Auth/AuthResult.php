<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\Models\User;

final readonly class AuthResult
{
    public function __construct(
        public User $user,
        public string $sessionId,
        public string $message
    ) {}
}
