<?php

namespace App\Services\Import;

use App\Models\Asset;
use App\Models\Finding;

class DuplicateDetectionService
{
    /**
     * Check if asset already exists.
     */
    public function findDuplicateAsset(string $value): ?Asset
    {
        return Asset::where('value', $value)->first();
    }

    /**
     * Check if finding already exists.
     */
    public function findDuplicateFinding(string $title, int $assetId): ?Finding
    {
        return Finding::where('title', $title)
            ->where('asset_id', $assetId)
            ->first();
    }
}
