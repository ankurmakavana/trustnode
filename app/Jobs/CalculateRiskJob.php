<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\RiskMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $findings;

    protected $savedCount;

    protected $dupCount;

    protected $scans;

    public function __construct(ImportJob $jobModel, string $scanner, array $findings, int $savedCount, int $dupCount, array $scans)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->findings = $findings;
        $this->savedCount = $savedCount;
        $this->dupCount = $dupCount;
        $this->scans = $scans;
    }

    public function handle(RiskMapper $mapper)
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Calculating and updating asset risk scores.',
        ]);

        $processedAssetIds = [];
        foreach ($this->findings as $finding) {
            if ($finding->asset && ! in_array($finding->asset_id, $processedAssetIds)) {
                try {
                    $mapper->recalculateAssetRisk($finding->asset);
                    $processedAssetIds[] = $finding->asset_id;
                } catch (\Exception $e) {
                    // Continue pipeline
                }
            }
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Asset risk score recalculations complete.',
        ]);

        $this->jobModel->update([
            'progress' => 95,
        ]);

        FinalizeImportJob::dispatch(
            $this->jobModel,
            $this->scanner,
            $processedAssetIds,
            $this->savedCount,
            $this->dupCount,
            $this->scans
        );
    }
}
