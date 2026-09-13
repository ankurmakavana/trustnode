<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Integration extends Model
{
    use HasFactory;

    protected $with = ['organization'];

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'type',
        'environment',
        'description',
        'organization_id',
        'status',
        'host',
        'port',
        'username',
        'tls',
        'health_status',
        'last_check_at',
        'options',
        'tags',
    ];

    protected $casts = [
        'tls' => 'boolean',
        'options' => 'array',
        'tags' => 'array',
        'last_check_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new \App\Models\Scopes\TenantScope);

        static::creating(function (Integration $integration) {
            if (empty($integration->uuid)) {
                $integration->uuid = (string) Str::uuid();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(IntegrationJob::class)->orderBy('created_at', 'desc');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IntegrationHistory::class)->orderBy('created_at', 'desc');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)
            ->orWhere('uuid', $value)
            ->firstOrFail();
    }
}
