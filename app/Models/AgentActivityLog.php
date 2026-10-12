<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentActivityLog extends Model
{
    protected $fillable = [
        'agent_id',
        'action',
        'properties',
        'organization_id',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new \App\Models\Scopes\TenantScope);

        static::creating(function ($model) {
            if (!$model->organization_id) {
                // If a user context is available, use their organization (first organization).
                $userId = \Illuminate\Support\Facades\Auth::id() ?? \App\Services\TenantContext::currentUserId();
                if ($userId) {
                    $orgId = \App\Models\Team::query()
                        ->whereHas('users', fn($q) => $q->whereKey($userId))
                        ->value('organization_id');
                    $model->organization_id = $orgId;
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }
}
