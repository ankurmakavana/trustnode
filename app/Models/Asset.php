<?php

namespace App\Models;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'value',
        'description',
        'criticality',
        'status',
        'risk_score',
        'owner',
        'notes',
        'asset_group_id',
        'created_by',
        'updated_by',
        'import_job_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
        static::creating(function ($asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'criticality' => AssetCriticality::class,
            'risk_score' => 'decimal:2',
        ];
    }

    /**
     * Get the group this asset belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }

    /**
     * Get the tags associated with the asset.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AssetTag::class, 'asset_tag_asset');
    }

    /**
     * Get the activity logs for the asset.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(AssetActivityLog::class);
    }

    /**
     * Get the creator of the asset.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the asset.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
