<?php

declare(strict_types=1);

namespace App\Exceptions\Authorization;

use Exception;

final class AuthorizationException extends Exception
{
    public function __construct(string $message = 'You do not have permission to perform this action.')
    {
        parent::__construct($message, 403);
    }
}
