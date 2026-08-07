<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'integration_id',
        'action',
        'description',
        'status',
        'created_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (IntegrationHistory $history) {
            if (empty($history->uuid)) {
                $history->uuid = (string) Str::uuid();
            }
        });
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
