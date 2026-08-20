<?php

namespace App\Services\Scan\Database;

class EvidenceSanitizer
{
    /**
     * Sanitizes evidence string to ensure no secrets are leaked.
     * Removes passwords, known connection strings, etc.
     */
    public static function sanitize(?string $evidence): ?string
    {
        if ($evidence === null) {
            return null;
        }

        // Basic sanity checks: 
        // 1. Remove strings that look like "password=xxx" or "pwd=xxx"
        $evidence = preg_replace('/(password|pwd|pass)\s*=\s*([^\s,;]+)/i', '$1=***', $evidence);
        
        // 2. Remove URL-style credentials from DSNs like mysql://user:pass@host
        $evidence = preg_replace('/:\/\/[^:]+:([^@]+)@/', '://user:***@', $evidence);
        
        // 3. Remove raw password hash formats commonly dumped by accident
        // e.g., MySQL 41-byte *ABCDEF... hashes
        $evidence = preg_replace('/(\*[A-F0-9]{40})/i', '***', $evidence);
        
        // 4. Remove caching_sha2_password or mysql_native_password strings
        // e.g. $A$005$.......
        $evidence = preg_replace('/(\$[A-Z0-9]+\$[A-Z0-9]+\$[^\s\'",;]+)/i', '***', $evidence);

        return $evidence;
    }
}
