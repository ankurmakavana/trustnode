<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

interface MysqlRuleInterface
{
    /**
     * Get the stable rule identifier (e.g., DB-MYSQL-001)
     */
    public function getRuleId(): string;

    /**
     * Run the security check against the database.
     */
    public function evaluate(PDO $pdo): DatabaseRuleResult;
}
