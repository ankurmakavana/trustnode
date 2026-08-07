<?php

namespace App\Services\Import;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\ImportJob;
use Illuminate\Support\Facades\Auth;

class FindingMapper
{
    protected $dups;

    protected $normalizer;

    public function __construct(DuplicateDetectionService $dups, NormalizationService $normalizer)
    {
        $this->dups = $dups;
        $this->normalizer = $normalizer;
    }

    /**
     * Map and save a normalized finding.
     */
    public function map(array $rawFinding, Asset $asset, ImportJob $job): ?Finding
    {
        $existing = $this->dups->findDuplicateFinding($rawFinding['title'], $asset->id);
        if ($existing) {
            return $existing;
        }

        // Map severity to enum value
        $sevStr = $this->normalizer->normalizeSeverity($rawFinding['severity'] ?? 'low');
        $severity = FindingSeverity::LOW;
        foreach (FindingSeverity::cases() as $case) {
            if ($case->value === $sevStr) {
                $severity = $case;
            }
        }

        $cvss = $this->normalizer->normalizeCvss($rawFinding['cvss_score'] ?? null);

        return Finding::create([
            'title' => $rawFinding['title'],
            'severity' => $severity,
            'status' => FindingStatus::OPEN,
            'category' => $rawFinding['category'] ?? 'Host',
            'cve' => $rawFinding['cve'] ?? null,
            'cvss_score' => $cvss,
            'cwe' => $rawFinding['cwe'] ?? null,
            'description' => $rawFinding['description'] ?? 'No description provided.',
            'remediation' => $rawFinding['remediation'] ?? 'Apply standard security hardening.',
            'technical_details' => $rawFinding['technical_details'] ?? null,
            'asset_id' => $asset->id,
            'created_by' => $job->created_by ?? Auth::id() ?? 1,
            'import_job_id' => $job->id,
        ]);
    }
}
