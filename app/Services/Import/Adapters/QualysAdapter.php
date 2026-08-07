<?php

namespace App\Services\Import\Adapters;

use Exception;
use SimpleXMLElement;

class QualysAdapter implements ImportAdapterInterface
{
    public function parse(string $content): array
    {
        $assets = [];
        $findings = [];
        $scans = [
            'name' => 'Qualys Vulnerability Scan',
            'engine' => 'Qualys',
            'type' => 'Vulnerability',
            'target' => 'Local Network',
            'status' => 'Completed',
            'progress' => 100,
        ];

        try {
            if (str_contains($content, '<SCAN') || str_contains($content, '<ASSET')) {
                // Simplified XML host extraction
                $xml = new SimpleXMLElement($content);
                foreach ($xml->IP as $ipNode) {
                    $ip = (string) $ipNode;
                    $assets[] = [
                        'name' => $ip,
                        'type' => 'Host',
                        'value' => $ip,
                        'criticality' => 'medium',
                        'status' => 'active',
                    ];
                }
            } else {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $assets = $data['assets'] ?? [];
                    $findings = $data['findings'] ?? [];
                }
            }
        } catch (Exception $e) {
        }

        return [
            'assets' => $assets,
            'findings' => $findings,
            'scans' => [$scans],
        ];
    }
}
