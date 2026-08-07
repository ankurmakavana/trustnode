<?php

namespace Database\Seeders;

use App\Models\Integration;
use App\Models\IntegrationHistory;
use App\Models\IntegrationJob;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scanners = [
            [
                'name' => 'Corporate Network Nmap',
                'code' => 'nmap',
                'type' => 'scanner',
                'status' => 'Connected',
                'host' => '192.168.1.1',
                'port' => 22,
                'username' => 'nmap_runner',
                'tls' => false,
                'health_status' => 'Healthy',
            ],
            [
                'name' => 'OpenVAS Web Application Scanner',
                'code' => 'greenbone',
                'type' => 'scanner',
                'status' => 'Disconnected',
                'host' => '10.0.2.15',
                'port' => 9390,
                'username' => 'admin',
                'tls' => true,
                'health_status' => 'Unreachable',
            ],
            [
                'name' => 'Enterprise Nessus Security Auditor',
                'code' => 'nessus',
                'type' => 'scanner',
                'status' => 'Connected',
                'host' => 'nessus.corp.trustnode',
                'port' => 8834,
                'username' => 'auditor',
                'tls' => true,
                'health_status' => 'Healthy',
            ],
            [
                'name' => 'Burp Suite Professional REST API',
                'code' => 'burp',
                'type' => 'scanner',
                'status' => 'Connected',
                'host' => '127.0.0.1',
                'port' => 8090,
                'username' => 'burp_api',
                'tls' => false,
                'health_status' => 'Healthy',
            ],
        ];

        foreach ($scanners as $scannerData) {
            DB::transaction(function () use ($scannerData) {
                $integration = Integration::create([
                    'name' => $scannerData['name'],
                    'code' => $scannerData['code'],
                    'type' => $scannerData['type'],
                    'status' => $scannerData['status'],
                    'host' => $scannerData['host'],
                    'port' => $scannerData['port'],
                    'username' => $scannerData['username'],
                    'tls' => $scannerData['tls'],
                    'health_status' => $scannerData['health_status'],
                    'last_check_at' => now()->subMinutes(rand(10, 120)),
                ]);

                // Create mock jobs history
                if ($integration->status === 'Connected') {
                    IntegrationJob::create([
                        'integration_id' => $integration->id,
                        'status' => 'Completed',
                        'duration' => rand(15, 60),
                        'imported_records' => rand(5, 30),
                        'started_at' => now()->subHours(2),
                        'finished_at' => now()->subHours(2)->addMinutes(1),
                    ]);

                    IntegrationJob::create([
                        'integration_id' => $integration->id,
                        'status' => 'Running',
                        'duration' => 0,
                        'imported_records' => 0,
                        'started_at' => now()->subMinutes(5),
                    ]);

                    IntegrationHistory::create([
                        'integration_id' => $integration->id,
                        'action' => 'Connected',
                        'description' => 'Auditor configured connector parameters.',
                        'status' => 'Success',
                        'created_at' => now()->subDays(2),
                    ]);

                    IntegrationHistory::create([
                        'integration_id' => $integration->id,
                        'action' => 'Validated',
                        'description' => 'Scheduled health validation succeeded.',
                        'status' => 'Success',
                        'created_at' => now()->subHours(1),
                    ]);
                }
            });
        }
    }
}
