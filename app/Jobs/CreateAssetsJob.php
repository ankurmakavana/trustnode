<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\AssetMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $assets;

    protected $findings;

    protected $scans;

    public function __construct(ImportJob $jobModel, string $scanner, array $assets, array $findings, array $scans)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->assets = $assets;
        $this->findings = $findings;
        $this->scans = $scans;
    }

    public function handle(AssetMapper $mapper)
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Saving asset records to database.',
        ]);

        $resolvedAssets = [];
        $savedCount = 0;

        foreach ($this->assets as $rawAsset) {
            try {
                $asset = $mapper->map($rawAsset, $this->jobModel);
                $resolvedAssets[$rawAsset['value']] = $asset;
                $savedCount++;
            } catch (\Exception $e) {
                $this->jobModel->logs()->create([
                    'level' => 'warning',
                    'message' => "Failed to save asset {$rawAsset['value']}: ".$e->getMessage(),
                ]);
            }
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => "Saved {$savedCount} asset records.",
        ]);

        $this->jobModel->update([
            'progress' => 75,
        ]);

        CreateFindingsJob::dispatch(
            $this->jobModel,
            $this->scanner,
            $resolvedAssets,
            $this->findings,
            $this->scans
        );
    }
}
