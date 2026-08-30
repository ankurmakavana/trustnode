<?php

namespace App\Enums\Finding;

enum FindingLifecycleStatus: string
{
    case NEW = 'new';
    case RECURRING = 'recurring';
    case RESOLVED = 'resolved';
    case REGRESSION = 'regression';

    /**
     * Get all values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
