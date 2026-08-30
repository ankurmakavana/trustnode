<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model)
    {
        $tenantId = null;

        if (Auth::check()) {
            $tenantId = Auth::id();
        } elseif (\App\Services\TenantContext::currentUserId()) {
            $tenantId = \App\Services\TenantContext::currentUserId();
        }

        if ($tenantId) {
            if ($model instanceof \App\Models\ScanReport) {
                $builder->where($model->getTable() . '.requested_by', $tenantId);
            } else {
                $builder->where($model->getTable() . '.created_by', $tenantId);
            }
        }
    }
}
