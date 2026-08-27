<?php

namespace App\Services\Scan\Scanners;

use App\DTOs\Import\NormalizedFinding;

abstract class AbstractRegexScanner implements ScannerInterface
{
    /**
     * @return array
     */
    abstract protected function getRules(): array;

    protected function isValidMatch(array $rule, string $matchedText): bool
    {
        return true;
    }

    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array
    {
        $findings = [];

        foreach ($this->getRules() as $rule) {
            if (preg_match_all($rule['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $matchedText = $match[0];
                    $offset = $match[1];

                    if (!$this->isValidMatch($rule, $matchedText)) {
                        continue;
                    }

                    // Determine line number
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $lineContent = $lines[$lineNumber - 1] ?? '';

                    // Sanitize/mask evidence if it contains secrets
                    $evidence = mb_convert_encoding(trim($lineContent), 'UTF-8', 'UTF-8');

                    if ($rule['category'] === 'Secret') {
                        $evidence = $this->maskSecret($matchedText);
                    } else {
                        if (mb_strlen($evidence) > 2000) {
                            $matchPos = mb_strpos($evidence, $matchedText);
                            if ($matchPos !== false) {
                                $start = max(0, $matchPos - 1000);
                                $truncatedEvidence = mb_substr($evidence, $start, 2000);
                                if ($start > 0) {
                                    $truncatedEvidence = '[truncated] ...' . $truncatedEvidence;
                                }
                                if ($start + 2000 < mb_strlen($evidence)) {
                                    $truncatedEvidence .= '... [truncated]';
                                }
                                $evidence = $truncatedEvidence;
                            } else {
                                $evidence = mb_substr($evidence, 0, 2000) . '... [truncated]';
                            }
                        }
                    }

                    $findings[] = new NormalizedFinding([
                        'scanner' => 'RepositoryScanner', // Preserved for fingerprint uniqueness
                        'scannerRuleId' => $rule['id'],
                        'title' => $rule['title'],
                        'severity' => $rule['severity'],
                        'category' => $rule['category'],
                        'cwe' => $rule['cwe'] ?? null,
                        'description' => $rule['description'],
                        'remediation' => $rule['remediation'],
                        'technicalDetails' => "Vulnerability found in {$relativePath} on line {$lineNumber}.",
                        'evidence' => $evidence,
                        'url' => $repositoryUrl.'/blob/main/'.$relativePath.'#L'.$lineNumber,
                        'path' => $relativePath,
                        'port' => null,
                        'protocol' => null,
                        'firstSeen' => now(),
                        'lastSeen' => now(),
                        'assetIdentifier' => $relativePath,
                    ]);
                }
            }
        }

        return $findings;
    }

    protected function maskSecret(string $secret): string
    {
        $secret = trim($secret);
        $len = strlen($secret);
        if ($len <= 8) {
            return '********';
        }

        return substr($secret, 0, 4).str_repeat('*', $len - 8).substr($secret, -4);
    }
}
