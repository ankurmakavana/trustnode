<?php

namespace Tests\Unit;

use App\DTOs\Scan\Database\DatabaseRuleResult;
use App\Services\Scan\Database\MysqlRules\AnonymousUsersRule;
use App\Services\Scan\Database\MysqlRules\EmptyPasswordsRule;
use App\Services\Scan\Database\MysqlRules\ExcessivePrivilegesRule;
use App\Services\Scan\Database\MysqlRules\GlobalPrivilegesRule;
use App\Services\Scan\Database\MysqlRules\VersionDisclosureRule;
use App\Services\Scan\Database\MysqlRules\WildcardHostRule;
use App\Services\Scan\Database\EvidenceSanitizer;
use Tests\TestCase;

class DatabaseSecurityRulesTest extends TestCase
{
    public function test_evidence_sanitization_removes_secrets()
    {
        $evidence = "Failed connecting with password=mysecret; also mysql://user:superpass@host\n";
        $evidence .= "Hash: *1234567890ABCDEF1234567890ABCDEF12345678";
        
        $sanitized = EvidenceSanitizer::sanitize($evidence);
        
        $this->assertStringNotContainsString('mysecret', $sanitized);
        $this->assertStringNotContainsString('superpass', $sanitized);
        $this->assertStringNotContainsString('*1234567890ABCDEF1234567890ABCDEF12345678', $sanitized);
        
        $this->assertStringContainsString('password=***', $sanitized);
        $this->assertStringContainsString('mysql://user:***@host', $sanitized);
    }
}
