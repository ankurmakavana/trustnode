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
                'regex' => '/(?:select|insert|update|delete|where|orderBy|DB::raw)\s*\(.*[\$].*\)|["\']\s*(?:select|insert|update|delete|where)\b.*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*(?:\[[\'"][a-zA-Z_][a-zA-Z0-9_]*[\'"]\])?|["\']\s*(?:select|insert|update|delete|where)\b[^"\']*\.?\s*\$[a-zA-Z_][a-zA-Z0-9_]*(?:\[[\'"][a-zA-Z_][a-zA-Z0-9_]*[\'"]\])?/is',
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
                $scopeKey = $this->detectScopeKeyForOffset($content, $offset);
                $decision = $this->evaluateContext($matchedText, $signals, $scopeKey);

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
        $signals = [
            'sources' => [],
            'sanitizers' => [],
            'scopeState' => ['global' => []],
        ];

        $scopes = $this->extractFunctionScopes($content);
        $scopes = ['global' => $this->stripFunctionBodies($content)] + $scopes;

        foreach ($scopes as $scopeKey => $scopeContent) {
            $scopeBody = is_array($scopeContent) ? ($scopeContent['content'] ?? '') : $scopeContent;
            $state = [];
            if (preg_match_all('/\$(\w+)(?:\[[\'"](\w+)[\'"]\])?\s*=\s*(.+?);/s', $scopeBody, $assignments, PREG_SET_ORDER)) {
                $maxHops = 5;

                foreach ($assignments as $assignment) {
                    $variable = $assignment[1];
                    $arrayKey = $assignment[2] !== '' ? $assignment[2] : null;
                    $expression = trim($assignment[3]);

                    $stateKey = $arrayKey !== null ? "{$variable}__{$arrayKey}" : $variable;
                    $variables = $this->extractVariableNames($expression);
                    $source = false;
                    $sanitizer = null;
                    $hop = null;
                    $safe = false;
                    $unknownOrigin = false;

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
                    } elseif (count($variables) === 1 && preg_match('/^\$(\w+)\[[\'"](\w+)[\'"]\]$/', $expression, $arrayAccess)) {
                        $sourceVariable = $arrayAccess[1];
                        $sourceKey = $arrayAccess[2];
                        $sourceStateKey = "{$sourceVariable}__{$sourceKey}";
                        $sourceState = $state[$sourceStateKey] ?? null;
                        if ($sourceState !== null && $sourceState['source'] && $sourceState['hop'] < $maxHops) {
                            $source = true;
                            $hop = $sourceState['hop'] + 1;
                            $sanitizer = $sourceState['sanitizer'];
                        }
                    }

                    if (!$source && $this->isConstantExpression($expression)) {
                        $safe = true;
                    } elseif (!$source && !empty($variables) && !$this->isUserControlledSource($expression)) {
                        $unknownOrigin = true;
                    }

                    $state[$stateKey] = [
                        'source' => $source,
                        'sanitizer' => $sanitizer,
                        'hop' => $hop,
                        'safe' => $safe,
                        'unknownOrigin' => $unknownOrigin,
                    ];
                }
            }

            $signals['scopeState'][$scopeKey] = $state;
            foreach ($state as $variable => $variableState) {
                if ($variableState['source']) {
                    $signals['sources'][$scopeKey][$variable] = 'user-controlled input';
                }
                if ($variableState['sanitizer'] !== null) {
                    $signals['sanitizers'][$scopeKey][$variable] = $variableState['sanitizer'];
                }
            }
        }

        return $signals;
    }

    private function evaluateContext(string $matchedText, array $signals, string $scopeKey = 'global', string $lineContent = ''): array
    {
        $variableNames = $this->extractVariableNames($matchedText);
        $arrayProperties = $this->extractArrayProperties($matchedText);

        if (empty($variableNames) && empty($arrayProperties)) {
            return ['emit' => false, 'context' => 'constant expression without user-controlled source'];
        }

        $scopeState = $signals['scopeState'][$scopeKey] ?? [];
        $sourceMatches = [];
        $sanitizerMatches = [];
        $undefinedVars = 0;
        $safeMatches = 0;
        $unknownMatches = 0;

        foreach ($variableNames as $variableName) {
            if (!isset($scopeState[$variableName])) {
                $undefinedVars++;
                continue;
            }

            if (($scopeState[$variableName]['source'] ?? false)) {
                $sourceMatches[] = $variableName;
            } elseif (($scopeState[$variableName]['safe'] ?? false)) {
                $safeMatches++;
            } elseif (($scopeState[$variableName]['unknownOrigin'] ?? false)) {
                $unknownMatches++;
            }

            if (($scopeState[$variableName]['sanitizer'] ?? null) !== null) {
                $sanitizerMatches[] = $variableName;
            }
        }

        foreach ($arrayProperties as $arrayProperty) {
            if (!isset($scopeState[$arrayProperty])) {
                continue;
            }

            if (($scopeState[$arrayProperty]['source'] ?? false)) {
                $sourceMatches[] = $arrayProperty;
            } elseif (($scopeState[$arrayProperty]['safe'] ?? false)) {
                $safeMatches++;
            } elseif (($scopeState[$arrayProperty]['unknownOrigin'] ?? false)) {
                $unknownMatches++;
            }

            if (($scopeState[$arrayProperty]['sanitizer'] ?? null) !== null) {
                $sanitizerMatches[] = $arrayProperty;
            }
        }

        if (!empty($sourceMatches)) {
            if (!empty($sanitizerMatches)) {
                return ['emit' => true, 'context' => 'source -> sink with sanitizer detected (' . ($signals['sanitizers'][$scopeKey][$sanitizerMatches[0]] ?? 'sanitizer') . ')'];
            }

            return ['emit' => true, 'context' => 'source -> sink without sanitizer (' . ($signals['sources'][$scopeKey][$sourceMatches[0]] ?? 'user-controlled input') . ')'];
        }

        if ($undefinedVars > 0 && empty($arrayProperties)) {
            return ['emit' => false, 'context' => 'variable not defined in current function scope'];
        }

        if (($safeMatches > 0 || !empty($arrayProperties)) && $undefinedVars === 0) {
            return ['emit' => false, 'context' => 'constant expression without user-controlled source'];
        }

        if ($undefinedVars > 0 && !empty($arrayProperties)) {
            return ['emit' => false, 'context' => 'variable not defined in current function scope'];
        }

        return ['emit' => true, 'context' => 'fallback pattern match kept because source origin could not be safely established'];
    }

    private function findArrayPropertiesForVariable(string $variableName, array $scopeState): array
    {
        $matching = [];
        $prefix = $variableName . '__';
        foreach ($scopeState as $stateKey => $stateValue) {
            if (strpos($stateKey, $prefix) === 0) {
                $matching[] = $stateKey;
            }
        }
        return $matching;
    }

    private function detectScopeKeyForOffset(string $content, int $offset): string
    {
        $functionScopes = $this->extractFunctionScopes($content);
        foreach ($functionScopes as $scopeKey => $scope) {
            $start = $scope['start'];
            $end = $scope['end'];
            if ($offset >= $start && $offset <= $end) {
                return $scopeKey;
            }
        }

        return 'global';
    }

    private function extractFunctionScopes(string $content): array
    {
        preg_match_all('/function\s+(\w+)\s*\([^)]*\)\s*\{/i', $content, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return [];
        }

        $scopes = [];
        $length = strlen($content);
        $cursor = 0;

        foreach ($matches as $match) {
            $functionName = $match[1];
            $openPos = strpos($content, '{', $cursor);
            if ($openPos === false) {
                break;
            }

            $braceDepth = 0;
            $bodyStart = $openPos;
            $bodyEnd = null;
            for ($i = $openPos; $i < $length; $i++) {
                $char = $content[$i];
                if ($char === '{') {
                    $braceDepth++;
                } elseif ($char === '}') {
                    $braceDepth--;
                    if ($braceDepth === 0) {
                        $bodyEnd = $i;
                        break;
                    }
                }
            }

            if ($bodyEnd === null) {
                continue;
            }

            $scopeKey = 'function:' . strtolower($functionName);
            $scopes[$scopeKey] = [
                'start' => $bodyStart,
                'end' => $bodyEnd,
                'content' => substr($content, $bodyStart + 1, $bodyEnd - $bodyStart - 1),
            ];
            $cursor = $bodyEnd + 1;
        }

        return $scopes;
    }

    private function stripFunctionBodies(string $content): string
    {
        $offsets = [];
        $matches = $this->extractFunctionScopes($content);
        foreach ($matches as $scope) {
            $offsets[] = [$scope['start'], $scope['end']];
        }

        if (empty($offsets)) {
            return $content;
        }

        usort($offsets, static fn ($left, $right) => $left[0] <=> $right[0]);
        $parts = [];
        $lastPos = 0;

        foreach ($offsets as [$start, $end]) {
            $parts[] = substr($content, $lastPos, $start - $lastPos);
            $lastPos = $end + 1;
        }
        $parts[] = substr($content, $lastPos);

        return implode('', $parts);
    }

    private function isConstantExpression(string $expression): bool
    {
        $trimmed = trim($expression);
        if ($trimmed === '') {
            return false;
        }

        $containsVariable = preg_match('/\$\w+/', $trimmed) === 1;
        if ($containsVariable) {
            return false;
        }

        return !preg_match('/\$_(?:GET|POST|REQUEST|COOKIE|SERVER)/', $trimmed);
    }

    private function extractVariableNames(string $text): array
    {
        preg_match_all('/\$(\w+)/', $text, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    private function extractArrayProperties(string $text): array
    {
        preg_match_all('/\$(\w+)\[[\'"](\w+)[\'"]\]/', $text, $matches, PREG_SET_ORDER);
        $properties = [];
        foreach ($matches as $match) {
            $variable = $match[1];
            $key = $match[2];
            $properties[] = "{$variable}__{$key}";
        }
        return array_values(array_unique($properties));
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
