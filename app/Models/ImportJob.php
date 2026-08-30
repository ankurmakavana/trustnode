<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ImportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'integration_id',
        'status',
        'progress',
        'source_type',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'progress' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ImportJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function file(): HasOne
    {
        return $this->hasOne(ImportFile::class);
    }

    public function history(): HasOne
    {
        return $this->hasOne(ImportHistory::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
    }
}
