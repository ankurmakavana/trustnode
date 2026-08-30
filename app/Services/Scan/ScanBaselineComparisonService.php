<?php

declare(strict_types=1);

namespace App\Services\Scan;

use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Finding\FindingSeverity;
use App\Enums\Scan\ScanStatus;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\FindingLifecycleHistory;
use App\Models\Scan;

class ScanBaselineComparisonService
{
    /**
     * Compute deterministic comparison, posture delta, and regression intelligence for a scan.
     */
    public function compare(Scan $scan): array
    {
        $statusValue = $scan->status instanceof ScanStatus ? $scan->status->value : (string) $scan->status;

        // Baseline (first completed scan for target) & Previous comparable scan
        $initialBaselineScan = $this->getInitialBaselineScan($scan);
        $previousScan = $this->getPreviousComparableScan($scan);

        // Current scan findings
        $currentFindings = $scan->findings()->with('findingIdentity')->get();
        $currentOpenCount = $currentFindings->count();

        // Count current occurrences by lifecycle_status
        $newCount = $currentFindings->where('lifecycle_status', FindingLifecycleStatus::NEW)->count();
        $recurringCount = $currentFindings->where('lifecycle_status', FindingLifecycleStatus::RECURRING)->count();
        $regressionCount = $currentFindings->where('lifecycle_status', FindingLifecycleStatus::REGRESSION)->count();

        // Regression details
        $regressionFindings = $currentFindings->where('lifecycle_status', FindingLifecycleStatus::REGRESSION)->values();
        $regressionDetails = $regressionFindings->map(function (Finding $f) {
            return [
                'finding_id' => $f->id,
                'finding_identity_id' => $f->finding_identity_id,
                'title' => $f->title,
                'severity' => $f->severity instanceof FindingSeverity ? $f->severity->value : (string) $f->severity,
                'category' => $f->category,
                'first_seen_at' => $f->findingIdentity?->first_seen_at?->toIso8601String(),
                'last_seen_at' => $f->findingIdentity?->last_seen_at?->toIso8601String(),
            ];
        })->all();

        // Calculate resolved count
        $resolvedCount = 0;
        if ($previousScan) {
            $resolvedCount = FindingLifecycleHistory::where('scan_id', $scan->id)
                ->where('state', FindingLifecycleStatus::RESOLVED)
                ->count();
        }

        // Previous scan counts & severity distribution
        $previousOpenCount = 0;
        $previousSeverityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        if ($previousScan) {
            $previousFindings = $previousScan->findings()->get();
            $previousOpenCount = $previousFindings->count();

            foreach ($previousFindings as $pf) {
                $sev = strtolower($pf->severity instanceof FindingSeverity ? $pf->severity->value : (string) $pf->severity);
                if (isset($previousSeverityCounts[$sev])) {
                    $previousSeverityCounts[$sev]++;
                } else {
                    $previousSeverityCounts['info']++;
                }
            }
        }

        // Current severity counts
        $currentSeverityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'info' => 0,
        ];

        foreach ($currentFindings as $cf) {
            $sev = strtolower($cf->severity instanceof FindingSeverity ? $cf->severity->value : (string) $cf->severity);
            if (isset($currentSeverityCounts[$sev])) {
                $currentSeverityCounts[$sev]++;
            } else {
                $currentSeverityCounts['info']++;
            }
        }

        // Severity delta
        $severityDelta = [];
        foreach (['critical', 'high', 'medium', 'low', 'info'] as $level) {
            $prev = $previousSeverityCounts[$level];
            $curr = $currentSeverityCounts[$level];
            $severityDelta[$level] = [
                'previous' => $prev,
                'current' => $curr,
                'delta' => $curr - $prev,
            ];
        }

        // Net change & Posture Assessment
        $netChange = $currentOpenCount - $previousOpenCount;

        $postureAssessment = 'initial_baseline';
        if ($previousScan) {
            if ($netChange < 0) {
                $postureAssessment = 'improving';
            } elseif ($netChange > 0) {
                $postureAssessment = 'worsening';
            } else {
                $postureAssessment = 'unchanged';
            }
        }

        return [
            'current_scan' => [
                'id' => $scan->id,
                'uuid' => $scan->uuid,
                'status' => $statusValue,
                'target' => $scan->target,
                'completed_at' => $scan->completed_at?->toIso8601String(),
            ],
            'previous_scan' => $previousScan ? [
                'id' => $previousScan->id,
                'uuid' => $previousScan->uuid,
                'status' => $previousScan->status instanceof ScanStatus ? $previousScan->status->value : (string) $previousScan->status,
                'completed_at' => $previousScan->completed_at?->toIso8601String(),
            ] : null,
            'initial_baseline' => $initialBaselineScan ? [
                'id' => $initialBaselineScan->id,
                'uuid' => $initialBaselineScan->uuid,
                'completed_at' => $initialBaselineScan->completed_at?->toIso8601String(),
            ] : null,
            'is_initial_baseline' => $initialBaselineScan ? ($initialBaselineScan->id === $scan->id) : false,
            'lifecycle_counts' => [
                'new' => $newCount,
                'recurring' => $recurringCount,
                'resolved' => $resolvedCount,
                'regression' => $regressionCount,
            ],
            'posture' => [
                'previous_open_count' => $previousOpenCount,
                'current_open_count' => $currentOpenCount,
                'net_change' => $netChange,
                'assessment' => $postureAssessment,
            ],
            'severity_delta' => $severityDelta,
            'regressions' => $regressionDetails,
        ];
    }

    /**
     * Get initial baseline scan (earliest completed scan for target).
     */
    public function getInitialBaselineScan(Scan $scan): ?Scan
    {
        return Scan::where('created_by', $scan->created_by)
            ->where('status', ScanStatus::COMPLETED)
            ->where(function ($query) use ($scan) {
                if ($scan->repository_id) {
                    $query->where('repository_id', $scan->repository_id);
                } elseif ($scan->local_project_id) {
                    $query->where('local_project_id', $scan->local_project_id);
                } else {
                    $query->where('target', $scan->target)
                          ->whereNull('repository_id')
                          ->whereNull('local_project_id');
                }
            })
            ->orderBy('id', 'asc')
            ->first();
    }

    /**
     * Get previous comparable scan (immediately preceding completed scan for target).
     */
    public function getPreviousComparableScan(Scan $scan): ?Scan
    {
        return Scan::where('created_by', $scan->created_by)
            ->where('id', '<', $scan->id)
            ->where('status', ScanStatus::COMPLETED)
            ->where(function ($query) use ($scan) {
                if ($scan->repository_id) {
                    $query->where('repository_id', $scan->repository_id);
                } elseif ($scan->local_project_id) {
                    $query->where('local_project_id', $scan->local_project_id);
                } else {
                    $query->where('target', $scan->target)
                          ->whereNull('repository_id')
                          ->whereNull('local_project_id');
                }
            })
            ->orderBy('id', 'desc')
            ->first();
    }
}
