<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationJob extends Model
{
    protected $fillable = [
        'uuid',
        'integration_id',
        'status',
        'duration',
        'imported_records',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (IntegrationJob $job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
