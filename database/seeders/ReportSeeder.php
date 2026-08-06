<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\ReportHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@trustnode.io')->first();
        if (! $admin) {
            $admin = User::first();
        }

        if (! $admin) {
            return;
        }

        $sampleReports = [
            [
                'title' => 'Q3 Enterprise Security Posture Assessment',
                'type' => 'Executive Summary',
                'status' => 'Generated',
                'options' => [],
            ],
            [
                'title' => 'Perimeter Network Penetration Testing Report',
                'type' => 'Technical Assessment',
                'status' => 'Generated',
                'options' => [],
            ],
            [
                'title' => 'Corporate Business Threat & Risk Ledger Q3',
                'type' => 'Risk Report',
                'status' => 'Generated',
                'options' => [],
            ],
            [
                'title' => 'NIST CSF & PCI-DSS V4.0 Compliance Gap Audit',
                'type' => 'Compliance Report',
                'status' => 'Generated',
                'options' => [],
            ],
            [
                'title' => 'Critical Cloud Asset Vulnerability Mapping',
                'type' => 'Asset Coverage',
                'status' => 'Archived',
                'options' => [],
            ],
            [
                'title' => 'Port Exposure Scan Success & Coverage Metrics',
                'type' => 'Scan Coverage',
                'status' => 'Generated',
                'options' => [],
            ],
        ];

        foreach ($sampleReports as $repData) {
            DB::transaction(function () use ($repData, $admin) {
                $report = Report::create([
                    'title' => $repData['title'],
                    'type' => $repData['type'],
                    'status' => $repData['status'],
                    'options' => $repData['options'],
                    'created_by' => $admin->id,
                ]);

                // Create report timeline logs
                ReportHistory::create([
                    'report_id' => $report->id,
                    'action' => 'Generated',
                    'description' => "Initial report compilation of type {$report->type}.",
                    'user_id' => $admin->id,
                    'created_at' => now()->subDays(3),
                ]);

                ReportHistory::create([
                    'report_id' => $report->id,
                    'action' => 'Viewed',
                    'description' => 'Report viewed by administration console.',
                    'user_id' => $admin->id,
                    'created_at' => now()->subDays(2),
                ]);

                if ($report->status === 'Archived') {
                    ReportHistory::create([
                        'report_id' => $report->id,
                        'action' => 'Archived',
                        'description' => 'Report archived due to assessment cycle rollover.',
                        'user_id' => $admin->id,
                        'created_at' => now()->subDay(),
                    ]);
                }
            });
        }
    }
}
