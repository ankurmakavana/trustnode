<?php

namespace App\Services\Import\Adapters;

use Exception;
use SimpleXMLElement;

class GreenboneAdapter implements ImportAdapterInterface
{
    public function parse(string $content): array
    {
        $assets = [];
        $findings = [];
        $scans = [
            'name' => 'Greenbone Vulnerability Scan',
            'engine' => 'Greenbone',
            'type' => 'Vulnerability',
            'target' => 'Local Network',
            'status' => 'Completed',
            'progress' => 100,
        ];

        try {
            if (str_contains($content, '<report')) {
                $xml = new SimpleXMLElement($content);
                foreach ($xml->report->results->result as $res) {
                    $ip = (string) $res->host;
                    if (! $ip) {
                        continue;
                    }

                    $assets[] = [
                        'name' => $ip,
                        'type' => 'Host',
                        'value' => $ip,
                        'criticality' => 'medium',
                        'status' => 'active',
                    ];

                    $severity = strtolower((string) $res->threat);
                    if ($severity === 'log') {
                        $severity = 'informational';
                    }

                    $findings[] = [
                        'title' => (string) $res->name,
                        'severity' => $severity,
                        'category' => 'Host',
                        'description' => (string) $res->description,
                        'remediation' => (string) $res->recommendation,
                        'cve' => (string) $res->nvt->cve ?? null,
                        'cvss_score' => (float) ($res->nvt->cvss_base ?? 0.0),
                        'cwe' => null,
                        'asset_value' => $ip,
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
