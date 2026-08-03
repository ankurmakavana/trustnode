<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMINISTRATOR = 'administrator';
    case MANAGER = 'manager';
    case OPERATOR = 'operator';

    /**
     * Get the display name for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrator',
            self::MANAGER => 'Manager',
            self::OPERATOR => 'Operator',
        };
    }
}
