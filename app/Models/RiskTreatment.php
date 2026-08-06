<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskTreatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_id',
        'treatment_type', // Mitigate, Transfer, Avoid, Accept
        'description',
        'target_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function risk()
    {
        return $this->belongsTo(Risk::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
