<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ComplianceMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MapComplianceJob implements ShouldQueue
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

    public function handle(ComplianceMapper $mapper)
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Mapping findings to compliance framework controls.',
        ]);

        foreach ($this->findings as $finding) {
            try {
                $mapper->map($finding);
            } catch (\Exception $e) {
                // Ignore mapping errors to continue pipeline
            }
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Compliance mapping completed.',
        ]);

        $this->jobModel->update([
            'progress' => 90,
        ]);

        CalculateRiskJob::dispatch(
            $this->jobModel,
            $this->scanner,
            $this->findings,
            $this->savedCount,
            $this->dupCount,
            $this->scans
        );
    }
}
