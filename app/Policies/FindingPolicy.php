<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

class FindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('manager') || $user->hasRole('operator');
    }

    public function view(User $user, Finding $finding): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('manager') || $user->hasRole('operator');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('manager');
    }

    public function update(User $user, Finding $finding): bool
    {
        return $user->hasRole('administrator') || $user->hasRole('manager');
    }

    public function delete(User $user, Finding $finding): bool
    {
        return $user->hasRole('administrator');
    }
}
