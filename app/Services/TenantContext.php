<?php

namespace App\Services;

class TenantContext
{
    /**
     * The current tenant ID (user ID in this context).
     */
    protected static ?int $tenantId = null;

    /**
     * Set the current tenant ID.
     */
    public static function setUserId(int $id): void
    {
        self::$tenantId = $id;
    }

    /**
     * Get the current tenant ID.
     */
    public static function currentUserId(): ?int
    {
        return self::$tenantId;
    }

    /**
     * Clear the current tenant context.
     */
    public static function clear(): void
    {
        self::$tenantId = null;
    }
}
