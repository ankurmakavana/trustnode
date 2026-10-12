<?php

namespace App\Models;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Services\SecretRedactor;
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

    /**
     * Free-text fields that can carry credentials from a scanner, an agent, an import or a user.
     */
    public const REDACTED_TEXT_FIELDS = [
        'title',
        'description',
        'technical_details',
        'business_impact',
        'remediation',
        'evidence',
    ];

    /**
     * Redacts secret-shaped text in memory only. The model is not saved. Used for read paths such as
     * report generation, where stored rows may predate server-side redaction.
     */
    public function redactSecrets(): static
    {
        foreach (self::REDACTED_TEXT_FIELDS as $field) {
            $value = $this->getAttribute($field);

            if (is_string($value)) {
                $this->setAttribute($field, SecretRedactor::redact($value));
            }
        }

        return $this;
    }

    /**
     * Replaces evidence stored by the old partial-masking scanner (first and last four characters
     * kept) with a placeholder. Only Secret-category findings whose evidence matches that exact shape
     * are changed. Returns true when the evidence was changed. Does not save.
     */
    public function redactLegacyPartialMask(): bool
    {
        if (! $this->hasLegacyPartialMask()) {
            return false;
        }

        $this->evidence = '[REDACTED: legacy partial mask]';

        return true;
    }

    public function hasLegacyPartialMask(): bool
    {
        return strcasecmp((string) $this->category, 'Secret') === 0
            && is_string($this->evidence)
            && preg_match('/^(?:\*{8}|.{4}\*+.{4})$/u', $this->evidence) === 1;
    }

    protected $fillable = [
        'uuid',
        'finding_id',
        'agent_id',
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

        // Every write path (agent, API, scanners, imports) is covered here, so no caller can persist raw text.
        static::saving(function (Finding $finding) {
            $finding->redactSecrets();
        });

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
