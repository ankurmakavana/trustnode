<?php

namespace App\Services\Scan\Scanners;

use App\DTOs\Import\NormalizedFinding;
use App\Services\Scan\Dependencies\ComposerLockParser;
use App\Services\Scan\Vulnerability\OsvApiClient;
use Illuminate\Support\Str;

class ScaScanner implements ScannerInterface
{
    protected ComposerLockParser $composerParser;
    protected \App\Services\Scan\Dependencies\NpmLockParser $npmParser;
    protected OsvApiClient $osvClient;

    public function __construct(ComposerLockParser $composerParser, \App\Services\Scan\Dependencies\NpmLockParser $npmParser, OsvApiClient $osvClient)
    {
        $this->composerParser = $composerParser;
        $this->npmParser = $npmParser;
        $this->osvClient = $osvClient;
    }

    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array
    {
        $filename = basename($relativePath);
        $dependencies = [];

        if ($filename === 'composer.lock') {
            $dependencies = $this->composerParser->parse($content);
        } elseif ($filename === 'package-lock.json') {
            $dependencies = $this->npmParser->parse($content);
        } else {
            return []; // Ignore unsupported files
        }

        if (empty($dependencies)) {
            return [];
        }

        $vulnerableDeps = $this->osvClient->query($dependencies);
        $findings = [];

        foreach ($vulnerableDeps as $dep) {
            foreach ($dep['vulns'] as $vuln) {
                // Determine CVE alias
                $cve = null;
                if (!empty($vuln['aliases'])) {
                    foreach ($vuln['aliases'] as $alias) {
                        if (str_starts_with($alias, 'CVE-')) {
                            $cve = $alias;
                            break;
                        }
                    }
                }

                // Title
                $title = $vuln['id']; // e.g. GHSA-xxx or OSV-xxx
                if (!empty($vuln['summary'])) {
                    $title .= ' - ' . $vuln['summary'];
                }

                // Fixed version
                $fixedVersion = null;
                if (!empty($vuln['affected'])) {
                    foreach ($vuln['affected'] as $affected) {
                        if (isset($affected['package']['name']) && $affected['package']['name'] === $dep['package']) {
                            if (!empty($affected['ranges'])) {
                                foreach ($affected['ranges'] as $range) {
                                    if (!empty($range['events'])) {
                                        foreach ($range['events'] as $event) {
                                            if (isset($event['fixed'])) {
                                                $fixedVersion = $event['fixed'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Format technical details
                $techDetails = "Ecosystem: {$dep['ecosystem']}\n";
                $techDetails .= "Package: {$dep['package']}\n";
                $techDetails .= "Installed Version: {$dep['version']}\n";
                if ($fixedVersion) {
                    $techDetails .= "Fixed Version: {$fixedVersion}\n";
                }
                $techDetails .= "OSV ID: {$vuln['id']}\n";
                if (!empty($vuln['aliases'])) {
                    $techDetails .= "Aliases: " . implode(', ', $vuln['aliases']) . "\n";
                }

                $evidence = json_encode([
                    'ecosystem' => $dep['ecosystem'],
                    'package' => $dep['package'],
                    'version' => $dep['version'],
                    'vuln_id' => $vuln['id'],
                ]);

                // Map to existing NormalizedFinding model.
                $findings[] = new NormalizedFinding([
                    'scanner' => 'ScaScanner',
                    'category' => 'SCA',
                    'scannerRuleId' => 'SEC-SCA-VULN',
                    'title' => Str::limit($title, 255),
                    'cve' => $cve,
                    'path' => $relativePath,
                    'parameter' => "{$dep['package']}@{$dep['version']}",
                    'description' => $vuln['details'] ?? 'No description provided.',
                    'technicalDetails' => $techDetails,
                    'evidence' => $evidence,
                    'severity' => 'High', // OSV does not consistently provide CVSS mapping in a unified place across ecosystems. Use High as default for vulnerabilities unless CVSS is extracted.
                    // Future improvement: parse CVSS from $vuln['severity']
                ]);
            }
        }

        return $findings;
    }
}
