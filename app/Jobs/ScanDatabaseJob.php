<?php

namespace App\Jobs;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Services\Import\FingerprintService;
use App\Services\Scan\Database\DatabaseCredentialVault;
use App\Services\Scan\Database\MysqlSecurityScanner;
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

class ScanDatabaseJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Scan $scan;
    
    // Crucially, this is ONLY the short-lived one-time token, NOT the credentials.
    protected string $credentialToken;

    public function __construct(Scan $scan, string $credentialToken)
    {
        $this->scan = $scan;
        $this->credentialToken = $credentialToken;
    }

    public function handle(
        DatabaseCredentialVault $vault,
        MysqlSecurityScanner $mysqlScanner, // Can inject a factory here if multiple DBs are supported in future
        FingerprintService $fingerprintService
    ) {
        $this->scan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        // 1. Securely retrieve credentials using the one-time token
        $credentials = $vault->retrieveAndConsume($this->credentialToken);
        
        if (!$credentials) {
            // Credentials expired, or token invalid/consumed.
            $this->failScan('Database credentials missing or expired. Token consumed.');
            return;
        }

        try {
            // Validate which scanner to use based on credentials
            $driver = $credentials['driver'] ?? 'mysql';
            
            if ($driver !== 'mysql') {
                $this->failScan("Database driver '{$driver}' is not supported yet.");
                return;
            }

            // 2. Perform database scan
            $target = $credentials['host'] . ':' . ($credentials['port'] ?? 3306);
            $findings = $mysqlScanner->scan($credentials, $target);

            // 3. Explicitly unset the memory array to minimize credential exposure time
            unset($credentials);

            // 4. Process rule results
            $savedFindings = [];
            $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
            $checkStats = ['total' => 0, 'passed' => 0, 'failed' => 0, 'unsupported' => 0];

            foreach ($findings as $ruleResult) {
                $checkStats['total']++;
                
                if ($ruleResult->status === \App\DTOs\Scan\Database\DatabaseRuleResult::STATUS_PASS) {
                    $checkStats['passed']++;
                    continue;
                }
                
                if ($ruleResult->status === \App\DTOs\Scan\Database\DatabaseRuleResult::STATUS_UNSUPPORTED) {
                    $checkStats['unsupported']++;
                    continue;
                }

                if ($ruleResult->status === \App\DTOs\Scan\Database\DatabaseRuleResult::STATUS_FAIL) {
                    $checkStats['failed']++;
                    
                    // Convert DatabaseRuleResult to NormalizedFinding
                    $dto = new \App\DTOs\Import\NormalizedFinding(
                        title: \App\Services\Scan\Database\EvidenceSanitizer::sanitize($ruleResult->title),
                        description: \App\Services\Scan\Database\EvidenceSanitizer::sanitize($ruleResult->description),
                        severity: $ruleResult->severity ?? 'Low',
                        scanner: 'DatabaseScanner (MySQL)',
                        url: $target,
                        evidence: \App\Services\Scan\Database\EvidenceSanitizer::sanitize($ruleResult->evidence),
                        remediation: \App\Services\Scan\Database\EvidenceSanitizer::sanitize($ruleResult->remediation),
                        scannerRuleId: $ruleResult->ruleId
                    );

                    // Generate stable fingerprint using target + ruleId
                    $fingerprint = $fingerprintService->generate($dto, null);
                    $dto->fingerprint = $fingerprint;

                    $severityVal = strtolower($dto->severity ?? 'low');
                    if (isset($severityCounts[$severityVal])) {
                        $severityCounts[$severityVal]++;
                    }

                    $severity = FindingSeverity::LOW;
                    foreach (FindingSeverity::cases() as $case) {
                        if (strtolower($case->value) === $severityVal) {
                            $severity = $case;
                            break;
                        }
                    }

                    $findingModel = Finding::updateOrCreate(
                        [
                            'fingerprint' => $fingerprint,
                            'scan_id' => $this->scan->id,
                        ],
                        [
                            'title' => $dto->title,
                            'asset_id' => null,
                            'severity' => $severity,
                            'status' => FindingStatus::OPEN,
                            'category' => 'Database',
                            'description' => $dto->description ?? '',
                            'remediation' => $dto->remediation ?? '',
                            'evidence' => $dto->evidence ?? '',
                            'url' => $dto->url ?? '',
                            'scanner' => $dto->scanner ?? 'DatabaseScanner',
                            'created_by' => $this->scan->created_by,
                        ]
                    );

                    $savedFindings[] = $findingModel;
                }
            }

            // 5. Generate Report
            $reportTitle = 'Database Security Assessment - '.$target;
            
            $viewName = view()->exists('reports.database_scan') ? 'reports.database_scan' : 'reports.repository_scan';
            $htmlContent = view($viewName, [
                'target' => $target,
                'scan' => $this->scan,
                'findings' => $savedFindings,
                'severityCounts' => $severityCounts,
                'checkStats' => $checkStats,
            ])->render();

            $report = Report::create([
                'uuid' => (string) Str::uuid(),
                'title' => $reportTitle,
                'type' => 'database',
                'status' => 'Ready',
                'options' => [
                    'scan_id' => $this->scan->id,
                    'severity_counts' => $severityCounts,
                    'check_stats' => $checkStats,
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

            // 7. Dispatch Notification
            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_COMPLETED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan),
                new ScanCompletedNotification($this->scan, array_sum($severityCounts))
            );

        } catch (\Throwable $e) {
            // NEVER log the exception message directly if it might contain PDO connection strings
            Log::error('ScanDatabaseJob failed unexpectedly.', [
                'scan_id' => $this->scan->id,
                'error_type' => get_class($e),
                'error_msg' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            
            $this->failScan('Scan failed during execution.');
        }
    }

    private function failScan(string $reason): void
    {
        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        \App\Services\Notification\NotificationRouter::dispatch(
            \App\Enums\Notification\NotificationType::SCAN_FAILED,
            new \App\Services\Notification\NotificationContext(scan: $this->scan),
            new ScanFailedNotification($this->scan, $reason)
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ScanDatabaseJob failed fatally.', [
            'scan_id' => $this->scan->id,
            'error_type' => get_class($exception),
        ]);
        
        $this->failScan('Scan failed fatally.');
    }

    public function getTenantId(): ?int
    {
        return $this->scan->created_by;
    }
}
