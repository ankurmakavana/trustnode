<?php

namespace App\Services\Finding;

use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Scan\ScanStatus;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\FindingLifecycleHistory;
use App\Models\Scan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FindingLifecycleService
{
    /**
     * Process lifecycle transitions for a completed scan within its deterministic target boundary.
     */
    public function process(Scan $scan): void
    {
        // Critical Rule: Only completed scans participate in lifecycle processing
        $statusValue = $scan->status instanceof ScanStatus ? $scan->status->value : (string) $scan->status;
        if ($statusValue !== ScanStatus::COMPLETED->value) {
            Log::info("Scan {$scan->id} status is {$statusValue}; skipping lifecycle processing.");
            return;
        }

        DB::transaction(function () use ($scan) {
            $currentScanTimestamp = $scan->started_at ?? now();

            // 1. Locate previous comparable successful scan for this target boundary and tenant
            $previousScan = $this->getPreviousComparableScan($scan);

            // 2. Fetch all Finding occurrences created in the current scan
            $currentFindings = Finding::where('scan_id', $scan->id)->get();
            $currentIdentityIds = $currentFindings->pluck('finding_identity_id')->filter()->unique()->values()->all();

            // 3. Fetch previous scan identity IDs if a previous comparable scan exists
            $previousIdentityIds = [];
            if ($previousScan) {
                $previousFindings = Finding::where('scan_id', $previousScan->id)->get();
                $previousIdentityIds = $previousFindings->pluck('finding_identity_id')->filter()->unique()->values()->all();
            }

            // 4. Classify each finding occurrence in the current scan
            foreach ($currentFindings as $finding) {
                $identityId = $finding->finding_identity_id;
                if (!$identityId) {
                    continue;
                }

                $identity = FindingIdentity::find($identityId);
                if (!$identity) {
                    continue;
                }

                $state = FindingLifecycleStatus::NEW;

                if ($previousScan && in_array($identityId, $previousIdentityIds, true)) {
                    // Existed in the immediately preceding comparable scan -> RECURRING
                    $state = FindingLifecycleStatus::RECURRING;
                } else {
                    // Not in the immediately preceding scan: check if it was previously seen/resolved
                    $wasPreviouslyKnown = $identity->first_seen_at !== null && $identity->first_seen_at->lt($currentScanTimestamp);
                    $wasPreviouslyResolved = $identity->resolved_at !== null || $identity->lifecycle_status === FindingLifecycleStatus::RESOLVED;

                    if ($wasPreviouslyKnown || $wasPreviouslyResolved) {
                        // Reappeared after prior resolution or absence -> REGRESSION
                        $state = FindingLifecycleStatus::REGRESSION;
                    } else {
                        // First known appearance -> NEW
                        $state = FindingLifecycleStatus::NEW;
                    }
                }

                // Update Finding occurrence
                $finding->update(['lifecycle_status' => $state]);

                // Update FindingIdentity persistent model
                $identityUpdates = [
                    'lifecycle_status' => $state,
                    'last_seen_at' => $currentScanTimestamp,
                    'resolved_at' => null,
                    'resolved_by_scan_id' => null,
                ];
                if (!$identity->first_seen_at) {
                    $identityUpdates['first_seen_at'] = $currentScanTimestamp;
                }
                $identity->update($identityUpdates);

                // Record historical lifecycle state for this scan (Idempotent)
                FindingLifecycleHistory::updateOrCreate(
                    [
                        'finding_identity_id' => $identity->id,
                        'scan_id' => $scan->id,
                    ],
                    [
                        'state' => $state,
                        'created_by' => $scan->created_by,
                    ]
                );
            }

            // 5. Detect and resolve findings that disappeared since the previous comparable scan
            if ($previousScan) {
                $resolvedIdentityIds = array_diff($previousIdentityIds, $currentIdentityIds);

                foreach ($resolvedIdentityIds as $resolvedId) {
                    $resolvedIdentity = FindingIdentity::find($resolvedId);
                    if (!$resolvedIdentity) {
                        continue;
                    }

                    // Update FindingIdentity to RESOLVED (last_seen_at is preserved!)
                    $resolvedIdentity->update([
                        'lifecycle_status' => FindingLifecycleStatus::RESOLVED,
                        'resolved_at' => $scan->completed_at ?? now(),
                        'resolved_by_scan_id' => $scan->id,
                    ]);

                    // Record RESOLVED transition in lifecycle history for this scan (Idempotent)
                    FindingLifecycleHistory::updateOrCreate(
                        [
                            'finding_identity_id' => $resolvedIdentity->id,
                            'scan_id' => $scan->id,
                        ],
                        [
                            'state' => FindingLifecycleStatus::RESOLVED,
                            'created_by' => $scan->created_by,
                        ]
                    );
                }
            }
        });
    }

    /**
     * Locate the immediate previous comparable successfully completed scan within the exact target boundary.
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
