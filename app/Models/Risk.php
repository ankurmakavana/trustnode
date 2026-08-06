<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Risk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'risk_id',
        'title',
        'description',
        'business_impact',
        'technical_impact',
        'likelihood',
        'impact',
        'risk_score',
        'risk_level',
        'status',
        'owner_id',
        'due_date',
        'review_date',
        'accepted',
        'accepted_by',
        'accepted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'due_date' => 'date',
        'review_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($risk) {
            $risk->uuid = (string) Str::uuid();
            if (empty($risk->risk_id)) {
                $maxId = static::max('id') ?? 0;
                $risk->risk_id = 'TN-RISK-'.str_pad($maxId + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function accepter()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function findings()
    {
        return $this->belongsToMany(Finding::class, 'finding_risk');
    }

    public function history()
    {
        return $this->hasMany(RiskHistory::class);
    }

    public function treatments()
    {
        return $this->hasMany(RiskTreatment::class);
    }
}
