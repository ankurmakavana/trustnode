<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportHistory extends Model
{
    protected $fillable = [
        'uuid',
        'report_id',
        'action',
        'description',
        'user_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ReportHistory $history) {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
