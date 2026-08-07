<?php

namespace App\Models;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Scan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'engine',
        'target',
        'schedule',
        'status',
        'progress',
        'started_at',
        'completed_at',
        'duration',
        'created_by',
        'updated_by',
        'import_job_id',
    ];

    protected $casts = [
        'type' => ScanType::class,
        'engine' => ScanEngine::class,
        'status' => ScanStatus::class,
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Scan $scan) {
            if (empty($scan->uuid)) {
                $scan->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function targetRelation(): BelongsTo
    {
        return $this->belongsTo(Target::class, 'target', 'value');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ScanActivityLog::class);
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
