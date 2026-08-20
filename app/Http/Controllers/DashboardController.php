<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\ScanActivityLog;
use App\Models\Target;
use Carbon\Carbon;
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

        // 3. Count critical findings will be done after gathering normalized stats

        // 4. Reports generated count
        $reportsGenerated = Scan::count(); // Use scans as proxy if Report model not actively used yet, or \App\Models\Report::count() if Report exists

        // Calculate count delta added this month
        $assetsDelta = Asset::where('created_at', '>=', now()->startOfMonth())->count();
        $targetsDelta = Target::where('created_at', '>=', now()->startOfMonth())->count();

        // Get actual severity counts
        $rawSeverityCounts = Finding::select('severity', \DB::raw('count(*) as count'))
            ->whereIn('status', ['open', 'in_progress']) // Count only real, unresolved findings
            ->whereNotNull('scan_id') // Ensure they belong to a real scan
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $severityCounts = [];
        foreach ($rawSeverityCounts as $key => $count) {
            $normalizedKey = strtolower(is_object($key) ? $key->value : $key);
            $severityCounts[$normalizedKey] = ($severityCounts[$normalizedKey] ?? 0) + $count;
        }

        $severityData = [
            ['severity' => 'Critical', 'count' => $severityCounts['critical'] ?? 0, 'fill' => '#e11d48'],
            ['severity' => 'High', 'count' => $severityCounts['high'] ?? 0, 'fill' => '#f97316'],
            ['severity' => 'Medium', 'count' => $severityCounts['medium'] ?? 0, 'fill' => '#fbbf24'],
            ['severity' => 'Low', 'count' => $severityCounts['low'] ?? 0, 'fill' => '#60a5fa'],
            ['severity' => 'Info', 'count' => $severityCounts['info'] ?? 0, 'fill' => '#cbd5e1'],
        ];

        // Get scan activity for the last 7 days
        $scanActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStr = $date->format('D'); // Mon, Tue...
            $dateStr = $date->toDateString();

            $completed = Scan::whereDate('created_at', $dateStr)->where('status', 'completed')->count();
            $failed = Scan::whereDate('created_at', $dateStr)->whereIn('status', ['failed', 'error'])->count();

            $scanActivity[] = [
                'day' => $dayStr,
                'completed' => $completed,
                'failed' => $failed,
            ];
        }

        // Get recent scans
        $recentScansRaw = Scan::orderBy('created_at', 'desc')->take(5)->get();
        $recentScans = $recentScansRaw->map(function ($scan) {
            $findingsCount = Finding::where('scan_id', $scan->id)->count();

            return [
                'id' => $scan->id,
                'name' => $scan->name,
                'target' => $scan->target,
                'status' => $scan->status,
                'progress' => $scan->progress,
                'findings' => $findingsCount,
                'duration' => $scan->completed_at ? Carbon::parse($scan->completed_at)->diffForHumans($scan->created_at, true) : 'Running',
                'severity' => $findingsCount > 0 ? 'High' : null,
            ];
        })->toArray();

        // Get recent activity (from ScanActivityLog)
        $recentActivityRaw = ScanActivityLog::with(['scan', 'user'])->orderBy('created_at', 'desc')->take(5)->get();
        $recentActivity = $recentActivityRaw->map(function ($log) {
            return [
                'id' => $log->id,
                'type' => 'scan',
                'actor' => $log->user->name ?? 'System',
                'action' => $log->action,
                'target' => $log->scan->name ?? 'Scan',
                'time' => $log->created_at->diffForHumans(),
            ];
        })->toArray();

        return response()->json([
            'severityData' => $severityData,
            'scanActivity' => $scanActivity,
            'recentScans' => $recentScans,
            'recentActivity' => $recentActivity,
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
                    'value' => (string) ($severityCounts['critical'] ?? 0),
                    'delta' => null,
                    'deltaLabel' => null,
                    'trend' => 'neutral',
                    'color' => 'red',
                    'icon' => 'ShieldAlert',
                    'description' => 'Unresolved critical-severity vulnerabilities',
                ],
                [
                    'id' => 'reports',
                    'label' => 'Reports Exported',
                    'value' => (string) $reportsGenerated,
                    'delta' => null,
                    'deltaLabel' => null,
                    'trend' => 'neutral',
                    'color' => 'emerald',
                    'icon' => 'FileText',
                    'description' => 'Compiled assessment reports',
                ],
            ],
        ]);
    }
}
