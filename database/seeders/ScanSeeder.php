<?php

namespace Database\Seeders;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        if (! $admin) {
            return;
        }

        $scans = [
            [
                'name' => 'Weekly Edge Firewall Port Audit',
                'type' => ScanType::PORT_DISCOVERY,
                'engine' => ScanEngine::NMAP,
                'target' => '10.10.1.254',
                'schedule' => '0 0 * * 0', // Every Sunday at midnight
                'status' => ScanStatus::COMPLETED,
                'progress' => 100,
                'started_at' => now()->subDays(2)->setHour(0)->setMinute(0)->setSecond(0),
                'completed_at' => now()->subDays(2)->setHour(0)->setMinute(45)->setSecond(0),
                'duration' => 2700,
                'description' => 'Periodic scan checking for newly opened external ports on primary gateway.',
            ],
            [
                'name' => 'Internal SSO Vulnerability Scan',
                'type' => ScanType::WEB_APPLICATION,
                'engine' => ScanEngine::OWASP_ZAP,
                'target' => 'https://auth.internal/sso/login',
                'schedule' => null, // Manual
                'status' => ScanStatus::RUNNING,
                'progress' => 65,
                'started_at' => now()->subMinutes(30),
                'completed_at' => null,
                'duration' => null,
                'description' => 'Dynamic application security assessment run against SSO auth logic.',
            ],
            [
                'name' => 'Corporate Main Server Attack Surface Audit',
                'type' => ScanType::API_VULNERABILITY,
                'engine' => ScanEngine::NUCLEI,
                'target' => 'corp.internal',
                'schedule' => '0 12 * * *', // Daily at 12:00
                'status' => ScanStatus::QUEUED,
                'progress' => 0,
                'started_at' => null,
                'completed_at' => null,
                'duration' => null,
                'description' => 'Targeted template scans checking for configuration disclosures.',
            ],
        ];

        foreach ($scans as $data) {
            Scan::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'engine' => $data['engine'],
                'target' => $data['target'],
                'schedule' => $data['schedule'],
                'status' => $data['status'],
                'progress' => $data['progress'],
                'started_at' => $data['started_at'],
                'completed_at' => $data['completed_at'],
                'duration' => $data['duration'],
                'description' => $data['description'],
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }
    }
}
