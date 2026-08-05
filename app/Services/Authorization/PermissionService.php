<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;

/**
 * PermissionService
 *
 * Loads and caches role permissions once per TTL window.
 * Cache is keyed by role slug so different roles are independent.
 * Automatic invalidation is triggered via model observers on
 * Role, Permission, and the pivot (permission_role) table.
 */
final class PermissionService
{
    /** Cache TTL in seconds (24 hours). */
    private const TTL = 86400;

    /** Cache key prefix for role permission sets. */
    private const KEY_PREFIX = 'role_permissions:';

    /**
     * Return the set of permission names for the given role slug.
     * Results are cached in Redis; subsequent calls within the TTL window
     * hit the cache only — no additional database queries.
     *
     * @return string[]
     */
    public function getPermissionsForRole(string $roleSlug): array
    {
        return Cache::remember(
            self::KEY_PREFIX.$roleSlug,
            self::TTL,
            function () use ($roleSlug): array {
                $role = Role::with('permissions')
                    ->where('slug', $roleSlug)
                    ->first();

                if ($role === null) {
                    return [];
                }

                return $role->permissions
                    ->pluck('name')
                    ->all();
            }
        );
    }

    /**
     * Invalidate the permission cache for a specific role slug.
     */
    public function invalidateForRole(string $roleSlug): void
    {
        Cache::forget(self::KEY_PREFIX.$roleSlug);
    }

    /**
     * Flush ALL role permission caches.
     * Used when a Permission or pivot row is updated and the affected
     * roles are not easily enumerable without an extra query.
     */
    public function invalidateAll(): void
    {
        $roles = Role::pluck('slug')->all();

        foreach ($roles as $slug) {
            Cache::forget(self::KEY_PREFIX.$slug);
        }
    }
}
