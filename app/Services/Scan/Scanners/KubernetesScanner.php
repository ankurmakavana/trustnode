<?php

namespace App\Services\Scan\Scanners;

use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Str;

class KubernetesScanner implements ScannerInterface
{
    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array
    {
        $findings = [];
        $basename = strtolower(basename($relativePath));

        // Only scan YAML files
        if (!Str::endsWith($basename, ['.yaml', '.yml'])) {
            return [];
        }

        // Ignore lockfiles and docker-compose
        if (in_array($basename, ['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'])) {
            return [];
        }

        // Split multidoc YAML
        $documents = explode('---', $content);
        $globalLineOffset = 0;

        foreach ($documents as $docIndex => $docContent) {
            $docLines = explode("\n", $docContent);
            $docFindings = $this->scanDocument($docLines, $relativePath, $repositoryUrl, $globalLineOffset);
            foreach ($docFindings as $f) {
                $findings[] = $f;
            }
            $globalLineOffset += count($docLines);
            // +1 for the --- separator if not the last document, but explode removes it
            // actually explode('---') means we lost the '---', so offset is roughly correct.
        }

        return $findings;
    }

    private function scanDocument(array $lines, string $relativePath, string $repositoryUrl, int $globalLineOffset): array
    {
        // 1. Detect Kubernetes manifest
        $apiVersion = null;
        $kind = null;
        $cleanLines = [];

        foreach ($lines as $i => $line) {
            $cleanLine = trim(preg_replace('/#.*$/', '', $line));
            $cleanLines[$i] = $cleanLine;

            if (preg_match('/^apiVersion:\s*([^\s]+)/', ltrim($line), $matches)) {
                $apiVersion = $matches[1];
            }
            if (preg_match('/^kind:\s*([^\s]+)/', ltrim($line), $matches)) {
                $kind = $matches[1];
            }
        }

        if (!$apiVersion || !$kind) {
            return [];
        }

        $validKinds = [
            'Pod', 'Deployment', 'StatefulSet', 'DaemonSet', 'Job', 'CronJob',
            'Service', 'Ingress', 'ConfigMap', 'Secret', 'Role', 'RoleBinding',
            'ClusterRole', 'ClusterRoleBinding'
        ];

        if (!in_array($kind, $validKinds)) {
            return [];
        }

        $findings = [];

        // Stack to track current context path based on indentation
        $contextStack = [];

        foreach ($lines as $localLineNum => $rawLine) {
            $realLineNum = $globalLineOffset + $localLineNum + 1;
            
            // Skip comments and empty lines
            $trimmedLine = trim($rawLine);
            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
                continue;
            }

            // Strip trailing comments for processing
            $line = preg_replace('/#.*$/', '', $rawLine);
            
            // Calculate indentation
            preg_match('/^(\s*)/', $line, $matches);
            $indent = strlen($matches[1]);
            
            // Parse key
            $key = null;
            if (preg_match('/^(\s*-?\s*)([a-zA-Z0-9_-]+):/', $line, $matches)) {
                $key = $matches[2];
            }

            // Maintain stack
            while (count($contextStack) > 0 && end($contextStack)['indent'] >= $indent) {
                array_pop($contextStack);
            }

            if ($key !== null) {
                $contextStack[] = ['key' => $key, 'indent' => $indent];
            }

            // Build path string e.g., "spec.template.spec.containers.securityContext"
            $pathKeys = array_column($contextStack, 'key');
            $currentPath = implode('.', $pathKeys);

            // Rule 1: Privileged (CRITICAL)
            if (preg_match('/^\s*privileged:\s*true\b/i', $line)) {
                if ($kind !== 'ConfigMap' && str_contains($currentPath, 'securityContext')) {
                    $findings[] = $this->createFinding(
                        'SEC-IAC-K8S-PRIVILEGED',
                        'Privileged Kubernetes Container',
                        'CRITICAL',
                        "Container is running as privileged, granting full host access.",
                        $realLineNum,
                        trim($line),
                        $relativePath,
                        $repositoryUrl,
                        $kind
                    );
                }
            }

            // Rule 2: HostNetwork (HIGH)
            if (preg_match('/^\s*hostNetwork:\s*true\b/i', $line) && $kind !== 'ConfigMap') {
                $findings[] = $this->createFinding(
                    'SEC-IAC-K8S-HOST-NETWORK',
                    'Kubernetes HostNetwork Enabled',
                    'HIGH',
                    "Pod shares the host's network namespace.",
                    $realLineNum,
                    trim($line),
                    $relativePath,
                    $repositoryUrl,
                    $kind
                );
            }

            // Rule 3: HostPID (HIGH)
            if (preg_match('/^\s*hostPID:\s*true\b/i', $line) && $kind !== 'ConfigMap') {
                $findings[] = $this->createFinding(
                    'SEC-IAC-K8S-HOST-PID',
                    'Kubernetes HostPID Enabled',
                    'HIGH',
                    "Pod shares the host's PID namespace.",
                    $realLineNum,
                    trim($line),
                    $relativePath,
                    $repositoryUrl,
                    $kind
                );
            }

            // Rule 4: HostPath (HIGH)
            if (preg_match('/^\s*hostPath:/i', $line) && $kind !== 'ConfigMap') {
                $findings[] = $this->createFinding(
                    'SEC-IAC-K8S-HOST-PATH',
                    'Kubernetes HostPath Mount',
                    'HIGH',
                    "Pod mounts a directory from the host filesystem.",
                    $realLineNum,
                    trim($line),
                    $relativePath,
                    $repositoryUrl,
                    $kind
                );
            }

            // Rule 5: Privilege Escalation (HIGH)
            if (preg_match('/^\s*allowPrivilegeEscalation:\s*true\b/i', $line)) {
                if ($kind !== 'ConfigMap' && str_contains($currentPath, 'securityContext')) {
                    $findings[] = $this->createFinding(
                        'SEC-IAC-K8S-PRIVILEGE-ESCALATION',
                        'Kubernetes Privilege Escalation Allowed',
                        'HIGH',
                        "Container allows privilege escalation.",
                        $realLineNum,
                        trim($line),
                        $relativePath,
                        $repositoryUrl,
                        $kind
                    );
                }
            }

            // Rule 6: Run As Root (HIGH)
            if (preg_match('/^\s*runAsUser:\s*0\b/i', $line)) {
                if ($kind !== 'ConfigMap' && str_contains($currentPath, 'securityContext')) {
                    $findings[] = $this->createFinding(
                        'SEC-IAC-K8S-RUN-AS-ROOT',
                        'Kubernetes Container Runs as Root',
                        'HIGH',
                        "Container is explicitly configured to run as root (user 0).",
                        $realLineNum,
                        trim($line),
                        $relativePath,
                        $repositoryUrl,
                        $kind
                    );
                }
            }

            // Rule 7: Public Service LoadBalancer (MEDIUM)
            if ($kind === 'Service' && preg_match('/^\s*type:\s*LoadBalancer\b/i', $line)) {
                $findings[] = $this->createFinding(
                    'SEC-IAC-K8S-PUBLIC-SERVICE',
                    'Kubernetes Public LoadBalancer Service',
                    'MEDIUM',
                    "Service exposes internal workloads to the internet via LoadBalancer.",
                    $realLineNum,
                    trim($line),
                    $relativePath,
                    $repositoryUrl,
                    $kind
                );
            }
        }

        return $findings;
    }

    private function createFinding(string $id, string $title, string $severity, string $desc, int $lineNum, string $evidence, string $path, string $repoUrl, string $kind): NormalizedFinding
    {
        return new NormalizedFinding([
            'scanner' => 'KubernetesScanner',
            'scannerRuleId' => $id,
            'title' => $title,
            'severity' => $severity,
            'category' => 'IAC',
            'description' => $desc,
            'remediation' => 'Review the configuration and apply least privilege principles.',
            'technicalDetails' => "Vulnerability found in Kubernetes {$kind} manifest at {$path} on line {$lineNum}.",
            'evidence' => mb_strlen($evidence) > 2000 ? mb_substr($evidence, 0, 2000) . '...' : $evidence,
            'url' => rtrim($repoUrl, '/') . '/blob/main/' . $path . '#L' . $lineNum,
            'path' => $path,
        ]);
    }
}
