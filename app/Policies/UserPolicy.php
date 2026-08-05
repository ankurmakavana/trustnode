<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * UserPolicy
 *
 * Delegates to the HasPermissions trait via the Gate-registered abilities.
 * Authorization never reads the UserRole enum at runtime — it resolves from
 * the database role and its cached permission set.
 */
final class UserPolicy
{
    /**
     * View any user in the system.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermission('users.view');
    }

    /**
     * Create a new user.
     */
    public function create(User $authUser): bool
    {
        return $authUser->hasPermission('users.create');
    }

    /**
     * Update an existing user.
     */
    public function update(User $authUser, User $targetUser): bool
    {
        return $authUser->hasPermission('users.update');
    }

    /**
     * Delete (soft-delete) a user.
     */
    public function delete(User $authUser, User $targetUser): bool
    {
        return $authUser->hasPermission('users.delete');
    }
}
