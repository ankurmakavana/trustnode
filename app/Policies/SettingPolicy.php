<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Setting;

class SettingPolicy
{
    /**
     * Determine whether the user can view settings.
     */
    public function viewSettings(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can update settings.
     */
    public function updateSettings(User $user): bool
    {
        return $user->hasRole('administrator');
    }
}
