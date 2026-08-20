<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

abstract class AbstractMysqlRule implements MysqlRuleInterface
{
    abstract protected function doEvaluate(PDO $pdo): DatabaseRuleResult;

    abstract public function getRuleId(): string;
    
    abstract protected function getTitle(): string;

    abstract protected function getDescription(): string;

    public function evaluate(PDO $pdo): DatabaseRuleResult
    {
        try {
            return $this->doEvaluate($pdo);
        } catch (\PDOException $e) {
            // Check if it's a "table or view not found" or "access denied" which typically means unsupported or lack of privileges
            // SQLSTATE 42S02: Base table or view not found
            // SQLSTATE 42000: Access denied
            if ($e->getCode() === '42S02' || $e->getCode() === '42000') {
                return DatabaseRuleResult::unsupported(
                    $this->getRuleId(),
                    $this->getTitle(),
                    $this->getDescription(),
                    ['reason' => 'Required system tables or columns are not accessible or do not exist in this database version.']
                );
            }
            
            // For other PDO exceptions, also fall back to unsupported to avoid breaking the entire scan
            return DatabaseRuleResult::unsupported(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                ['reason' => 'Database returned an error during check: ' . $e->getCode()]
            );
        } catch (\Exception $e) {
            return DatabaseRuleResult::unsupported(
                $this->getRuleId(),
                $this->getTitle(),
                $this->getDescription(),
                ['reason' => 'An unexpected error occurred during evaluation.']
            );
        }
    }
}
