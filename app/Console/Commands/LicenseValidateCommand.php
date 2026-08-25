<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\LicenseGate;
use App\Services\License\LicenseManager;
use App\Models\LicenseInstallation;

class LicenseValidateCommand extends Command
{
    protected $signature = "license:validate";
    protected $description = "Perform daily license validation for paid features";

    public function handle(LicenseManager $licenseManager)
    {
        if (!config("license.enabled", true)) {
            $this->info("License system is disabled.");
            return 0;
        }

        $installation = LicenseInstallation::first();
        if (!$installation || !$installation->installation_token) {
            $this->info("Running in Free Core mode. No license to validate.");
            return 0;
        }

        $this->info("Validating TrustNode license...");
        $result = $licenseManager->validate();

        if ($result["success"]) {
            $this->info("License validated successfully.");
            return 0;
        }

        if (isset($result["grace"]) && $result["grace"]) {
            $this->warn($result["error"]);
            return 0;
        }

        $this->error($result["error"] ?? "License validation failed.");
        return 1;
    }
}
