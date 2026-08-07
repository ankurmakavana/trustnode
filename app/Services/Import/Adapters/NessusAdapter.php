<?php

namespace App\Services\Import\Adapters;

use Exception;
use SimpleXMLElement;

class NessusAdapter implements ImportAdapterInterface
{
    public function parse(string $content): array
    {
        $assets = [];
        $findings = [];
        $scans = [
            'name' => 'Nessus Vulnerability Scan',
            'engine' => 'Nessus',
            'type' => 'Vulnerability',
            'target' => 'Local Network',
            'status' => 'Completed',
            'progress' => 100,
        ];

        try {
            if (str_contains($content, '<NessusClientData_v2')) {
                $xml = new SimpleXMLElement($content);
                foreach ($xml->Report->ReportHost as $host) {
                    $ip = (string) $host['name'];
                    $assets[] = [
                        'name' => $ip,
                        'type' => 'Host',
                        'value' => $ip,
                        'criticality' => 'high',
                        'status' => 'active',
                    ];

                    foreach ($host->ReportItem as $item) {
                        $severityMap = [
                            0 => 'informational',
                            1 => 'low',
                            2 => 'medium',
                            3 => 'high',
                            4 => 'critical',
                        ];
                        $sevVal = (int) $item['severity'];
                        $severity = $severityMap[$sevVal] ?? 'medium';

                        $findings[] = [
                            'title' => (string) $item['pluginName'],
                            'severity' => $severity,
                            'category' => 'Host',
                            'description' => (string) $item->description,
                            'remediation' => (string) $item->solution,
                            'cve' => isset($item->cve) ? (string) $item->cve : null,
                            'cvss_score' => isset($item->cvss_base_score) ? (float) $item->cvss_base_score : null,
                            'cwe' => isset($item->cwe) ? (string) $item->cwe : null,
                            'asset_value' => $ip,
                        ];
                    }
                }
            } else {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $assets = $data['assets'] ?? [];
                    $findings = $data['findings'] ?? [];
                }
            }
        } catch (Exception $e) {
            // Fallback
        }

        return [
            'assets' => $assets,
            'findings' => $findings,
            'scans' => [$scans],
        ];
    }
}
