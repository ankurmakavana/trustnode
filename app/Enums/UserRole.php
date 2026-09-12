<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case ADMINISTRATOR = 'administrator';
    case SECURITY_MANAGER = 'security_manager';
    case MANAGER = 'manager';
    case DEVELOPER = 'developer';
    case OPERATOR = 'operator';
    case VIEWER = 'viewer';

    /**
     * Get the display name for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMINISTRATOR => 'Administrator',
            self::SECURITY_MANAGER => 'Security Manager',
            self::MANAGER => 'Manager',
            self::DEVELOPER => 'Developer',
            self::OPERATOR => 'Operator',
            self::VIEWER => 'Viewer',
        };
    }
}
