<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Exception;

final class AccountSuspendedException extends Exception
{
    public function __construct(string $message = 'Your account has been suspended.')
    {
        parent::__construct($message, 403);
    }
}
