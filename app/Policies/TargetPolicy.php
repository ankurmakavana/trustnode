<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * TargetPolicy — V1 Foundation
 *
 * Governs access to Target resources. All checks delegate to the
 * HasPermissions trait backed by PermissionService (Redis cache).
 */
final class TargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('targets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('targets.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('targets.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('targets.delete');
    }
}
