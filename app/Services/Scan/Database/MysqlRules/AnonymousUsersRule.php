<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class AnonymousUsersRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-001';
    }

    protected function getTitle(): string
    {
        return 'Anonymous Database Accounts';
    }

    protected function getDescription(): string
    {
        return 'Detects database accounts that have no username (User = \'\'), allowing anonymous connection attempts.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE User = ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            $evidence = "Anonymous accounts found:\n";
            foreach ($users as $u) {
                // Ensure no password hashes or sensitive things are in the evidence.
                $evidence .= "- ''@'{$u['Host']}'\n";
            }

            return DatabaseRuleResult::fail(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                'High', // High because anonymous access is dangerous, but we don't know privileges yet
                $evidence,
                "Remove anonymous user accounts: DROP USER ''@'localhost';"
            );
        }

        return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
    }
}
