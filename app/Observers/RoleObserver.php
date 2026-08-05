<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Role;
use App\Services\Authorization\PermissionService;

/**
 * RoleObserver
 *
 * Invalidates the permission cache for a role when it is updated or deleted.
 * This ensures PermissionService never returns stale data after a role change.
 */
final class RoleObserver
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function updated(Role $role): void
    {
        $this->permissionService->invalidateForRole($role->slug);
    }

    public function deleted(Role $role): void
    {
        $this->permissionService->invalidateForRole($role->slug);
    }
}
