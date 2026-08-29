<?php

namespace App\Services\Scan\Scanners;

use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Str;

class ContainerScanner implements ScannerInterface
{
    /**
     * @return NormalizedFinding[]
     */
    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array
    {
        $findings = [];
        $basename = basename($relativePath);

        $isDockerfile = Str::startsWith(strtolower($basename), 'dockerfile') || strtolower($basename) === 'dockerfile';
        $isCompose = in_array(strtolower($basename), ['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml']);

        if (!$isDockerfile && !$isCompose) {
            return [];
        }

        if ($isDockerfile) {
            $findings = array_merge($findings, $this->scanDockerfile($content, $relativePath));
        }

        if ($isCompose) {
            $findings = array_merge($findings, $this->scanCompose($content, $relativePath));
        }

        return $findings;
    }

    /**
     * @return NormalizedFinding[]
     */
    protected function scanDockerfile(string $content, string $relativePath): array
    {
        $findings = [];
        
        $rules = [
            [
                'id' => 'SEC-CONTAINER-DF-LATEST',
                'title' => 'Unpinned Base Image (latest)',
                'severity' => 'medium',
                'regex' => '/^FROM\s+[^\s@]+(:latest)?(\s+AS\s+[a-z0-9_-]+)?\s*$/mi',
                'description' => 'The Dockerfile uses the "latest" tag or an implicit latest tag for its base image. This can lead to unpredictable builds and security regressions.',
                'remediation' => 'Pin the base image to a specific version or SHA256 digest.',
            ],
            [
                'id' => 'SEC-CONTAINER-DF-ROOT',
                'title' => 'Explicit Root User Execution',
                'severity' => 'high',
                'regex' => '/^USER\s+root\s*$/mi',
                'description' => 'The Dockerfile explicitly sets the runtime user to "root". Running as root increases the impact of container escape vulnerabilities.',
                'remediation' => 'Create and use a non-root user for executing the application.',
            ],
            [
                'id' => 'SEC-CONTAINER-DF-CURL-BASH',
                'title' => 'Risky curl/wget Pipe to Shell',
                'severity' => 'critical',
                'regex' => '/(?:curl|wget)[^|]*\|\s*(?:bash|sh)/i',
                'description' => 'The Dockerfile pipes a downloaded script directly into a shell. This is highly unsafe if the remote source is compromised.',
                'remediation' => 'Download the script, verify its checksum/hash, and execute it separately.',
            ],
            [
                'id' => 'SEC-CONTAINER-DF-ADD',
                'title' => 'Use of ADD Instruction',
                'severity' => 'low',
                'regex' => '/^ADD\s+(https?:\/\/[^\s]+|.*\.tar\.(gz|bz2|xz))\s+/mi',
                'description' => 'The Dockerfile uses ADD to fetch remote URLs or automatically extract archives. This can lead to unexpected behavior or remote execution risks.',
                'remediation' => 'Use COPY for local files and fetch remote files securely with curl/wget and hash verification.',
            ],
        ];

        foreach ($rules as $rule) {
            if (preg_match_all($rule['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $evidence = $match[0];
                    $offset = $match[1];
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                    
                    // Truncate evidence
                    if (mb_strlen($evidence) > 2000) {
                        $evidence = mb_substr($evidence, 0, 2000) . '... [truncated]';
                    }

                    $findings[] = new NormalizedFinding([
                        'scanner' => 'ContainerScanner',
                        'category' => 'CONTAINER',
                        'scannerRuleId' => $rule['id'],
                        'title' => $rule['title'],
                        'severity' => $rule['severity'],
                        'description' => $rule['description'],
                        'path' => $relativePath,
                        'lineNumber' => $lineNumber,
                        'evidence' => trim($evidence),
                        'technicalDetails' => "Container File: {$relativePath}\nRule: {$rule['id']}\nContext: {$rule['title']}",
                        'remediation' => $rule['remediation'],
                    ]);
                }
            }
        }

        return $findings;
    }

    /**
     * @return NormalizedFinding[]
     */
    protected function scanCompose(string $content, string $relativePath): array
    {
        $findings = [];
        
        $rules = [
            [
                'id' => 'SEC-CONTAINER-DC-PRIVILEGED',
                'title' => 'Privileged Container Access',
                'severity' => 'critical',
                'regex' => '/^\s*privileged:\s*(true|"true"|\'true\')/mi',
                'description' => 'The Docker Compose service requests privileged access. This effectively grants host-level permissions, bypassing container isolation.',
                'remediation' => 'Remove "privileged: true" and explicitly grant only required capabilities using "cap_add".',
            ],
            [
                'id' => 'SEC-CONTAINER-DC-SOCK',
                'title' => 'Docker Socket Mount',
                'severity' => 'critical',
                'regex' => '/\s*-\s*["\']?\/var\/run\/docker\.sock:\/var\/run\/docker\.sock["\']?/i',
                'description' => 'The Docker Compose service mounts the host Docker socket. This grants the container full control over the host Docker daemon.',
                'remediation' => 'Do not expose the Docker socket unless absolutely necessary for dedicated infrastructure services.',
            ],
            [
                'id' => 'SEC-CONTAINER-DC-HOST-NET',
                'title' => 'Host Network Mode',
                'severity' => 'high',
                'regex' => '/^\s*network_mode:\s*["\']?host["\']?/mi',
                'description' => 'The Docker Compose service uses host networking. This disables network namespace isolation, exposing the host network stack.',
                'remediation' => 'Use default bridge or custom overlay networks instead of "network_mode: host".',
            ],
        ];

        foreach ($rules as $rule) {
            if (preg_match_all($rule['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $evidence = $match[0];
                    $offset = $match[1];
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                    // Truncate evidence
                    if (mb_strlen($evidence) > 2000) {
                        $evidence = mb_substr($evidence, 0, 2000) . '... [truncated]';
                    }
                    
                    $findings[] = new NormalizedFinding([
                        'scanner' => 'ContainerScanner',
                        'category' => 'CONTAINER',
                        'scannerRuleId' => $rule['id'],
                        'title' => $rule['title'],
                        'severity' => $rule['severity'],
                        'description' => $rule['description'],
                        'path' => $relativePath,
                        'lineNumber' => $lineNumber,
                        'evidence' => trim($evidence),
                        'technicalDetails' => "Container File: {$relativePath}\nRule: {$rule['id']}\nContext: {$rule['title']}",
                        'remediation' => $rule['remediation'],
                    ]);
                }
            }
        }

        return $findings;
    }
}
