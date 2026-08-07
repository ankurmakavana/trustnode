<?php

namespace App\Services\Import;

use App\Enums\Finding\FindingSeverity;
use App\Models\Asset;

class RiskMapper
{
    /**
     * Recalculate risk score of an asset based on all its findings.
     */
    public function recalculateAssetRisk(Asset $asset): void
    {
        $findings = $asset->findings()->where('status', 'open')->get();
        $score = 0.0;

        foreach ($findings as $finding) {
            $severity = $finding->severity;
            if ($severity === FindingSeverity::CRITICAL) {
                $score += 4.0;
            } elseif ($severity === FindingSeverity::HIGH) {
                $score += 2.5;
            } elseif ($severity === FindingSeverity::MEDIUM) {
                $score += 1.0;
            } elseif ($severity === FindingSeverity::LOW) {
                $score += 0.25;
            }
        }

        // Cap risk score between 0.00 and 10.00
        $asset->risk_score = min(10.00, max(0.00, $score));
        $asset->save();
    }
}
