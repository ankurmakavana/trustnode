<?php

declare(strict_types=1);

namespace App\DTO\Auth;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $ipAddress,
        public string $userAgent
    ) {}
}
