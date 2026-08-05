<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles and Permissions
        $this->call(RolePermissionSeeder::class);

        // 2. Fetch administrator role by slug (No ID coupling)
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        // 3. Seed default system administrator user
        User::firstOrCreate(
            ['email' => 'admin@trustnode.local'],
            [
                'name' => 'TrustNode Admin',
                'password' => bcrypt('password'), // Recommended default password
                'role_id' => $adminRole->id,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'UTC',
                'locale' => 'en',
                'email_verified_at' => now(),
            ]
        );

        // 4. Seed Asset Management Data
        $this->call(AssetSeeder::class);

        // 5. Seed Target Management Data
        $this->call(TargetSeeder::class);
    }
}
