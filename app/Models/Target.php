<?php

namespace App\Models;

use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Target extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'value',
        'environment',
        'description',
        'criticality',
        'status',
        'scope_notes',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(function ($target) {
            if (empty($target->uuid)) {
                $target->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => TargetType::class,
            'environment' => TargetEnvironment::class,
            'status' => TargetStatus::class,
            'criticality' => TargetCriticality::class,
        ];
    }

    /**
     * Get the tags associated with the target.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TargetTag::class, 'target_tag_target');
    }

    /**
     * Get the activity logs for the target.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(TargetActivityLog::class);
    }

    /**
     * Get the creator of the target.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the target.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
