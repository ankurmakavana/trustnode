<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Models with explicit organization ownership that should be scoped by organization.
     * All others remain scoped by created_by/requested_by for backward compatibility.
     */
    private const ORGANIZATION_OWNED_MODELS = [
        \App\Models\Asset::class,
        \App\Models\Target::class,
        \App\Models\Repository::class,
        \App\Models\LocalProject::class,
        \App\Models\Scan::class,
        \App\Models\Integration::class,
        \App\Models\Setting::class,
    ];

    private const DERIVED_ORGANIZATION_RELATIONS = [
        \App\Models\Finding::class => 'scan',
        \App\Models\ScanReport::class => 'scan',
        \App\Models\IntegrationCredential::class => 'integration',
    ];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * For organization-owned models: scope by user's accessible organizations.
     * For other models: scope by created_by/requested_by (legacy user-level isolation).
     */
    public function apply(Builder $builder, Model $model)
    {
        $userId = Auth::id() ?? \App\Services\TenantContext::currentUserId();

        if (in_array(get_class($model), self::ORGANIZATION_OWNED_MODELS, true)) {
            if (!$userId) {
                $builder->whereIn($model->getTable() . '.organization_id', []);
                return;
            }

            $builder->whereIn(
                $model->getTable() . '.organization_id',
                $this->organizationIdsForUser($userId)
            );

            return;
        }

        if (isset(self::DERIVED_ORGANIZATION_RELATIONS[get_class($model)]) && $userId) {
            $organizationIds = $this->organizationIdsForUser($userId);
            $relation = self::DERIVED_ORGANIZATION_RELATIONS[get_class($model)];

            $builder->whereHas($relation, function (Builder $query) use ($organizationIds) {
                $query->whereIn('organization_id', $organizationIds);
            });
        }

        // Legacy user-scoped models (created_by/requested_by)
        $tenantId = null;

        if (Auth::check()) {
            $tenantId = Auth::id();
        } elseif (\App\Services\TenantContext::currentUserId()) {
            $tenantId = \App\Services\TenantContext::currentUserId();
        }

        if ($tenantId && !($model instanceof \App\Models\IntegrationCredential)) {
            if ($model instanceof \App\Models\ScanReport) {
                $builder->where($model->getTable() . '.requested_by', $tenantId);
            } else {
                $builder->where($model->getTable() . '.created_by', $tenantId);
            }
        }
    }

    private function organizationIdsForUser(int $userId)
    {
        return \App\Models\Team::query()
            ->whereHas('users', function (Builder $query) use ($userId) {
                $query->whereKey($userId);
            })
            ->distinct()
            ->pluck('organization_id');
    }
}
