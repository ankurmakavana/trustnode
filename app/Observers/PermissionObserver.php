<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Permission;
use App\Services\Authorization\PermissionService;

/**
 * PermissionObserver
 *
 * When a Permission record is updated or its pivot (permission_role) is
 * modified, ALL role caches are flushed because a single permission change
 * can affect multiple roles simultaneously.
 */
final class PermissionObserver
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function updated(Permission $permission): void
    {
        $this->permissionService->invalidateAll();
    }

    public function deleted(Permission $permission): void
    {
        $this->permissionService->invalidateAll();
    }
}
