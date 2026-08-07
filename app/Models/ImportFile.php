<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'import_job_id',
        'filename',
        'filepath',
        'filesize',
        'mime_type',
    ];

    protected $casts = [
        'filesize' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ImportFile $file) {
            if (empty($file->uuid)) {
                $file->uuid = (string) Str::uuid();
            }
        });
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
