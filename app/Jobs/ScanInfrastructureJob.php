<?php

namespace App\Jobs;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Services\Import\FingerprintService;
use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\Services\Scan\Infrastructure\TargetValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\ScanCompletedNotification;
use App\Notifications\ScanFailedNotification;

class ScanInfrastructureJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Scan $scan;

    public function __construct(Scan $scan)
    {
        $this->scan = $scan;
    }

    public function handle(
        TargetValidator $targetValidator,
        NativeInfrastructureScanner $scanner,
        FingerprintService $fingerprintService
    ) {
        Log::info('ScanInfrastructureJob starting for scan '.$this->scan->id);

        $this->scan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // 1. Validate Target
            $validatedTarget = $targetValidator->validate($this->scan->target);

            // 2. Perform infrastructure scan
            $findings = $scanner->scan($validatedTarget);

            // 3. Asset creation is deliberately bypassed to decouple scanning from manual inventory.
            // Findings will not be linked to an Asset, but to the Scan and Target string.

            // 4. Save and deduplicate findings
            $savedFindings = [];
            $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];

            foreach ($findings as $dto) {
                // FingerprintService expects a NormalizedFinding object.
                $fingerprint = $fingerprintService->generate($dto, null); // No asset
                $dto->fingerprint = $fingerprint;

                $severityVal = strtolower($dto->severity ?? 'low');
                if (isset($severityCounts[$severityVal])) {
                    $severityCounts[$severityVal]++;
                }

                // Map severity string to enum
                $severity = FindingSeverity::LOW;
                foreach (FindingSeverity::cases() as $case) {
                    if (strtolower($case->value) === $severityVal) {
                        $severity = $case;
                        break;
                    }
                }

                $identityHash = hash('sha256', ($this->scan->created_by ?? '') . '|' . $fingerprint . '||' . $validatedTarget->original . '|');

                $findingIdentity = \App\Models\FindingIdentity::firstOrCreate(
                    [
                        'identity_hash' => $identityHash,
                    ],
                    [
                        'fingerprint' => $fingerprint,
                        // target_id is null since we bypass inventory creation, but hash ensures identity
                        'created_by' => $this->scan->created_by,
                        'first_seen_at' => $this->scan->started_at ?? now(),
                        'last_seen_at' => $this->scan->started_at ?? now(),
                    ]
                );

                $findingModel = Finding::updateOrCreate(
                    [
                        'fingerprint' => $fingerprint,
                        'scan_id' => $this->scan->id,
                    ],
                    [
                        'title' => $dto->title,
                        'asset_id' => null,
                        'finding_identity_id' => $findingIdentity->id,
                        'severity' => $severity,
                        'status' => FindingStatus::OPEN,
                        'category' => $dto->category ?? 'Infrastructure',
                        'cwe' => $dto->cwe ?? null,
                        'description' => $dto->description ?? '',
                        'remediation' => $dto->remediation ?? '',
                        'technical_details' => $dto->technicalDetails ?? '',
                        'evidence' => $dto->evidence ?? '',
                        'url' => $dto->url ?? '',
                        'scanner' => $dto->scanner ?? 'NativeInfrastructureScanner',
                        'created_by' => $this->scan->created_by,
                    ]
                );

                $savedFindings[] = $findingModel;
            }

            // 5. Generate generic vulnerability HTML report for infrastructure
            $reportTitle = 'Infrastructure Vulnerability Assessment - '.$validatedTarget->original;
            // Fallback to repository_scan view if infra_scan view is missing, but ideally we should have one.
            // For now, if we don't have infra view, we will just pass basic params. We will create it if needed.
            $viewName = view()->exists('reports.infrastructure_scan') ? 'reports.infrastructure_scan' : 'reports.repository_scan';
            $htmlContent = view($viewName, [
                'target' => $validatedTarget->original,
                'scan' => $this->scan,
                'findings' => $savedFindings,
                'severityCounts' => $severityCounts,
            ])->render();

            $report = Report::create([
                'uuid' => (string) Str::uuid(),
                'title' => $reportTitle,
                'type' => 'infrastructure',
                'status' => 'Ready',
                'options' => [
                    'scan_id' => $this->scan->id,
                    'severity_counts' => $severityCounts,
                    'html_report' => $htmlContent,
                ],
                'created_by' => $this->scan->created_by,
            ]);

            // 6. Complete scan
            $duration = now()->diffInSeconds($this->scan->started_at);
            $this->scan->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now(),
                'duration' => $duration,
            ]);

            app(\App\Services\Finding\FindingLifecycleService::class)->process($this->scan);

            // 7. Dispatch Notification
            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_COMPLETED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan),
                new ScanCompletedNotification($this->scan, array_sum($severityCounts))
            );

        } catch (\Throwable $e) {
            Log::error('ScanInfrastructureJob failed: '.$e->getMessage(), ['exception' => $e]);
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_FAILED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan),
                new ScanFailedNotification($this->scan, 'Scan failed during execution.')
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ScanInfrastructureJob failed fatally: '.$exception->getMessage(), ['exception' => $exception]);
        
        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        \App\Services\Notification\NotificationRouter::dispatch(
            \App\Enums\Notification\NotificationType::SCAN_FAILED,
            new \App\Services\Notification\NotificationContext(scan: $this->scan),
            new ScanFailedNotification($this->scan, 'Scan failed fatally.')
        );
    }

    public function getTenantId(): ?int
    {
        return $this->scan->created_by;
    }
}
