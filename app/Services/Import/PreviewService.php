<?php

namespace App\Services\Import;

use App\Models\Asset;

class PreviewService
{
    /**
     * Compute a summary preview of the normalized records.
     */
    public function generatePreview(array $normalizedData): array
    {
        $assets = $normalizedData['assets'] ?? [];
        $findings = $normalizedData['findings'] ?? [];

        $criticalCount = 0;
        $highCount = 0;
        $mediumCount = 0;
        $lowCount = 0;

        foreach ($findings as $f) {
            $severity = strtolower($f['severity'] ?? 'low');
            if ($severity === 'critical') {
                $criticalCount++;
            } elseif ($severity === 'high') {
                $highCount++;
            } elseif ($severity === 'medium') {
                $mediumCount++;
            } else {
                $lowCount++;
            }
        }

        // Simple duplicate check prediction
        $existingAssets = Asset::pluck('value')->toArray();
        $duplicateAssets = 0;
        foreach ($assets as $asset) {
            if (in_array($asset['value'], $existingAssets)) {
                $duplicateAssets++;
            }
        }

        return [
            'assets_count' => count($assets),
            'findings_count' => count($findings),
            'severities' => [
                'critical' => $criticalCount,
                'high' => $highCount,
                'medium' => $mediumCount,
                'low' => $lowCount,
            ],
            'duplicates' => $duplicateAssets,
            'compliance_impact' => count($findings) > 0 ? 'High (Requires Control Update)' : 'None',
            'risk_impact' => count($findings) > 5 ? 'High Risk Increase' : 'Moderate Risk Increase',
        ];
    }
}
