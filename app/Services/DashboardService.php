<?php

namespace App\Services;

use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Scan\ScanStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\Scan;
use App\Models\ScanActivityLog;
use App\Models\Target;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get aggregate statistics and security posture intelligence.
     *
     * @param int|null $repositoryId
     * @param int|null $localProjectId
     * @param int|null $targetId
     * @return array
     */
    public function getStats(?int $repositoryId = null, ?int $localProjectId = null, ?int $targetId = null): array
    {
        // 1. Assets count
        $assetsQuery = Asset::query();
        $assetsCount = $assetsQuery->count();
        $assetsDelta = Asset::where('created_at', '>=', now()->startOfMonth())->count();

        // 2. Targets count
        $targetsQuery = Target::query();
        $targetsCount = $targetsQuery->count();
        $targetsDelta = Target::where('created_at', '>=', now()->startOfMonth())->count();

        // 3. Reports / Scans count
        $reportsQuery = Scan::query();
        $this->applyTargetFilterToScans($reportsQuery, $repositoryId, $localProjectId, $targetId);
        $reportsGenerated = $reportsQuery->count();

        // 4. Severity counts
        $findingsQuery = Finding::select('severity', \DB::raw('count(*) as count'))
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('scan_id');

        if ($repositoryId || $localProjectId || $targetId) {
            $findingsQuery->whereHas('scan', function ($q) use ($repositoryId, $localProjectId, $targetId) {
                $this->applyTargetFilterToScans($q, $repositoryId, $localProjectId, $targetId);
            });
        }

        $rawSeverityCounts = $findingsQuery
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $severityCounts = [];
        foreach ($rawSeverityCounts as $key => $count) {
            $normalizedKey = strtolower(is_object($key) ? $key->value : $key);
            $severityCounts[$normalizedKey] = ($severityCounts[$normalizedKey] ?? 0) + (int) $count;
        }

        $severityData = [
            ['severity' => 'Critical', 'count' => $severityCounts['critical'] ?? 0, 'fill' => '#e11d48'],
            ['severity' => 'High', 'count' => $severityCounts['high'] ?? 0, 'fill' => '#f97316'],
            ['severity' => 'Medium', 'count' => $severityCounts['medium'] ?? 0, 'fill' => '#fbbf24'],
            ['severity' => 'Low', 'count' => $severityCounts['low'] ?? 0, 'fill' => '#60a5fa'],
            ['severity' => 'Info', 'count' => $severityCounts['info'] ?? 0, 'fill' => '#cbd5e1'],
        ];

        // 5. Scan activity (last 7 days)
        $scanActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStr = $date->format('D');
            $dateStr = $date->toDateString();

            $completedQuery = Scan::whereDate('created_at', $dateStr)->where('status', ScanStatus::COMPLETED);
            $this->applyTargetFilterToScans($completedQuery, $repositoryId, $localProjectId, $targetId);
            $completed = $completedQuery->count();

            $failedQuery = Scan::whereDate('created_at', $dateStr)->whereIn('status', [ScanStatus::FAILED, 'error']);
            $this->applyTargetFilterToScans($failedQuery, $repositoryId, $localProjectId, $targetId);
            $failed = $failedQuery->count();

            $scanActivity[] = [
                'day' => $dayStr,
                'completed' => $completed,
                'failed' => $failed,
            ];
        }

        // 6. Recent Scans (last 5)
        $recentScansQuery = Scan::orderBy('created_at', 'desc')->take(5);
        $this->applyTargetFilterToScans($recentScansQuery, $repositoryId, $localProjectId, $targetId);
        $recentScansRaw = $recentScansQuery->get();

        $recentScans = $recentScansRaw->map(function ($scan) {
            $findingsCount = Finding::where('scan_id', $scan->id)->count();

            return [
                'id' => $scan->id,
                'name' => $scan->name,
                'target' => $scan->target,
                'status' => $scan->status instanceof ScanStatus ? $scan->status->value : (string) $scan->status,
                'progress' => $scan->progress,
                'findings' => $findingsCount,
                'duration' => $scan->completed_at ? Carbon::parse($scan->completed_at)->diffForHumans($scan->created_at, true) : 'Running',
                'severity' => $findingsCount > 0 ? 'High' : null,
            ];
        })->toArray();

        // 7. Recent Activity
        $recentActivityQuery = ScanActivityLog::with(['scan', 'user'])->orderBy('created_at', 'desc')->take(5);
        if ($repositoryId || $localProjectId || $targetId) {
            $recentActivityQuery->whereHas('scan', function ($q) use ($repositoryId, $localProjectId, $targetId) {
                $this->applyTargetFilterToScans($q, $repositoryId, $localProjectId, $targetId);
            });
        }
        $recentActivityRaw = $recentActivityQuery->get();

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

        // 8. Lifecycle Summary
        $lifecycleQuery = FindingIdentity::query();
        if ($repositoryId) {
            $identityIds = Finding::whereHas('scan', function ($q) use ($repositoryId) {
                $q->where('repository_id', $repositoryId);
            })->whereNotNull('finding_identity_id')->pluck('finding_identity_id')->unique();
            $lifecycleQuery->whereIn('id', $identityIds);
        } elseif ($localProjectId) {
            $lifecycleQuery->where('local_project_id', $localProjectId);
        } elseif ($targetId) {
            $lifecycleQuery->where('target_id', $targetId);
        }

        $lifecycleSummary = [
            'new' => (clone $lifecycleQuery)->where('lifecycle_status', FindingLifecycleStatus::NEW)->count(),
            'recurring' => (clone $lifecycleQuery)->where('lifecycle_status', FindingLifecycleStatus::RECURRING)->count(),
            'resolved' => (clone $lifecycleQuery)->where('lifecycle_status', FindingLifecycleStatus::RESOLVED)->count(),
            'regression' => (clone $lifecycleQuery)->where('lifecycle_status', FindingLifecycleStatus::REGRESSION)->count(),
        ];

        // 9. Posture Trend (Bounded last 10 completed scans)
        $trendScansQuery = Scan::where('status', ScanStatus::COMPLETED);
        $this->applyTargetFilterToScans($trendScansQuery, $repositoryId, $localProjectId, $targetId);
        $completedScans = $trendScansQuery->orderBy('id', 'desc')->take(10)->get()->reverse()->values();

        $postureTrend = [];
        $previousOpen = null;

        foreach ($completedScans as $scan) {
            $findings = Finding::where('scan_id', $scan->id)->get();

            $critical = 0;
            $high = 0;
            $medium = 0;
            $low = 0;
            $info = 0;

            foreach ($findings as $f) {
                $sev = strtolower(is_object($f->severity) ? $f->severity->value : (string) $f->severity);
                if ($sev === 'critical') {
                    $critical++;
                } elseif ($sev === 'high') {
                    $high++;
                } elseif ($sev === 'medium') {
                    $medium++;
                } elseif ($sev === 'low') {
                    $low++;
                } elseif ($sev === 'info') {
                    $info++;
                }
            }

            $totalOpen = count($findings);
            $netChange = ($previousOpen === null) ? 0 : ($totalOpen - $previousOpen);
            $previousOpen = $totalOpen;

            $postureTrend[] = [
                'scan_id' => $scan->id,
                'name' => $scan->name,
                'completed_at' => $scan->completed_at ? Carbon::parse($scan->completed_at)->toIso8601String() : ($scan->created_at ? Carbon::parse($scan->created_at)->toIso8601String() : null),
                'critical' => $critical,
                'high' => $high,
                'medium' => $medium,
                'low' => $low,
                'info' => $info,
                'total_open' => $totalOpen,
                'net_change' => $netChange,
            ];
        }

        // 10. Posture Assessment
        $postureAssessment = 'initial_baseline';
        if (count($postureTrend) > 1) {
            $latestPoint = end($postureTrend);
            if ($latestPoint['net_change'] < 0) {
                $postureAssessment = 'improving';
            } elseif ($latestPoint['net_change'] > 0) {
                $postureAssessment = 'worsening';
            } else {
                $postureAssessment = 'unchanged';
            }
        }

        return [
            'severityData' => $severityData,
            'scanActivity' => $scanActivity,
            'recentScans' => $recentScans,
            'recentActivity' => $recentActivity,
            'lifecycleSummary' => $lifecycleSummary,
            'postureTrend' => $postureTrend,
            'postureAssessment' => $postureAssessment,
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
        ];
    }

    /**
     * Helper to apply target constraints to a Scan query.
     */
    private function applyTargetFilterToScans($query, ?int $repositoryId, ?int $localProjectId, ?int $targetId): void
    {
        if ($repositoryId) {
            $query->where('repository_id', $repositoryId);
        } elseif ($localProjectId) {
            $query->where('local_project_id', $localProjectId);
        } elseif ($targetId) {
            $query->where('target_id', $targetId);
        }
    }
}
