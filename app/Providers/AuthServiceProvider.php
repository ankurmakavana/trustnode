<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Policies\AssetPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ScanPolicy;
use App\Policies\TargetPolicy;
use App\Policies\UserPolicy;
use App\Services\Authorization\PermissionService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * AuthServiceProvider
 *
 * Registers Gates and Policies for the application.
 *
 * Gate definitions are generated DYNAMICALLY from the database so that
 * adding a new permission requires no code change — only a database entry.
 *
 * Observers are registered here to ensure cache invalidation is wired up
 * before any request resolves permissions.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policy map: model class → policy class.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Target::class => TargetPolicy::class,
        Scan::class => ScanPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Register model observers for automatic cache invalidation
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);

        // Register asset, scan, report and target policies manually (no model yet or explicit overrides)
        Gate::policy('asset', AssetPolicy::class);
        Gate::policy('target', TargetPolicy::class);
        Gate::policy('scan', ScanPolicy::class);
        Gate::policy('report', ReportPolicy::class);

        // Generate Gates dynamically from the permission cache.
        // Gate::before() is used so that a single cached permission lookup
        // serves every Gate check in a request — zero repeated DB queries.
        Gate::before(function (User $user, string $ability): ?bool {
            // Return null to fall through to policy checks for abilities
            // that are not plain permission slugs (e.g. 'viewAny', 'create').
            if (! str_contains($ability, '.')) {
                return null;
            }

            /** @var PermissionService $permissionService */
            $permissionService = app(PermissionService::class);
            $permissions = $permissionService->getPermissionsForRole(
                $user->role?->slug ?? ''
            );

            return in_array($ability, $permissions, true) ? true : null;
        });
    }
}
