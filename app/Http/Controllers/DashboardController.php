<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Target;
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

        // 2. Monitored Targets count (Replaces active scans or adds target statistics)
        $targetsCount = Target::count();

        // 3. Count critical findings (this stays mock for now as findings is future module)
        $criticalFindings = 34;

        // 4. Reports generated count (this stays mock for now as reports is future module)
        $reportsGenerated = 128;

        // Calculate count delta added this month
        $assetsDelta = Asset::where('created_at', '>=', now()->startOfMonth())->count();
        $targetsDelta = Target::where('created_at', '>=', now()->startOfMonth())->count();

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
                    'id' => 'targets',
                    'label' => 'Target Scopes',
                    'value' => (string) $targetsCount,
                    'delta' => '+'.$targetsDelta,
                    'deltaLabel' => 'added this month',
                    'trend' => 'up',
                    'color' => 'violet',
                    'icon' => 'Crosshair',
                    'description' => 'Configured scanning target environments',
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
