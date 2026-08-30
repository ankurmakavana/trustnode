<?php

namespace App\Models;

use App\Enums\Finding\FindingLifecycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingLifecycleHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'finding_identity_id',
        'scan_id',
        'state',
        'created_by',
    ];

    protected $casts = [
        'state' => FindingLifecycleStatus::class,
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
    }

    public function findingIdentity(): BelongsTo
    {
        return $this->belongsTo(FindingIdentity::class);
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
