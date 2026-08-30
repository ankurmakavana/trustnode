<?php

namespace App\Models;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Finding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'finding_id',
        'title',
        'cve',
        'cvss_score',
        'severity',
        'status',
        'lifecycle_status',
        'category',
        'cwe',
        'description',
        'technical_details',
        'business_impact',
        'remediation',
        'evidence',
        'asset_id',
        'target_id',
        'scan_id',
        'finding_identity_id',
        'assigned_analyst',
        'created_by',
        'updated_by',
        'import_job_id',
        'fingerprint',
        'url',
        'scanner',
    ];

    protected $casts = [
        'severity' => FindingSeverity::class,
        'status' => FindingStatus::class,
        'lifecycle_status' => \App\Enums\Finding\FindingLifecycleStatus::class,
        'cvss_score' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new \App\Models\Scopes\TenantScope);

        static::creating(function (Finding $finding) {
            if (empty($finding->uuid)) {
                $finding->uuid = (string) Str::uuid();
            }
            if (empty($finding->finding_id)) {
                $latestId = \Illuminate\Support\Facades\DB::table('findings')->max('id');
                $next = $latestId ? ($latestId + 1) : 1;
                
                // Ensure the finding_id is truly unique
                $findingId = 'TN-FIND-'.str_pad($next, 6, '0', STR_PAD_LEFT);
                while (\Illuminate\Support\Facades\DB::table('findings')->where('finding_id', $findingId)->exists()) {
                    $next++;
                    $findingId = 'TN-FIND-'.str_pad($next, 6, '0', STR_PAD_LEFT);
                }
                
                $finding->finding_id = $findingId;
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function findingIdentity(): BelongsTo
    {
        return $this->belongsTo(FindingIdentity::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_analyst');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(FindingActivityLog::class);
    }

    public function complianceControls(): BelongsToMany
    {
        return $this->belongsToMany(ComplianceControl::class, 'finding_compliance', 'finding_id', 'control_id')
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
