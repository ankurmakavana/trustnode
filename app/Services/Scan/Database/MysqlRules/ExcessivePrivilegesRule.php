<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class ExcessivePrivilegesRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-005';
    }

    protected function getTitle(): string
    {
        return 'Excessive Administrative Privileges (SUPER)';
    }

    protected function getDescription(): string
    {
        return 'Detects database accounts that possess the SUPER privilege, which allows extensive server modification and bypass of some security controls.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        // Not all mysql versions or forks might expose Super_priv exactly the same way, but it's very standard.
        // We catch PDOException in base class if the column doesn't exist.
        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE Super_priv = 'Y'");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            $evidence = "Accounts with SUPER privilege:\n";
            foreach ($users as $u) {
                // Ensure we don't list root@localhost if it's considered normal, 
                // but usually it's still good to report as an admin account.
                // We'll report all of them.
                $evidence .= "- '{$u['User']}'@'{$u['Host']}'\n";
            }

            return DatabaseRuleResult::fail(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                'High', 
                $evidence,
                "Revoke SUPER privilege from accounts that do not strictly require it."
            );
        }

        return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
    }
}
