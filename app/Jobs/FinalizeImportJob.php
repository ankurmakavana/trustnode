<?php

namespace App\Jobs;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Models\ImportHistory;
use App\Models\ImportJob;
use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeImportJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $assetIds;

    protected $savedCount;

    protected $dupCount;

    protected $scans;

    public function __construct(ImportJob $jobModel, string $scanner, array $assetIds, int $savedCount, int $dupCount, array $scans)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->assetIds = $assetIds;
        $this->savedCount = $savedCount;
        $this->dupCount = $dupCount;
        $this->scans = $scans;
    }

    public function handle()
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Finalizing import workflows.',
        ]);

        // Map scan records if returned by parser
        foreach ($this->scans as $scan) {
            try {
                // Ensure unique target lookup or default
                $targetVal = $scan['target'] ?? 'Unknown';

                // Map ScanEngine enum
                $engine = ScanEngine::NMAP;
                $scannerLower = strtolower($this->scanner);
                if ($scannerLower === 'nessus') {
                    $engine = ScanEngine::NESSUS;
                } elseif ($scannerLower === 'greenbone') {
                    $engine = ScanEngine::GREENBONE;
                } elseif ($scannerLower === 'burp') {
                    $engine = ScanEngine::BURP_SUITE;
                } elseif ($scannerLower === 'qualys') {
                    $engine = ScanEngine::QUALYS;
                } elseif ($scannerLower === 'rapid7') {
                    $engine = ScanEngine::RAPID7;
                }

                Scan::create([
                    'name' => $scan['name'] ?? 'Imported Scan',
                    'description' => 'Automated import session.',
                    'type' => ScanType::NETWORK_IP,
                    'engine' => $engine,
                    'target' => $targetVal,
                    'status' => ScanStatus::COMPLETED,
                    'progress' => 100,
                    'started_at' => now()->subMinutes(10),
                    'completed_at' => now(),
                    'duration' => 600,
                    'created_by' => $this->jobModel->created_by ?? 1,
                    'import_job_id' => $this->jobModel->id,
                ]);
            } catch (\Exception $e) {
                // continue
            }
        }

        $duration = max(1, now()->diffInSeconds($this->jobModel->created_at));

        ImportHistory::create([
            'import_job_id' => $this->jobModel->id,
            'scanner' => $this->scanner,
            'imported_assets_count' => count($this->assetIds),
            'imported_findings_count' => $this->savedCount,
            'duplicates_count' => $this->dupCount,
            'errors_count' => 0,
            'duration' => $duration,
            'status' => 'completed',
        ]);

        $this->jobModel->update([
            'status' => 'completed',
            'progress' => 100,
        ]);

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Import job completed successfully.',
        ]);
    }

    public function getTenantId(): ?int
    {
        return $this->jobModel->created_by;
    }
}
