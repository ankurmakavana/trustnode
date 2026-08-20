<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class GlobalPrivilegesRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-006';
    }

    protected function getTitle(): string
    {
        return 'Accounts With Global Grant Privileges';
    }

    protected function getDescription(): string
    {
        return 'Detects database accounts that possess the Grant privilege globally (Grant_priv = \'Y\'), allowing them to grant their own privileges to other users.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE Grant_priv = 'Y'");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            $evidence = "Accounts with global Grant privilege:\n";
            foreach ($users as $u) {
                $evidence .= "- '{$u['User']}'@'{$u['Host']}'\n";
            }

            return DatabaseRuleResult::fail(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                'High', 
                $evidence,
                "Revoke global GRANT privilege from accounts that do not strictly require it."
            );
        }

        return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
    }
}
