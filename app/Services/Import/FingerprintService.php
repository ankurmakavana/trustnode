<?php

namespace App\Services\Import;

use App\DTOs\Import\NormalizedFinding;

class FingerprintService
{
    /**
     * Generate scanner-aware SHA256 fingerprint for a finding.
     */
    public function generate(NormalizedFinding $dto, int $assetId): string
    {
        $scanner = strtolower($dto->scanner ?? '');

        // Normalize port ONLY during fingerprint generation
        $port = $dto->port;
        if ($port === null) {
            $proto = strtolower($dto->protocol ?? '');
            if ($proto === 'https' || str_contains($proto, 'ssl') || str_contains($proto, 'tls')) {
                $port = 443;
            } else {
                $port = 80;
            }
        }

        if ($scanner === 'nessus') {
            $input = implode('|', [
                $assetId,
                $dto->scannerPluginId ?? '',
                $port,
                strtolower($dto->protocol ?? ''),
            ]);
        } elseif ($scanner === 'nmap') {
            $input = implode('|', [
                $assetId,
                $dto->scannerRuleId ?? $dto->title,
                $port,
            ]);
        } elseif ($scanner === 'burp' || $scanner === 'burpsuite') {
            $input = implode('|', [
                $dto->host ?? $dto->assetIdentifier ?? '',
                $dto->path ?? '',
                $dto->scannerRuleId ?? $dto->title,
                $dto->parameter ?? '',
            ]);
        } elseif ($scanner === 'greenbone' || $scanner === 'openvas') {
            $input = implode('|', [
                $assetId,
                $dto->scannerOid ?? '',
                $port,
            ]);
        } elseif ($scanner === 'repositoryscanner') {
            $input = implode('|', [
                $assetId,
                $dto->scannerRuleId ?? $dto->title,
                $dto->path ?? '',
            ]);
        } else {
            // Fallback rule
            $input = implode('|', [
                $assetId,
                $dto->title,
                $port,
                $dto->url ?? '',
            ]);
        }

        return hash('sha256', $input);
    }
}
