<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get aggregate statistics for the dashboard, replacing mocks.
     */
    public function stats(): JsonResponse
    {
        // 1. Monitored Assets count
        $assetsCount = Asset::count();

        // 2. Count active scans (this stays mock for now as scans is future module)
        $runningScans = 8;

        // 3. Count critical findings (this stays mock for now as findings is future module)
        $criticalFindings = 34;

        // 4. Reports generated count (this stays mock for now as reports is future module)
        $reportsGenerated = 128;

        // Calculate count delta added this month
        $assetsDelta = Asset::where('created_at', '>=', now()->startOfMonth())->count();

        return response()->json([
            'stats' => [
                [
                    'id' => 'assets',
                    'label' => 'Monitored Assets',
                    'value' => (string) $assetsCount,
                    'delta' => '+'.$assetsDelta,
                    'deltaLabel' => 'added this month',
                    'trend' => 'up',
                    'color' => 'blue',
                    'icon' => 'Server',
                    'description' => 'Registered IPs, domains & network ranges',
                ],
                [
                    'id' => 'scans',
                    'label' => 'Active Scans',
                    'value' => (string) $runningScans,
                    'delta' => '3 queued',
                    'deltaLabel' => '',
                    'trend' => 'neutral',
                    'color' => 'violet',
                    'icon' => 'ScanLine',
                    'description' => 'Running + pending assessments',
                ],
                [
                    'id' => 'findings',
                    'label' => 'Critical Findings',
                    'value' => (string) $criticalFindings,
                    'delta' => '−7',
                    'deltaLabel' => 'vs last week',
                    'trend' => 'down',
                    'color' => 'red',
                    'icon' => 'ShieldAlert',
                    'description' => 'Unresolved critical-severity vulnerabilities',
                ],
                [
                    'id' => 'reports',
                    'label' => 'Reports Exported',
                    'value' => (string) $reportsGenerated,
                    'delta' => '+4',
                    'deltaLabel' => 'this week',
                    'trend' => 'up',
                    'color' => 'emerald',
                    'icon' => 'FileText',
                    'description' => 'Compiled assessment reports',
                ],
            ],
        ]);
    }
}
