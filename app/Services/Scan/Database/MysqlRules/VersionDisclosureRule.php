<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class VersionDisclosureRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-008';
    }

    protected function getTitle(): string
    {
        return 'Database Version Information';
    }

    protected function getDescription(): string
    {
        return 'Retrieves the version of the MySQL server. This is informational metadata and does not necessarily constitute a vulnerability on its own.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        $stmt = $pdo->query("SELECT VERSION() as v");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['v'])) {
            // Version disclosure alone should NOT automatically become a vulnerability.
            // Returning PASS with metadata so it shows up in report summary but NOT as a finding.
            return DatabaseRuleResult::pass(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                ['version' => $row['v']]
            );
        }

        return DatabaseRuleResult::unsupported(
            $this->getRuleId(),
            $this->getTitle(),
            $this->getDescription(),
            ['reason' => 'Could not retrieve database version.']
        );
    }
}
