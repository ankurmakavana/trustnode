<?php

namespace App\Enums\Target;

enum TargetStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNDER_REVIEW = 'under_review';

    /**
     * Get all values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
