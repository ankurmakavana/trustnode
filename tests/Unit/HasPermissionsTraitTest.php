<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HasPermissionsTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeUser(array $permissions, string $roleSlug = 'test-role'): User
    {
        $role = Role::create(['name' => ucfirst($roleSlug), 'slug' => $roleSlug]);

        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($perm->id);
        }

        return User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    // -------------------------------------------------------------------------
    // hasPermission()
    // -------------------------------------------------------------------------

    public function test_has_permission_returns_true_when_granted(): void
    {
        $user = $this->makeUser(['assets.view']);

        $this->assertTrue($user->hasPermission('assets.view'));
    }

    public function test_has_permission_returns_false_when_not_granted(): void
    {
        $user = $this->makeUser(['assets.view']);

        $this->assertFalse($user->hasPermission('assets.delete'));
    }

    // -------------------------------------------------------------------------
    // hasAnyPermission()
    // -------------------------------------------------------------------------

    public function test_has_any_permission_returns_true_when_one_matches(): void
    {
        $user = $this->makeUser(['scans.view']);

        $this->assertTrue($user->hasAnyPermission(['assets.view', 'scans.view']));
    }

    public function test_has_any_permission_returns_false_when_none_match(): void
    {
        $user = $this->makeUser(['scans.view']);

        $this->assertFalse($user->hasAnyPermission(['assets.delete', 'users.create']));
    }

    // -------------------------------------------------------------------------
    // hasAllPermissions()
    // -------------------------------------------------------------------------

    public function test_has_all_permissions_returns_true_when_all_granted(): void
    {
        $user = $this->makeUser(['scans.view', 'scans.execute']);

        $this->assertTrue($user->hasAllPermissions(['scans.view', 'scans.execute']));
    }

    public function test_has_all_permissions_returns_false_when_one_missing(): void
    {
        $user = $this->makeUser(['scans.view']);

        $this->assertFalse($user->hasAllPermissions(['scans.view', 'scans.execute']));
    }

    // -------------------------------------------------------------------------
    // hasRole()
    // -------------------------------------------------------------------------

    public function test_has_role_returns_true_for_matching_slug(): void
    {
        $user = $this->makeUser([], 'operator');

        $this->assertTrue($user->hasRole('operator'));
    }

    public function test_has_role_returns_false_for_wrong_slug(): void
    {
        $user = $this->makeUser([], 'operator');

        $this->assertFalse($user->hasRole('administrator'));
    }

    // -------------------------------------------------------------------------
    // assignRole()
    // -------------------------------------------------------------------------

    public function test_assign_role_updates_user_role(): void
    {
        $user = $this->makeUser([], 'operator');
        Role::create(['name' => 'Admin', 'slug' => 'administrator']);

        $user->assignRole('administrator');
        $user->refresh();

        $this->assertTrue($user->hasRole('administrator'));
    }

    // -------------------------------------------------------------------------
    // syncPermissions()
    // -------------------------------------------------------------------------

    public function test_sync_permissions_updates_role_permissions(): void
    {
        Permission::firstOrCreate(['name' => 'reports.view'], ['description' => 'View reports']);
        $user = $this->makeUser(['assets.view']);

        $user->syncPermissions(['reports.view']);

        // Cache is invalidated — must re-resolve from DB
        $this->assertTrue($user->hasPermission('reports.view'));
    }
}
