<?php

namespace App\Services\Import;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use App\Models\Asset;
use App\Models\ImportJob;
use Illuminate\Support\Facades\Auth;

class AssetMapper
{
    protected $dups;

    public function __construct(DuplicateDetectionService $dups)
    {
        $this->dups = $dups;
    }

    /**
     * Map and save a normalized asset.
     */
    public function map(array $rawAsset, ImportJob $job): Asset
    {
        $existing = $this->dups->findDuplicateAsset($rawAsset['value']);
        if ($existing) {
            return $existing;
        }

        // Map status, criticality and type enums if needed
        $type = AssetType::IPV4;
        if (isset($rawAsset['type']) && str_contains(strtolower($rawAsset['type']), 'web')) {
            $type = AssetType::URL;
        }

        return Asset::create([
            'name' => $rawAsset['name'] ?? $rawAsset['value'],
            'type' => $type,
            'value' => $rawAsset['value'],
            'description' => $rawAsset['description'] ?? 'Imported via '.$job->source_type,
            'criticality' => AssetCriticality::MEDIUM,
            'status' => AssetStatus::ACTIVE,
            'risk_score' => 0.00,
            'created_by' => $job->created_by ?? Auth::id() ?? 1,
            'import_job_id' => $job->id,
        ]);
    }
}
