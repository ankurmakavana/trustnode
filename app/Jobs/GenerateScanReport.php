<?php

namespace App\Jobs;

use App\Models\Finding;
use App\Models\Repository;
use App\Models\ScanReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Notifications\ReportReadyNotification;
use App\Notifications\ReportFailedNotification;

class GenerateScanReport implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // allow 10 minutes for massive reports
    public $tries = 1;

    public function __construct(protected ScanReport $scanReport)
    {
    }

    public function handle(): void
    {
        $this->scanReport->update([
            'status' => 'generating',
            'started_at' => now(),
            'progress' => 10,
        ]);

        try {
            $scan = $this->scanReport->scan;
            $scan->loadMissing('creator'); // Only load what's strictly necessary for cover page

            // Fetch only specific columns needed
            $findings = Finding::where('scan_id', $scan->id)
                ->select('id', 'scan_id', 'title', 'severity', 'category', 'cwe', 'technical_details', 'url', 'cvss_score', 'description', 'evidence', 'remediation')
                ->get();
            
            $this->scanReport->update(['progress' => 30]);

            $repository = Repository::where('name', $scan->target)->select('id', 'name', 'visibility', 'default_branch')->first();

            if (! $repository) {
                $repository = (object) [
                    'name' => $scan->target,
                    'visibility' => 'unknown',
                    'default_branch' => 'main',
                ];
            }

            $severityCounts = [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'info' => 0,
            ];

            foreach ($findings as $finding) {
                $sev = strtolower($finding->severity->value ?? $finding->severity);
                if (array_key_exists($sev, $severityCounts)) {
                    $severityCounts[$sev]++;
                } else {
                    $severityCounts['info']++;
                }

                // Mask secrets in evidence
                if (stripos($finding->category, 'secret') !== false || stripos($finding->title, 'secret') !== false || stripos($finding->title, 'token') !== false) {
                    if ($finding->evidence) {
                        $finding->evidence = '******** [REDACTED IN REPORT] ********';
                    }
                } elseif ($finding->evidence) {
                    $pattern = '/((?:api_key|apikey|token|secret|password|passwd|key|auth)(?:\s*[:=]\s*[\'"]?))([^\'"\s]+)([\'"]?)/i';
                    $finding->evidence = preg_replace($pattern, '$1********$3', $finding->evidence);
                }
            }

            $this->scanReport->update(['progress' => 50]);

            // Set infinite memory and time for DomPDF in CLI
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $pdf = Pdf::loadView('reports.repository_scan', [
                'scan' => $scan,
                'findings' => $findings,
                'repository' => $repository,
                'severityCounts' => $severityCounts,
            ]);

            $this->scanReport->update(['progress' => 80]);

            $date = $scan->created_at ? $scan->created_at->format('Y-m-d') : date('Y-m-d');
            $safeRepoName = str_replace(['/', '\\', '_'], '-', $repository->name);
            $filename = "reports/{$this->scanReport->id}/trustnode-{$safeRepoName}-scan-{$date}.pdf";

            Storage::disk('local')->put($filename, $pdf->output(), 'public');

            $this->scanReport->update([
                'status' => 'completed',
                'progress' => 100,
                'file_path' => $filename,
                'completed_at' => now(),
            ]);

            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::REPORT_READY,
                new \App\Services\Notification\NotificationContext(scan: $scan, report: $this->scanReport),
                new ReportReadyNotification($scan->id, $scan->name)
            );

        } catch (Throwable $e) {
            $this->scanReport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            
            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::REPORT_FAILED,
                new \App\Services\Notification\NotificationContext(scan: $this->scanReport->scan, report: $this->scanReport),
                new ReportFailedNotification($this->scanReport->scan->id, $this->scanReport->scan->name, 'Report generation encountered an error.')
            );
            
            throw $e;
        }
    }

    public function getTenantId(): ?int
    {
        return $this->scanReport->requested_by;
    }
}
