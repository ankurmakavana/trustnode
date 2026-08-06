<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ComplianceFramework extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ComplianceFramework $framework) {
            if (empty($framework->uuid)) {
                $framework->uuid = (string) Str::uuid();
            }
        });
    }

    public function controls(): HasMany
    {
        return $this->hasMany(ComplianceControl::class, 'framework_id');
    }
}
