<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * ReportPolicy — V1 Foundation
 *
 * Governs access to Report resources. All permission checks delegate
 * to the HasPermissions trait backed by PermissionService (Redis cache).
 */
final class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }
}
