<?php

namespace App\Services\Import\Adapters;

use Exception;
use SimpleXMLElement;

class Rapid7Adapter implements ImportAdapterInterface
{
    public function parse(string $content): array
    {
        $assets = [];
        $findings = [];
        $scans = [
            'name' => 'Rapid7 Nexpose Scan',
            'engine' => 'Rapid7',
            'type' => 'Vulnerability',
            'target' => 'Local Network',
            'status' => 'Completed',
            'progress' => 100,
        ];

        try {
            if (str_contains($content, '<NexposeReport')) {
                $xml = new SimpleXMLElement($content);
                foreach ($xml->nodes->node as $node) {
                    $ip = (string) $node['address'];
                    $assets[] = [
                        'name' => $ip,
                        'type' => 'Host',
                        'value' => $ip,
                        'criticality' => 'medium',
                        'status' => 'active',
                    ];

                    foreach ($node->tests->test as $test) {
                        $severity = 'medium';
                        $status = (string) $test['status'];
                        if ($status === 'vulnerable-exploited') {
                            $severity = 'critical';
                        } elseif ($status === 'vulnerable') {
                            $severity = 'high';
                        }

                        $findings[] = [
                            'title' => (string) $test['id'],
                            'severity' => $severity,
                            'category' => 'Host',
                            'description' => 'Rapid7 detected vulnerability identifier '.(string) $test['id'],
                            'remediation' => 'Apply security patch or configuration hardening related to '.(string) $test['id'],
                            'cve' => null,
                            'cvss_score' => null,
                            'cwe' => null,
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
        }

        return [
            'assets' => $assets,
            'findings' => $findings,
            'scans' => [$scans],
        ];
    }
}
