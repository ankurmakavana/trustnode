<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FindingIdentity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'identity_hash',
        'fingerprint',
        'lifecycle_status',
        'asset_id',
        'target_id',
        'local_project_id',
        'created_by',
        'updated_by',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'resolved_by_scan_id',
    ];

    protected $casts = [
        'lifecycle_status' => \App\Enums\Finding\FindingLifecycleStatus::class,
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
        static::creating(function ($identity) {
            if (empty($identity->uuid)) {
                $identity->uuid = (string) Str::uuid();
            }
        });
    }

    public function findings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function lifecycleHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FindingLifecycleHistory::class);
    }

    public function resolvedByScan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Scan::class, 'resolved_by_scan_id');
    }

    public function asset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function target(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function localProject(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LocalProject::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
