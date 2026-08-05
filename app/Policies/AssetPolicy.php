<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * AssetPolicy — V1 Foundation
 *
 * Governs access to Asset resources. All checks delegate to the
 * HasPermissions trait backed by PermissionService (Redis cache).
 */
final class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assets.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('assets.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('assets.delete');
    }
}
