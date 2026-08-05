<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = app(PermissionService::class);
    }

    public function test_returns_permissions_for_role(): void
    {
        $role = Role::create(['name' => 'Tester', 'slug' => 'tester']);
        $perm = Permission::create(['name' => 'assets.view', 'description' => 'View assets']);
        $role->permissions()->attach($perm->id);

        $permissions = $this->service->getPermissionsForRole('tester');

        $this->assertContains('assets.view', $permissions);
    }

    public function test_caches_permissions_after_first_load(): void
    {
        $role = Role::create(['name' => 'Tester', 'slug' => 'tester']);
        $perm = Permission::create(['name' => 'scans.view', 'description' => 'View scans']);
        $role->permissions()->attach($perm->id);

        // Prime the cache
        $this->service->getPermissionsForRole('tester');

        // Detach without invalidating cache — service must still return from cache
        $role->permissions()->detach($perm->id);

        $permissions = $this->service->getPermissionsForRole('tester');

        $this->assertContains('scans.view', $permissions);
    }

    public function test_invalidate_for_role_clears_specific_cache(): void
    {
        $role = Role::create(['name' => 'Tester', 'slug' => 'tester']);
        $perm = Permission::create(['name' => 'reports.view', 'description' => 'View reports']);
        $role->permissions()->attach($perm->id);

        // Prime the cache
        $this->service->getPermissionsForRole('tester');

        // Detach and invalidate
        $role->permissions()->detach($perm->id);
        $this->service->invalidateForRole('tester');

        // Must re-query from DB — permission no longer present
        $permissions = $this->service->getPermissionsForRole('tester');

        $this->assertNotContains('reports.view', $permissions);
    }

    public function test_invalidate_all_clears_all_role_caches(): void
    {
        $role1 = Role::create(['name' => 'A', 'slug' => 'role-a']);
        $role2 = Role::create(['name' => 'B', 'slug' => 'role-b']);
        $perm = Permission::create(['name' => 'users.view', 'description' => 'View users']);
        $role1->permissions()->attach($perm->id);
        $role2->permissions()->attach($perm->id);

        // Prime both caches
        $this->service->getPermissionsForRole('role-a');
        $this->service->getPermissionsForRole('role-b');

        // Detach from both, then invalidate all
        $role1->permissions()->detach();
        $role2->permissions()->detach();
        $this->service->invalidateAll();

        $this->assertEmpty($this->service->getPermissionsForRole('role-a'));
        $this->assertEmpty($this->service->getPermissionsForRole('role-b'));
    }

    public function test_returns_empty_array_for_unknown_role(): void
    {
        $permissions = $this->service->getPermissionsForRole('non-existent-role');

        $this->assertSame([], $permissions);
    }
}
