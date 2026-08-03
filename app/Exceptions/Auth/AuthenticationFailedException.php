<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Exception;

final class AuthenticationFailedException extends Exception
{
    public function __construct(string $message = 'Invalid email or password.')
    {
        parent::__construct($message, 401);
    }
}
