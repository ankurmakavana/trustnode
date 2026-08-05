<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionService;

/**
 * HasPermissions
 *
 * Provides permission and role helper methods for the User model.
 * All permission resolution goes through PermissionService so that
 * Redis cache is honoured — the database is never queried more than
 * once per role per TTL window.
 *
 * Prerequisite: the model must have a `role()` BelongsTo relationship
 * and a `role_id` foreign key.
 */
trait HasPermissions
{
    /**
     * Return the cached permission set for this user's role.
     *
     * @return string[]
     */
    protected function resolvedPermissions(): array
    {
        if ($this->role === null) {
            return [];
        }

        return app(PermissionService::class)
            ->getPermissionsForRole($this->role->slug);
    }

    /**
     * Return true if the user's role includes the given permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->resolvedPermissions(), true);
    }

    /**
     * Return true if the user's role includes at least one of the given permissions.
     *
     * @param  string[]  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $resolved = $this->resolvedPermissions();

        foreach ($permissions as $permission) {
            if (in_array($permission, $resolved, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return true if the user's role includes ALL of the given permissions.
     *
     * @param  string[]  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $resolved = $this->resolvedPermissions();

        foreach ($permissions as $permission) {
            if (! in_array($permission, $resolved, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return true if the user's role slug matches the given slug.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->role?->slug === $roleSlug;
    }

    /**
     * Assign a new role to the user by slug.
     * Persists immediately. Invalidates the old and new role caches.
     */
    public function assignRole(string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        $this->role_id = $role->id;
        $this->save();

        // Refresh the in-memory relationship
        $this->load('role');
    }

    /**
     * Sync the permissions granted to the user's role.
     * Delegates to the role model so the cache can be invalidated correctly.
     *
     * @param  string[]  $permissionNames
     */
    public function syncPermissions(array $permissionNames): void
    {
        $role = $this->role;

        if ($role === null) {
            return;
        }

        $permissionIds = Permission::whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        // Invalidate cache for this role
        app(PermissionService::class)->invalidateForRole($role->slug);
    }
}
