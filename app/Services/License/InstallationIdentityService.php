<?php

namespace App\Services\License;

use Illuminate\Support\Str;
use App\Models\LicenseInstallation;

class InstallationIdentityService
{
    /**
     * Retrieve the existing installation record or generate a new one.
     */
    protected function getInstallation(): LicenseInstallation
    {
        $installation = LicenseInstallation::first();

        if (!$installation) {
            $installation = LicenseInstallation::create([
                'installation_id' => Str::uuid()->toString(),
                'installation_secret' => bin2hex(random_bytes(32)),
            ]);
        } elseif (empty($installation->installation_secret)) {
            $installation->update([
                'installation_secret' => bin2hex(random_bytes(32)),
            ]);
        }

        return $installation;
    }

    /**
     * Retrieve the existing installation UUID or generate a new one.
     */
    public function getInstallationId(): string
    {
        return $this->getInstallation()->installation_id;
    }

    /**
     * Provide a deterministic installation fingerprint.
     */
    public function getFingerprint(): string
    {
        $secret = $this->getInstallation()->installation_secret;
        
        return hash('sha256', $secret);
    }
}