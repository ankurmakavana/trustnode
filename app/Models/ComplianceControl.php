<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ComplianceControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'framework_id',
        'code',
        'title',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ComplianceControl $control) {
            if (empty($control->uuid)) {
                $control->uuid = (string) Str::uuid();
            }
        });
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(ComplianceFramework::class, 'framework_id');
    }

    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(Finding::class, 'finding_compliance', 'control_id', 'finding_id')
            ->withPivot('status', 'notes')
            ->withTimestamps();
    }
}
