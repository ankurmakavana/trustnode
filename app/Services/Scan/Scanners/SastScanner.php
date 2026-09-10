<?php

namespace App\Services\Scan\Scanners;

use App\DTOs\Import\NormalizedFinding;

class SastScanner extends AbstractRegexScanner
{
    protected function getRules(): array
    {
        return [
            [
                'id' => 'SEC-SAST-SQLI',
                'title' => 'Potential SQL Injection',
                'severity' => 'high',
                'category' => 'SAST',
                'cwe' => 'CWE-89',
                'regex' => '/(?:select|insert|update|delete|where|orderBy|DB::raw)\s*\(.*[\$].*\)|["\']\s*(?:select|insert|update|delete|where)\b.*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*|["\']\s*(?:select|insert|update|delete|where)\b[^"\']*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*/is',
                'description' => 'A raw SQL query concatenates/interpolates variables directly. This makes the application vulnerable to SQL Injection.',
                'remediation' => 'Use parameterized queries or prepared statements instead of directly concatenating user input.',
            ],
            [
                'id' => 'SEC-SAST-CMD',
                'title' => 'Unsafe Command Execution',
                'severity' => 'critical',
                'category' => 'SAST',
                'cwe' => 'CWE-78',
                'regex' => '/(?:shell_exec|exec|system|passthru|proc_open|popen)\s*\(.*[\$].*\)|(?:shell_exec|exec|system|passthru|proc_open|popen)\s*\(\s*["\'].*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*/i',
                'description' => 'The application executes system shell commands using interpolated variables. This can lead to Remote Command Execution (RCE).',
                'remediation' => 'Avoid executing shell commands from dynamic user input. If unavoidable, use strict validation or pass arguments as an array.',
            ],
            [
                'id' => 'SEC-SAST-EVAL',
                'title' => 'Unsafe Dynamic Code Execution (eval)',
                'severity' => 'critical',
                'category' => 'SAST',
                'cwe' => 'CWE-95',
                'regex' => '/\beval\s*\(.*[\$].*\)|\beval\s*\(\s*["\'].*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*/i',
                'description' => 'The eval() function executes arbitrary strings as code. If user input is passed here, it allows arbitrary code execution.',
                'remediation' => 'Do not use eval(). Use safer alternative programming patterns or strict input whitelisting.',
            ],
            [
                'id' => 'SEC-SAST-PATH',
                'title' => 'Potential Path Traversal',
                'severity' => 'medium',
                'category' => 'SAST',
                'cwe' => 'CWE-22',
                'regex' => '/(?:file_get_contents|readfile|file|fopen)\s*\(\s*[\$].*(?:dir|path|file|url).*\)|(?:file_get_contents|readfile|file|fopen)\s*\(\s*["\'].*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*/i',
                'description' => 'Unvalidated user input is concatenated into a file system read function, potentially allowing path traversal to read arbitrary system files.',
                'remediation' => 'Sanitize file paths using basename() or restrict operations to a whitelist of allowed files/directories.',
            ],
        ];
    }

    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array
    {
        $findings = [];
        $signals = $this->collectContextSignals($content);

        foreach ($this->getRules() as $rule) {
            if (!preg_match_all($rule['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $matchedText = $match[0];
                $offset = $match[1];
                $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                $lineContent = $lines[$lineNumber - 1] ?? '';
                $decision = $this->evaluateContext($matchedText, $signals);

                if (!$decision['emit']) {
                    continue;
                }

                $evidence = mb_convert_encoding(trim($lineContent), 'UTF-8', 'UTF-8');
                if (mb_strlen($evidence) > 2000) {
                    $matchPos = mb_strpos($evidence, $matchedText);
                    $evidence = $matchPos === false
                        ? mb_substr($evidence, 0, 2000) . '... [truncated]'
                        : mb_substr($evidence, max(0, $matchPos - 1000), 2000);
                }

                $findings[] = new NormalizedFinding([
                    'scanner' => 'RepositoryScanner',
                    'scannerRuleId' => $rule['id'],
                    'title' => $rule['title'],
                    'severity' => $rule['severity'],
                    'category' => $rule['category'],
                    'cwe' => $rule['cwe'],
                    'description' => $rule['description'],
                    'remediation' => $rule['remediation'],
                    'technicalDetails' => "Vulnerability found in {$relativePath} on line {$lineNumber}. Context: {$decision['context']}.",
                    'evidence' => $evidence,
                    'url' => $repositoryUrl.'/blob/main/'.$relativePath.'#L'.$lineNumber,
                    'path' => $relativePath,
                    'firstSeen' => now(),
                    'lastSeen' => now(),
                    'assetIdentifier' => $relativePath,
                ]);
            }
        }

        return $findings;
    }

    private function collectContextSignals(string $content): array
    {
        $signals = ['sources' => [], 'sanitizers' => []];
        if (!preg_match_all('/\$(\w+)\s*=\s*(.+?);/s', $content, $assignments, PREG_SET_ORDER)) {
            return $signals;
        }

        $state = [];
        $maxHops = 5;

        foreach ($assignments as $assignment) {
            $variable = $assignment[1];
            $expression = trim($assignment[2]);
            $variables = $this->extractVariableNames($expression);
            $source = false;
            $sanitizer = null;
            $hop = null;

            $detectedSanitizer = $this->detectSanitizer($expression);
            if ($detectedSanitizer !== null) {
                $sanitizer = $detectedSanitizer;
                foreach ($variables as $sourceVariable) {
                    if (($state[$sourceVariable]['source'] ?? false) && ($state[$sourceVariable]['hop'] ?? 99) < $maxHops) {
                        $source = true;
                        $hop = ($state[$sourceVariable]['hop'] ?? 0) + 1;
                        break;
                    }
                }
                if (!$source && $this->isUserControlledSource($expression)) {
                    $source = true;
                    $hop = 0;
                }
            } elseif ($this->isUserControlledSource($expression)) {
                $source = true;
                $hop = 0;
            } elseif (count($variables) === 1 && preg_match('/^\$(\w+)$/', $expression, $copy)) {
                $sourceState = $state[$copy[1]] ?? null;
                if ($sourceState !== null && $sourceState['source'] && $sourceState['hop'] < $maxHops) {
                    $source = true;
                    $hop = $sourceState['hop'] + 1;
                    $sanitizer = $sourceState['sanitizer'];
                }
            }

            $state[$variable] = [
                'source' => $source,
                'sanitizer' => $sanitizer,
                'hop' => $hop,
            ];
        }

        foreach ($state as $variable => $variableState) {
            if ($variableState['source']) {
                $signals['sources'][$variable] = 'user-controlled input';
            }
            if ($variableState['sanitizer'] !== null) {
                $signals['sanitizers'][$variable] = $variableState['sanitizer'];
            }
        }

        return $signals;
    }

    private function evaluateContext(string $matchedText, array $signals): array
    {
        $variableNames = $this->extractVariableNames($matchedText);
        if (empty($variableNames)) {
            return ['emit' => false, 'context' => 'constant expression without user-controlled source'];
        }

        $sourceMatches = array_values(array_intersect($variableNames, array_keys($signals['sources'])));
        $sanitizerMatches = array_values(array_intersect($variableNames, array_keys($signals['sanitizers'])));

        if (empty($sourceMatches)) {
            return ['emit' => true, 'context' => 'fallback pattern match kept because source origin could not be safely established'];
        }

        if (!empty($sanitizerMatches)) {
            return ['emit' => true, 'context' => 'source -> sink with sanitizer detected (' . $signals['sanitizers'][$sanitizerMatches[0]] . ')'];
        }

        return ['emit' => true, 'context' => 'source -> sink without sanitizer (' . $signals['sources'][$sourceMatches[0]] . ')'];
    }

    private function extractVariableNames(string $text): array
    {
        preg_match_all('/\$(\w+)/', $text, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    private function isUserControlledSource(string $expression): bool
    {
        return preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)(?:\[[^\]]+\])?/', $expression) === 1;
    }

    private function detectSanitizer(string $expression): ?string
    {
        if (preg_match('/\(\s*(?:int|integer|float|bool|string)\s*\)\s*\$/i', $expression)) {
            return 'type cast';
        }
        if (preg_match('/\b(?:intval|floatval|strval)\s*\(/i', $expression)) {
            return 'primitive conversion';
        }
        if (preg_match('/\bescapeshellarg\s*\(/i', $expression)) {
            return 'escapeshellarg';
        }
        if (preg_match('/\bescapeshellcmd\s*\(/i', $expression)) {
            return 'escapeshellcmd';
        }
        if (preg_match('/\bbasename\s*\(/i', $expression)) {
            return 'basename';
        }
        return null;
    }
}
