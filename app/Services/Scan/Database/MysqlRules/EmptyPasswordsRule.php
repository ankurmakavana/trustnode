<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class EmptyPasswordsRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-002';
    }

    protected function getTitle(): string
    {
        return 'Empty Authentication Credentials';
    }

    protected function getDescription(): string
    {
        return 'Detects accounts with empty authentication credentials.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        // Check if authentication_string exists (MySQL >= 5.7) or Password (MySQL < 5.7)
        // First try authentication_string
        $column = 'authentication_string';
        try {
            // Just check if the column exists by doing a limit 0 query
            $pdo->query("SELECT authentication_string FROM mysql.user LIMIT 0");
        } catch (\PDOException $e) {
            // Fall back to Password
            try {
                $pdo->query("SELECT Password FROM mysql.user LIMIT 0");
                $column = 'Password';
            } catch (\PDOException $e) {
                return DatabaseRuleResult::unsupported(
                    $this->getRuleId(),
                    $this->getTitle(),
                    $this->getDescription(),
                    ['reason' => 'Could not detect authentication column in mysql.user.']
                );
            }
        }

        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE {$column} = '' OR {$column} IS NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($users) > 0) {
            $evidence = "Accounts with empty authentication credentials found:\n";
            foreach ($users as $u) {
                $evidence .= "- '{$u['User']}'@'{$u['Host']}'\n";
            }

            return DatabaseRuleResult::fail(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                'Critical',
                $evidence,
                "Set strong passwords for all database users using ALTER USER."
            );
        }

        return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
    }
}
