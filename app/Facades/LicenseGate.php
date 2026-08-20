<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Services\License\LicenseManager;

/**
 * @method static bool allows(string $feature)
 * @method static bool isProfessional()
 * @method static array status()
 * @method static array activate(string $licenseKey)
 * @method static array validate()
 * @method static array deactivate()
 */
class LicenseGate extends Facade
{
    protected static function getFacadeAccessor()
    {
        return LicenseManager::class;
    }
}
