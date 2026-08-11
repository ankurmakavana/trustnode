<?php

namespace App\Services\Import;

use App\Models\Asset;
use App\Models\Finding;

class DuplicateDetectionService
{
    protected $findingsMap = [];
    protected $assetsMap = [];
    protected $preloaded = false;

    /**
     * Preload existing assets and findings for in-memory duplicate check.
     */
    public function preload(array $assetIds, array $assetValues = []): void
    {
        if (!empty($assetIds)) {
            $findings = Finding::whereIn('asset_id', $assetIds)->get();
            foreach ($findings as $f) {
                $key = $f->asset_id . '_' . strtolower($f->title);
                $this->findingsMap[$key] = $f;
            }
        }

        if (!empty($assetValues)) {
            $assets = Asset::whereIn('value', $assetValues)->get();
            foreach ($assets as $a) {
                $this->assetsMap[strtolower($a->value)] = $a;
            }
        }

        $this->preloaded = true;
    }

    /**
     * Check if asset already exists.
     */
    public function findDuplicateAsset(string $value): ?Asset
    {
        $valLower = strtolower($value);
        if ($this->preloaded && isset($this->assetsMap[$valLower])) {
            return $this->assetsMap[$valLower];
        }

        return Asset::where('value', $value)->first();
    }

    /**
     * Check if finding already exists.
     */
    public function findDuplicateFinding(string $title, int $assetId): ?Finding
    {
        $key = $assetId . '_' . strtolower($title);
        if ($this->preloaded && isset($this->findingsMap[$key])) {
            return $this->findingsMap[$key];
        }

        return Finding::where('title', $title)
            ->where('asset_id', $assetId)
            ->first();
    }
}
