<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseInstallation extends Model
{
    protected $fillable = [
        'installation_id',
        'installation_secret',
        'installation_token',
        'license_status',
        'entitlements',
        'validated_at',
        'last_successful_validation_at',
        'grace_expires_at',
    ];

    protected $casts = [
        'entitlements' => 'array',
        'validated_at' => 'datetime',
        'last_successful_validation_at' => 'datetime',
        'grace_expires_at' => 'datetime',
        'installation_token' => 'encrypted',
        'installation_secret' => 'encrypted',
    ];
}
