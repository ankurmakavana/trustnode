<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentApproval extends Model
{
    protected $guarded = [];
    protected $casts = [
        'scope' => 'array',
        'constraints' => 'array',
        'expires_at' => 'datetime',
    ];
}
