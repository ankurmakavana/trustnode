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
        if (Auth::check()) {
            if ($model instanceof \App\Models\ScanReport) {
                $builder->where($model->getTable() . '.requested_by', Auth::id());
            } else {
                $builder->where($model->getTable() . '.created_by', Auth::id());
            }
        }
    }
}
