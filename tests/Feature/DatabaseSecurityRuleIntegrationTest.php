<?php

namespace Tests\Feature;

use App\Services\Scan\Database\MysqlSecurityScanner;
use App\DTOs\Scan\Database\DatabaseRuleResult;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PDO;

class DatabaseSecurityRuleIntegrationTest extends TestCase
{
    public function test_mysql_scanner_with_real_database()
    {
        // Try connecting to the real docker MySQL instance if available
        $dsn = 'mysql:host=mysql;port=3306';
        try {
            $pdo = new PDO($dsn, 'root', 'root', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 2,
            ]);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Real MySQL database not available for integration test. ' . $e->getMessage());
        }

        // Create some controlled test data
        try {
            $pdo->exec("CREATE USER IF NOT EXISTS 'test_anon'@'localhost'");
            $pdo->exec("UPDATE mysql.user SET User = '' WHERE User = 'test_anon'"); // Create an anonymous user
            
            $pdo->exec("CREATE USER IF NOT EXISTS 'test_wildcard'@'%'");
            $pdo->exec("CREATE USER IF NOT EXISTS 'test_super'@'localhost'");
            $pdo->exec("GRANT SUPER ON *.* TO 'test_super'@'localhost'");
            $pdo->exec("CREATE USER IF NOT EXISTS 'test_grant'@'localhost'");
            $pdo->exec("GRANT ALL PRIVILEGES ON *.* TO 'test_grant'@'localhost' WITH GRANT OPTION");
            
            // Empty password user
            $pdo->exec("CREATE USER IF NOT EXISTS 'test_empty'@'localhost'");
            
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Exception $e) {
            // Ignore if already exists or other errors in test setup
        }

        $scanner = new MysqlSecurityScanner();
        $credentials = [
            'host' => 'mysql',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
        ];

        $results = $scanner->scan($credentials, 'mysql:3306');
        
        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        $hasAnon = false;
        $hasWildcard = false;
        $hasSuper = false;
        $hasGrant = false;
        $hasVersion = false;
        $hasEmptyPass = false;

        foreach ($results as $result) {
            $this->assertInstanceOf(DatabaseRuleResult::class, $result);
            
            if ($result->ruleId === 'DB-MYSQL-001' && $result->status === 'fail') $hasAnon = true;
            if ($result->ruleId === 'DB-MYSQL-003' && $result->status === 'fail') $hasWildcard = true;
            if ($result->ruleId === 'DB-MYSQL-005' && $result->status === 'fail') $hasSuper = true;
            if ($result->ruleId === 'DB-MYSQL-006' && $result->status === 'fail') $hasGrant = true;
            if ($result->ruleId === 'DB-MYSQL-008' && $result->status === 'pass') $hasVersion = true;
            if ($result->ruleId === 'DB-MYSQL-002' && $result->status === 'fail') $hasEmptyPass = true;
        }

        $this->assertTrue($hasAnon, 'Failed to detect anonymous user');
        $this->assertTrue($hasWildcard, 'Failed to detect wildcard user');
        $this->assertTrue($hasSuper, 'Failed to detect SUPER privilege');
        $this->assertTrue($hasGrant, 'Failed to detect global Grant privilege');
        $this->assertTrue($hasVersion, 'Failed to detect version metadata');
        $this->assertTrue($hasEmptyPass, 'Failed to detect empty password');

        // Clean up
        try {
            $pdo->exec("DELETE FROM mysql.user WHERE User = '' AND Host = 'localhost'");
            $pdo->exec("DROP USER IF EXISTS 'test_wildcard'@'%'");
            $pdo->exec("DROP USER IF EXISTS 'test_super'@'localhost'");
            $pdo->exec("DROP USER IF EXISTS 'test_grant'@'localhost'");
            $pdo->exec("DROP USER IF EXISTS 'test_empty'@'localhost'");
            $pdo->exec("FLUSH PRIVILEGES");
        } catch (\Exception $e) {
            // Ignore
        }
    }
}
