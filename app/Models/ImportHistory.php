<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'import_job_id',
        'scanner',
        'imported_assets_count',
        'imported_findings_count',
        'duplicates_count',
        'errors_count',
        'duration',
        'status',
    ];

    protected $casts = [
        'imported_assets_count' => 'integer',
        'imported_findings_count' => 'integer',
        'duplicates_count' => 'integer',
        'errors_count' => 'integer',
        'duration' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ImportHistory $history) {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
        });
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
