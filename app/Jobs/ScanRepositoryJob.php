<?php

namespace App\Jobs;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Mail\SecurityAlertMail;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Repository;
use App\Models\Scan;
use App\Services\Import\FingerprintService;
use App\Services\Repository\RepositoryService;
use App\Services\Scan\RepositoryScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Notifications\ScanCompletedNotification;
use App\Notifications\ScanFailedNotification;

class ScanRepositoryJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Scan $scan;

    protected Repository $repository;

    public function __construct(Scan $scan, Repository $repository)
    {
        $this->scan = $scan;
        $this->repository = $repository;
    }

    public function handle(
        RepositoryService $repositoryService,
        RepositoryScanner $scanner,
        FingerprintService $fingerprintService
    ) {
        Log::info('ScanRepositoryJob starting for scan '.$this->scan->id);

        $this->scan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $workspacePath = $repositoryService->createTemporaryWorkspace($this->scan->uuid);

        try {
            $this->scan->update(['progress' => 10]);

            // 1. Checkout repository
            $checkedOut = $repositoryService->checkout($this->repository, $workspacePath);
            if (! $checkedOut) {
                throw new \RuntimeException('Git clone command failed.');
            }

            $this->scan->update(['progress' => 20]);

            // Count files scanned
            $filesScanned = 0;
            if (is_dir($workspacePath)) {
                $directoryIterator = new \RecursiveDirectoryIterator($workspacePath, \FilesystemIterator::SKIP_DOTS);
                $iterator = new \RecursiveIteratorIterator($directoryIterator);
                foreach ($iterator as $file) {
                    if ($file->isFile() && !str_contains($file->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
                        $filesScanned++;
                    }
                }
            }
            $this->scan->update(['files_scanned' => $filesScanned]);

            $this->scan->update(['progress' => 50]);

            // 2. Perform static analysis scan
            $findings = $scanner->scan($workspacePath, $this->repository->repository_url);

            $this->scan->update(['progress' => 80]);

            // 3. Obtain or create Asset representation
            $asset = Asset::firstOrCreate(
                [
                    'value' => $this->repository->name,
                    'type' => 'code_repository',
                ],
                [
                    'name' => $this->repository->name,
                    'criticality' => 'high',
                    'status' => 'active',
                    'created_by' => $this->scan->created_by,
                ]
            );

            // 4. Save and deduplicate findings
            $savedFindings = [];
            $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];

            foreach ($findings as $dto) {
                $fingerprint = $fingerprintService->generate($dto, $asset->id);
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

                // Check for duplicates
                $findingModel = Finding::updateOrCreate(
                    [
                        'fingerprint' => $fingerprint,
                        'scan_id' => $this->scan->id,
                    ],
                    [
                        'title' => $dto->title,
                        'asset_id' => $asset->id,
                        'severity' => $severity,
                        'status' => FindingStatus::OPEN,
                        'category' => $dto->category ?? 'SAST',
                        'cwe' => $dto->cwe ?? null,
                        'description' => $dto->description ?? '',
                        'remediation' => $dto->remediation ?? '',
                        'technical_details' => $dto->technicalDetails ?? '',
                        'evidence' => $dto->evidence ?? '',
                        'url' => $dto->url ?? '',
                        'scanner' => 'RepositoryScanner',
                        'created_by' => $this->scan->created_by,
                    ]
                );

                $savedFindings[] = $findingModel;
            }

            $this->scan->update(['progress' => 90]);

            // 5. Generate Professional vulnerability HTML report
            $reportTitle = 'Repository Vulnerability Assessment - '.$this->repository->name;
            $htmlContent = view('reports.repository_scan', [
                'repository' => $this->repository,
                'scan' => $this->scan,
                'findings' => $savedFindings,
                'severityCounts' => $severityCounts,
            ])->render();

            $report = Report::create([
                'uuid' => (string) Str::uuid(),
                'title' => $reportTitle,
                'type' => 'sast',
                'status' => 'Ready',
                'options' => [
                    'repository_id' => $this->repository->id,
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

            $this->repository->update([
                'last_scan_at' => now(),
                'status' => 'Connected',
            ]);

            // 7. Dispatch Notification
            $hasVulnerabilities = ($severityCounts['critical'] > 0 || $severityCounts['high'] > 0 || $severityCounts['medium'] > 0 || $severityCounts['low'] > 0);
            
            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_COMPLETED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan, repository: $this->repository),
                new ScanCompletedNotification($this->scan, array_sum($severityCounts))
            );

        } catch (\Throwable $e) {
            Log::error('ScanRepositoryJob failed: '.$e->getMessage(), ['exception' => $e]);
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            $this->repository->update([
                'status' => 'Failed',
            ]);

            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_FAILED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan, repository: $this->repository),
                new ScanFailedNotification($this->scan, 'Scan failed during execution.')
            );
        } finally {
            // Always cleanup workspace directory safely
            $repositoryService->cleanWorkspace($workspacePath);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScanRepositoryJob failed fatally: '.$exception->getMessage(), ['exception' => $exception]);
        
        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        if (isset($this->repository)) {
            $this->repository->update([
                'status' => 'Failed',
            ]);
        }

        \App\Services\Notification\NotificationRouter::dispatch(
            \App\Enums\Notification\NotificationType::SCAN_FAILED,
            new \App\Services\Notification\NotificationContext(scan: $this->scan, repository: $this->repository ?? null),
            new ScanFailedNotification($this->scan, 'Scan failed fatally.')
        );
    }

    public function getTenantId(): ?int
    {
        return $this->scan->created_by;
    }
}
