<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExtendedRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createRoleWithPermissions(string $roleSlug, string $roleName, array $permissionNames): Role
    {
        $role = Role::create(['slug' => $roleSlug, 'name' => $roleName]);

        foreach ($permissionNames as $permissionName) {
            $perm = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['description' => $permissionName]
            );
            $role->permissions()->attach($perm->id);
        }

        return $role;
    }

    private function createUserWithRole(string $roleSlug, string $roleName, array $permissionNames): User
    {
        $role = $this->createRoleWithPermissions($roleSlug, $roleName, $permissionNames);

        return User::create([
            'name' => ucfirst($roleSlug) . ' User',
            'email' => uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_owner_has_all_permissions(): void
    {
        $allPermissions = [
            'users.create', 'users.view', 'users.update', 'users.delete',
            'assets.create', 'assets.view', 'assets.update', 'assets.delete',
            'targets.create', 'targets.view', 'targets.update', 'targets.delete',
            'scans.execute', 'scans.view',
            'reports.export', 'reports.view',
            'findings.view', 'findings.update',
        ];

        $owner = $this->createUserWithRole(
            UserRole::OWNER->value,
            UserRole::OWNER->label(),
            $allPermissions
        );

        $this->assertTrue($owner->hasRole(UserRole::OWNER->value));
        $this->assertTrue($owner->hasAllPermissions($allPermissions));
    }

    public function test_security_manager_has_security_permissions(): void
    {
        $securityPermissions = [
            'assets.view',
            'targets.view',
            'scans.execute', 'scans.view',
            'reports.export', 'reports.view',
            'findings.view', 'findings.update',
        ];

        $securityManager = $this->createUserWithRole(
            UserRole::SECURITY_MANAGER->value,
            UserRole::SECURITY_MANAGER->label(),
            $securityPermissions
        );

        $this->assertTrue($securityManager->hasRole(UserRole::SECURITY_MANAGER->value));
        $this->assertTrue($securityManager->hasPermission('findings.view'));
        $this->assertTrue($securityManager->hasPermission('scans.execute'));
        $this->assertFalse($securityManager->hasPermission('users.create'));
        $this->assertFalse($securityManager->hasPermission('users.delete'));
    }

    public function test_developer_has_limited_permissions(): void
    {
        $devPermissions = [
            'assets.view',
            'targets.view',
            'scans.view',
            'reports.view',
            'findings.view', 'findings.update',
        ];

        $developer = $this->createUserWithRole(
            UserRole::DEVELOPER->value,
            UserRole::DEVELOPER->label(),
            $devPermissions
        );

        $this->assertTrue($developer->hasRole(UserRole::DEVELOPER->value));
        $this->assertTrue($developer->hasPermission('findings.view'));
        $this->assertFalse($developer->hasPermission('scans.execute'));
        $this->assertFalse($developer->hasPermission('users.view'));
    }

    public function test_viewer_can_only_view(): void
    {
        $viewerPermissions = [
            'assets.view',
            'targets.view',
            'scans.view',
            'reports.view',
            'findings.view',
        ];

        $viewer = $this->createUserWithRole(
            UserRole::VIEWER->value,
            UserRole::VIEWER->label(),
            $viewerPermissions
        );

        $this->assertTrue($viewer->hasRole(UserRole::VIEWER->value));
        $this->assertTrue($viewer->hasPermission('findings.view'));
        $this->assertFalse($viewer->hasPermission('findings.update'));
        $this->assertFalse($viewer->hasPermission('scans.execute'));
    }

    public function test_administrator_has_full_permissions(): void
    {
        $allPermissions = [
            'users.create', 'users.view', 'users.update', 'users.delete',
            'assets.create', 'assets.view', 'assets.update', 'assets.delete',
            'targets.create', 'targets.view', 'targets.update', 'targets.delete',
            'scans.execute', 'scans.view',
            'reports.export', 'reports.view',
            'findings.view', 'findings.update',
        ];

        $admin = $this->createUserWithRole(
            UserRole::ADMINISTRATOR->value,
            UserRole::ADMINISTRATOR->label(),
            $allPermissions
        );

        $this->assertTrue($admin->hasRole(UserRole::ADMINISTRATOR->value));
        $this->assertTrue($admin->hasAllPermissions($allPermissions));
    }

    public function test_user_can_be_reassigned_to_different_role(): void
    {
        $viewer = $this->createUserWithRole(
            UserRole::VIEWER->value,
            UserRole::VIEWER->label(),
            ['findings.view']
        );

        $this->assertTrue($viewer->hasPermission('findings.view'));
        $this->assertFalse($viewer->hasPermission('findings.update'));

        // Reassign to developer role
        $developer = $this->createUserWithRole(
            UserRole::DEVELOPER->value,
            UserRole::DEVELOPER->label(),
            ['findings.view', 'findings.update']
        );

        $viewer->assignRole(UserRole::DEVELOPER->value);
        $viewer->refresh();

        $this->assertTrue($viewer->hasRole(UserRole::DEVELOPER->value));
        $this->assertTrue($viewer->hasPermission('findings.update'));
    }

    public function test_role_enums_have_correct_labels(): void
    {
        $this->assertEquals('Owner', UserRole::OWNER->label());
        $this->assertEquals('Administrator', UserRole::ADMINISTRATOR->label());
        $this->assertEquals('Security Manager', UserRole::SECURITY_MANAGER->label());
        $this->assertEquals('Manager', UserRole::MANAGER->label());
        $this->assertEquals('Developer', UserRole::DEVELOPER->label());
        $this->assertEquals('Operator', UserRole::OPERATOR->label());
        $this->assertEquals('Viewer', UserRole::VIEWER->label());
    }
}
