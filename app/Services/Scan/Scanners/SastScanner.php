<?php

namespace App\Services\Scan\Scanners;

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
    }
}
