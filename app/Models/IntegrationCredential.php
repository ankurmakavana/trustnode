<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationCredential extends Model
{
    protected $fillable = [
        'uuid',
        'integration_id',
        'key',
        'value',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (IntegrationCredential $credential) {
            if (empty($credential->uuid)) {
                $credential->uuid = (string) Str::uuid();
            }
        });
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
