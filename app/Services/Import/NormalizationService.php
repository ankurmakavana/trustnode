<?php

namespace App\Services\Import;

class NormalizationService
{
    /**
     * Normalize severity string.
     */
    public function normalizeSeverity(?string $severity): string
    {
        $sev = strtolower(trim($severity ?? 'low'));
        if (in_array($sev, ['critical', 'high', 'medium', 'low', 'informational'])) {
            return $sev;
        }
        if ($sev === 'info' || $sev === 'log') {
            return 'informational';
        }

        return 'low';
    }

    /**
     * Normalize CVSS score.
     *
     * @param  mixed  $score
     */
    public function normalizeCvss($score): ?float
    {
        if ($score === null || $score === '') {
            return null;
        }
        $val = (float) $score;

        return max(0.0, min(10.0, $val));
    }
}
