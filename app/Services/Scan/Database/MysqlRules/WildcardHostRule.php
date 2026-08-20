<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class WildcardHostRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-003';
    }

    protected function getTitle(): string
    {
        return 'Wildcard Host Access';
    }

    protected function getDescription(): string
    {
        return 'Detects accounts configured with Host = \'%\', allowing connection from any IP address.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE Host = '%'");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            $evidence = "Accounts with wildcard host access:\n";
            foreach ($users as $u) {
                $evidence .= "- '{$u['User']}'@'{$u['Host']}'\n";
            }

            return DatabaseRuleResult::fail(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                'Medium', // Just Medium because it depends on network boundary
                $evidence,
                "Restrict host access to specific IP ranges (e.g., '10.0.0.%' or '192.168.1.100')."
            );
        }

        return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
    }
}
