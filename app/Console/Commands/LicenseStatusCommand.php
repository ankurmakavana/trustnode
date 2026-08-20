<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Facades\LicenseGate;

class LicenseStatusCommand extends Command
{
    protected $signature = 'license:status';
    protected $description = 'Check the current TrustNode license status';

    public function handle()
    {
        $status = LicenseGate::status();
        
        if ($status['status'] === 'active') {
            $this->info("{$status['message']}");
        } elseif ($status['status'] === 'community') {
            $this->line("{$status['message']}");
        } else {
            $this->error("{$status['message']}");
        }

        return 0;
    }
}
