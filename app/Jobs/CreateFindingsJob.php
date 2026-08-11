<?php

namespace App\Jobs;

use App\Models\Finding;
use App\Models\ImportJob;
use App\Services\Import\FindingMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Services\Import\DuplicateDetectionService;

class CreateFindingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $resolvedAssets;

    protected $findings;

    protected $scans;

    public function __construct(ImportJob $jobModel, string $scanner, array $resolvedAssets, array $findings, array $scans)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->resolvedAssets = $resolvedAssets;
        $this->findings = $findings;
        $this->scans = $scans;
    }

    public function handle(FindingMapper $mapper, DuplicateDetectionService $dups)
    {
        $assetIds = collect($this->resolvedAssets)->pluck('id')->toArray();
        $dups->preload($assetIds, []);

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Saving vulnerability findings to database.',
        ]);

        $createdFindings = [];
        $savedCount = 0;
        $dupCount = 0;

        foreach ($this->findings as $rawFinding) {
            $asset = $this->resolvedAssets[$rawFinding['asset_value']] ?? null;
            if (! $asset) {
                $this->jobModel->logs()->create([
                    'level' => 'warning',
                    'message' => "Finding {$rawFinding['title']} skipped: parent asset not found.",
                ]);

                continue;
            }

            try {
                // Check if finding is duplicate beforehand for counting
                $isDup = Finding::where('title', $rawFinding['title'])
                    ->where('asset_id', $asset->id)
                    ->exists();

                $finding = $mapper->map($rawFinding, $asset, $this->jobModel);
                if ($finding) {
                    $createdFindings[] = $finding;
                    if ($isDup) {
                        $dupCount++;
                    } else {
                        $savedCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->jobModel->logs()->create([
                    'level' => 'warning',
                    'message' => "Failed to save finding {$rawFinding['title']}: ".$e->getMessage(),
                ]);
            }
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => "Saved {$savedCount} new findings. Duplicates detected: {$dupCount}.",
        ]);

        $this->jobModel->update([
            'progress' => 85,
        ]);

        MapComplianceJob::dispatch(
            $this->jobModel,
            $this->scanner,
            $createdFindings,
            $savedCount,
            $dupCount,
            $this->scans
        );
    }
}
