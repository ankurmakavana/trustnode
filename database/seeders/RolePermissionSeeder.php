<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define Permissions
        $permissions = [
            'users.create' => 'Create new system users',
            'users.view' => 'View system users list',
            'users.update' => 'Modify user profiles or statuses',
            'users.delete' => 'Soft-delete and redact user profiles',

            'assets.create' => 'Add targets, IPs, and domains',
            'assets.view' => 'View registered asset directories',
            'assets.update' => 'Modify assets properties',
            'assets.delete' => 'Delete target scopes',

            'scans.execute' => 'Trigger scans and configure profiles',
            'scans.view' => 'View active queues and histories',

            'reports.export' => 'Compile and export PDF summaries',
            'reports.view' => 'View report histories',

            'findings.view' => 'Access vulnerability lists',
            'findings.update' => 'Triage findings and track remediation status',
        ];

        $permissionModels = [];
        foreach ($permissions as $name => $description) {
            $permissionModels[$name] = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => $description]
            );
        }

        // 2. Define Roles
        $roles = [
            UserRole::ADMINISTRATOR->value => [
                'name' => UserRole::ADMINISTRATOR->label(),
                'permissions' => array_keys($permissions), // Gets all permissions
            ],
            UserRole::MANAGER->value => [
                'name' => UserRole::MANAGER->label(),
                'permissions' => [
                    'assets.create', 'assets.view', 'assets.update', 'assets.delete',
                    'scans.execute', 'scans.view',
                    'reports.export', 'reports.view',
                    'findings.view', 'findings.update',
                    'users.view',
                ],
            ],
            UserRole::OPERATOR->value => [
                'name' => UserRole::OPERATOR->label(),
                'permissions' => [
                    'assets.view',
                    'scans.view',
                    'reports.view',
                    'findings.view', 'findings.update',
                ],
            ],
        ];

        foreach ($roles as $slug => $data) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $data['name']]
            );

            // Sync permissions by resolving models using slugs, preventing numeric ID dependency
            $rolePermissions = [];
            foreach ($data['permissions'] as $permissionName) {
                if (isset($permissionModels[$permissionName])) {
                    $rolePermissions[] = $permissionModels[$permissionName]->id;
                }
            }
            $role->permissions()->sync($rolePermissions);
        }
    }
}
