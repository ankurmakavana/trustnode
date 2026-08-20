<?php

namespace App\Services\Scan\Database;

use App\DTOs\Import\NormalizedFinding;

interface DatabaseSecurityScannerInterface
{
    /**
     * Run security checks against the database using the provided credentials.
     * 
     * @param array $credentials The credential bundle (driver, host, port, username, password)
     * @param string $target The target identifier (for evidence/reporting)
     * @return \App\DTOs\Scan\Database\DatabaseRuleResult[] Array of rule results
     */
    public function scan(array $credentials, string $target): array;
}
