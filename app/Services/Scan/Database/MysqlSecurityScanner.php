<?php

namespace App\Services\Scan\Database;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use Illuminate\Support\Facades\Log;
use PDO;
use App\Services\Scan\Database\MysqlRules\AnonymousUsersRule;
use App\Services\Scan\Database\MysqlRules\EmptyPasswordsRule;
use App\Services\Scan\Database\MysqlRules\ExcessivePrivilegesRule;
use App\Services\Scan\Database\MysqlRules\GlobalPrivilegesRule;
use App\Services\Scan\Database\MysqlRules\TlsConfigurationRule;
use App\Services\Scan\Database\MysqlRules\VersionDisclosureRule;
use App\Services\Scan\Database\MysqlRules\WildcardHostRule;

class MysqlSecurityScanner implements DatabaseSecurityScannerInterface
{
    private array $rules = [];

    public function __construct()
    {
        $this->rules = [
            new AnonymousUsersRule(),
            new EmptyPasswordsRule(),
            new WildcardHostRule(),
            new ExcessivePrivilegesRule(),
            new GlobalPrivilegesRule(),
            new VersionDisclosureRule(),
            new TlsConfigurationRule(),
        ];
    }

    /**
     * @return DatabaseRuleResult[] Array of rule results (PASS, FAIL, UNSUPPORTED)
     */
    public function scan(array $credentials, string $target): array
    {
        $results = [];
        $pdo = null;

        try {
            // Establish isolated PDO connection, keeping credentials purely in memory variables
            $dsn = sprintf('mysql:host=%s;port=%d', $credentials['host'], $credentials['port'] ?? 3306);
            if (!empty($credentials['database'])) {
                $dsn .= ';dbname=' . $credentials['database'];
            }

            $pdo = new PDO($dsn, $credentials['username'], $credentials['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Ensure timeout so we don't hang forever
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // Clear password immediately from memory array
            unset($credentials['password']);

            foreach ($this->rules as $rule) {
                $results[] = $rule->evaluate($pdo);
            }

        } catch (\PDOException $e) {
            // Never log the DSN/password. Just the fact that it failed for the target.
            Log::warning('DatabaseSecurityScanner failed to connect to MySQL target', [
                'target' => $target,
                'host' => $credentials['host'] ?? 'unknown',
            ]);
            
            // Connection failure is a critical operational failure, we can return a FAIL result representing it
            $results[] = DatabaseRuleResult::fail(
                'DB-CONNECTION-001',
                'Database Connection Failed',
                'The scanner could not connect to the database. Verify credentials, network access, and port.',
                'Info',
                null,
                'Verify network reachability and credentials.'
            );
        } finally {
            // Unset PDO to close connection
            $pdo = null;
        }

        return $results;
    }
}
