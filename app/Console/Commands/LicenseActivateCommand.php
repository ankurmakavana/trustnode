<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\LicenseGate;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {key : The license key to activate}';
    protected $description = 'Activate a TrustNode Professional license';

    public function handle()
    {
        $key = $this->argument('key');
        $this->info('Activating license...');

        $result = LicenseGate::activate($key);

        if ($result['success']) {
            $this->info($result['message']);
            return 0;
        }

        $this->error('Activation failed: ' . ($result['error']['message'] ?? 'Unknown error'));
        return 1;
    }
}
