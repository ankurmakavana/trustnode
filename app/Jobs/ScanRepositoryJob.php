<?php

namespace App\Jobs;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Scan;
use App\Models\Report;
use App\Services\Import\FingerprintService;
use App\Services\Repository\RepositoryService;
use App\Services\Scan\RepositoryScanner;
use App\Mail\SecurityAlertMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ScanRepositoryJob implements ShouldQueue
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
        $this->scan->update([
            'status' => 'running',
            'progress' => 10,
            'started_at' => now(),
        ]);

        $workspacePath = $repositoryService->createTemporaryWorkspace($this->scan->uuid);

        try {
            $this->scan->update(['progress' => 30]);

            // 1. Checkout repository
            $checkedOut = $repositoryService->checkout($this->repository, $workspacePath);
            if (!$checkedOut) {
                throw new \RuntimeException("Git clone command failed.");
            }

            $this->scan->update(['progress' => 50]);

            // 2. Perform static analysis scan
            $findings = $scanner->scan($workspacePath, $this->repository->repository_url);

            $this->scan->update(['progress' => 70]);

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
                        'asset_id' => $asset->id,
                    ],
                    [
                        'title' => $dto->title,
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
                        'scan_id' => $this->scan->id,
                        'created_by' => $this->scan->created_by,
                    ]
                );

                $savedFindings[] = $findingModel;
            }

            $this->scan->update(['progress' => 90]);

            // 5. Generate Professional vulnerability HTML report
            $reportTitle = "Repository Vulnerability Assessment - " . $this->repository->name;
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

            // 7. Dispatch Email Notification if vulnerabilities exist
            $hasVulnerabilities = ($severityCounts['critical'] > 0 || $severityCounts['high'] > 0 || $severityCounts['medium'] > 0 || $severityCounts['low'] > 0);
            if ($hasVulnerabilities) {
                $user = $this->scan->creator;
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new SecurityAlertMail(
                        $this->repository->name,
                        $this->repository->default_branch,
                        $severityCounts,
                        $report->uuid,
                        $this->scan->uuid
                    ));
                }
            }

        } catch (\Exception $e) {
            $this->scan->update([
                'status' => 'failed',
                'progress' => 100,
                'completed_at' => now(),
            ]);

            $this->repository->update([
                'status' => 'Failed',
            ]);
        } finally {
            // Always cleanup workspace directory safely
            $repositoryService->cleanWorkspace($workspacePath);
        }
    }
}
