<?php

namespace App\Services\Scan;

use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Facades\File;

class RepositoryScanner
{
    /**
     * Regex rules for static analysis vulnerability scanning.
     */
    protected array $rules = [
        [
            'id' => 'SEC-SECRET-AWS',
            'title' => 'Hardcoded AWS Access Key',
            'severity' => 'critical',
            'category' => 'Secret',
            'regex' => '/(A3T[A-Z0-9]|AKIA|AGPA|AIDA|AROA|AIPA|ANPA|ANVA|ASIA)[A-Z0-9]{16}/',
            'description' => 'A hardcoded AWS access key ID was discovered. If exposed, this allows unauthorized access to your AWS resources.',
            'remediation' => 'Revoke the AWS access key immediately and replace it with a dynamically retrieved credential from AWS Secrets Manager.',
        ],
        [
            'id' => 'SEC-SECRET-GENERIC',
            'title' => 'Exposed Private Token or Secret',
            'severity' => 'high',
            'category' => 'Secret',
            'regex' => '/(secret|token|password|passwd|api_key|apikey|private_key)\s*[:=]\s*["\'\s]*[a-zA-Z0-9_\-\.\/+=]{20,}["\'\s]*/i',
            'description' => 'An API token, private key, or password was found hardcoded in the source code.',
            'remediation' => 'Move hardcoded credentials to configuration environment variables (.env) or a credential vault.',
        ],
        [
            'id' => 'SEC-SAST-SQLI',
            'title' => 'Potential SQL Injection',
            'severity' => 'high',
            'category' => 'SAST',
            'cwe' => 'CWE-89',
            'regex' => '/(select|insert|update|delete|where|orderBy|DB::raw)\s*\(.*[\$].*\)/i',
            'description' => 'A raw SQL query concatenates/interpolates variables directly. This makes the application vulnerable to SQL Injection.',
            'remediation' => 'Use parameterized queries or prepared statements instead of directly concatenating user input.',
        ],
        [
            'id' => 'SEC-SAST-CMD',
            'title' => 'Unsafe Command Execution',
            'severity' => 'critical',
            'category' => 'SAST',
            'cwe' => 'CWE-78',
            'regex' => '/(shell_exec|exec|system|passthru|proc_open|popen)\s*\(.*[\$].*\)/i',
            'description' => 'The application executes system shell commands using interpolated variables. This can lead to Remote Command Execution (RCE).',
            'remediation' => 'Avoid executing shell commands from dynamic user input. If unavoidable, use strict validation or pass arguments as an array.',
        ],
        [
            'id' => 'SEC-SAST-EVAL',
            'title' => 'Unsafe Dynamic Code Execution (eval)',
            'severity' => 'critical',
            'category' => 'SAST',
            'cwe' => 'CWE-95',
            'regex' => '/\beval\s*\(.*[\$].*\)/i',
            'description' => 'The eval() function executes arbitrary strings as code. If user input is passed here, it allows arbitrary code execution.',
            'remediation' => 'Do not use eval(). Use safer alternative programming patterns or strict input whitelisting.',
        ],
        [
            'id' => 'SEC-SAST-PATH',
            'title' => 'Potential Path Traversal',
            'severity' => 'medium',
            'category' => 'SAST',
            'cwe' => 'CWE-22',
            'regex' => '/(file_get_contents|readfile|file|fopen)\s*\(\s*[\$].*(dir|path|file|url).*\)/i',
            'description' => 'Unvalidated user input is concatenated into a file system read function, potentially allowing path traversal to read arbitrary system files.',
            'remediation' => 'Sanitize file paths using basename() or restrict operations to a whitelist of allowed files/directories.',
        ],
    ];

    /**
     * Run scan recursively on the workspace directory.
     */
    public function scan(string $workspacePath, string $repositoryUrl): array
    {
        $findings = [];
        $files = File::allFiles($workspacePath);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            // Skip binary or vendor or .git files
            if ($this->shouldSkip($relativePath)) {
                continue;
            }

            $content = @file_get_contents($file->getRealPath());
            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($this->rules as $rule) {
                // Check if the whole content matches the rule regex
                if (preg_match_all($rule['regex'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $matchedText = $match[0];
                        $offset = $match[1];

                        // Determine line number
                        $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                        $lineContent = $lines[$lineNumber - 1] ?? '';

                        // Sanitize/mask evidence if it contains secrets
                        $evidence = mb_convert_encoding(trim($lineContent), 'UTF-8', 'UTF-8');
                        if ($rule['category'] === 'Secret') {
                            $evidence = $this->maskSecret($matchedText);
                        }

                        $findings[] = new NormalizedFinding([
                            'scanner' => 'RepositoryScanner',
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
        }

        return $findings;
    }

    /**
     * Skip binary files, vendor directories, git data, or lock files.
     */
    protected function shouldSkip(string $path): bool
    {
        $skippedPatterns = [
            '/^\.git\//',
            '/^vendor\//',
            '/^node_modules\//',
            '/\.(png|jpg|jpeg|gif|ico|zip|tar|gz|exe|bin|pdf|woff|woff2|eot|ttf|mp4|db|sqlite|sqlite3)$/i',
        ];

        foreach ($skippedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask discovered secrets to prevent credential leakage in reports.
     */
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
