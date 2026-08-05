<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * ScanPolicy — V1 Foundation
 *
 * Governs access to Scan resources. Permission checks delegate to
 * HasPermissions so the Redis cache is always consulted first.
 */
final class ScanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('scans.view');
    }

    public function execute(User $user): bool
    {
        return $user->hasPermission('scans.execute');
    }
}
