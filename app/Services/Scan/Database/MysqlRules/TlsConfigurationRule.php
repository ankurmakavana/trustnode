<?php

namespace App\Services\Scan\Database\MysqlRules;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use PDO;

class TlsConfigurationRule extends AbstractMysqlRule
{
    public function getRuleId(): string
    {
        return 'DB-MYSQL-009';
    }

    protected function getTitle(): string
    {
        return 'TLS/SSL Support Configuration';
    }

    protected function getDescription(): string
    {
        return 'Checks if the MySQL server supports and is configured to use TLS/SSL for encrypted connections.';
    }

    protected function doEvaluate(PDO $pdo): DatabaseRuleResult
    {
        // SHOW VARIABLES is mostly universally available
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'have_ssl'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['Value'])) {
            $value = strtoupper($row['Value']);
            
            if ($value === 'YES') {
                return DatabaseRuleResult::pass($this->getRuleId(), $this->getTitle(), $this->getDescription());
            } elseif ($value === 'DISABLED') {
                return DatabaseRuleResult::fail(
                    $this->getRuleId(),
                    $this->getTitle(),
                    $this->getDescription(),
                    'Medium',
                    "The database server has SSL compiled in, but it is currently DISABLED.",
                    "Enable SSL/TLS in the MySQL configuration (e.g., generate certificates and remove 'skip_ssl')."
                );
            } else {
                return DatabaseRuleResult::fail(
                    $this->getRuleId(),
                    $this->getTitle(),
                    $this->getDescription(),
                    'High',
                    "The database server does not support SSL (have_ssl = '{$value}').",
                    "Upgrade or reconfigure the database engine to support encrypted connections."
                );
            }
        }

        return DatabaseRuleResult::unsupported(
            $this->getRuleId(),
            $this->getTitle(),
            $this->getDescription(),
            ['reason' => 'Could not reliably determine TLS/SSL support status from SHOW VARIABLES.']
        );
    }
}
