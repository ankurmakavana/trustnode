<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LocalProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'path',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);
        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    public function scans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function findingIdentities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FindingIdentity::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
