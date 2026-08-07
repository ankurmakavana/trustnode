<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'type',
        'environment',
        'description',
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

        static::creating(function (Integration $integration) {
            if (empty($integration->uuid)) {
                $integration->uuid = (string) Str::uuid();
            }
        });
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
}
