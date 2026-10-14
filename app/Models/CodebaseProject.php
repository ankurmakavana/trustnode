<?php

namespace App\Models;

use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Enums\CodebaseProject\ScanProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CodebaseProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'path',
        'source_type',
        'agent_id',
        'scan_profile',
        'continuous_scan_enabled',
        'organization_id',
        'created_by',
        'status',
    ];

    protected $casts = [
        'scan_profile' => ScanProfile::class,
        'status' => CodebaseProjectStatus::class,
        'continuous_scan_enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope());

        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the organization that owns this codebase project.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this codebase project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
