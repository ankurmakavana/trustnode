<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\NormalizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel;

    protected $scanner;

    protected $parsedData;

    public function __construct(ImportJob $jobModel, string $scanner, array $parsedData)
    {
        $this->jobModel = $jobModel;
        $this->scanner = $scanner;
        $this->parsedData = $parsedData;
    }

    public function handle(NormalizationService $normalizer)
    {
        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Normalization phase started.',
        ]);

        $normalizedAssets = [];
        foreach ($this->parsedData['assets'] as $asset) {
            $normalizedAssets[] = [
                'name' => $asset['name'] ?? $asset['value'],
                'type' => $asset['type'] ?? 'Host',
                'value' => $asset['value'],
                'description' => $asset['description'] ?? null,
            ];
        }

        $normalizedFindings = [];
        foreach ($this->parsedData['findings'] as $finding) {
            $normalizedFindings[] = [
                'title' => $finding['title'],
                'severity' => $normalizer->normalizeSeverity($finding['severity'] ?? 'low'),
                'category' => $finding['category'] ?? 'Host',
                'cve' => $finding['cve'] ?? null,
                'cvss_score' => $normalizer->normalizeCvss($finding['cvss_score'] ?? null),
                'cwe' => $finding['cwe'] ?? null,
                'description' => $finding['description'] ?? null,
                'remediation' => $finding['remediation'] ?? null,
                'technical_details' => $finding['technical_details'] ?? null,
                'asset_value' => $finding['asset_value'],
            ];
        }

        $this->jobModel->logs()->create([
            'level' => 'info',
            'message' => 'Normalization completed successfully.',
        ]);

        $this->jobModel->update([
            'status' => 'importing',
            'progress' => 60,
        ]);

        CreateAssetsJob::dispatch(
            $this->jobModel,
            $this->scanner,
            $normalizedAssets,
            $normalizedFindings,
            $this->parsedData['scans'] ?? []
        );
    }
}
