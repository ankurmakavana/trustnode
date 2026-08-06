<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ComplianceAssessment extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'status',
        'score',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ComplianceAssessment $assessment) {
            if (empty($assessment->uuid)) {
                $assessment->uuid = (string) Str::uuid();
            }
        });
    }
}
