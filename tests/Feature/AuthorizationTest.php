<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * AuthorizationTest
 *
 * Feature tests covering:
 * - Administrator access (full access)
 * - Manager access (partial access)
 * - Operator restrictions (view-only)
 * - Missing permission → 403
 * - Missing role → 403
 * - Middleware (CheckPermissionMiddleware, CheckRoleMiddleware)
 * - Policy denial
 * - Gate authorization
 * - Permission cache population and invalidation
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected Role $managerRole;

    protected Role $operatorRole;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $this->managerRole = Role::where('slug', UserRole::MANAGER->value)->first();
        $this->operatorRole = Role::where('slug', UserRole::OPERATOR->value)->first();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function makeUser(Role $role): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    // -----------------------------------------------------------------------
    // Middleware: permission
    // -----------------------------------------------------------------------

    public function test_middleware_grants_access_when_permission_present(): void
    {
        // Register a test route protected by the permission middleware
        $this->app['router']->get('/_test/permission', function () {
            return response()->json(['ok' => true]);
        })->middleware(['auth', 'permission:users.view']);

        $admin = $this->makeUser($this->adminRole);

        $response = $this->actingAs($admin)->getJson('/_test/permission');

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_middleware_denies_access_when_permission_missing(): void
    {
        $this->app['router']->get('/_test/permission-deny', function () {
            return response()->json(['ok' => true]);
        })->middleware(['auth', 'permission:users.delete']);

        $operator = $this->makeUser($this->operatorRole);

        $response = $this->actingAs($operator)->getJson('/_test/permission-deny');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ]);
    }

    public function test_middleware_returns_401_for_unauthenticated_user(): void
    {
        $this->app['router']->get('/_test/permission-unauth', function () {
            return response()->json(['ok' => true]);
        })->middleware(['permission:users.view']);

        $response = $this->getJson('/_test/permission-unauth');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Middleware: role
    // -----------------------------------------------------------------------

    public function test_role_middleware_grants_access_for_correct_role(): void
    {
        $this->app['router']->get('/_test/role-admin', function () {
            return response()->json(['ok' => true]);
        })->middleware(['auth', 'role:administrator']);

        $admin = $this->makeUser($this->adminRole);

        $this->actingAs($admin)->getJson('/_test/role-admin')->assertStatus(200);
    }

    public function test_role_middleware_denies_access_for_wrong_role(): void
    {
        $this->app['router']->get('/_test/role-deny', function () {
            return response()->json(['ok' => true]);
        })->middleware(['auth', 'role:administrator']);

        $operator = $this->makeUser($this->operatorRole);

        $this->actingAs($operator)->getJson('/_test/role-deny')->assertStatus(403);
    }

    public function test_role_middleware_accepts_multiple_allowed_roles(): void
    {
        $this->app['router']->get('/_test/role-multi', function () {
            return response()->json(['ok' => true]);
        })->middleware(['auth', 'role:administrator,manager']);

        $manager = $this->makeUser($this->managerRole);

        $this->actingAs($manager)->getJson('/_test/role-multi')->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Gate authorization
    // -----------------------------------------------------------------------

    public function test_gate_allows_administrator_all_permissions(): void
    {
        $admin = $this->makeUser($this->adminRole);

        $this->assertTrue($admin->can('users.view'));
        $this->assertTrue($admin->can('users.create'));
        $this->assertTrue($admin->can('assets.manage') || $admin->can('assets.view'));
        $this->assertTrue($admin->can('scans.execute'));
        $this->assertTrue($admin->can('reports.export'));
    }

    public function test_gate_denies_operator_restricted_permissions(): void
    {
        $operator = $this->makeUser($this->operatorRole);

        $this->assertFalse($operator->can('users.create'));
        $this->assertFalse($operator->can('users.delete'));
        $this->assertFalse($operator->can('scans.execute'));
        $this->assertFalse($operator->can('reports.export'));
    }

    public function test_gate_allows_manager_permitted_abilities(): void
    {
        $manager = $this->makeUser($this->managerRole);

        $this->assertTrue($manager->can('assets.view'));
        $this->assertTrue($manager->can('scans.execute'));
        $this->assertTrue($manager->can('reports.export'));
    }

    public function test_gate_denies_manager_admin_only_permissions(): void
    {
        $manager = $this->makeUser($this->managerRole);

        $this->assertFalse($manager->can('users.create'));
        $this->assertFalse($manager->can('users.delete'));
    }

    // -----------------------------------------------------------------------
    // Policy denial
    // -----------------------------------------------------------------------

    public function test_user_policy_denies_create_for_operator(): void
    {
        $operator = $this->makeUser($this->operatorRole);
        $target = $this->makeUser($this->operatorRole);

        $this->assertFalse($operator->can('create', User::class));
    }

    public function test_user_policy_allows_view_any_for_manager(): void
    {
        $manager = $this->makeUser($this->managerRole);

        $this->assertTrue($manager->can('viewAny', User::class));
    }

    // -----------------------------------------------------------------------
    // Permission cache
    // -----------------------------------------------------------------------

    public function test_permission_cache_is_populated_on_first_check(): void
    {
        $admin = $this->makeUser($this->adminRole);

        // Ensure cache is empty, then trigger resolution
        Cache::flush();
        $admin->hasPermission('users.view');

        $this->assertTrue(
            Cache::has('role_permissions:'.UserRole::ADMINISTRATOR->value)
        );
    }

    public function test_cache_invalidation_on_permission_update(): void
    {
        $admin = $this->makeUser($this->adminRole);

        // Prime the cache
        $admin->hasPermission('users.view');
        $this->assertTrue(Cache::has('role_permissions:administrator'));

        // Triggering observer via update
        $permission = Permission::where('name', 'users.view')->first();
        $permission->update(['description' => 'Updated description']);

        // PermissionObserver must have cleared ALL role caches
        $this->assertFalse(Cache::has('role_permissions:administrator'));
    }

    public function test_cache_invalidation_on_role_update(): void
    {
        $admin = $this->makeUser($this->adminRole);

        Cache::flush();
        $admin->hasPermission('users.view');
        $this->assertTrue(Cache::has('role_permissions:administrator'));

        // Triggering RoleObserver
        $this->adminRole->update(['name' => 'Administrator (Updated)']);

        $this->assertFalse(Cache::has('role_permissions:administrator'));
    }
}
