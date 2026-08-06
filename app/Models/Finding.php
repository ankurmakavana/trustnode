<?php

namespace App\Models;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'assigned_analyst',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'severity' => FindingSeverity::class,
        'status' => FindingStatus::class,
        'cvss_score' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Finding $finding) {
            if (empty($finding->uuid)) {
                $finding->uuid = (string) Str::uuid();
            }
            if (empty($finding->finding_id)) {
                // Autogenerate unique Finding ID string
                $latest = self::orderBy('id', 'desc')->first();
                $nextNum = $latest ? ($latest->id + 1) : 1;
                $finding->finding_id = 'TN-FIND-'.str_pad($nextNum, 6, '0', STR_PAD_LEFT);
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
}
