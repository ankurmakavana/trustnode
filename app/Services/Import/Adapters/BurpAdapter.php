<?php

namespace App\Services\Import\Adapters;

use Exception;
use SimpleXMLElement;

class BurpAdapter implements ImportAdapterInterface
{
    public function parse(string $content): array
    {
        $assets = [];
        $findings = [];
        $scans = [
            'name' => 'Burp Suite Web Scan',
            'engine' => 'Burp Suite',
            'type' => 'Web',
            'target' => 'Local Web App',
            'status' => 'Completed',
            'progress' => 100,
        ];

        try {
            if (str_contains($content, '<issues')) {
                $xml = new SimpleXMLElement($content);
                foreach ($xml->issue as $issue) {
                    $host = (string) $issue->host;
                    $ip = (string) $issue->host['ip'] ?? $host;

                    $assets[] = [
                        'name' => $host,
                        'type' => 'Web Application',
                        'value' => $host,
                        'criticality' => 'high',
                        'status' => 'active',
                    ];

                    $severity = strtolower((string) $issue->severity);

                    $findings[] = [
                        'title' => (string) $issue->name,
                        'severity' => $severity,
                        'category' => 'Web',
                        'description' => (string) $issue->issueBackground,
                        'remediation' => (string) $issue->remediationBackground,
                        'cve' => null,
                        'cvss_score' => null,
                        'cwe' => isset($issue->vulnerabilityClassifications) ? (string) $issue->vulnerabilityClassifications : null,
                        'asset_value' => $host,
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
