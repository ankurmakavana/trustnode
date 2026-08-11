<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'provider',
        'repository_url',
        'repository_id',
        'name',
        'visibility',
        'default_branch',
        'integration_credential_id',
        'status',
        'last_scan_at',
        'created_by',
    ];

    protected $casts = [
        'last_scan_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Repository $repository) {
            if (empty($repository->uuid)) {
                $repository->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class, 'integration_credential_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }
}
