<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_id',
        'action',
        'description',
        'user_id',
    ];

    public function risk()
    {
        return $this->belongsTo(Risk::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
