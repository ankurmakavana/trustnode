<?php

namespace App\Enums\Asset;

enum AssetStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case INACTIVE = 'inactive';

    /**
     * Get all values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
